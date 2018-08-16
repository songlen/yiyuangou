<?php

namespace app\api\controller;
use think\Controller;

class Base extends Controller {
    protected $method = 'GET';

    public function __construct(){
        header("Access-Control-Allow-Origin: *"); // 允许跨域
        date_default_timezone_set('America/Toronto'); // 设置多伦多时区
        parent::__construct();

        // 请求写入log
        $this->requestToLog();
        $this->checkMethod();
    }

    protected function checkMethod(){
        // 允许的请求方式
        $allow_method = strtoupper($this->method);

        // 当前请求方式
        $method = $this->request->method();

        if(!($method == $allow_method)) {
            response_error('', '不被允许的请求');
        }
    }

    protected function requestToLog(){
        $pathinfo = $this->request->pathinfo();
        $method = $this->request->method();
        $param = $this->request->param();

        if($_FILES){
            $param = array_merge($param, $_FILES);
        }

        $data = "\r\n".date('Y-m-d H:i:s')." ".$pathinfo." method: {$method} \r\n param: ".var_export($param, true);

        // $logPath = ROOT_PATH.'/runtime/log/'.date('Ymd').'/requestlog.txt';

        file_put_contents('runtime/log/request.log', $data, FILE_APPEND);
    }

     /**
     * [getAllCategory 获取活动分类]
     * @return [type] [description]
     */
    protected function getAllCategory(){
        $where = array(
            'is_show' => '1',
            'parent_id' => '0',
        );

        $categoryList = M('goods_category')->where($where)->field('id, name')->order('sort_order desc')->select();

        return $categoryList;
    }
}