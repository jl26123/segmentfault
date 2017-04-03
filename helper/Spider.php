<?php
/**
 * Post.php
 *
 * @User    : wsj6563@gmail.com
 * @Date    : 16/2/2 15:10
 * @Encoding: UTF-8
 * @Created by PhpStorm.
 */
namespace helper;

use Symfony\Component\DomCrawler\Crawler;
use config\Config;

class Spider
{
    /***
     * @var int 当前抓取的分页数
     */
    private $cur_page = 0;

    public function craw()
    {
        $content = $this->getUrlContent($this->getUrl());
        $crawler = new Crawler();
        $crawler->addHtmlContent($content);

        $found = $crawler->filter(".stream-list__item");

        //判断是否页面已经结束
        if ($found->count()) {
            $data = $found->each(
                function (Crawler $node, $i) {
                    //问答ID
                    $href    = trim($node->filter(".author li a")->eq(1)->attr('href'));
                    $a       = explode("/", $href);
                    $post_id = isset($a[2]) ? $a[2] : 0;

                    //检查该问题是否已经抓取过
                    if ($post_id == 0 || !(new Redis())->checkPostExists($post_id)) {
                        return $this->getPostData($node, $post_id, $href);
                    }

                    return false;
                }
            );
            //去除空的数据
            foreach ($data as $i => $v) {
                if (!$v) {
                    unset($data[$i]);
                }
            }
            $data = array_values($data);
            $this->incrementPage();

            $continue = true;
        } else {
            $data     = [];
            $continue = false;
        }


        return [$data, $continue];
    }

    /****
     *
     * 解析完整的问答信息
     *
     * @param Crawler $node
     * @param         $post_id
     * @param         $href
     * @return array
     */
    private function getPostData(Crawler $node, $post_id, $href)
    {
        $tmp            = [];
        $tmp['post_id'] = $post_id;
        //标题
        $tmp['title'] = trim($node->filter(".summary h2.title a")->text());

        //回答数
        $tmp['reply_num'] = intval(trim($node->filter(".qa-rank .answers")->text()));

        //浏览数
        $tmp['view_num'] = intval(trim($node->filter(".qa-rank .views")->text()));

        //投票数
        $tmp['vote_num'] = intval(trim($node->filter(".qa-rank .votes")->text()));

        //发布者
        $tmp['author'] = trim($node->filter(".author li a")->eq(0)->text());

        //发布时间
        $origin_time = trim($node->filter(".author li a")->eq(1)->text());
        if (mb_substr($origin_time, -2, 2, 'utf-8') == '提问') {
            $tmp['post_time'] = Util::parseDate($origin_time);
        } else {
            $tmp['post_time'] = Util::parseDate($this->getPostDateByDetail($href));
        }

        //收藏数
        $collect = $node->filter(".author .pull-right");
        if ($collect->count()) {
            $tmp['collect_num'] = intval(trim($collect->text()));
        } else {
            $tmp['collect_num'] = 0;
        }

        $tmp['tags'] = [];
        //标签列表
        $tags = $node->filter(".taglist--inline");
        if ($tags->count()) {
            $tmp['tags'] = $tags->filter(".tagPopup")->each(
                function (Crawler $node, $i) {
                    return $node->filter('.tag')->text();
                }
            );
        }

        $tmp['tag_num'] = count($tmp['tags']);

        return $tmp;
    }

    /***
     *
     * 设置待抓取页面的url,并保持分页递增
     *
     * @return string
     */
    private function incrementPage()
    {
        if (false === $this->cur_page) {
            $this->cur_page = Config::$spider['from_page'];
        }
        $this->cur_page++;
    }

    /***
     *
     * 获取当前分页
     *
     * @return int
     */
    public function getCurrentPage()
    {
        return $this->cur_page;
    }

    /***
     *
     * 获取待抓取的页面
     *
     * @return string
     */
    public function getUrl()
    {
        if (!$this->cur_page) {
            $this->cur_page = Config::$spider['from_page'];
        }

        return Config::$spider['base_url'] . '&page=' . intval($this->cur_page);
    }

    /***
     *
     * 抓取指定url的内容
     *
     * @param $url
     * @return bool|mixed
     */
    public function getUrlContent($url)
    {
        if (!$url || !\filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }

        $curl = \curl_init();
        \curl_setopt($curl, CURLOPT_URL, $url);
        \curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        \curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
        \curl_setopt($curl, CURLOPT_TIMEOUT, Config::$spider['timeout']);
        \curl_setopt($curl, CURLOPT_USERAGENT, 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_10_5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/47.0.2526.111 Safari/537.36');
        $content = curl_exec($curl);
        curl_close($curl);

        return $content;
    }

    /***
     *
     * 根据详细页获取问题发布时间
     *
     * @param $url
     * @return string
     */
    public function getPostDateByDetail($url)
    {
        $crawler = new Crawler();
        $crawler->addHtmlContent($this->getUrlContent('http://segmentfault.com' . $url));
        $node = $crawler->filter('.author');
        if ($node->count()) {
            return trim($node->text());
        } else {
            return '';
        }
    }
}