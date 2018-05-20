<?php

namespace app\api\controller;
use think\Db;

class User extends Base {

	public function __construct(){
		// 设置所有方法的默认请求方式
		$this->method = 'POST';

		parent::__construct();
	}


    /**
     * 登录
     */
    public function do_login()
    {
        $username = trim(I('post.username'));
        $password = trim(I('post.password'));
        //验证码验证
        if (isset($_POST['verify_code'])) {
            $verify_code = I('post.verify_code');
            $verify = new Verify();
            if (!$verify->check($verify_code, 'user_login')) {
                $res = array('status' => 0, 'msg' => '验证码错误');
                exit(json_encode($res));
            }
        }
        $logic = new UsersLogic();
        $res = $logic->login($username, $password);
        if ($res['status'] == 1) {
            $res['url'] = htmlspecialchars_decode(I('post.referurl'));
            session('user', $res['result']);
            setcookie('user_id', $res['result']['user_id'], null, '/');
            setcookie('is_distribut', $res['result']['is_distribut'], null, '/');
            $nickname = empty($res['result']['nickname']) ? $username : $res['result']['nickname'];
            setcookie('uname', urlencode($nickname), null, '/');
            setcookie('cn', 0, time() - 3600, '/');
            $cartLogic = new CartLogic();
            $cartLogic->setUserId($res['result']['user_id']);
            $cartLogic->doUserLoginHandle();// 用户登录后 需要对购物车 一些操作
            $orderLogic = new OrderLogic();
            $orderLogic->setUserId($res['result']['user_id']);//登录后将超时未支付订单给取消掉
            $orderLogic->abolishOrder();
        }
        exit(json_encode($res));
    }

    /**
     *  注册
     */
    public function register() {
    	$mobile = I('mobile');
    	$code = I('code');
    	$password = I('password');
    	$password_confirm = I('password_confirm');

    	if(check_mobile($mobile) == false){
    		response_error('手机号格式错误');
    	}

    	$userInfo = Db::name('users')->where("mobile={$mobile}")->find();
    	if($userInfo){
    		response_error('该手机号已注册');
    	}

    	// 验证码检测
    	// 

    	if(empty($password) || empty($password_confirm)){
    		response_error('密码不能为空');
    	}
    	if($password != $password_confirm){
    		response_error('两次密码输入不一致');
    	}

    	$map = array(
    		'mobile' => $mobile,
    		'password' => encrypt($password),
    		'nickname' => $mobile,
    		'reg_time' => time(),
    		'last_login' => time(),
    		'token' => md5(time().mt_rand(1,999999999)),
    	);

    	$user_id = M('users')->insertGetId($map);
        if($user_id === false){
           response_error('注册失败');
        }
        
        $pay_points = tpCache('basic.reg_integral'); // 会员注册赠送积分
        if($pay_points > 0){
            accountLog($user_id, 0,$pay_points, '会员注册赠送积分'); // 记录日志流水
        }
        
        $userInfo = $this->getUserInfo($user_id);
        return response_success($userInfo, '注册成功');
    }

    /**
     * [getUserInfo 获取用户基本资料]
     * @param  [type] $user_id [description]
     * @return [type]          [description]
     */
    private function getUserInfo($user_id){
    	$userInfo = M('users')->where("user_id", $user_id)
    		->field('user_id, mobile, nickname, head_pic')
    		->find();
       
       return $userInfo;
    }
}