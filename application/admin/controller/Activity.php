<?php
/**
 * tpshop

 * 采用最新Thinkphp5助手函数特性实现单字母函数M D U等简写方式
 * ============================================================================
 * Author: 当燃
 * 专题管理
 * Date: 2016-03-09
 */

namespace app\admin\controller;

use app\admin\model\FlashSale;
use app\admin\model\Goods;
use app\admin\model\GoodsActivity;
use app\admin\model\GroupBuy;
use app\common\model\PromGoods;
use think\AjaxPage;
use think\Page;
use app\admin\logic\GoodsLogic;
use think\Loader;
use think\Db;

class Activity extends Base
{

    public function index()
    {
        $where = array(
        );

        $count = M('goods_activity')->where($where)->count();
        $Page = new Page($count, 20);
        $showPage = $Page->show();

        $lists = M('goods_activity')->alias('ga')
            ->join('goods g', 'ga.goods_id=g.goods_id')
            ->where($where)
            ->order('act_id desc')
            ->limit($Page->firstRow.','.$Page->listRows)
            ->select()
            ;

        $this->assign('page', $showPage);
        $this->assign('lists', $lists);
        return $this->fetch();
    }

    public function duobao_modify(){
        if(IS_POST){
            $data = I('post.');
            $data['end_time'] = strtotime($data['end_time']);

            $data['total_count'] = floor(100.22);

            // 数据验证
            $validate = Loader::validate('Duobao');
            if (!$validate->batch()->check($data)) {
                $error = $validate->getError();
                $error_msg = array_values($error);
                $return = ['status' => 0, 'msg' => $error_msg[0], 'result' => $error];
                $this->ajaxReturn($return);
            }

            if(empty($data['id'])){
                // 计算期数
                $max_phase = Db::name('goods_activity')->max('phase');
                $data['phase'] = $max_phase+1;
                $insertId = Db::name('goods_activity')->insert($data);

                adminLog("添加夺宝 " . $data['goods_name']);
                if ($insertId !== false) {
                    $this->ajaxReturn(['status' => 1, 'msg' => '添加夺宝成功', 'result' => '']);
                } else {
                    $this->ajaxReturn(['status' => 0, 'msg' => '添加夺宝失败', 'result' => '']);
                }
            } else {
                $act_id = $data['id'];
                unset($data['id']);
                Db::name('goods_activity')->where(array('act_id'=>$act_id))->save($data);

                $this->ajaxReturn(['status' => 1, 'msg' => '修改成功', 'result' => '']);
            }
        }

        $id = I('id');
        $info = M('goods_activity')->alias('ga')
            ->join('goods g', 'ga.goods_id=g.goods_id')
            ->find($id)
            ;

        $this->assign('info', $info);
        return $this->fetch();
    }

    /**
     * [robot_add 添加机器人]
     * @return [type] [description]
     */
    public function robot_add(){
        if(IS_POST){

            $data = I('post.');
            

            // $Validate = Loader::validate('RobotAdd');
            // if(!$Validate->batch()->check($data)){
            //     $error = '';
            //     foreach ($promGoodsValidate->getError() as $value){
            //         $error .= $value.'！';
            //     }
            //     $this->ajaxReturn(['status' => 0,'msg' =>$error,'token'=>\think\Request::instance()->token()]);
            // }

            $act_id = $data['act_id'];
            $robot_num = $data['robot_num'];
            $num = $data['num'];
            $start_time = strtotime($data['start_time']);
            $end_time = strtotime($data['end_time']);
            
            $activity = M('goods_activity')->find($act_id);
            $surplus_num = $activity['total_count']-$activity['buy_count'];

            if($num > $surplus_num){
                $this->ajaxReturn(array('code'=>'-1', 'msg'=>'购买数量超过剩余数量'));
            }

            // 取出机器人
            $robots = $this->getRobot($act_id, $robot_num);
            // 给机器人分配份额和执行时间
            $robotsDetail = $this->assignNum($robots, $num, $act_id, $start_time, $end_time);

            $robotData = array(
                'act_id' => $act_id,
                'robot_num' => $robot_num,
                'total_num' => $num,
                'start_time' => $start_time,
                'end_time' => $end_time,
            );

            // 记录写入数据库
            $robot_id = Db::name('robot')->insertGetId($robotData);
            foreach ($robotsDetail as $item) {
                $item['robot_id'] = $robot_id;
                Db::name('robot_detail')->insert($item);
            }

            $return = ['status' => 1, 'msg' => '操作成功'];
            $this->ajaxReturn($return);
        }

        $act_id = I('act_id');
        $info = M('goods_activity')->alias('ga')
            ->join('goods g', 'ga.goods_id=g.goods_id')
            ->find($id)
            ;

        $this->assign('info', $info);
        return $this->fetch();
    }

    /**
     * [robot_task 执行定时任务读取数据库执行下单]
     * @return [type] [description]
     */
    public function robot_task(){
        $time = time();
        $where = array(
            'ready_time'=>[['>', $time-60], ['<=', $time+60]],
            'status' => ['<>', '1'],
        );
        $robot_detail = Db::name('robot_detail')->where($where)->select();

        if($robot_detail){
            foreach ($robot_detail as $item) {
                $actinfo = Db::name('goods_activity')->find($item['act_id']);
                $surplus_num = $actinfo['total_count']-$actinfo['buy_count'];
                if($item['num'] > $surplus_num){
                    $data = array(
                        'id'=>$item['id'],
                        'exec_time' => time(),
                        'status' => '2',
                    );
                    Db::name('robot_detail')->update($data);
                } else {
                    $this->addRobotOrder($item['act_id'], $item['user_id'], $item['num']);

                    $data = array(
                        'id'=>$item['id'],
                        'exec_time' => time(),
                        'status' => '1',
                    );
                    Db::name('robot_detail')->update($data);
                    // 更新机器人日记总表
                    Db::name('robot')->where('id', $item['robot_id'])->setInc('exec_num', $item['num']);
                }
            }
        }
    }

    /**
     * [addRobotOrder 机器人下单]
     */
    private function addRobotOrder($act_id=1, $user_id=1, $num=2){
        $actinfo = Db::name('goods_activity')->find($act_id);

        // 判断
        $actinfo = Db::name('goods_activity')->find($act_id);
        $phase_num = $actinfo['total_count']-$actinfo['buy_count'];
        if($num > $phase_num){
            return false;
        }

        // 用户资料
        $userinfo = Db::name('users')->find($user_id);

        $order_sn = date('YmdHis').mt_rand(1000,9999);
        $orderdata = array(
            'order_sn' => $order_sn,
            'user_id' => $user_id,
            'pay_status' => 1,
            'mobile' => $userinfo['mobile'],
            'goods_price' => $num,
            'add_time' => time(),
            'robot' => 1,
        );

        $order_id = Db::name('order')->insertGetId($orderdata);

        // 活动订单附加表
        if($order_id){
            $activityData = array(
                'order_id' => $order_id,
                'order_sn' => $order_sn,
                'user_id' => $user_id,
                'act_id' => $act_id,
                'num' => $num,
                'add_time' => time(),
                'add_time_ms' => $add_time_ms,
            );

            Db::name('order_activity')->insert($activityData);

            // 活动表增减数量
            Db::name('GoodsActivity')->where('act_id', $act_id)->setDec('total_count', $num);
            Db::name('GoodsActivity')->where('act_id', $act_id)->setInc('buy_count', $num);
        }

        

    }

    private function getRobot($act_id, $num){
        $sql = "select user_id from tp_users as u  where not exists(select 1 from tp_order_activity as o where u.user_id=o.user_id and o.act_id = $act_id) and u.robot='1' order by rand() limit $num";
        return Db::query($sql);
    }

    /**
     * [assignNum 给机器人分配份额, 并整理]
     * @param  [array] $robots [机器人数组]
     * @param  [int] $num    [总份额]
     * @return [type]         [description]
     */
    private function assignNum($robots, $num, $act_id, $start_time, $end_time){
        if(empty($robots)) return false;

        // 首先循环一次，保证没个机器人都能分到一份

        $robots_count = count($robots);
        $remainder = $num-$robots_count; //剩余份额
        foreach ($robots as $k => &$robot) {
            // 如果分配完了跳出
            if($random_num < 0) break;
            // 如果到最后一个人了，把剩余的全他
            if($k == $robots_count-2 && $remainder > 0){
                $robot['num'] = $remainder+1;
                break;
            }

           $random_num = rand(0, $remainder);
           $robot['num'] = $random_num+1;
           $robot['act_id'] = $act_id;
           // 随机时间
           $start_time = $start_time+60;
           $end_time = $end_time-60;
           $robot['ready_time'] = rand($start_time, $end_time);

           $remainder -= $random_num;
        }

        return $robots;
    }

    private function initEditor()
    {
        $this->assign("URL_upload", U('Admin/Ueditor/imageUp', array('savepath' => 'promotion')));
        $this->assign("URL_fileUp", U('Admin/Ueditor/fileUp', array('savepath' => 'promotion')));
        $this->assign("URL_scrawlUp", U('Admin/Ueditor/scrawlUp', array('savepath' => 'promotion')));
        $this->assign("URL_getRemoteImage", U('Admin/Ueditor/getRemoteImage', array('savepath' => 'promotion')));
        $this->assign("URL_imageManager", U('Admin/Ueditor/imageManager', array('savepath' => 'promotion')));
        $this->assign("URL_imageUp", U('Admin/Ueditor/imageUp', array('savepath' => 'promotion')));
        $this->assign("URL_getMovie", U('Admin/Ueditor/getMovie', array('savepath' => 'promotion')));
        $this->assign("URL_Home", "");
    }
}