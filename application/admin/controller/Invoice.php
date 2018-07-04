<?php

/**
 * tpshop

 * 如果商业用途务必到官方购买正版授权, 以免引起不必要的法律纠纷.
 * ============================================================================
 * 发票控制器
 * Author: 545
 * Date: 2017-10-23
 */

namespace app\admin\controller;

use think\AjaxPage;
use think\Db;
use think\Page;

class Invoice extends Base {
    /*
     * 初始化操作
     */

    public function _initialize() {
        parent::_initialize();
        C('TOKEN_ON', false); // 关闭表单令牌验证
    }

    /*
     * 发票列表
     */
    public function index() {
       header("Content-type: text/html; charset=utf-8");
exit("功能未开发");
    }
    
    /**
     * 发票列表 ajax
     * @date 2017/10/23
     */
    public function ajaxindex() {
    header("Content-type: text/html; charset=utf-8");
exit("功能未开发");
    }
    
     //开票时间
    function changetime(){
     header("Content-type: text/html; charset=utf-8");
exit("功能未开发");
    }
    
    public function export_invoice()
    {
    header("Content-type: text/html; charset=utf-8");
exit("功能未开发");
    }

}
