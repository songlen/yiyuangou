<?php
/**
 * 订单处理类
 */


namespace app\api\logic;

use think\Db;

class MessageLogic {

    /**
     * [winningMessage 活动开奖后后给参与这发送是否中奖消息]
     * @param  [type] $act_id      [description]
     * @param  [type] $win_user_id [description]
     * @param  [type] $goods_id    [description]
     * @param  [type] $goods_name  [description]
     * @return [type]              [description]
     */
    public function winningMessage($act_id){
        $activity = M('goods_activity')->where('act_id', $act_id)->find();
        if($activity['status'] != 3) return false;

        $win_user_id = $activity['win_user_id'];
        $goods_name = $activity['goods_name'];
        // 通过中奖号查找中奖订单id
        $lucky = M('lucky_number')->where(array('act_id'=>$act_id, 'lucky_number'=>$activity['lucky_number']))->field('order_id')->find();
        $winOrderId = $lucky['order_id'];

        // 获取活动参与的订单
        $orders = Db::name('order')
            ->where('prom_id', $act_id)
            ->where('robot', 0)
            ->where('pay_status', 1)
            ->field('user_id, order_id, num')
            ->group('user_id')
            ->order('order_id desc')
            ->select();

        if(!empty($orders)){
            foreach ($orders as $item) {
                // $message = $item['user_id'] == $win_user_id ? '恭喜您中奖' : '很遗憾您购买的'.$goods_name.'商品未中奖';

                if($item['user_id'] == $win_user_id){
                    $message = '恭喜您中奖，点击';
                    $jsondata = array(
                        'hrefValue' => '追踪中奖的幸运号码',
                        'router' => 'finishedDetails',
                        'param' => array(
                            'id' => $winOrderId,
                            'type' => '1',
                        ),
                    );
                } else {
                    $message = '很遗憾，您购买的'.$activity['goods_name'].'商品未中奖，点击进行';
                    $jsondata = array(
                        'hrefValue' => '补价购买',
                        'router' => 'buyAgain',
                        'param' => array(
                            'order_id' => $item['order_id'],
                            'goods_id' => $activity['goods_id'],
                            'num' => 1
                        ),
                    );
                }

                $data = array(
                    'message' => $message,
                    'type' => 0,
                    'category' => '1',
                    'send_time' => time(),
                    'data' => serialize($jsondata),
                );
                $message_id = M('message')->insertGetId($data);

                $user_message = array(
                    'user_id' => $item['user_id'],
                    'message_id' => $message_id,
                    'category' => '1',
                );
                M('user_message')->insert($user_message);
            }
        }
    }
}