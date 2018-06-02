<?php

namespace app\api\controller;
use think\Db;
use app\common\logic\UsersLogic;

class Address extends Base {

	public function __construct(){
		// 设置所有方法的默认请求方式
		$this->method = 'POST';

		parent::__construct();
	}
    
    /*
     * 用户地址列表
     */
    public function address_list()
    {
        $user_id = I('user_id/d');

        $address_lists = M('user_address')->where("user_id={$user_id}")
            ->field('address_id, consignee, zipcode, door_number, address, city, province, mobile, country, is_default')
            ->select();

        response_success($address_lists);
    }

    public function get_default_address(){
        $user_id = I('user_id/d');

        $address_lists = M('user_address')->where("user_id={$user_id} and is_default=1")
            ->field('address_id, consignee, zipcode, door_number, address, city, province, mobile, country, is_default')
            ->find();

        response_success($address_lists);
    }

    /*
     * 添加地址
     * params [user_id, consignee, zipcode, door_number, address, city, province, mobile, country, is_default]
     */
    public function add_address()
    {
        $postdata = input('post.');
        $user_id = $postdata['user_id'];

        $logic = new UsersLogic();
        $data = $logic->add_address($user_id, 0, $postdata);

        if($data['status'] == 1){
            response_success('', '添加成功');
        } else {
            response_error($data['msg']);
        }
    }

    /*
     * 地址编辑
     */
    public function edit_address()
    {
        $postdata = input('post.');
        $user_id = $postdata['user_id'];
        $address_id = $postdata['address_id'];

        $logic = new UsersLogic();
        $data = $logic->add_address($user_id, $address_id, $postdata);

        if($data['status'] == 1){
            response_success('', '操作成功');
        } else {
            response_error($data['msg']);
        }
    }

    /*
     * 设置默认收货地址
     */
    public function set_default()
    {
        $user_id = I('user_id/d');
        $address_id = I('address_id/d');
        M('user_address')->where(array('user_id' => $user_id))->save(array('is_default' => 0));
        $row = M('user_address')->where(array('user_id' => $user_id, 'address_id' => $address_id))->save(array('is_default' => 1));
        
        if($row){
            response_success('', '操作成功');
        } else {
            response_error('', '操作失败');
        }
    }

    /*
     * 地址删除
     */
    public function del_address()
    {
        $user_id = I('user_id/d');
        $address_id = I('address_id/d');

        $address = M('user_address')->where("address_id", $address_id)->find();
        $row = M('user_address')->where(array('user_id' => $user_id, 'address_id' => $address_id))->delete();
        // 如果删除的是默认收货地址 则要把第一个地址设置为默认收货地址
        if ($address['is_default'] == 1) {
            $address2 = M('user_address')->where("user_id", $user_id)->find();
            $address2 && M('user_address')->where("address_id", $address2['address_id'])->save(array('is_default' => 1));
        }
        
        if($row){
            response_success('', '操作成功');
        } else {
            response_error('操作失败');
        }
    }
}