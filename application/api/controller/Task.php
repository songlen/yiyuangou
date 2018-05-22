<?php
/**
 * tpshop
 * 个人学习免费, 如果商业用途务必到TPshop官网购买授权.
 * 采用最新Thinkphp5助手函数特性实现单字母函数M D U等简写方式
 * ============================================================================
 *
 */ 
namespace app\api\controller;
use think\Db;

class Task {

	    /**
     * [robot_task 执行定时任务读取数据库执行下单]
     * @return [type] [description]
     */
    public function robot_task(){

        $time = time();
        $where = array(
            'ready_time'=>[['>', $time-60], ['<=', $time+60]],
            'status' => ['<>', '1'],
        );
        $robot_detail = Db::name('robot_detail')->where($where)->select();

        if($robot_detail){
            foreach ($robot_detail as $item) {
                $actinfo = Db::name('goods_activity')->find($item['act_id']);
                $surplus_num = $actinfo['total_count']-$actinfo['buy_count'];
                if($item['num'] > $surplus_num){
                    $data = array(
                        'id'=>$item['id'],
                        'exec_time' => time(),
                        'status' => '2',
                    );
                    Db::name('robot_detail')->update($data);
                } else {
                    $this->addRobotOrder($item['act_id'], $item['user_id'], $item['num']);

                    $data = array(
                        'id'=>$item['id'],
                        'exec_time' => time(),
                        'status' => '1',
                    );
                    Db::name('robot_detail')->update($data);
                    // 更新机器人日记总表
                    Db::name('robot')->where('id', $item['robot_id'])->setInc('exec_num', $item['num']);
                }
            }
        }
    }

    /**
     * [addRobotOrder 机器人下单]
     */
    private function addRobotOrder($act_id=1, $user_id=1, $num=2){
        $actinfo = Db::name('goods_activity')->find($act_id);

        // 判断
        $actinfo = Db::name('goods_activity')->find($act_id);
        $phase_num = $actinfo['total_count']-$actinfo['buy_count'];
        if($num > $phase_num){
            return false;
        }

        // 用户资料
        $userinfo = Db::name('users')->find($user_id);

        $order_sn = date('YmdHis').mt_rand(1000,9999);
        $orderdata = array(
            'order_sn' => $order_sn,
            'user_id' => $user_id,
            'pay_status' => 1,
            'mobile' => $userinfo['mobile'],
            'goods_price' => $num,
            'add_time' => time(),
            'robot' => 1,
            'prom_type' => 4, // 订单类型
        );

        $order_id = Db::name('order')->insertGetId($orderdata);

        // 活动订单附加表
        if($order_id){
            $activityData = array(
                'order_id' => $order_id,
                'order_sn' => $order_sn,
                'user_id' => $user_id,
                'act_id' => $act_id,
                'num' => $num,
                'add_time' => time(),
                'add_time_ms' => 0,
            );

            Db::name('order_activity')->insert($activityData);

            // 活动表增减数量
            Db::name('GoodsActivity')->where('act_id', $act_id)->setDec('surplus', $num);
            Db::name('GoodsActivity')->where('act_id', $act_id)->setInc('buy_count', $num);
        }
    }
}