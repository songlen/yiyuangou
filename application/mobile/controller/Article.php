<?php
/**
 * tpshop
 * ============================================================================

 * 如果商业用途务必到官方购买正版授权, 以免引起不必要的法律纠纷.
 * ============================================================================
 * $Author: IT宇宙人 2015-08-10 $
 */
namespace app\mobile\controller;

use think\Db;
use app\common\model\WxNews;
 
class Article extends MobileBase
{
    /**
     * 文章内容页
     */
    public function detail()
    {
        $article_id = input('article_id/d', 1);
        $article = Db::name('article')->where("article_id", $article_id)->find();
        $this->assign('article', $article);
        return $this->fetch();
    }

    public function news()
    {
        $id = input('id');
        if (!$news = WxNews::get($id)) {
            $this->error('文章不存在了~', null, '', 100);
        }

        $news->content = htmlspecialchars_decode($news->content);
        $this->assign('news', $news);
        return $this->fetch();
    }
}