<?php
/**
 * tpshop
 * ============================================================================
 * * 版权所有 2015-2027 深圳搜豹网络科技有限公司，并保留所有权利。
 * 网站地址: http://www.tp-shop.cn
 * ----------------------------------------------------------------------------
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和使用 .
 * 不允许对程序代码以任何形式任何目的的再发布。
 * 采用最新Thinkphp5助手函数特性实现单字母函数M D U等简写方式
 * ============================================================================
 * $Author: 当燃   2016-05-10
 */ 
namespace app\mobile\controller;
use app\common\logic\GoodsLogic;
use app\common\logic\GoodsActivityLogic;
use app\common\model\FlashSale;
use app\common\model\GroupBuy;
use think\Db;
use think\Page;
use app\common\logic\ActivityLogic;

class Activity extends MobileBase {
    public function index(){      
        // return $this->fetch();
    }

    // 夺宝详情
    public function activityInfo(){
        $activity_id = $_GET['activity_id'];
        
        $info = M('goods_activity')->alias('ga')
                    ->join('goods g', 'g.goods_id=ga.goods_id')
                    ->find($activity_id)
                    ;
p($info);
        // 计算购买百分比
        $buy_percent = number_format($info['buy_count']/$info['total_count']*100).'%';

        $this->assign('info', $info);
        $this->assign('buy_percent', $buy_percent);

        return $this->fetch();
    }

    public function buy_rules(){
        $config_basic = tpcache('basic');


        $this->assign('title', '购买规则');
        $this->assign('content', html_entity_decode($config_basic['buy_rules'], ENT_QUOTES, 'utf-8'));

        return $this->fetch();
    }
    
    /**
     * 商品活动页面
     * @author lxl
     * @time2017-1
     */
    public function promote_goods(){
        $now_time = time();
        $where = " start_time <= $now_time and end_time >= $now_time ";
        $count = M('prom_goods')->where($where)->count();  // 查询满足要求的总记录数
        $pagesize = C('PAGESIZE');  //每页显示数
        $Page  = new Page($count,$pagesize); //分页类
        $promote = M('prom_goods')->field('id,title,start_time,end_time,prom_img')->where($where)->limit($Page->firstRow.','.$Page->listRows)->select();    //查询活动列表
        $this->assign('promote',$promote);
        if(I('is_ajax')){
            return $this->fetch('ajax_promote_goods');
        }
        return $this->fetch();
    }


    /**
     * 抢购活动列表页
     */
    public function flash_sale_list()
    {
        $time_space = flash_sale_time_space();
        $this->assign('time_space', $time_space);
        return $this->fetch();
    }

    /**
     * 抢购活动列表ajax
     */
    public function ajax_flash_sale()
    {
        $p = I('p',1);
        $start_time = I('start_time');
        $end_time = I('end_time');
        $where = array(
            'fl.start_time'=>array('egt',$start_time),
            'fl.end_time'=>array('elt',$end_time),
            'g.is_on_sale'=>1
        );
        $FlashSale = new FlashSale();
        $flash_sale_goods = $FlashSale->alias('fl')->join('__GOODS__ g', 'g.goods_id = fl.goods_id')->with(['specGoodsPrice','goods'])
            ->field('*,100*(FORMAT(buy_num/goods_num,2)) as percent')
            ->where($where)
            ->page($p,10)
            ->select();
        $this->assign('flash_sale_goods',$flash_sale_goods);
        return $this->fetch();
    }
    
}