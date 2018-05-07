<?php
/**
 * tpshop HelloWorld 插件  demo 示例

 * ============================================================================
 * Author: IT宇宙人      
 * Date: 2015-09-09
 */
namespace Admin\Controller;

// 这是一个demo 插件
class HelloWorldController extends BaseController {

    public function index(){        
        $hello = M('HelloWorld')->find();        
        $this->assign('hello',$hello);
        $this->display();
    }
}