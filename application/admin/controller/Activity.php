<?php

namespace app\admin\controller;

use app\admin\model\Goods;
use think\Page;
use think\Loader;
use think\Db;

class Activity extends Base
{

    // 夺宝列表
    public function index()
    {
        $where = array(
            'parent_id' => '0',
        );

        $count = M('goods_activity')->where($where)->count();
        $page = new Page($count, 20);
        $showPage = $page->show();

        $lists = M('goods_activity')->alias('ga')
            ->join('goods g', 'ga.goods_id=g.goods_id')
            ->where($where)
            ->order('act_id desc')
            ->limit($page->firstRow.','.$page->listRows)
            ->select()
            ;

        $this->assign('page', $page);
        $this->assign('showPage', $showPage);
        $this->assign('lists', $lists);
        return $this->fetch();
    }

    // 夺宝列表
    public function continueList()
    {
        $act_id = I('act_id');
        $where = array(
            'parent_id' => $act_id,
        );

        $count = M('goods_activity')->where($where)->count();
        $page = new Page($count, 20);
        $showPage = $page->show();

        $lists = M('goods_activity')->alias('ga')
            ->join('goods g', 'ga.goods_id=g.goods_id')
            ->where($where)
            ->order('act_id desc')
            ->limit($page->firstRow.','.$page->listRows)
            ->select()
            ;

        $this->assign('page', $page);
        $this->assign('showPage', $showPage);
        $this->assign('lists', $lists);
        return $this->fetch();
    }

    public function forRobotList(){
        $where = array(
            'status' => ['<>', '3'],
        );

        $count = M('goods_activity')->where($where)->count();
        $page = new Page($count, 20);
        $showPage = $page->show();

        $lists = M('goods_activity')->alias('ga')
            ->join('goods g', 'ga.goods_id=g.goods_id')
            ->where($where)
            ->order('act_id desc')
            ->limit($page->firstRow.','.$page->listRows)
            ->select()
            ;

        $this->assign('page', $page);
        $this->assign('showPage', $showPage);
        $this->assign('lists', $lists);
        return $this->fetch();
    }

    public function duobao_modify(){
        if(IS_POST){
            $data = I('post.');
            p($data);
            $data['end_time'] = strtotime($data['end_time']);
            $data['total_count'] = floor($data['shop_price']);
            $data['surplus'] = floor($data['shop_price']);

            // 数据验证
            $validate = Loader::validate('Duobao');
            if (!$validate->batch()->check($data)) {
                $error = $validate->getError();
                $error_msg = array_values($error);
                $return = ['status' => 0, 'msg' => $error_msg[0], 'result' => $error];
                $this->ajaxReturn($return);
            }
p($data);
            if(empty($data['id'])){
                // 计算期数
                $max_phase = Db::name('goods_activity')->max('phase');
                $data['phase'] = $max_phase+1;
                $insertId = Db::name('goods_activity')->insert($data);

                adminLog("添加夺宝 " . $data['goods_name']);
                if ($insertId !== false) {
                    Db::name('goods_activity')->update(array('act_id'=>$insertId, 'relation_id'=>$insertId));
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

    public function orderList()
    {
        $act_id = I('act_id/d');
        $where = array(
            'prom_type' => '4',
            'prom_id' => $act_id,
        );

        $count = M('order')->where($where)->count();
        $page = new Page($count, 20);
        $showPage = $page->show();

        $lists = M('order')->alias('o')
            ->join('users u', 'u.user_id=o.user_id')
            ->where($where)
            ->order('order_id desc')
            ->limit($page->firstRow.','.$page->listRows)
            ->field('nickname, u.mobile, num, add_time')
            ->select()
            ;

        $this->assign('page', $page);
        $this->assign('showPage', $showPage);
        $this->assign('lists', $lists);
        return $this->fetch();
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