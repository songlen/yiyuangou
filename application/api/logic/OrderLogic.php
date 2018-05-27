<?php
/**
 * 订单处理类
 */


namespace app\api\logic;

use think\Db;

class OrderLogic {
     public function placeOrder($user_id, $goodsList, $address, $use_point=0){
        if($user_id == '') return array('status'='-1', 'error'=>'用户不存在');
        if(empty($goodsList)) return array('status'='-1', 'error'=>'商品不存在');
        if(empty($address)) return array('status'='-1', 'error'=>'地址不存在');

        $goodsInfo = current($goodsList);

        $order_sn = generateOrderSn();

        // 时间戳和毫秒数
        list($usec, $sec) = explode(" ", microtime());
        $usec = round($usec *1000);

        $orderdata = array(
            'order_sn' => $order_sn,
            'user_id' => $user_id,
            'order_status' => 0,
            'pay_status' => 0,
            'consignee' => $address['consignee'],
            'country' => $address['country'],
            'province' => $address['province'],
            'city' => $address['city'],
            'address' => $address['address'],
            'zipcode' => $address['zipcode'],
            'mobile' => $address['mobile'],
            'goods_price' => $priceInfo['money'],
            'integral' => 0,
            'integral_money' => 0,
            'order_amount' => $priceInfo['actual_amount'], // 实付款
            'total_amount' => $priceInfo['tax_amount'], // 税后金额就是总金额
            'add_time' => $sec,
            'add_time_ms' => $usec,
            'prom_id' => $goodsInfo['act_id'],
            'prom_type' => 4, // 订单类型 夺宝活动
            'num' => $goodsInfo['num'],
        );


        if($priceInfo['points'] > 0){
            $orderdata['integral'] = $priceInfo['points'];
            $orderdata['integral_money'] = $priceInfo['tax_amount'];
        }

        $order_id = Db::name('order')->insertGetId($orderdata);

        // 活动表增减数量
        Db::name('GoodsActivity')->where('act_id', $goodsInfo['act_id'])->setDec('surplus', $goodsInfo['num']);
        Db::name('GoodsActivity')->where('act_id', $goodsInfo['act_id'])->setInc('buy_count', $goodsInfo['num']);

        // 如果使用了积分
        if($priceInfo['points']>0){
            accountLog($user_id, 0, -$priceInfo['points'], '订单使用积分', 0,$order_id, $order_sn);
        }

        $i = 1;
        while ($i <= $goodsInfo['num']) {
            // 找出最大的幸运码
           $max_lucky_number = Db::name('lucky_number')->where(array('act_id'=>$goodsInfo['act_id']))->max('lucky_number');
           $lucky_number = $max_lucky_number ? $max_lucky_number+1 : 10000001;
           // 更新附加表
           $luckynumber = array(
               'order_id' => $order_id,
               'order_sn' => $order_sn,
               'user_id' => $user_id,
               'act_id' => $goodsInfo['act_id'],
               'goods_id' => $goodsInfo['goods_id'],
               'num' => $goodsInfo['num'],
               'add_time' => $sec,
               'add_time_ms' => $usec,
               'lucky_number' => $lucky_number,
           );
           Db::name('LuckyNumber')->insert($luckynumber);
           $i++;
        }

        // 下单成功去付款
        response_success('', '订单提交成功');
    }
}