<?php

namespace app\api\controller;
use app\common\logic\CartLogic;
use app\common\logic\GoodsActivityLogic;
use app\common\logic\CouponLogic;
use app\common\logic\Integral;
use app\common\logic\OrderLogic;
use app\common\logic\Pay;
use app\common\logic\PlaceOrder;
use app\common\model\Goods;
use app\common\model\SpecGoodsPrice;
use app\common\util\TpshopException;
use think\Db;
use think\Url;

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

    public function index(){
        $cartLogic = new CartLogic();
        $cartLogic->setUserId($this->user_id);
        $cartList = $cartLogic->getCartList();//用户购物车
        $userCartGoodsTypeNum = $cartLogic->getUserCartGoodsTypeNum();//获取用户购物车商品总数
        $hot_goods = M('Goods')->where('is_hot=1 and is_on_sale=1')->limit(20)->cache(true,TPSHOP_CACHE_TIME)->select();
        $this->assign('hot_goods', $hot_goods);
        $this->assign('userCartGoodsTypeNum', $userCartGoodsTypeNum);
        $this->assign('cartList', $cartList);//购物车列表
        return $this->fetch();
    }

    /**
     * 更新购物车，并返回计算结果
     */
    public function AsyncUpdateCart()
    {
        $cart = input('cart/a', []);
        $cartLogic = new CartLogic();
        $cartLogic->setUserId($this->user_id);
        $result = $cartLogic->AsyncUpdateCart($cart);
        $this->ajaxReturn($result);
    }

    /**
     *  购物车加减
     */
    public function changeNum(){
        $cart = input('cart/a',[]);
        if (empty($cart)) {
            $this->ajaxReturn(['status' => 0, 'msg' => '请选择要更改的商品', 'result' => '']);
        }
        $cartLogic = new CartLogic();
        $result = $cartLogic->changeNum($cart['id'],$cart['goods_num']);
        $this->ajaxReturn($result);
    }

    /**
     * 删除购物车商品
     */
    public function delete(){
        $cart_ids = input('cart_ids/a',[]);
        $cartLogic = new CartLogic();
        $cartLogic->setUserId($this->user_id);
        $result = $cartLogic->delete($cart_ids);
        if($result !== false){
            $this->ajaxReturn(['status'=>1,'msg'=>'删除成功','result'=>$result]);
        }else{
            $this->ajaxReturn(['status'=>0,'msg'=>'删除失败','result'=>$result]);
        }
    }


    /**
     * 购物车第二步确定页面/立即购买
     * selectInfo json 数组 [{"act_id":"1","goods_id":"1","num":"1"}]
     */
    public function cart2(){
        $selectInfo = I('selectInfo');
        $selectInfo =  stripslashes(html_entity_decode($selectInfo));
        $selectInfo = json_decode($selectInfo, true);

        $action = input("action/s"); // 行为

        // 登录判断
        if ($this->user_id == 0){
            $this->error('请先登录', U('Mobile/User/login'));
        }

        // 收货地址
        $address_id = I('address_id/d');
        if($address_id){
            $address = M('user_address')->where("address_id", $address_id)->find();
        } else {
            $address = Db::name('user_address')->where(['user_id'=>$this->user_id])->order(['is_default'=>'desc'])->find();
        }
        if(empty($address)){
            $address = M('user_address')->where(['user_id'=>$this->user_id])->find();
        }

        //立即购买
        // if($action == 'buy_now'){
        //     $actInfo = current($selectInfo);
        //     // $cartLogic->setGoodsModel($actInfo['goods_id']);
        //     // // $cartLogic->setSpecGoodsPriceModel($item_id);
        //     // $cartLogic->setGoodsBuyNum($actInfo['num']);
        //     // $buyGoods = [];
        //     // try{
        //     //     $buyGoods = $cartLogic->buyNow($actInfo['act_id']);
        //     // }catch (TpshopException $t){
        //     //     $error = $t->getErrorArr();
        //     //     $this->error($error['msg']);
        //     // }

        //     $cartList['cartList'][0] = $buyGoods;
        //     $cartGoodsTotalNum = $goods_num;
        // }else{
        //     if ($cartLogic->getUserCartOrderCount() == 0){
        //         $this->error('你的购物车没有选中商品', 'Cart/index');
        //     }
        //     $cartList['cartList'] = $cartLogic->getCartList(1); // 获取用户选中的购物车商品
        //     $cartGoodsTotalNum = count($cartList['cartList']);
        // }

        // $cartGoodsList = get_arr_column($cartList['cartList'],'goods');
        // $cartGoodsId = get_arr_column($cartGoodsList,'goods_id');
        // $cartGoodsCatId = get_arr_column($cartGoodsList,'cat_id');
        // $cartPriceInfo = $cartLogic->getCartPriceInfo($cartList['cartList']);  //初始化数据。商品总额/节约金额/商品总共数量
        $cartList = array();
        $money = 0; // 商品金额总额

        foreach ($selectInfo as $item) {
            $where = array(
                'ga.act_id' => $item['act_id'],
            );
            $actInfo = Db::name('goods_activity')->alias('ga')
                ->join('goods g', 'ga.goods_id=g.goods_id')
                ->where($where)
                ->find()
                ;

            $cartList[] = array(
                'act_id' => $item['act_id'],
                'goods_id' => $item['goods_id'],
                'goods_name' => $actInfo['goods_name'],
                'num' => $item['num'],
            );

            $money += $item['num'];
            
        }

        $tax_rate = 0.13; // 税率
        $tax_money += $money*$tax_rate; // 税额
        $tax_amount = $money+$tax_money; // 税后金额
        $total_amount += $tax_amount;

        

        $priceInfo = array(
            'money' => $money,
            'tax_rate' => '13%',
            'tax_money' => $tax_money,
            'tax_amount' => $tax_amount,
            'total_amount' => $total_amount, // 总金额
        );


        $this->assign('address',$address); //收货地址
        $this->assign('cartList', $cartList); // 购物车的商品
        $this->assign('priceInfo', $priceInfo); // 价格信息

        return $this->fetch();
    }

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


    /**
     * ajax 获取订单商品价格 或者提交 订单
     */
    public function cart3(){
        if($this->user_id == 0){
            exit(json_encode(array('status'=>-100,'msg'=>"登录超时请重新登录!",'result'=>null))); // 返回结果状态
        }
        $address_id = I("address_id/d"); //  收货地址id
        $invoice_title = I('invoice_title'); // 发票
        $taxpayer = I('taxpayer'); // 纳税人编号
        $coupon_id =  I("coupon_id/d"); //  优惠券id
        $pay_points =  I("pay_points/d",0); //  使用积分
        $user_money =  I("user_money/f",0); //  使用余额
        $user_note = trim(I('user_note'));   //买家留言
        $goods_id = input("goods_id/d"); // 商品id
        $goods_num = input("goods_num/d");// 商品数量
        $item_id = input("item_id/d"); // 商品规格id
        $action = input("action"); // 立即购买
        $payPwd =  I("payPwd",''); // 支付密码
        strlen($user_note) > 50 && exit(json_encode(['status'=>-1,'msg'=>"备注超出限制可输入字符长度！",'result'=>null]));
        $address = Db::name('UserAddress')->where("address_id", $address_id)->find();
        $cartLogic = new CartLogic();
        $pay = new Pay();
        $cartList = [];
        try {
            $cartLogic->setUserId($this->user_id);
            $pay->setUserId($this->user_id);
            if ($action == 'buy_now') {
                $cartLogic->setGoodsModel($goods_id);
                $cartLogic->setSpecGoodsPriceModel($item_id);
                $cartLogic->setGoodsBuyNum($goods_num);
                $buyGoods = $cartLogic->buyNow();
                array_push($cartList,$buyGoods);
                $pay->payGoodsList($cartList);
            } else {
                $userCartList = $cartLogic->getCartList(1);
                $cartLogic->checkStockCartList($userCartList);
                $cartList = array_merge_recursive($cartList,$userCartList);
                $pay->payCart($cartList);
            }
            $pay->delivery($address['district']);
            $pay->orderPromotion();
            $pay->useCouponById($coupon_id);
            $pay->useUserMoney($user_money);
            $pay->usePayPoints($pay_points);
        } catch (TpshopException $t) {
            $error = $t->getErrorArr();
            $this->ajaxReturn($error);
        }
        // 提交订单
        if ($_REQUEST['act'] == 'submit_order') {
            $placeOrder = new PlaceOrder($pay);
            $placeOrder->setUserAddress($address);
            $placeOrder->setInvoiceTitle($invoice_title);
            $placeOrder->setUserNote($user_note);
            $placeOrder->setTaxpayer($taxpayer);
            $placeOrder->setPayPsw($payPwd);
            try{
                $placeOrder->addNormalOrder();
            }catch (TpshopException $t) {
                $error = $t->getErrorArr();
                $this->ajaxReturn($error);
            }
            $cartLogic->clear();
            $order = $placeOrder->getOrder();
            $this->ajaxReturn(['status'=>1,'msg'=>'提交订单成功','result'=>$order['order_sn']]);
        }
        $car_price = $pay->toArray();
        $this->ajaxReturn(['status'=>1,'msg'=>'计算成功','result'=>$car_price]);
    }
    /*
     * 订单支付页面
     */
    public function cart4(){
        if(empty($this->user_id)){
            $this->redirect('User/login');
        }
        $order_id = I('order_id/d');
        $order_sn= I('order_sn/s','');
        $order_where = ['user_id'=>$this->user_id];
        if($order_sn)
        {
            $order_where['order_sn'] = $order_sn;
        }else{
            $order_where['order_id'] = $order_id;
        }
        $order = M('Order')->where($order_where)->find();
        empty($order) && $this->error('订单不存在！');
        if($order['order_status'] == 3){
            $this->error('该订单已取消',U("Mobile/Order/order_detail",array('id'=>$order['order_id'])));
        }
        if(empty($order) || empty($this->user_id)){
            $order_order_list = U("User/login");
            header("Location: $order_order_list");
            exit;
        }
        // 如果已经支付过的订单直接到订单详情页面. 不再进入支付页面
        if($order['pay_status'] == 1){
            $order_detail_url = U("Mobile/Order/order_detail",array('id'=>$order['order_id']));
            header("Location: $order_detail_url");
            exit;
        }
        $orderGoodsPromType = M('order_goods')->where(['order_id'=>$order['order_id']])->getField('prom_type',true);
        $payment_where['type'] = 'payment';
        $no_cod_order_prom_type = ['4,5'];//预售订单，虚拟订单不支持货到付款
        if(strstr($_SERVER['HTTP_USER_AGENT'],'MicroMessenger')){
            //微信浏览器
            if(in_array($order['prom_type'],$no_cod_order_prom_type) || in_array(1,$orderGoodsPromType)){
                //预售订单和抢购不支持货到付款
                $payment_where['code'] = 'weixin';
            }else{
                $payment_where['code'] = array('in',array('weixin','cod'));
            }
        }else{
            if(in_array($order['prom_type'],$no_cod_order_prom_type) || in_array(1,$orderGoodsPromType)){
                //预售订单和抢购不支持货到付款
                $payment_where['code'] = array('neq','cod');
            }
            $payment_where['scene'] = array('in',array('0','1'));
        }
        $payment_where['status'] = 1;
        //预售和抢购暂不支持货到付款
        $orderGoodsPromType = M('order_goods')->where(['order_id'=>$order['order_id']])->getField('prom_type',true);
        if($order['prom_type'] == 4 || in_array(1,$orderGoodsPromType)){
            $payment_where['code'] = array('neq','cod');
        }
        $paymentList = M('Plugin')->where($payment_where)->select();
        $paymentList = convert_arr_key($paymentList, 'code');

        foreach($paymentList as $key => $val)
        {
            $val['config_value'] = unserialize($val['config_value']);
            if($val['config_value']['is_bank'] == 2)
            {
                $bankCodeList[$val['code']] = unserialize($val['bank_code']);
            }
            //判断当前浏览器显示支付方式
            if(($key == 'weixin' && !is_weixin()) || ($key == 'alipayMobile' && is_weixin())){
                unset($paymentList[$key]);
            }
        }

        $bank_img = include APP_PATH.'home/bank.php'; // 银行对应图片
        $this->assign('paymentList',$paymentList);
        $this->assign('bank_img',$bank_img);
        $this->assign('order',$order);
        $this->assign('bankCodeList',$bankCodeList);
        $this->assign('pay_date',date('Y-m-d', strtotime("+1 day")));
        return $this->fetch();
    }

    /**
     * ajax 将商品加入购物车
     */
    function addCart()
    {
        $act_id = I("act_id/d"); // 商品id
        $num = I("num/d");// 商品数量

        $actInfo = Db::name('goods_activity')->find($act_id);
        $surplus = $actInfo['total_count'] - $actInfo['buy_count'];
        
        if($num > $surplus) response_error('购买数量超过商品剩余数量');

        // 判断商品是否已经存在购物车里，存在的话直接更新数量
        $cart = Db::name('cart')->where("user_id={$this->user_id} and act_id={$act_id}")->find();
        if($cart){
            Db::name('cart')->where("user_id={$this->user_id} and act_id={$act_id}")->update(array('num'=>$num));
        } else {
            $cartData = array(
                'user_id' => $this->user_id,
                'act_id' => $act_id,
                'goods_id' => $actInfo['goods_id'],
                'num' => $num,
            );
            Db::name('cart')->insert($cartData);
        }

        response_success('已添加至购物车');
    }
}
