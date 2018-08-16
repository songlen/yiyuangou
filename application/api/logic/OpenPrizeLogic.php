<?php
/**
 * 订单处理类
 */


namespace app\api\logic;

use think\Db;
use app\api\logic\MessageLogic;

// 开奖
class OpenPrizeLogic {

    function exec($act_id){
         // 计算幸运号
        $luckyInfo =$this->generateLuckyInfo($act_id);

        $lucky_number = $luckyInfo['lucky_number'];
        // 活动表记录中奖信息
        $actUpdateData = array(
            'act_id'=>$act_id,
            'lucky_number'=>$lucky_number,
            'win_user_id'=>$luckyInfo['win_user_id'],
            'status' => '3',
            'open_time' => time(),
        );
        Db::name('goods_activity')->update($actUpdateData);
        // 幸运码表记录中奖信息
        Db::name('LuckyNumber')->where("lucky_number=$lucky_number and act_id=$act_id")->update(array('is_win'=>'1'));
        // 订单表中记录是否中奖
        Db::name('order')->where("order_id={$luckyInfo['order_id']}")->update(array('is_win'=>'1'));
        // 给中奖用户下商品订单
        $this->winningGoodsOrder($luckyInfo['win_user_id'], $luckyInfo['order_id']);
        // 进行滚期
        $this->continueActivity($act_id);
        // 给参与用户发送是否中奖消息
        $MessageLogic = new MessageLogic();
        $MessageLogic->winningMessage($act_id);
    }



    /**
     * [generateLuckyNumber 生成活动的幸运号]
     * @param  [type] $act_id [description]
     * @return [type]         [array order_id, win_user_id, lucky_number]
     */
    private function generateLuckyInfo($act_id){
        $actInfo = Db::name('goods_activity')->field('total_count, set_win')->find($act_id);
         // 购买最后100条记录
        $lastlist100 = Db::name('lucky_number')->where(array('act_id'=>$act_id))->limit('0, 100')->field('add_time, add_time_ms')->order('id desc')->select();
        // 时间加起来
        $sumTime = 0;
        foreach ($lastlist100 as $item) {
            $sumTime += date('YmdHis', $item['add_time']).$item['add_time_ms'];
        }
        // 参与人次
        // $count = Db::name('order')->where("prom_id=$act_id")->count();
        $mod = fmod($sumTime, $actInfo['total_count']);
        $lucky_number = $mod + 10000001;  // 诞生中奖幸运号

        // 查找中奖者
        $luckyInfo = Db::name('LuckyNumber')->where("lucky_number=$lucky_number and act_id=$act_id")
            ->field('order_id, user_id')
            ->find();
        $win_user_id = $luckyInfo['user_id'];

        // 如果活动设置的是机器人中奖，则检测是否机器人
        if($actInfo['set_win'] == '1'){

            // 1检测幸运号是否机器人
            $user = Db::name('users')->where("user_id=$win_user_id")->field('user_id, robot')->find();
            if($user['robot']  == '1'){ // 如果抽中的是机器人，直接返回结果
               goto returnResult;
            }

            // 如果抽中的幸运号不是机器人，则继续抽机器人
             // 判断订单中是否有机器人，如果没有机器人就停止，如果有机器人就找最近的机器人
            $exist_robot_order = Db::name('order')->where("prom_id=$act_id and prom_type=4 and robot=1")->field('count(1)')->find();

            if( ! $exist_robot_order){
                goto returnResult;
            }
            
            // 向上查找
            $luckyOrder = Db::name('order')->where("order_id < {$luckyInfo['order_id']} and prom_id=$act_id and robot=1")->order('order_id desc')->field('order_id')->find();

            if(empty($luckyOrder)){
                // 向下查找
                 $luckyOrder = Db::name('order')->where("order_id > {$luckyInfo['order_id']} and prom_id=$act_id and robot=1")->order('order_id asc')->field('order_id')->find();
            }

            // 如果设定了机器人中奖，但是没有机器人订单，就让原中奖者中奖
           if( ! empty($luckyOrder)){
                $luckyInfo = Db::name('LuckyNumber')->where("order_id={$luckyOrder['order_id']}")
                    ->field('order_id, user_id, lucky_number')
                    ->find();
                $win_user_id = $luckyInfo['user_id'];
                $lucky_number = $luckyInfo['lucky_number'];
           }

        } else { // 如果活动设置的是真人中奖，不管幸运号是机器人还是真人，直接返回幸运号

        }

        returnResult:
        return array(
            'order_id' => $luckyInfo['order_id'],
            'win_user_id' => $win_user_id,
            'lucky_number' => $lucky_number,
        );
    }

    private function continueActivity($act_id){
        $activity = M('goods_activity')->where('act_id', $act_id)->find();
        if($activity['status'] != '3') return false;
        if($activity['continue'] == 0) return false;

        $end_time = time() + $activity['continue_hour_step']*3600;
        $phase = $activity['phase']+1;

        if($activity['parent_id'] == '0'){
            $parent_id = $act_id;
        } else {
            $parent_id = $activity['parent_id'];
        }
        $data = array(
            'act_type' => '3',
            'goods_id' => $activity['goods_id'],
            'goods_name' => $activity['goods_name'],
            'end_time' => $end_time,
            'phase' => $phase,
            'total_count' => $activity['total_count'],
            'surplus' => $activity['total_count'],
            'set_win' => $activity['set_win'],
            'is_publish' => 1,
            'parent_id' => $parent_id,
            'add_time' => time(),
            'publish_time' => time(),
            'continue_hour_step' => $activity['continue_hour_step'],
        );

        M('goods_activity')->insert($data);
    }

    // 中奖订单自动下单
    public function winningGoodsOrder($user_id, $order_id){
        // 如果是真实用户，才下订单
        $userInfo = M('users')->where('user_id', $user_id)->field('robot')->find();
        if(empty($userInfo) || $userInfo['robot'] == '1') return true;

        // 查询原订单信息
        $orderInfo = M('order')->where('order_id', $order_id)->find();

        // 时间
        list($usec, $sec) = explode(" ", microtime());
        $usec = round($usec *1000);
        // 商品信息
        $goodsInfo = M('goods_activity')->alias('ga')
            ->join('goods g', 'ga.goods_id=g.goods_id', 'left')
            ->where('ga.act_id', $orderInfo['prom_id'])
            ->field('g.goods_id, g.goods_name, g.shop_price')
            ->find();

        $order_sn = date('YmdHis').mt_rand(1000,9999);
        $orderdata = array(
            'order_sn' => $order_sn,
            'user_id' => $user_id,
            'consignee' => $orderInfo['consignee'],
            'country' => $orderInfo['country'],
            'province' => $orderInfo['province'],
            'city' => $orderInfo['city'],
            'address' => $orderInfo['address'],
            'zipcode' => $orderInfo['zipcode'],
            'mobile' => $orderInfo['mobile'],
            'goods_price' => $goodsInfo['shop_price'],
            'order_amount' => 0, // 实付款
            'total_amount' => 0, // 税后金额就是总金额
            'deductible_amount' => 0, // 可抵扣金额（原订单价格）
            'add_time' => $sec,
            'add_time_ms' => $usec,
            'prom_id' => $orderInfo['prom_id'],
            'prom_type' => 5, // 订单类型 中奖下单
            'num' => '1',
            'tax_amount' => 0,
            'original_order_id' => $order_id,
        );

        $commit_result = true;
        Db::startTrans(); // 开启事物
        try {

            $order_id = Db::name('order')->insertGetId($orderdata);

            // 商品表减库存
            Db::name('goods')->where('goods_id', $goodsInfo['goods_id'])->setDec('store_count', '1');

            // 更新订单商品附加表
            $orderGoods = array(
               'order_id' => $order_id,
               'goods_id' => $goodsInfo['goods_id'],
               'goods_name' => $goodsInfo['goods_name'],
               'goods_num' => '1',
            );
            Db::name('OrderGoods')->insert($orderGoods);
            // 提交事务
            Db::commit();
        } catch(\Exception $e){
            // 回滚事务
            Db::rollback();
            $commit_result = false;
        }
    }
}