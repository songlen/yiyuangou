<?php
/**
 * 订单处理类
 */


namespace app\api\logic;

use think\Db;

class OrderLogic {

    /**
     * [placeOrder 活动下单]
     * @param  [int]  $user_id   [用户id]
     * @param  [array]  $goodsList [活动列表数数组]
     * @param  [array]  $address   [地址]
     * @param  [1, 0] $use_point [是否使用积分]
     * @return [type]             [description]
     */
    public function placeOrder($user_id, $goodsList, $address, $use_point=0){
        $user = Db::name('users')->field('pay_points')->find($user_id);
        if(empty($user)) return array('status'=>'-1', 'error'=>'用户不存在');
        if(empty($goodsList)) return array('status'=>'-1', 'error'=>'商品不存在');
        if(empty($address)) return array('status'=>'-1', 'error'=>'地址不存在');

        $commit_result = true;
        $order_sn_gather = ''; // 订单号集合
        // 当一个商品的时候控制商品数量不能为0
        if(count($goodsList) == 0){
            $currentGood = current($goodsList);
            if($currentGood['num'] == 0) return array('status'=>'-1', '商品数量不能为0');
        }
        foreach ($goodsList as $item) {
            $act_id = $item['act_id'];
            $num = $item['num'];
            $goods_id = $item['goods_id'];

            $order_sn = generateOrderSn(); // 生成订单号
            $order_sn_gather .= $order_sn_gather ? '-'.$order_sn : $order_sn;

            // 时间戳和毫秒数
            list($usec, $sec) = explode(" ", microtime());
            $usec = round($usec *1000);

            $orderdata = array(
                'order_sn' => $order_sn,
                'user_id' => $user_id,
                'consignee' => $address['consignee'],
                'country' => $address['country'],
                'province' => $address['province'],
                'city' => $address['city'],
                'address' => $address['address'],
                'zipcode' => $address['zipcode'],
                'mobile' => $address['mobile'],
                'add_time' => $sec,
                'add_time_ms' => $usec,
                'prom_id' => $act_id,
                'prom_type' => 4, // 订单类型 夺宝活动
                'num' => $item['num'],
            );

            // 计算各种价格
            $goods_price = $item['num']; // 商品价格等于购买份额
            $tax_amount = $goods_price*0.13; // 税额
            $total_amount = $goods_price+$tax_amount; // 订单总额（商品价格+税额）

            // 如果能够使用积分，实际付款为0
            if($use_point > 0){
                // 应需积分
                $points = $total_amount*100;
                if($user['pay_points'] < $points){
                    return array('status'=>'-1', 'error'=>'积分不足');
                }
                $used_points = $points; // 使用的积分
                // 如果可以使用积分
                $orderdata['integral'] = $points; // 使用积分
                $orderdata['integral_money'] = $total_amount; // 积分抵扣金额（购买夺宝，全部抵扣）
                $order_amount = 0; // 实付款就为0
            } else {
                $used_points = 0;
                $order_amount = $total_amount;
            }

            $orderdata['goods_price'] =  $goods_price;
            $orderdata['tax_amount'] =  $tax_amount;
            $orderdata['order_amount'] =  $order_amount;
            $orderdata['total_amount'] =  $total_amount;

            Db::startTrans(); // 开启事物
            try {
                // 订单写入数据库
                $order_id = Db::name('order')->insertGetId($orderdata);

                // 活动表增减数量
                Db::name('GoodsActivity')->where('act_id', $act_id)->setDec('surplus', $num); // 减剩余份额
                Db::name('GoodsActivity')->where('act_id', $act_id)->setInc('freeze_count', $num); // 冻结份额

                // 积分记录
                if($used_points>0){
                    accountLog($user_id, 0, -$used_points, '订单使用积分', 0,$order_id, $order_sn);
                }

                $i = 0;
                // 批量生成随机幸运号 $num（幸运号数量)
                $lucky_numbers = $this->generateLuckyNumber($act_id, $num);
                while ($i < $num) {
                    // 生成幸运码
                   $lucky_number = $lucky_numbers[$i];
                   // 更新附加表
                   // 时间戳和毫秒数
                    list($usec, $sec) = explode(" ", microtime());
                    $usec = round($usec *1000);
                    $luckynumber = array(
                       'order_id' => $order_id,
                       'order_sn' => $order_sn,
                       'user_id' => $user_id,
                       'act_id' => $act_id,
                       'goods_id' => $goods_id,
                       'num' => 1,
                       'add_time' => $sec,
                       'add_time_ms' => $usec,
                       'lucky_number' => $lucky_number,
                   );
                   Db::name('LuckyNumber')->insert($luckynumber);
                   $i++;
                }

                // 提交事务
                Db::commit();
            } catch (\Exception $e){
                // 回滚事务
                Db::rollback();
                $commit_result = false;
                break;
            }
        }

        if($commit_result){
            return array('status'=>'1', 'data'=>array('order_sn_gather'=>$order_sn_gather, 'order_id'=>$order_id));
        }  else {
            return array('status'=>'-1', 'error'=>'提交失败');
        }
    }

    // 随机生成幸运号
    public function generateLuckyNumber($act_id, $num){
        $actInfo = Db::name('goods_activity')->where('act_id', $act_id)->field('total_count')->find();
        // 所有的幸运号
        $allLuckys = range(10000001, 10000000+$actInfo['total_count']);
        // 查找已被使用的幸运号
        $usedLucky = Db::name('lucky_number')->where('act_id', $act_id)->getField('lucky_number', true);
        // 求出两个数组的差集（未被使用的幸运号）
        $usableLucky = array_diff($allLuckys, $usedLucky);
        $keys = array_rand($usableLucky, $num);

        if(is_array($keys)){
            return array_values(array_intersect_key($usableLucky, array_flip($keys)));
        } else {
            return array($usableLucky[$keys]);
        }
    }

    // public function generateLuckyNumber($act_id){
    //     $lucky_number = Db::name('lucky_number')->where('act_id', $act_id)->max('lucky_number');

    //     $lucky_number = $lucky_number ? $lucky_number+1 : '10000001';
    //     return $lucky_number;
    // }
    // 
    // 
     /**
     * [addRobotOrder 执行机器人下单流程]
     * @param [type] $act_id  [description]
     * @param [type] $user_id [description]
     * @param [type] $num     [description]
     * @param [type] $[lucky_number] [如何传入了lucky_number 说明是开奖时添加的机器人下单]
     */
    public function placeRobotOrder($act_id, $user_id, $num, $lucky_number = false){
        $user = Db::name('users')->field('mobile')->find($user_id);
        if(empty($user_id)){
            return false;
        }

        $commit_result = true;

        $order_sn = generateOrderSn(); // 生成订单号

        // 时间戳和毫秒数
        list($usec, $sec) = explode(" ", microtime());
        $usec = round($usec *1000);

        $orderdata = array(
            'order_sn' => $order_sn,
            'user_id' => $user_id,
            'mobile' => $user['mobile'],
            'add_time' => $sec,
            'add_time_ms' => $usec,
            'prom_id' => $act_id,
            'prom_type' => 4, // 订单类型 夺宝活动
            'num' => $num,
            'pay_status' => '1',
        );

        // 计算各种价格
        $goods_price = $num; // 商品价格等于购买份额
        $tax_amount = $goods_price*0.13; // 税额
        $total_amount = $goods_price+$tax_amount; // 订单总额（商品价格+税额）

        $used_points = 0;
        $order_amount = $total_amount;

        $orderdata['goods_price'] =  $goods_price;
        $orderdata['tax_amount'] =  $tax_amount;
        $orderdata['order_amount'] =  $order_amount;
        $orderdata['total_amount'] =  $total_amount;

        Db::startTrans(); // 开启事物
        try {
            // 订单写入数据库
            $order_id = Db::name('order')->insertGetId($orderdata);

            // 活动表增减数量
            Db::name('GoodsActivity')->where('act_id', $act_id)->setDec('surplus', $num); // 减剩余份额
            Db::name('GoodsActivity')->where('act_id', $act_id)->setInc('buy_count', $num); // 加已购份额

            $i = 0;
            // 批量生成随机幸运号 $num（幸运号数量)
            if($lucky_number && ($num == 1)){
                $lucky_numbers = array($lucky_number);
            } else {
                $lucky_numbers = $this->generateLuckyNumber($act_id, $num);
            }
            while ($i < $num) {
                // 生成幸运码
               $lucky_number = $lucky_numbers[$i];
               // 更新附加表
               $luckynumber = array(
                   'order_id' => $order_id,
                   'order_sn' => $order_sn,
                   'user_id' => $user_id,
                   'act_id' => $act_id,
                   'num' => $num,
                   'add_time' => $sec,
                   'add_time_ms' => $usec,
                   'lucky_number' => $lucky_number,
               );
               Db::name('LuckyNumber')->insert($luckynumber);
               $i++;
            }

            // 提交事务
            Db::commit();
        } catch (\Exception $e){
            // 回滚事务
            Db::rollback();
            $commit_result = false;
            break;
        }

        if($commit_result){
            return true;
        }  else {
            return false;
        }
    }
}