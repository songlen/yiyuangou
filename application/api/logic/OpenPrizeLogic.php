<?php
/**
 * 订单处理类
 */


namespace app\api\logic;

use think\Db;

// 开奖
class OpenPrizeLogic {

    function exec($act_id){
         // 计算幸运号
        $luckyInfo =$this->generateLuckyInfo($act_id);

        $lucky_number = $luckyInfo['lucky_number'];
        // 活动表记录中奖信息
        $actUpdateData = array(
            'act_id'=>$act_id,
            'lucky_number'=>$lucky_number,
            'win_user_id'=>$luckyInfo['win_user_id'],
            'status' => '3',
        );
        Db::name('goods_activity')->update($actUpdateData);
        // 幸运码表记录中奖信息
        Db::name('LuckyNumber')->where("lucky_number=$lucky_number and act_id=$act_id")->update(array('is_win'=>'1'));
        // 订单表中记录是否中奖
        Db::name('order')->where("order_id={$luckyInfo['order_id']}")->update(array('is_win'=>'1'));
        // 给参与用户发送是否中奖消息
        $this->send_message($act_id, $luckyInfo['win_user_id'], $item['goods_name']);
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

            // 如果设定了机器人中奖，但是没有机器人订单，就让原中奖者中奖
           if( ! empty($luckyOrder)){
                $luckyInfo = Db::name('LuckyNumber')->where("order_id={$luckyOrder['order_id']}")
                    ->field('order_id, user_id, lucky_number')
                    ->find();
                $win_user_id = $luckyInfo['user_id'];
                $lucky_number = $luckyInfo['lucky_number'];
           }

        } else { // 如果活动设置的是真人中奖，不管幸运号是机器人还是真人，直接返回幸运号

        }

        returnResult:
        return array(
            'order_id' => $luckyInfo['order_id'],
            'win_user_id' => $win_user_id,
            'lucky_number' => $lucky_number,
        );
    }

    private function send_message($act_id, $win_user_id, $goods_id, $goods_name){
        // 获取活动参与的订单
        $orders = Db::name('order')
            ->where('prom_id', $act_id)
            ->where('robot', 0)
            ->where('pay_status', 1)
            ->field('user_id, num')
            ->select();

        if(!empty($orders)){
            foreach ($orders as $item) {
                $message = $item['user_id'] == $win_user_id ? '恭喜您中奖' : '很遗憾您购买的'.$goods_name.'商品未中奖';
                if($item['user_id'] == $win_user_id){
                    $url = '/web/#/finishedDetails?id='.$item['order_id'].'&type=1';
                }
                
                if($item['user_id'] != $win_user_id){
                    $url = '/web/#/buyAgain?order_id='.$item['order_id'].'&goods_id='.$goods_id.'&num='.$item['num'];
                }
                $data = array(
                    'message' => $message,
                    'goods_name' => $goods_name,
                    'category' => '1',
                    'send_time' => time(),
                    'data' => serialize(array(
                        'url' => $url,
                    )),
                );
                $message_id = M('message')->insertGetId($data);

                $user_message = array(
                    'user_id' => $order['user_id'],
                    'message_id' => $message_id,
                    'category' => '1',
                );
                M('user_message')->insert($user_message);
            }
        }

    }
}