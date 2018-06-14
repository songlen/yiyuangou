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
        $where = array(
            'act_id' => $act_id,
            'is_publish' => '1',
        );
        $info = M('goods_activity')->alias('ga')
                ->join('goods g', 'g.goods_id=ga.goods_id')
                ->where($where)
                ->field('ga.act_id, ga.end_time, ga.phase, ga.status, ga.total_count, ga.buy_count, ga.surplus, ga.freeze_count, ga.maiman_time, ga.parent_id, g.goods_id, g.goods_name, g.shop_price, g.original_img')
                ->find()
                ;

        if($info['buy_count'] == 0 && $info['freeze_count'] == 0){
            $info['maiman'] = 1;
        } else {
            $info['maiman'] = 0;
        }

        $data['actInfo'] = $info;
        // 购买规则
        $config_basic = tpcache('basic');
        $data['buy_rules'] = html_entity_decode($config_basic['buy_rules']);
        // 获取累计购买数
        $statistics_buy_count = Db::name('order')
            ->where('prom_id', $act_id)
            ->where('pay_status', '0')
            ->field('num, FROM_UNIXTIME(add_time, "%Y-%m-%d %H:%i:%s") time')
            ->select();

        $data['statistics_buy_count'] = $statistics_buy_count;
        // 购买过程数据
        $buy_harf_hour = Db::name('order')
            ->where('prom_id', $act_id)
            ->where('pay_status', '0')
            ->where('add_time', ['>=', time()-1800])
            ->field('num, add_time')
            ->select();


        $now_time = time();
        $time0 = $now_time-60*30;
        $time1 = $now_time-60*25;
        $time2 = $now_time-60*20;
        $time3 = $now_time-60*15;
        $time4 = $now_time-60*10;
        $time5 = $now_time-60*5;
        $time6 = $now_time;

        $statistics_buy_process = array(
            $time1 => 0,
            $time2 => 0,
            $time3 => 0,
            $time4 => 0,
            $time5 => 0,
            $time6 => 0,
        );
        $statistics_buy_process_tmp = array();
        if(!empty($buy_harf_hour)){
            foreach ($buy_harf_hour as $item) {
                $add_time = $item['add_time'];
                

                if($add_time >  $time0 && $add_time <=  $time1){
                    $statistics_buy_process_tmp[$time1] += $item['num'] ;
                }
                if($add_time >  $time1 && $add_time <=  $time2){
                    $statistics_buy_process_tmp[$time2] += $item['num'] ;
                }
                if($add_time >  $time2 && $add_time <=  $time3){
                    $statistics_buy_process_tmp[$time3] += $item['num'] ;
                }
                if($add_time >  $time3 && $add_time <=  $time4){
                    $statistics_buy_process_tmp[$time4] += $item['num'] ;
                }
                if($add_time >  $time4 && $add_time <=  $time5){
                    $statistics_buy_process_tmp[$time5] += $item['num'] ;
                }
                if($add_time >  $time5 && $add_time <=  $time6){
                    $statistics_buy_process_tmp[$time6] += $item['num'] ;
                }
            }
        }

        foreach ($statistics_buy_process as $k => $item) {
            $statistics_buy_process[] = array(
                'time' => date('H:i', $k),
                'num' =>  $statistics_buy_process_tmp[$k] ? $statistics_buy_process_tmp[$k] : 0,
            );
            unset($statistics_buy_process[$k]);
        }
        $data['statistics_buy_process'] = array_values($statistics_buy_process);

        // 查找往期
        $parent_id = $info['parent_id'];
        if($parent_id == 0){
            $data['past_phase'] = array();
        } else {

            $past_activity = M('goods_activity')->where('parent_id', $parent_id)
                ->where('phase', ['<', $info['phase']])
                ->whereOr('act_id', $parent_id)
                ->order('act_id desc')
                ->field('act_id, phase, win_user_id, lucky_number')
                ->find();


            $user = M('users')->where('user_id', $past_activity['win_user_id'])->field('nickname')->find();
            $data['past_phase'] = array(
                'act_id' => $past_activity['act_id'],
                'phase' => $past_activity['phase'],
                'luck_number' => $past_activity['luck_number'],
                'nickname' => $user['nickname'],
            );

        }

        response_success($data);
    }

    // 支付成功页，推荐的活动
    public function recommendActivity(){
        // 商品
        $ga_where = array(
            'ga.act_type' => '3',
            'ga.status' => '1',
            'is_publish' => '1',
        );

        $goods_activity = M('goods_activity')->alias('ga')
            ->join('goods g', 'ga.goods_id=g.goods_id')
            ->where($ga_where)
            ->field('ga.act_id, ga.total_count, ga.status, ga.surplus, ga.buy_count, g.goods_id, g.goods_name, g.shop_price, g.original_img')
            ->order('act_id desc')
            ->limit(4)
            ->select()
            ;

        response_success($goods_activity);
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
            'surplus' => ['<=', 10],
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