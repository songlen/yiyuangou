<?php

namespace app\api\controller;
use think\Db;
use app\api\logic\OpenPrizeLogic;

class Task extends Base {
    public function __construct(){
        // 设置所有方法的默认请求方式
        $this->method = 'GET';

        parent::__construct();
    }

    // public function test(){
    //     file_put_contents('test.log', date('Y-m-d H:i:s')."\r\n", FILE_APPEND);
    // }

    /**
     * [openPrize 活动开奖]
     *（1） 求和：最后一百条购买记录的时间之和
     *（2） 取余：按照步骤（1）的数值%本商品参与人次
     *（3） 计算结果：步骤（2）的数值+10000001=中奖幸运码
     * @return [type] [description]
     */
    public function openPrize(){
        $time = time();
        $where = array(
            'end_time' => ['<=', time()+30],
            'status' => '1',
        );

        $activits = Db::name('goods_activity')->where($where)->field('act_id, goods_id, goods_name')->select();
        if(empty($activits)){
            exit();
        }

       foreach ($activits as $key => $item) {
            $act_id = $item['act_id'];
            // 执行开奖流程
            $OpenPrizeLogic = new OpenPrizeLogic();
            $OpenPrizeLogic->exec($act_id);
       }
    }

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

    /**
     * [cancleOrder 取消下单十分钟未支付的订单 ]
     * @return [type] [description]
     */
    public function cancelOrder(){

        $where = array(
            'pay_status' => '0',
            'order_status' => '0',
            'add_time' => ['<', time()-600], // 下单超过十分钟的
        );
        $orders = M('order')->where($where)
            ->field('order_id, prom_id, num')
            ->select();

        if(!empty($orders)){
            foreach ($orders as $item) {
                // 活动表增减数量
                $act_id = $item['prom_id'];
                $num = $item['num'];
                // 剩余份额加回去
                Db::name('GoodsActivity')->where('act_id', $act_id)->setInc('surplus', $num); 
                // 冻结份额减掉
                Db::name('GoodsActivity')->where('act_id', $act_id)->setDec('freeze_count', $num); 
                // 更改订单状态为 已作废 5
                Db::name('order')->where('order_id', $item['order_id'])->setField('order_status', 5);
            }
        }
    }
}