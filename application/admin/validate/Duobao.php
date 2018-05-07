<?php
namespace app\admin\validate;
use think\Validate;
use think\Db;
class Duobao extends Validate
{
    // 验证规则
    protected $rule = [
        'id'=>'checkId',
        // 'title'=>'require|max:50',
        'goods_id'=> 'require',
        // 'start_time'=>'require',
        'end_time'=>'require',
        // 'description'=>'max:100',
    ];
    //错误信息
    protected $message  = [
        // 'title.require'                 => '促销标题必须',
        // 'title.max'                     => '促销标题小于50字符',
        'goods_id.require'                 => '请选择参与夺宝的商品',
//        'expression.checkExpression'    => '优惠有误',
//        'group.require'         => '请选择适合用户范围',
        'start_time.require'            => '请选择开始时间',
        'end_time.require'              => '请选择结束时间',
        // 'end_time.checkEndTime'         => '结束时间不能早于开始时间',
    ];
    /**
     * 检查结束时间
     * @param $value|验证数据
     * @param $rule|验证规则
     * @param $data|全部数据
     * @return bool|string
     */
    protected function checkEndTime($value, $rule ,$data)
    {
        return ($value < $data['start_time']) ? false : true;
    }

    /**
     * 该活动是否可以编辑
     * @param $value|验证数据
     * @param $rule|验证规则
     * @param $data|全部数据
     * @return bool|string
     */
    protected function checkId($value, $rule ,$data)
    {
        // $isHaveOrder = Db::name('order_goods')->where(['prom_type'=>3,'prom_id'=>$value])->find();
        // if($isHaveOrder){
        //     return '该活动已有用户下单购买不能编辑';
        // }else{
        //     return true;
        // }
    }
}