<?php

namespace app\api\controller;
use think\Db;
use app\api\logic\OpenPrizeLogic;
use app\api\logic\OrderLogic;

include APP_PATH.'common/util/File.class.php';

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
p($activits);
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
            'status' => '0', // 未执行
        );
        $robot_detail = Db::name('robot_detail')->where($where)->select();

        if($robot_detail){
            $OrderLogic = new OrderLogic();
            foreach ($robot_detail as $item) {
                $actinfo = Db::name('goods_activity')->find($item['act_id']);
               
                if($item['num'] > $actinfo['surplus']){
                    $data = array(
                        'id'=>$item['id'],
                        'exec_time' => time(),
                        'status' => '2',
                    );
                    Db::name('robot_detail')->update($data);
                } else {
                    $OrderLogic->placeRobotOrder($item['act_id'], $item['user_id'], $item['num']);

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
     * [cancleOrder 取消下单十分钟未支付的订单 ]
     * @return [type] [description]
     */
    public function cancelOrder(){

        $where = array(
            'pay_status' => '0',
            'order_status' => '0',
            'prom_type' => '4',
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
                // 剩余份额加回去、冻结份额减掉
                Db::name('GoodsActivity')->where('act_id', $act_id)
                    ->inc('surplus', $num)
                    ->dec('freeze_count', $num)
                    ->update();

                // 更改订单状态为 已作废 5
                Db::name('order')->where('order_id', $item['order_id'])->setField('order_status', 5);
                Db::name('lucky_number')->where('order_id', $item['order_id'])->delete();
            }
        }
    }

    public function test(){


        // array_filter($arr, function($item){
        //     return $item['status'] == 1;
        // });
        /*$act_id = 1; $num = 5;
        $actInfo = Db::name('goods_activity')->where('act_id', $act_id)->field('total_count')->find();
        // 所有的幸运号
        $allLuckys = range(10000001, 10000000+$actInfo['total_count']);
        // 查找已被使用的幸运号
        $usedLucky = Db::name('lucky_number')->where('act_id', $act_id)->getField('lucky_number', true);
        // 求出两个数组的差集（未被使用的幸运号）
        $usableLucky = array_diff($allLuckys, $usedLucky);
        $keys = array_rand($usableLucky, $num);
        // p($usableLucky, array_flip($keys));
        if(is_array($keys)){
            return array_values(array_intersect_key($usableLucky, array_flip($keys)));
        } else {
            return array($usableLucky[$keys]);
        }*/
        $act_id = 8;
      $activity = M('goods_activity')->where('act_id', $act_id)->find();
        if($activity['status'] != 3) return false;

        $win_user_id = $activity['win_user_id'];
        $goods_name = $activity['goods_name'];
        // 通过中奖号查找中奖订单id
        $lucky = M('lucky_number')->where('lucky_number', $activity['lucky_number'])->field('order_id winOrderId')->find();
        $winOrderId = $lucky['winOrderId'];
        p($winOrderId);
    }
}