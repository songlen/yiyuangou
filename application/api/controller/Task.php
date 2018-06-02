<?php

namespace app\api\controller;
use think\Db;

class Task {

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
            'end_time' => ['<=', time()+5],
            'status' => '1',
        );

        $activits = Db::name('goods_activity')->where($where)->field('act_id')->select();
        if(empty($activits)){
            exit();
        }

       foreach ($activits as $key => $item) {
            $act_id = $item['act_id'];
            // 计算幸运号
            $luckyInfo =$this->generateLuckyInfo($act_id);

            // 活动表记录中奖信息
            $actUpdateData = array(
                'act_id'=>$act_id,
                'lucky_number'=>$luckyInfo[' lucky_number'],
                'win_user_id'=>$luckyInfo[' win_user_id'],
                'status' => '3',
            );
            Db::name('goods_activity')->update($actUpdateData);
            // 幸运码表记录中奖信息
            Db::name('LuckyNumber')->where("lucky_number=$lucky_number and act_id=$act_id")->update(array('is_win'=>'1'));
            // 订单表中记录是否中奖
            Db::name('order')->where("order_id={$luckyInfo['order_id']}")->update(array('is_win'=>'1'));
       }
    }

    /**
     * [generateLuckyNumber 生成活动的幸运号]
     * @param  [type] $act_id [description]
     * @return [type]         [array order_id, win_user_id, lucky_number]
     */
    private function generateLuckyInfo($act_id){
        $actInfo = Db::name('goods_activity')->field('set_win')->find($act_id);
         // 购买最后100条记录
        $lastlist100 = Db::name('order')->where(array('prom_id'=>$act_id))->limit('0, 100')->field('add_time, add_time_ms')->order('order_id desc')->select();
        // 时间加起来
        $sumTime = 0;
        foreach ($lastlist100 as $item) {
            $sumTime += date('YmdHis', $item['add_time']).$item['add_time_ms'];
        }
        // 参与人次
        $count = Db::name('order')->where("prom_id=$act_id")->count();
        $mod = fmod($sumTime, $count);
        $lucky_number = $mod + 10000001;  // 诞生中奖幸运号

        // 查找中奖者
        $luckyInfo = Db::name('LuckyNumber')->where("lucky_number=$lucky_number and act_id=$act_id")
            ->field('order_id, user_id')
            ->find();
        $win_user_id = $luckyInfo['user_id'];

        // 如果活动设置的是机器人中奖，则检测是否机器人
        if($actInfo['set_win'] == '1'){

            // 1检测幸运号是否机器人
            $user = Db::name('users')->where("user_id=$win_user_id")->field('user_id, robot')->find();
            if($user['robot']  == '1'){ // 如果抽中的是机器人，直接返回结果
               goto returnResult;
            }

            // 如果抽中的幸运号不是机器人，则继续抽机器人
             // 判断订单中是否有机器人，如果没有机器人就停止，如果有机器人就找最近的机器人
            $exist_robot_order = Db::name('order')->where("prom_id=$act_id and prom_type=4 and robot=1")->field('count(1)')->find();

            if( ! $exist_robot_order){
                goto returnResult;
            }
            
            // 向上查找
            $luckyOrder = Db::name('order')->where("order_id < {$luckyInfo['order_id']} and prom_id=$act_id and robot=1")->order('order_id desc')->field('order_id')->find();

            if(empty($luckyOrder)){
                // 向下查找
                 $luckyOrder = Db::name('order')->where("order_id > {$luckyInfo['order_id']} and prom_id=$act_id and robot=1")->order('order_id asc')->field('order_id')->find();
            }

            // 再次查找幸运码信息
            $luckyInfo = Db::name('LuckyNumber')->where("order_id={$luckyOrder['order_id']}")
                ->field('order_id, user_id, lucky_number')
                ->find();
            $win_user_id = $luckyInfo['user_id'];
            $lucky_number = $luckyInfo['lucky_number'];

        } else { // 如果活动设置的是真人中奖，不管幸运号是机器人还是真人，直接返回幸运号

        }

        returnResult:
        return array(
            'order_id' => $luckyInfo['order_id'],
            'win_user_id' => $win_user_id,
            'lucky_number' => $lucky_number,
        );
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
}