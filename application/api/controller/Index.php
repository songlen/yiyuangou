<?php

namespace app\api\controller;
use think\Db;

class Index extends Base {

	public function __construct(){
		// 设置所有方法的默认请求方式
		$this->method = 'POST';

		parent::__construct();
	}

    // 获取banner
    public function adv(){
        $ad_where = array(
            'pid' => '1',
            'media_type' => '0',
            'enabled' => '1',
        );
        $adList = M('ad')->where($ad_where)->field('ad_name, ad_code, ad_link')->select();

        response_success($adList);
    }
    
    public function index(){
        $page = I('page', 1);
        $keyword = I('keyword');

        // 商品
        $ga_where = array(
        	'ga.act_type' => '3',
        	'ga.status' => '1',
            'is_publish' => '1', 
        );

        if($keyword){
            $ga_where['g.goods_name'] = ['like', "%$keyword%"];
        }

        $goods_activity = M('goods_activity')->alias('ga')
        	->join('goods g', 'ga.goods_id=g.goods_id')
        	->where($ga_where)
        	->field('ga.act_id, ga.total_count, ga.status, ga.buy_count, g.goods_id, g.goods_name, g.shop_price, g.original_img')
            ->limit(($page-1), 10)
        	->select()
        	;

        if(!empty($goods_activity)){
        	foreach ($goods_activity as &$item) {
        		$item['surplus'] = $item['total_count']-$item['buy_count'];
        	}
        }

        response_success($goods_activity);
    }
}