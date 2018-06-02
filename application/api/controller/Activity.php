<?php

namespace app\api\controller;
use think\Db;

class Activity extends Base {

	public function __construct(){
		// 设置所有方法的默认请求方式
		$this->method = 'POST';

		parent::__construct();
	}
    
    // 夺宝详情
    public function actInfo(){
        $act_id = I('act_id');

        // 活动详情
        $info = M('goods_activity')->alias('ga')
                ->join('goods g', 'g.goods_id=ga.goods_id')
                ->field('ga.act_id, ga.end_time, ga.phase, ga.total_count, ga.buy_count, g.goods_id, g.goods_name, g.shop_price, g.original_img')
                ->find($act_id)
                ;
        $data['actInfo'] = $info;
        // 购买规则
        $config_basic = tpcache('basic');
        $data['buy_rules'] = $config_basic['buy_rules'];

        response_success($data);
    }

    /**
     * 即将揭晓
     */
    public function publishSoon(){
        $cat_id = I('cat_id/d');

        $categoryList = $this->getAllCategory();
        $data['categoryList'] = $categoryList;

        // 商品
        $ga_where = array(
            'ga.act_type' => '3',
            'ga.is_publish' => '1',
            'ga.status' => '1',
        );
        
        if($cat_id){
            $ga_where['g.cat_id'] = $cat_id;
        }

        $goods_activity = M('goods_activity')->alias('ga')
            ->join('goods g', 'ga.goods_id=g.goods_id')
            ->where($ga_where)
            ->field('ga.act_id, ga.phase, ga.surplus, g.goods_id, g.goods_name, g.shop_price, g.original_img')
            ->select()
            ;


        $data['goods_activity'] = $goods_activity;

        response_success($data);
    }

    /**
     * [finished 揭晓结果]
     * @return [type] [description]
     */
    public function finished(){
        $cat_id = I('cat_id/d');
        $page = I('page/d', 1);

        $categoryList = $this->getAllCategory();
        $data['categoryList'] = $categoryList;

        // 商品
        $ga_where = array(
            'ga.act_type' => '3', // 活动类别、夺宝
            'ga.is_publish' => '1', // 是否发布
            'ga.status' => '3', // 已结束状态
        );
        
        if($cat_id){
            $ga_where['g.cat_id'] = $cat_id;
        }

        $goods_activity = M('goods_activity')->alias('ga')
            ->join('goods g', 'ga.goods_id=g.goods_id')
            ->where($ga_where)
            ->field('ga.act_id, ga.win_user_id, g.goods_id, g.goods_name, g.shop_price, g.original_img')
            ->limit(($page-1)*10, 10)
            ->select()
            ;


        if(!empty($goods_activity)){
            foreach ($goods_activity as &$item) {
                $user = Db::name('users')->find($item['win_user_id']);
                $item['winner'] = substr_replace($user['mobile'], '****', 3, 4);
            }
        }

        $data['goods_activity'] = $goods_activity;

        response_success($data);
    }

    /**
     * [getAllCategory description]
     * @return [type] [description]
     */
    private function getAllCategory(){
        $where = array(
            'is_show' => '1',
            'parent_id' => '0',
        );

        $categoryList = Db::name('goods_category')->where($where)->field('id, name')->order('sort_order desc')->select();

        return $categoryList;
    }
}