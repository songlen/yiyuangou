<?php
/**
 * tpshop
 * 个人学习免费, 如果商业用途务必到TPshop官网购买授权.
 * 采用最新Thinkphp5助手函数特性实现单字母函数M D U等简写方式
 * ============================================================================
 *
 */ 
namespace app\api\controller;
use think\Db;

class Activity extends Base {

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

        

        $data['goods_activity'] = $goods_activity;

        response_success($data);
    }

    /**
     * 即将揭晓
     */
    public function publishSoon(){
        $categoryList = $this->getAllCategory();
        $data['categoryList'] = $categoryList;

        // 商品
        $ga_where = array(
            'ga.act_type' => '3',
            'ga.is_publish' => '1',
            'ga.is_finished' => '0',
        );

        $goods_activity = M('goods_activity')->alias('ga')
            ->join('goods g', 'ga.goods_id=g.goods_id')
            ->where($ga_where)
            ->field('ga.act_id, ga.phase, ga.total_count, ga.buy_count, g.goods_id, g.goods_name, g.shop_price, g.original_img')
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

    /**
     * [getAllCategory description]
     * @return [type] [description]
     */
    public function getAllCategory(){
        $where = array(
            'is_show' => '1',
            'parent_id' => '0',
        );

        $categoryList = Db::name('goods_category')->where($where)->field('id, name')->order('sort_order desc')->select();

        return $categoryList;
    }
}