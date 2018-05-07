<?php
/**
 * tpshop

 * 采用最新Thinkphp5助手函数特性实现单字母函数M D U等简写方式
 * ============================================================================
 * @Author: lhb
 */

namespace app\admin\controller;

use think\Controller;
use app\common\logic\Saas;

class Sso extends Controller
{
    public function logout()
    {
        $ssoToken = input('sso_token', '');

        $return = Saas::instance()->ssoLogout($ssoToken);

        ajaxReturn($return);
    }
}