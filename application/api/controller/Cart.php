<?php

namespace app\api\controller;
// use app\common\logic\CartLogic;
// use app\common\logic\Integral;
use app\api\logic\OrderLogic;
use app\api\logic\OpenPrizeLogic;
use think\Db;

class Cart extends Base {

    public $cartLogic; // 购物车逻辑操作类    
    public $user_id = 0;
    public $user = array();
    /**
     * 析构流函数
     */
    public function  __construct() {
        // 设置所有方法的默认请求方式
        $this->method = 'POST';
        $this->user_id = 1;

        parent::__construct();
    }

    public function cartlist(){
        $user_id = I("user_id/d");

        $cartList = Db::name('cart')->where("user_id=$user_id")
            ->field('id as cart_id, act_id, num')
            ->select();

        if($cartList){
            foreach ($cartList as $k => &$item) {
                $where = array(
                    'ga.status' => '1',
                    'ga.is_publish' => '1',
                    'ga.act_id' => $item['act_id'],
                );
                $actInfo = Db::name('GoodsActivity')->alias('ga')
                    ->join('goods g', 'ga.goods_id=g.goods_id')
                    ->where($where)
                    ->field('ga.act_id, ga.goods_id, ga.surplus, g.goods_name, g.original_img')
                    ->find();

                if(empty($actInfo)){
                    unset($cartList[$k]);
                    Db::name('cart')->where(array('user_id'=>$user_id, 'id'=>$item['cart_id']))->delete();
                    continue;
                }

                if($item['num'] > $actInfo['surplus']){
                    $item['num'] = 1;
                    Db::name('cart')->update(array('id'=>$item['cart_id'], 'num'=>1));
                }
                $item = array_merge($item, $actInfo);
            }
        }

        response_success(array_values($cartList));
    }

    /**
     *  购物车加减
     */
    public function changeNum(){
        $user_id = I('user_id/d');
        $act_id = I("act_id/d"); // 商品id
        $num = I('num/d');

        $actInfo = Db::name('goods_activity')->find($act_id);
        if($num > $actInfo['surplus']) response_error('', '商品数量超过剩余量');
        if($num < 1) response_error('', '至少一个商品');

        // 判断商品是否已经存在购物车里，存在的话直接更新数量

        Db::name('cart')->where("user_id={$user_id} and act_id={$act_id}")->update(array('num'=>$num));
        response_success('', '操作成功');
    }

    /**
     * ajax 将商品加入购物车
     */
    function addCart()
    {
        $user_id = I('user_id/d');
        $act_id = I("act_id/d"); // 商品id
        $num = I("num/d");// 商品数量

        $actInfo = Db::name('goods_activity')
            ->where("status=1")
            ->find($act_id);
        if(empty($actInfo)) response_error('', '活动不存在');
        
        if($num > $actInfo['surplus']) response_error('', '商品数量超过剩余量');

        // 判断商品是否已经存在购物车里，存在的话直接更新数量
        $cart = Db::name('cart')->where("user_id={$user_id} and act_id={$act_id}")->find();
        if($cart){
            Db::name('cart')->where("user_id={$user_id} and act_id={$act_id}")->update(array('num'=>$num));
        } else {
            $cartData = array(
                'user_id' => $user_id,
                'act_id' => $act_id,
                'goods_id' => $actInfo['goods_id'],
                'num' => $num,
            );
            Db::name('cart')->insert($cartData);
        }

        response_success('', '已添加至购物车');
    }

    /**
     * 删除购物车商品
     */
    public function delete(){
        $user_id = I('user_id/d');
        $cart_id = I('cart_id/d');

        $where = array(
            'user_id' => $user_id,
            'id' => $cart_id,
        );

        $result =  Db::name('cart')->where($where)->delete();
        if($result !== false){
            response_success('', '操作成功');
        }else{
            response_error('', '操作失败');
        }
    }

    /**
     * 购物车第二步确定页面/立即购买/下单
     * goodsInfo json 数组 [{"act_id":"1","goods_id":"1","num":"1"}]
     */
    public function prepareOrder(){
        $user_id = I('user_id/d');
        $goodsInfo = I('goodsInfo');
        $use_point = I('use_point', 0); // 是否使用积分
        $submit_order = I('submit_order', 0); // 是否提交订单
        $address_id = I('address_id/d');

        $goodsInfo =  stripslashes(html_entity_decode($goodsInfo));
        $goodsInfo = json_decode($goodsInfo, true);

        // 登录判断
        $user = Db::name('users')->find($user_id);
        if(empty($user)) response_error('用户不存在');

        // 收货地址
        if($address_id){
            $address = M('user_address')->where("address_id", $address_id)->find();
        } else {
            $address = Db::name('user_address')->where(['user_id'=>$user_id])->order(['is_default'=>'desc'])->find();
        }
        if(empty($address)){
            $address = M('user_address')->where(['user_id'=>$user_id])->find();
        }
        $address = array(
            'address_id' => $address['address_id'],
            'consignee' => $address['consignee'],
            'mobile' => $address['mobile'],
            'country' => $address['country'],
            'province' => $address['province'],
            'city' => $address['city'],
            'address' => $address['address'],
            'zipcode' => $address['zipcode'],
        );
       
        $goodsList = array();
        $money = 0; // 商品金额总额

        foreach ($goodsInfo as $item) {
            $where = array(
                'ga.act_id' => $item['act_id'],
            );
            $actInfo = Db::name('goods_activity')->alias('ga')
                ->join('goods g', 'ga.goods_id=g.goods_id')
                ->where($where)
                ->find()
                ;
            if(empty($actInfo)){
                response_error('', '活动不存在');
            }
            if($actInfo['status'] != '1'){
                response_error('', ' 已结束');
            }
            if($actInfo['is_publish'] != '1'){
                response_error('', ' 未发布');
            }

            if($item['num'] > $actInfo['surplus']){
                response_error('', ' 商品数量超过剩余数量');
            }

            $goodsList[] = array(
                'act_id' => $item['act_id'],
                'goods_id' => $item['goods_id'],
                'goods_name' => $actInfo['goods_name'],
                'original_img' => $actInfo['original_img'],
                'num' => $item['num'],
            );

            $money += $item['num'];
        }

        $tax_rate = 0.13; // 税率
        $tax_money += $money*$tax_rate; // 税额
        $tax_amount = $money+$tax_money; // 税后金额
        if($use_point){
            $points = $tax_amount*100;

            if($user['pay_points'] < $points){
                response_error('', '积分不够');
            }
            $actual_amount = 0.00; // 如果使用积分，全部抵扣，实付款为0
        } else {
            $points = 0;
            $actual_amount = $tax_amount; // 实付款
        }

        $priceInfo = array(
            'total_point' => $user['pay_points'], // 用户总积分
            'money' => $money,
            'tax_rate' => '13%',
            'tax_amount' => $tax_amount, // 税后金额
            'points' => $points, // 使用的积分数
            'actual_amount' => $actual_amount, // 实付款
        );

        $data['address'] = $address;
        $data['goodsList'] = $goodsList;
        $data['priceInfo'] = $priceInfo;

        if($submit_order){
            $OrderLogic = new OrderLogic();
            $orderResult = $OrderLogic->placeOrder($user_id, $goodsList, $address, $use_point);
            if($orderResult['status'] == '-1') response_error('', $orderResult['error']);
            
            // 
            $order_sn_gather = $orderResult['data']['order_sn_gather'];
            // 如果使用积分支付，直接返给前端支付成功；，并检查互动是否满额，如果满额，则开奖
            // 如果使用积分支付的
            if($use_point){
                $this->payCallback($order_sn_gather);
                response_success('', '支付成功');
            } else {
                    $this->payCallback($order_sn_gather);
                 response_success('', '下单成功');
            }
           
        } else {
            response_success($data);
        }
    }

    /**
     * [payCallback 支付回调]
     * @return [type] [description]
     */
    public function payCallback($order_sn_gather = ''){
        $order_sns = explode('-', trim($order_sn_gather));
        if(!empty($order_sns)){
            foreach ($order_sns as $order_sn) {
                // 获取订单信息，判断是否已支付
                $order = M('order')->where('order_sn', $order_sn)->field('pay_status, prom_id')->find();
                if($order['pay_status'] == '1'){
                    break;
                }
                M('order')->where('order_sn', $order_sn)->setfield('pay_status', '1');
                // 判断是否满额，满额开奖
                $act_id = $order_sn['prom_id'];
                $activity = M('order')->where('act_id', $act_id)->field('surplus, freeze_count')->find();
                if($activity['surplus'] == 0 && $activity['freeze_count'] == 0){
                    // 开奖
                    $OpenPrizeLogic = new OpenPrizeLogic();
                    $OpenPrizeLogic->exec($act_id);
                }
            }
        }
    }


    /**
     * [ajaxCountAmount 计算商品金额]
     * @return [type] [description]
     */
    public function ajaxTotalAmount(){
        $post = I('p.');
        
        $act_ids = $post['act_id'];
        $nums = $post['num'];
        foreach ($act_ids as $k => $act_id) {
            $goodsInfo[] = array(
                'act_id' =>$act_id,
                'num' => $nums[$k],
            );
        }
        p($goodsInfo);
        // $goodsInfo =  stripslashes(html_entity_decode($goodsInfo));
        // $goodsInfo = json_decode($goodsInfo, true);

        $use_jifen = I('use_jifen/d'); // 是否使用积分

        $money = 0; // 商品金额
        foreach ($goodsInfo as $item) {

            $where = array(
                'ga.act_id' => $item['act_id'],
            );

            $activitInfo = Db::name('goods_activity')->alias('ga')
                ->join('goods g', 'ga.goods_id=g.goods_id')
                ->where($where)
                ->find()
                ;

            // 判断活动份额是否足够
            $store_count = $activitInfo['total_count'] - $activitInfo['buy_count'];
            if($num > $store_count){
                $this->ajaxReturn(['status'=>'-1', 'msg'=>'选购份额超出剩余份额']);
            }

            $money += $item['num'];

        }

        $tax_rate = 0.13; // 税率
        $tax_money = $money*$tax_rate; // 税额
        $tax_amount = $money+$tax_money; // 税后金额
        $total_amount = $tax_amount;

        $priceInfo = array(
            'money' => $money,
            'tax_rate' => '13%',
            'tax_money' => $tax_money,
            'tax_amount' => $tax_amount,
            'total_amount' => $total_amount, // 总金额
            // 'use_point' => 0,
        );

        if($use_point){
            
        }

        $this->ajaxReturn(['status'=>200, 'data'=>$priceInfo]);
    }

    /**
     * [ajaxCheckNum 修改商品数量判断是否超出剩余份额]
     * @return [type] [description]
     */
    public function ajaxCheckActNum(){
        $act_id = I('act_id/d');
        $num = I('num/d');

        if(empty($act_id)){
            $this->ajaxReturn(['status'=>-1,'msg'=>'请选择要购买的商品','result'=>'']);
        }
        if(empty($num)){
            $this->ajaxReturn(['status'=>-1,'msg'=>'购买商品数量不能为0','result'=>'']);
        }

        $activity = Db::name('goods_activity')->field('total_count, buy_count')->find($act_id);
        
        $store_count = $activity['total_count'] - $activity['buy_count'];
        if($num > $store_count){
            $this->ajaxReturn(['status'=>'-1', 'msg'=>'选购份额超出剩余份额']);
        }

        $this->ajaxReturn(['status'=>'1', 'msg'=>'']);

    }

}
