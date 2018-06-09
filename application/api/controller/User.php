<?php

namespace app\api\controller;
use think\Db;
use app\api\logic\SmsLogic;

class User extends Base {

	public function __construct(){
		// 设置所有方法的默认请求方式
		$this->method = 'POST';

		parent::__construct();
	}


    /**
     * 登录
     */
    public function login()
    {
        $mobile = trim(I('mobile'));
        $password = trim(I('password'));

        if (!$mobile || !$password) {
        	response_error('', '请填写账号或密码');
        }
        $user = Db::name('users')->where("mobile", $mobile)->find();
        if (!$user) {
            response_error('', '账号不存在！');
        } elseif (encrypt($password) != $user['password']) {
            response_error('', '密码错误！');
        } elseif ($user['is_lock'] == 1) {
            response_error('', '账号异常已被锁定！');
        }
        
        // $res['url'] = htmlspecialchars_decode(I('referurl'));
        session('user', $res['result']);
        setcookie('user_id', $res['result']['user_id'], null, '/');
        setcookie('nickname', urlencode($nickname), null, '/');

        // $orderLogic = new OrderLogic();
        // $orderLogic->setUserId($res['result']['user_id']);//登录后将超时未支付订单给取消掉
        // $orderLogic->abolishOrder();
        
        $userInfo = $this->getUserInfo($user['user_id']);
       	response_success($userInfo);
    }

    /**
     *  注册
     */
    public function register() {
    	$mobile = I('mobile');
    	$code = I('code');
    	$password = trim(I('password'));
    	// $password_confirm = trim(I('password_confirm'));

    	if(check_mobile($mobile) == false){
    		response_error('', '手机号格式错误');
    	}

    	$userInfo = Db::name('users')->where("mobile={$mobile}")->find();
    	if($userInfo){
    		response_error('', '该手机号已注册');
    	}

    	// 验证码检测
    	$SmsLogic = new SmsLogic();
        if($SmsLogic->checkCode($mobile, $code, '1', $error) == false) response_error('', $error);


    	if(empty($password)){
    		response_error('', '密码不能为空');
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
           response_error('', '注册失败');
        }
        
        $pay_points = tpCache('basic.reg_integral'); // 会员注册赠送积分
        if($pay_points > 0){
            accountLog($user_id, 0,$pay_points, '会员注册赠送积分'); // 记录日志流水
        }
        
        $userInfo = $this->getUserInfo($user_id);
        return response_success($userInfo, '注册成功');
    }

    public function wx_login(){
        $openid = I('openid');
        $nickname = I('nickname');
        $headimgurl = I('headimgurl');
        $sex = I('sex/d');

        // 检测用户是否已注册
        $user = Db::name('users')->where("openid=$openid")->find();
        if($user){
            $user_id = $user['user_id'];
        } else {
            $data = array(
                'openid' => $openid,
                'nickname' => $nickname,
                'head_pic' => $headimgurl,
                'sex' => $sex,
                'token' => md5(time().mt_rand(1,999999999)),
            );

            $user_id = Db::name('users')->insertGetId($data);
        }

        $userInfo = $this->getUserInfo($user_id);

        response_success($userInfo);
    }

    /**
     * [sendMobleCode 发送手机验证码]
     * @param [scene 1 注册 2 找回密码]
     * @return [type] [description]
     */
    public function sendMobileCode(){
        $scene = I('scene', 1);
        $mobile = I('mobile');

        $SmsLogic = new SmsLogic();
        $code = $SmsLogic->send($mobile, $scene, $error);
        if($code != false){
            response_success(array('code'=>$code), '发送成功');
        } else {
            response_error('', $error);
        }
    }

    // 忘记密码
    public function resetPwd(){
        $mobile = I('mobile');
        $code = I('code');
        $password = I('password');
        $password_confirm = I('password_confirm');

        if(check_mobile($mobile) == false){
            response_error('', '手机号码有误');
        }
        // 检测验证码
        $SmsLogic = new SmsLogic();
        if($SmsLogic->checkCode($mobile, $code, '1', $error) == false) response_error('', $error);

        if($password != $password_confirm){
            response_error('', '两次密码输入不一致');
        }

        $user = Db::name('users')->where("mobile = $mobile")->find();
        if(empty($user)){
            response_error('', '手机号不存在');
        }

        $password = encrypt($password);
        Db::name('users')->where("mobile=$mobile")->update(array('password'=>$password));

        response_success('', '操作成功');
    }

    // 我的积分
    public function points(){
        $user_id = I('user_id/d');
        $page = I('page/d', 1);

        $user = Db::name('users')->field('pay_points')->find($user_id);

        $account_log = M('account_log')->where("user_id=" . $user_id." and pay_points!=0 ")
        ->order('log_id desc')
        ->field("pay_points, FROM_UNIXTIME(change_time, '%Y-%m-%d %H:%i:%s') change_time, desc, order_sn")
        ->limit(($page-1)*10 . ', 10')
        ->select();

        $result['total_points'] = $user['pay_points'];
        $result['points_log'] = $account_log;

        response_success($result);
    }

    // 修改昵称
    public function changeNickname(){
        $user_id = I('user_id/d');
        $nickname = I('nickname');

        $updateData = array(
            'user_id' => $user_id,
            'nickname' => $nickname,
        );
        Db::name('users')->update($updateData);

        response_success('', '操作成功');
    }
    // 修改昵称
    public function changeSex(){
        $user_id = I('user_id/d');
        $sex = I('sex');

        $updateData = array(
            'user_id' => $user_id,
            'sex' => $sex,
        );
        Db::name('users')->update($updateData);

        response_success('', '操作成功');
    }

    // 头像修改 
    public function changeHeadPic(){
        $user_id = I('user_id/d');
        $file = $this->request->file('head_pic');

        $image_upload_limit_size = config('image_upload_limit_size');
        $validate = ['size'=>$image_upload_limit_size,'ext'=>'jpg,png,gif,jpeg'];
        $dir = UPLOAD_PATH.'head_pic/';
        if (!($_exists = file_exists($dir))){
            $isMk = mkdir($dir);
        }
        $parentDir = date('Ymd');
        $info = $file->validate($validate)->move($dir, true);
        if($info){
            $head_pic = '/'.$dir.$parentDir.'/'.$info->getFilename();
            Db::name('users')->update(array('user_id'=>$user_id, 'head_pic'=>$head_pic));
            response_success(array('head_pic'=>$head_pic));
        }else{
            response_error('', $file->getError());
        }
    }

    // 中英文切换
    public function changeLanguage(){
        $user_id = I('user_id/d');
        $language = I('language');

        $result = Db::name('users')->update(array('user_id'=>$user_id, 'language'=>$language));

        if($result){
            response_success('', '修改成功');
        } else {
            response_error('', '需改失败');
        }
    }

    public function point_rules(){
        $config = tpCache('basic');
        $point_rules = html_entity_decode($config['point_rules']);


        response_success(array('point_rules'=>$point_rules));
    }

    // 设置页面
    public function setting(){
        $user_id = I('user_id');

        $user = Db::name('users')->where("user_id={$user_id}")->field('language')->find();
        $config = tpCache('basic');

        $result = array(
            'language' => $user['language'],
            'hotline' => $config['hotline'],
        );

        response_success($result);
    }

    // 常见问题
    public function questions(){
        $user_id = I('user_id/d');

        $where = array(
            'cat_id' => '1',
            'is_open' => '1',
        );
        $questions = Db::name('article')->where($where)
            ->order('article_id desc')
            ->field('article_id, title, description, content')
            ->select();
        response_success($questions);
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

    public function message(){
        $user_id = I('user_id/d');

        $message = Db::name('message')->alias('m')
            ->join('user_message um', 'um.message_id=m.message_id')
            ->where('user_id', $user_id)
            ->whereOr('m.type', 0)
            ->field('m.message_id, message, m.category, send_time, status')
            ->select();

        if(!empty($message)){
            $now_date = strtotime(date('Y-m-d')); // 今日凌晨
            $mid_date = strtotime(date('Y-m-d 12:00:00')) ;// 今日中午

            foreach ($message as &$item) {
                if($item['send_time'] < $now_date) $item['send_time'] = date('Y-m-d', $item['send_time']);
                if($item['send_time'] > $now_date && $item['send_time'] < $mid_date) $item['send_time'] = '上午'.date('H:i', $item['send_time']);
                if($item['send_time'] > $mid_date) $item['send_time'] = '下午'.date('H:i', $item['send_time']);
            }
        }

        response_success($message);
    }
}