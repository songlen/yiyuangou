<?php

namespace app\api\controller;
use think\Db;
use app\api\logic\PayLogic;

class Order extends Base {

	public function __construct(){
		// 设置所有方法的默认请求方式
		$this->method = 'POST';

		parent::__construct();
	}
    
    /**
     * [publishSoon 夺宝订单 未揭晓]
     * @return [type] [description]
     */
    public function publishSoon(){
        $user_id = I('user_id/d');
        $page = I('page/d', 1);

        $where = array(
            'user_id' => $user_id,
            'prom_type' => 4,
            'ga.status' => 1,
        );

        $orderList = M('order')->alias('o')
            ->join('goods_activity ga', 'o.prom_id=ga.act_id')
            ->where($where)
            ->order('order_id DESC')
            ->limit(($page-1)*10 . ',' . 10)
            ->field('order_id, order_sn, phase, surplus, pay_status, total_amount, goods_id')
            ->select();

        if($orderList){
            // 活动及商品信息
            foreach ($orderList as &$order) {
                $goods = M('goods')
                    ->where('goods_id='.$order['goods_id'])
                    ->field('goods_name, original_img')
                    ->find();
                $order = array_merge($order, $goods);
            }
        }

        response_success($orderList);
    }

    /**
     * [finished 夺宝订单已揭晓]
     * @return [type] [description]
     */
    public function finished(){
        $user_id = I('user_id/d');
        $page = I('page/d', 1);

        $where = array(
            'user_id' => $user_id,
            'prom_type' => 4,
            'ga.status' => 3,
        );

        $orderList = M('order')->alias('o')
            ->join('goods_activity ga', 'o.prom_id=ga.act_id')
            ->where($where)
            ->order('order_id DESC')
            ->limit(($page-1)*10 . ',' . 10)
            ->field('order_id, order_sn, o.num, phase, is_win, pay_status, total_amount, goods_id')
            ->select();
            
        if($orderList){
            // 商品信息
            foreach ($orderList as &$order) {
                $goods = M('goods')
                    ->where('goods_id='.$order['goods_id'])
                    ->field('goods_name, original_img')
                    ->find();
                $order = array_merge($order, $goods);
            }
        }

        response_success($orderList);
    }

    /**
     * [detail 订单详情]
     * @return [type] [description]
     */
    public function detail(){
        $user_id = I('user_id');
        $order_id = I('order_id/d');

        $orderInfo = Db::name('order')->where("user_id=$user_id and order_id=$order_id")
            ->field('order_id, order_sn, is_win, consignee, mobile, country, province , city, address, prom_id, order_amount, add_time')
            ->find();

        if(empty($orderInfo)) response_error('', '订单不存在');

        // 订单信息
        $result['order'] = array(
            'order_id' => $orderInfo['order_id'],
            'order_sn' => $orderInfo['order_sn'],
            'is_win' => $orderInfo['is_win'],
            'order_amount' => $orderInfo['order_amount'],
            'act_id' => $orderInfo['prom_id'],
            'add_time' => date('Y-m-d H:i:s', $orderInfo['add_time']),
        );
        // 地址信息
        $result['address'] = array(
            'consignee' => $orderInfo['consignee'],
            'mobile' => $orderInfo['mobile'],
            'country' => $orderInfo['country'],
            'province' => $orderInfo['province'],
            'city' => $orderInfo['city'],
            'address' => $orderInfo['address'],
        );
        // 活动信息
        $actInfo = Db::name('goods_activity')->alias('ga')
            ->join('goods g', 'ga.goods_id=g.goods_id')
            ->where("act_id={$orderInfo['prom_id']}")
            ->field('ga.status, g.goods_name, g.goods_id, g.original_img, ga.win_user_id, ga.lucky_number')
            ->find();
        $result['actInfo'] = $actInfo;

        if($actInfo['status'] == '3'){
            $winner = Db::name('users')->where("user_id={$actInfo['win_user_id']}")->field('nickname')->find();
            $result['actInfo']['winner_nickname'] = $winner['nickname'];
        }

        $my_lucky_number = Db::name('lucky_number')->where("user_id=$user_id and order_id=$order_id")
            ->field("lucky_number, add_time, add_time_ms")
            ->select();
        if($my_lucky_number){
            foreach ($my_lucky_number as &$item) {
                $item['date'] = date('Y-m-d', $item['add_time']);
                $item['time'] = date('H:i:s', $item['add_time']).'.'.$item['add_time_ms'];
                unset($item['add_time']);
                unset($item['add_time_ms']);
            }
        }
        $result['my_lucky_number'] = $my_lucky_number;

        response_success($result);
    }

    /**
     * [goodsOrder 商品订单]
     * @return [type] [description]
     */
    public function goodsOrder(){
        $user_id = I('user_id/d');
        $page = I('page/d', 1);

        $where = array(
            'o.user_id' => $user_id,
            'o.prom_type' => 0,
        );

        $orderList = M('order')->alias('o')
            ->join('order_goods og', 'o.order_id=og.order_id')
            ->where($where)
            ->whereOr('is_win', 1)
            ->order('order_id DESC')
            ->limit(($page-1)*10 . ',' . 10)
            ->field('o.order_id, order_sn, goods_id, goods_num, order_status, shipping_status, pay_status, total_amount')
            ->select();

        if($orderList){
            // 商品信息
            foreach ($orderList as &$order) {
                $goods = M('goods')
                    ->where('goods_id='.$order['goods_id'])
                    ->field('shop_price, goods_name, original_img')
                    ->find();
                $order = array_merge($order, $goods);
            }
        }

        response_success($orderList);
    }

    /**
     * [detail 订单详情]
     * @return [type] [description]
     */
    public function goodsOrderDetail(){
        $user_id = I('user_id');
        $order_id = I('order_id/d');


        $orderInfo = Db::name('order')->where("user_id=$user_id and order_id=$order_id")
            ->field('order_id, order_sn, consignee, country, province , city, address, order_amount, add_time, shipping_time, pay_time')
            ->find();

        // 订单信息
        $result['order'] = array(
            'order_id' => $orderInfo['order_id'],
            'order_sn' => $orderInfo['order_sn'],
            'order_amount' => $orderInfo['order_amount'],
            'add_time' => date('Y-m-d H:i:s', $orderInfo['add_time']),
            'pay_time' => $orderInfo['pay_time'] ? date('Y-m-d H:i:s', $orderInfo['pay_time']) : '',
            'shipping_time' => $orderInfo['shipping_time'] ? date('Y-m-d H:i:s', $orderInfo['shipping_time']) : '',
        );
        // 地址信息
        $result['address'] = array(
            'consignee' => $orderInfo['consignee'],
            'mobile' => $orderInfo['mobile'],
            'country' => $orderInfo['country'],
            'province' => $orderInfo['province'],
            'city' => $orderInfo['city'],
            'address' => $orderInfo['address'],
        );
        // 商品信息
        $goodsInfo = Db::name('order_goods')->alias('og')
            ->join('goods g', 'og.goods_id=g.goods_id')
            ->where("order_id=$order_id")
            ->field('og.goods_num, g.goods_name, g.original_img')
            ->find();

        $result['goodsInfo'] = $goodsInfo;

       
        response_success($result);
    }

    /**
     * [result 计算结果]
     * @return [type] [description]
     */
    public function calculation (){
        $act_id = I('act_id/d');

        $lists = Db::name('lucky_number')->alias('ln')
            ->join('users u', 'ln.user_id=u.user_id')
            ->where("act_id=$act_id")
            ->order('id desc')
            // ->group('ln.user_id')
            ->limit(100)
            ->field('lucky_number, nickname, ln.add_time, add_time_ms')
            ->select();

        if($lists){
            foreach ($lists as &$item) {
                $item['date'] = date('Y-m-d', $item['add_time']);
                $item['time'] = date('H:i:s', $item['add_time']).'.'.$item['add_time_ms'];
                unset($item['add_time']);
                unset($item['add_time_ms']);
            }
        }

        response_success($lists);
    }

    /**
     * [buy_goods 补差价购买商品]
     * @return [type] [description]
     */
    public function buy_goods(){
        $user_id = I('user_id/d');
        $order_id = I('order_id/d'); // 原订单id
        $goods_id = I('goods_id/d');
        $num = I('num/d', 1);
        $use_point = I('use_point', 0); // 是否使用积分
        $submit_order = I('submit_order', 0); // 是否提交订单
        $address_id = I('address_id/d');

        $user = Db::name('users')->find($user_id);
        if(empty($user)){
            response_error('', '用户不存在');
        }

        // 收货地址
        if($address_id){
            $address = M('user_address')->where("address_id", $address_id)->find();
        } else {
            $address = Db::name('user_address')->where(['user_id'=>$user_id])->order(['is_default'=>'desc'])->find();
        }
        if(empty($address)){
            $address = M('user_address')->where(['user_id'=>$user_id])->find();
        }

        // 商品信息
        $goodsInfo = Db::name('goods')->where("goods_id=$goods_id")->find();
        if(empty($goodsInfo)) response_error('', '商品不存在');
        if($goodsInfo['is_on_sale'] != '1') response_error('', '商品已下架');
        if($num > $goodsInfo['store_count']) response_error('', '商品库存不足');

        $goodsInfo = array(
            'goods_id' => $goodsInfo['goods_id'],
            'goods_name' => $goodsInfo['goods_name'],
            'shop_price' => $goodsInfo['shop_price'],
            'original_img' => $goodsInfo['original_img'],
            'num' => $num,
        );
        
        // 订单信息
        $order = Db::name('order')->where("order_id=$order_id")->find();
        if(empty($order)) response_error('', '订单不存在');

        $maxDeductible = round($goodsInfo['shop_price'] * 0.1, 2); // 最多可抵扣原商品价格的10%
        $deductible_amount = $order['goods_price'] > $maxDeductible ? $maxDeductible : $order['goods_price'];
        $tax_amount = ($goodsInfo['shop_price']*$goodsInfo['num']-$deductible_amount)*1.13;

        if($use_point){
            $points = $tax_amount*100;

            if($user['pay_points'] < $points){
                response_error('', '积分不足抵扣金额');
            }
            $actual_amount = 0.00; // 如果使用积分，全部抵扣，实付款为0
        } else {
            $points = 0;
            $actual_amount = $tax_amount; // 实付款
        }


        $priceInfo = array(
            'money' => $goodsInfo['shop_price']*$goodsInfo['num'], // 商品金额
            'deductible_amount' => $deductible_amount, // 可抵扣金额（原订单价格）
            'tax_rate' => '13%', // 税率
            'tax_amount' =>$tax_amount, // 税后金额
            'actual_amount' =>$tax_amount, // 实付金额
            'total_point' => $user['pay_points'], // 总积分
            'points' => $points,
        );

        $result['address'] = $address;
        $result['goodsInfo'] = $goodsInfo;
        $result['priceInfo'] = $priceInfo;

        if($submit_order){
            $result = $this->placeGoodsOrder($user_id, $goodsInfo, $address, $priceInfo);
            // 如果下单失败，返回失败信息
            if ($result['status'] ==  '-1') response_error('', $result['error']);
            // 则 下单成功
             // 如果使用了积分 直接返回支付成功
            if($priceInfo['points'] > 0){
                response_success(array('type'=>'pay_success', 'order_id' => $result['order_id']), '支付成功');
            } else {
                response_success(array('type' => 'order_success', 'order_sn'=>$result['order_sn']), '下单成功');
            }

        } else {
            response_success($result);
        }
    }

    /**
     * [placeGoodsOrder 补差价购买商品下单]
     * @param  [type] $user_id   [description]
     * @param  [type] $goodsInfo [description]
     * @param  [type] $address   [description]
     * @param  [type] $priceInfo [description]
     * @return [type]            [description]
     */
    private function placeGoodsOrder($user_id, $goodsInfo, $address, $priceInfo){
        if(empty($address)) return array('status'=>'-1', 'error' => '请填写收货地址');

        $order_sn = date('YmdHis').mt_rand(1000,9999);

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
            'goods_price' => $priceInfo['money'],
            'order_amount' => $priceInfo['actual_amount'], // 实付款
            'total_amount' => $priceInfo['tax_amount'], // 税后金额就是总金额
            'deductible_amount' => $priceInfo['deductible_amount'], // 可抵扣金额（原订单价格）
            'add_time' => $sec,
            'add_time_ms' => $usec,
            'prom_id' => $goodsInfo['act_id'],
            'prom_type' => 0, // 订单类型 普通订单
            'num' => $goodsInfo['num'],
            'tax_amount' => ($priceInfo['money'] - $priceInfo['deductible_amount'])*0.13
        );


        if($priceInfo['points'] > 0){
            $orderdata['integral'] = $priceInfo['points'];
            $orderdata['integral_money'] = $priceInfo['tax_amount'];
        }

        $commit_result = true;
        Db::startTrans(); // 开启事物
        try {

            $order_id = Db::name('order')->insertGetId($orderdata);

            // 商品表减库存
            Db::name('goods')->where('goods_id', $goodsInfo['goods_id'])->setDec('store_count', $goodsInfo['num']);

            // 如果使用了积分
            if($priceInfo['points']>0){
                accountLog($user_id, 0, -$priceInfo['points'], '订单使用积分', 0,$order_id, $order_sn);
            } else {
                accountLog($user_id, 0, -$priceInfo['points'], '下单', 0,$order_id, $order_sn);
            }

            // 更新订单商品附加表
            $orderGoods = array(
               'order_id' => $order_id,
               'goods_id' => $goodsInfo['goods_id'],
               'goods_name' => $goodsInfo['goods_name'],
               'goods_num' => $goodsInfo['num'],
            );
            Db::name('OrderGoods')->insert($orderGoods);
            // 提交事务
            Db::commit();
        } catch(\Exception $e){
            // 回滚事务
            Db::rollback();
            $commit_result = false;
        }

        if($commit_result){
            return array('status'=>'1', 'order_sn'=>$order_sn, 'order_id'=>$order_id);
        } else {
            return array('status'=>'-1', 'error' => '下单失败');
        }
    }


    // 商品支付
    public function pay(){
        $user_id = I('user_id');
        $order_sn = I('order_sn');
        $param['card_number'] = I('card_number'); // 卡号
        $param['expiry_date'] = I('expiry_date'); // 有效期
        $param['cvd_value'] = I('cvd_value'); // CVD
        $param['street_number'] = I('street_number'); // 街道号
        $param['street_name'] = I('street_name'); // 街道地址
        $param['zipcode'] = I('zipcode'); // 邮编
        $param['email'] = I('email'); // 邮箱
        $param['custphone'] = I('custphone'); // 手机号

        $order_sns = explode('-', trim($order_sn));
        $order = M('order')->whereIn('order_sn', $order_sns)->field('order_id, pay_status, order_amount')->select();


        $order_amount = 0;
        if(is_array($order)){
            foreach ($order as $item) {
                if($item['pay_status'] == '1'){
                    response_error('', '该订单已支付');
                }

                $order_amount += $item['order_amount'];
            }
        }

        $param['order_amount'] = $order_amount;
        $PayLogic = new PayLogic();
        $pay_result = $PayLogic->doPay($user_id, $order_sn, $param, $error);
        if($pay_result == true){
            $this->payCallback($order_sn);
            response_success('', '支付成功');
        } else {
            response_error('', $error);
        }
    }

    /**
     * [payCallback 支付回调]
     * @return [type] [description]
     */
    public function payCallback($order_sn = ''){
        // 获取订单信息，判断是否已支付 
        $order = M('order')->where('order_sn', $order_sn)->field('pay_status, prom_id, num')->find();
        if($order['pay_status'] == '1'){
            break;
        }

        // 支付成功修改订单状态为已支付
        M('order')->where('order_sn', $order_sn)->update(array('pay_status'=>'1', 'pay_time'=>time()));
    }
}