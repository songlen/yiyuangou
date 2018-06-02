<?php

namespace app\api\controller;
use think\Db;

class Goods extends Base {

	public function __construct(){
		// 设置所有方法的默认请求方式
		$this->method = 'POST';

		parent::__construct();
	}
    
    public function goodsInfo(){
        $goods_id = I('goods_id');

        $goodsInfo = Db::name('goods')->field('goods_content')->find($goods_id);

        response_success($goodsInfo);
    }
}