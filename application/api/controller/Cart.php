<?php

namespace app\api\controller;
// use app\common\logic\CartLogic;
// use app\common\logic\Integral;
use app\api\logic\OrderLogic;
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
        // $this->cartLogic = new CartLogic();
        // if (session('?user')) {
        //     $user = session('user');
        //     $user = M('users')->where("user_id", $user['user_id'])->find();
        //     session('user', $user);  //覆盖session 中的 user
        //     $this->user = $user;
        //     $this->user_id = $user['user_id'];
        // } else {
        //     response_error('请先登录');
        // }
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
     * 更新购物车，并返回计算结果
     */
    /*public function AsyncUpdateCart()
    {
        $cart = input('cart/a', []);
        $cartLogic = new CartLogic();
        $cartLogic->setUserId($this->user_id);
        $result = $cartLogic->AsyncUpdateCart($cart);
        $this->ajaxReturn($result);
    }*/

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
            response_success('', '下单成功');
        } else {
            response_success($data);
        }
    }

    /*public function placeOrder($user_id, $goodsList, $address, $use_point=0){
        if($user_id == '') return array('status'=>'-1', 'error'=>'用户不存在');
        if(empty($goodsList)) return array('status'=>'-1', 'error'=>'商品不存在');
        if(empty($address)) return array('status'=>'-1', 'error'=>'地址不存在');

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
        return array('status'=>'1');
    }*/


    // public function buy_now(){
    //     $act_id = input("act_id/d"); // 夺宝活动id
    //     $goods_id = input("goods_id/d"); // 商品规格id
    //     $goods_num = input("goods_num/d");// 商品数量

    //     if ($this->user_id == 0){
    //         $this->error('请先登录', U('Mobile/User/login'));
    //     }


    //     $address_id = I('address_id/d');
    //     if($address_id){
    //         $address = M('user_address')->where("address_id", $address_id)->find();
    //     } else {
    //         $address = Db::name('user_address')->where(['user_id'=>$this->user_id])->order(['is_default'=>'desc'])->find();
    //     }
    //     if(empty($address)){
    //         $address = M('user_address')->where(['user_id'=>$this->user_id])->find();
    //     }

    // }
   
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
