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
        $cat_id = I('cat_id');

        // 商品
        $ga_where = array(
        	'ga.act_type' => '3',
        	'ga.status' => '1',
            'is_publish' => '1', 
        );

        if($keyword){
            $ga_where['g.goods_name'] = ['like', "%$keyword%"];
        }
        if($cat_id) $ga_where['cat_id'] = $cat_id;

        $limit_start = ($page-1)*10;
        $goods_activity = M('goods_activity')->alias('ga')
        	->join('goods g', 'ga.goods_id=g.goods_id')
        	->where($ga_where)
        	->field('ga.act_id, ga.total_count, ga.status, ga.surplus, ga.buy_count, g.goods_id, g.goods_name, g.shop_price, g.original_img')
            ->limit($limit_start, 10)
            ->order('act_id desc')
        	->select()
        	;

        
        $data['categoryList'] = $categoryList;


        response_success($goods_activity);
    }

    public function getCategory(){
        $categoryList = $this->getAllCategory();
        array_unshift($categoryList, array('id'=>0, 'name'=>'全部'));
        array_unshift($categoryList, array('id'=>0, 'name'=>'全部'));
        array_unshift($categoryList, array('id'=>0, 'name'=>'全部'));
        array_unshift($categoryList, array('id'=>0, 'name'=>'全部'));
        array_unshift($categoryList, array('id'=>0, 'name'=>'全部'));
        array_unshift($categoryList, array('id'=>0, 'name'=>'全部'));
        response_success($categoryList);
    }
}