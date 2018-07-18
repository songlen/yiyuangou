<?php
/**
 * 短信验证码类
 */

namespace app\api\logic;
use think\Db;

class SmsLogic {

    private $day_count = 10; // 一天限制次数
    private $expire = 300; // 5分钟内有效

    /**
     * [send 发送验证码对外接口]
     * @param  [type] $mobile [手机号]
     * @param  [type] $scene  [场景 1 注册 2 找回密码]
     * @param  [type] &$error [返回错误信息]
     * @return [type]         [description]
     */
    public function send($mobile, $scene, &$error){
        // 检测手机号
        if(check_ca_mobile($mobile) == false){
            $error = '手机号格式错误';
            return false;
        }

        // 注册场景检测是否注册
        if($scene == '1'){
            $count = Db::name('users')->where("mobile=$mobile")->count();
            if($count){
                $error = '该手机号已注册';
                return false;
            }
        }

        // 找回密码场景检测是否注册
        if($scene == '2'){
            $count = Db::name('users')->where("mobile=$mobile")->count();
            if(!$count){
                $error = '该手机号未注册';
                return false;
            }
        }
        
        // 检测发送次数
        $day_time_start = strtotime(date('Y-m-d'));
        $day_time_end = $day_time_start + 3600*24;
        $count = Db::name('SmsLog')
            ->where('mobile', $mobile)
            ->where('add_time', ['>', $day_time_start], ['<', $day_time_end])
            ->count();

        if($count >= $this->day_count){
            $error = '您的次数已超限';
            return false;
        }

        $code = rand(100000, 999999);
        $data = array(
            'mobile' => $mobile,
            'code' => $code,
            'scene' => '1',
            'add_time' => time(),
        );

        $smsLogid = Db::name('sms_log')->insertGetId($data);

        // 执行短信网关发送 12269890032
        $result = $this->exec($mobile, '您的验证码是：'.$code);
        if($result['status'] == 1){
            Db::name('sms_log')->where("id=$smsLogid")->update(array('status'=>'1'));
        } else {
            $error = $result['error'];
            return false;
        }

        
        return $code;
    }

    /**
     * [checkCode 检测手机验证码是否正确]
     * @param  [type] $mobile [description]
     * @param  [type] $code   [description]
     * @param  [type] $scene  [description]
     * @param  [type] &$error [description]
     * @return [type]         [description]
     */
    public function checkCode($mobile, $code, $scene, &$error){
        $smsLog = Db::name('SmsLog')
            ->where("mobile=$mobile and scene=$scene")
            ->order('id desc')
            ->find();

        if(!$smsLog){
            $error = '手机验证码错误';
            return false;
        }
        if($smsLog['code'] != $code){
            $error = '手机验证码错误';
            return false;
        }

        if(time() > ($smsLog['add_time'] + $this->expire)){
            $error = '验证码已失效';
            return false;
        }

        return true;
    }

    private function exec($to_phone, $message){
        $to_phone = '+86'.$to_phone;
        // Your Account SID and Auth Token from twilio.com/console
        $account_sid = 'AC39ff2cf9521cd38011ff0bd3602a8494';
        $auth_token = 'ad2016cee4e6c67f882be74254dc43c5'; // 1a2e0aa20c3572106e790f1364a0cd5d
        // In production, these should be environment variables. E.g.:
        // $auth_token = $_ENV["TWILIO_ACCOUNT_SID"]
        $from_phone = '+12268943988';

        // A Twilio number you own with SMS capabilities

        vendor('Twilio.Deserialize');
        vendor('Twilio.InstanceResource');
        vendor('Twilio.Rest.Api.V2010.Account.MessageInstance');
        vendor('Twilio.Exceptions.TwilioException');
        vendor('Twilio.Exceptions.ConfigurationException');
        vendor('Twilio.Exceptions.TwilioException');
        vendor('Twilio.Exceptions.RestException');
        vendor('Twilio.Http.Response');
        vendor('Twilio.Exceptions.TwilioException');
        vendor('Twilio.Exceptions.EnvironmentException');
        vendor('Twilio.VersionInfo');
        vendor('Twilio.Rest.Client');
        vendor('Twilio.Http.Client');
        vendor('Twilio.Http.CurlClient');
        vendor('Twilio.Domain');
        vendor('Twilio.Rest.Api');
        vendor('Twilio.Version');
        vendor('Twilio.InstanceContext');
        vendor('Twilio.ListResource');
        vendor('Twilio.Values');
        vendor('Twilio.Serialize');
        vendor('Twilio.Rest.Api.V2010.Account.MessageList');
        vendor('Twilio.Rest.Api.V2010.AccountContext');
        vendor('Twilio.Rest.Api.V2010');

        $client = new \Twilio\Rest\Client($account_sid, $auth_token);

        try{
            $result = $client->messages->create(
                // Where to send a text message (your cell phone?)
                $to_phone,
                array(
                    'from' => $from_phone,
                    'body' => $message
                )
            );
            return array('status'=>1);
        } catch (\Exception $e){
            return array('status'=>0, 'error'=>$e->getMessage());
        }
    }
}