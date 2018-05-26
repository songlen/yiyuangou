<?php

namespace app\api\controller;
use think\Db;

class Index extends Base {

	public function __construct(){
		// 设置所有方法的默认请求方式
		$this->method = 'POST';

		parent::__construct();
	}
    
    public function index(){

    	$ad_where = array(
    		'pid' => '1',
    		'media_type' => '0',
    		'enabled' => '1',
    	);
        $adList = M('ad')->where($ad_where)->field('ad_name, ad_code, ad_link')->select();

        $data['adList'] = $adList;

        // 商品
        $ga_where = array(
        	'ga.act_type' => '3',
        	'ga.status' => '1',
        );

        $page = I('page', 1);

        $goods_activity = M('goods_activity')->alias('ga')
        	->join('goods g', 'ga.goods_id=g.goods_id')
        	->where($ga_where)
        	->field('ga.act_id, ga.total_count, ga.buy_count, g.goods_id, g.goods_name, g.shop_price, g.original_img')
            ->limit($page, 10)
        	->select()
        	;

        if(!empty($goods_activity)){
        	foreach ($goods_activity as &$item) {
        		$item['surplus'] = $item['total_count']-$item['buy_count'];
        	}
        }

        $data['goods_activity'] = $goods_activity;

        response_success($data);
    }
}