<?php

namespace app\api\controller;
use think\Db;

class Article extends Base {

	public function __construct(){
		// 设置所有方法的默认请求方式
		$this->method = 'POST';

		parent::__construct();
	}
    
    // 夺宝详情
    public function detail(){
        $article_id = I('article_id');

        // 活动详情
        $info = M('Article')
            ->where('is_open', 1)
            ->where('article_id', $article_id)
            ->field('article_id, title, content')
            ->find();

        if($info){
            $info['content'] = htmlspecialchars_decode($info['content']);
            response_success($info);
        } else {
            response_error('', '文章不存在');
        }
    }
}