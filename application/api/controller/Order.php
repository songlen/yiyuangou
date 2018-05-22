<?php

namespace app\api\controller;
use think\Db;
use think\Page;

class Order extends Base {

	public function __construct(){
		// 设置所有方法的默认请求方式
		$this->method = 'POST';

		parent::__construct();
	}
    
    public function orderList(){
        $user_id = I('user_id/d');

        $where['user_id'] = $user_id;
        $where['prom_type'] = 4;

        $count = M('order')->where($where)->count();
        $Page = new Page($count, 10);

        $order_list = M('order')
            ->where($where)
            ->order('order_id DESC')
            ->limit($Page->firstRow . ',' . $Page->listRows)
            ->field('order_id, order_sn, order_status, pay_status, total_amount')
            ->select();

        if($order_list){
            // 活动及商品信息
            foreach ($order_list as &$order) {
                $orderActivity = M('orderActivity')->alias('oa')
                    ->join('goods_activity ga', 'oa.act_id=ga.act_id')
                    ->join('goods g', 'ga.goods_id=g.goods_id')
                    ->where('order_id='.$order['order_id'])
                    ->field('oa.act_id, ga.surplus, ga.phase, g.goods_name, g.original_img')
                    ->select();
                $order['goodsList'] = $orderActivity;
            }
        }

        response_success($order_list);
    }
}