<?php
/**
 * tpshop
 * 个人学习免费, 如果商业用途务必到TPshop官网购买授权.
 * 采用最新Thinkphp5助手函数特性实现单字母函数M D U等简写方式
 * ============================================================================
 *
 */ 
namespace app\api\controller;
use think\Controller;

class Base extends Controller {
    protected $method = 'GET';

    public function __construct(){
        parent::__construct();

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
}