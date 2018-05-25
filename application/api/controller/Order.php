<?php

namespace app\api\controller;
use think\Db;

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

        $where['user_id'] = $user_id;
        $where['prom_type'] = 4;
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
            ->field('order_id, order_sn, phase, order_status, pay_status, total_amount, goods_id')
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

        $where['user_id'] = $user_id;
        $where['prom_type'] = 4;
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
            ->field('order_id, order_sn, o.num, phase, is_win, order_status, pay_status, total_amount, goods_id')
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

    public function detail(){
        $user_id = I('user_id');
        $order_id = I('order_id/d');


        $orderInfo = Db::name('order')->where("user_id=$user_id and order_id=$order_id")
            ->field('order_id, order_sn, is_win, consignee, country, province , city, address, prom_id, order_amount')
            ->find();

        // 订单信息
        $result['order'] = array(
            'order_id' => $orderInfo['order_id'],
            'order_sn' => $orderInfo['order_sn'],
            'is_win' => $orderInfo['is_win'],
            'order_amount' => $orderInfo['order_amount'],
        );
        // 地址信息
        $result['address'] = array(
            'consignee' => $orderInfo['consignee'],
            'country' => $orderInfo['country'],
            'province' => $orderInfo['province'],
            'city' => $orderInfo['city'],
            'address' => $orderInfo['address'],
        );
        // 活动信息
        $actInfo = Db::name('goods_activity')->alias('ga')
            ->join('goods g', 'ga.goods_id=g.goods_id')
            ->where("act_id={$orderInfo['prom_id']}")
            ->field('ga.status, g.goods_name, g.original_img, ga.win_user_id, ga.lucky_number')
            ->find();
        $result['actInfo'] = $actInfo;

        $winner = Db::name('users')->where("user_id={$actInfo['win_user_id']}")->field('nickname')->find();
        $result['actInfo']['winner_nickname'] = $winner['nickname'];

        $my_lucky_number = Db::name('lucky_number')->where("user_id=$user_id")
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
}