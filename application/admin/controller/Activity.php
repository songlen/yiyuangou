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
            'is_finished' => '0',
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