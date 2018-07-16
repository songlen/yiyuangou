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
            'status' => '0', // 未执行
        );
        $robot_detail = Db::name('robot_detail')->where($where)->select();

        if($robot_detail){
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
     * [addRobotOrder 执行机器人下单流程]
     * @param [type] $act_id  [description]
     * @param [type] $user_id [description]
     * @param [type] $num     [description]
     */
    private function addRobotOrder($act_id, $user_id, $num){
        $user = Db::name('users')->field('mobile')->find($user_id);
        if(empty($user_id)){
            return false;
        }

        $commit_result = true;

        $order_sn = generateOrderSn(); // 生成订单号

        // 时间戳和毫秒数
        list($usec, $sec) = explode(" ", microtime());
        $usec = round($usec *1000);

        $orderdata = array(
            'order_sn' => $order_sn,
            'user_id' => $user_id,
            'mobile' => $user['mobile'],
            'add_time' => $sec,
            'add_time_ms' => $usec,
            'prom_id' => $act_id,
            'prom_type' => 4, // 订单类型 夺宝活动
            'num' => $num,
            'pay_status' => '1',
        );

        // 计算各种价格
        $goods_price = $num; // 商品价格等于购买份额
        $tax_amount = $goods_price*0.13; // 税额
        $total_amount = $goods_price+$tax_amount; // 订单总额（商品价格+税额）

        $used_points = 0;
        $order_amount = $total_amount;

        $orderdata['goods_price'] =  $goods_price;
        $orderdata['tax_amount'] =  $tax_amount;
        $orderdata['order_amount'] =  $order_amount;
        $orderdata['total_amount'] =  $total_amount;

        Db::startTrans(); // 开启事物
        try {
            // 订单写入数据库
            $order_id = Db::name('order')->insertGetId($orderdata);

            // 活动表增减数量
            Db::name('GoodsActivity')->where('act_id', $act_id)->setDec('surplus', $num); // 减剩余份额
            Db::name('GoodsActivity')->where('act_id', $act_id)->setInc('buy_count', $num); // 加已购份额

            $i = 1;
            while ($i <= $num) {
                // 找出最大的幸运码
               $max_lucky_number = Db::name('lucky_number')->where(array('act_id'=>$act_id))->max('lucky_number');
               $lucky_number = $max_lucky_number ? $max_lucky_number+1 : 10000001;
               // 更新附加表
               $luckynumber = array(
                   'order_id' => $order_id,
                   'order_sn' => $order_sn,
                   'user_id' => $user_id,
                   'act_id' => $act_id,
                   'num' => $num,
                   'add_time' => $sec,
                   'add_time_ms' => $usec,
                   'lucky_number' => $lucky_number,
               );
               Db::name('LuckyNumber')->insert($luckynumber);
               $i++;
            }

            // 提交事务
            Db::commit();
        } catch (\Exception $e){
            // 回滚事务
            Db::rollback();
            $commit_result = false;
            break;
        }

        if($commit_result){
            return true;
        }  else {
            return false;
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