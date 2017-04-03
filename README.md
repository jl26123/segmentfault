# PHP爬虫抓取segmentfault问答

### 一 需求概述
___
抓取中国领先的开发者社区[segment.com](1)网站上问答及标签数据,侧面反映最新的技术潮流以及国内程序猿的关注焦点.

> 注:抓取脚本纯属个人技术锻炼,非做任何商业用途.

### 二 开发环境及包依赖
___
运行环境
- CentOS Linux release 7.0.1406 (Core)
- PHP7.0.2
- Redis3.0.5
- Mysql5.5.46
- Composer1.0-dev

composer依赖
- [symfony/dom-crawler](2)

### 三 流程与实践
___
首先,先设计两张表:`post`,`post_tag`
```mysql
CREATE TABLE `post` (
 `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'pk',
 `post_id` varchar(32) NOT NULL COMMENT '文章id',
 `author` varchar(64) NOT NULL COMMENT '发布用户',
 `title` varchar(512) NOT NULL COMMENT '文章标题',
 `view_num` int(11) NOT NULL COMMENT '浏览次数',
 `reply_num` int(11) NOT NULL COMMENT '回复次数',
 `collect_num` int(11) NOT NULL COMMENT '收藏次数',
 `tag_num` int(11) NOT NULL COMMENT '标签个数',
 `vote_num` int(11) NOT NULL COMMENT '投票次数',
 `post_time` date NOT NULL COMMENT '发布日期',
 `create_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '抓取时间',
 PRIMARY KEY (`id`),
 KEY `idx_post_id` (`post_id`)
) ENGINE=MyISAM AUTO_INCREMENT=7108 DEFAULT CHARSET=utf8 COMMENT='帖子';
```

```mysql
CREATE TABLE `post_tag` (
 `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'PK',
 `post_id` varchar(32) NOT NULL COMMENT '帖子ID',
 `tag_name` varchar(128) NOT NULL COMMENT '标签名称',
 PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=15349 DEFAULT CHARSET=utf8 COMMENT='帖子-标签关联表';
```
当然有同学说,这么设计不对,标签是个独立的主体,应该设计`post`,`tag`,`post_tag`三张表,文档和标签之间再建立联系,这样不仅清晰明了,而且查询也很方便.
这里简单处理是因为首先不是很正式的开发需求,自娱自乐,越简单搞起来越快,另外三张表抓取入库时就要多一张表,更重要的判断标签重复性,导致抓取速度减慢.

整个项目工程文件如下:
```php
app/config/config.php  /*配置文件*/
app/helper/Db.php  /*入库脚本*/
app/helper/Redis.php /*缓存服务*/
app/helper/Spider.php /*抓取解析服务*/
app/helper/Util.php /*工具*/
app/vendor/composer/ /*composer自动加载*/
app/vendor/symfony/ /*第三方抓取服务包*/
app/vendor/autoload.php /*自动加载*/
app/composer.json /*项目配置*/
app/composer.lock /*项目配置*/
app/run.php /*入口脚本*/
```

> 因为功能很简单,所以没有必要引用第三方开源的PHP框架

**基本配置**
```php
class Config
{
    public static $spider = [
        'base_url'  => 'http://segmentfault.com/questions?',
        'from_page' => 1,
        'timeout'   => 5,
    ];

    public static $redis = [
        'host'    => '127.0.0.1',
        'port'    => 10000,
        'timeout' => 5,
    ];

    public static $mysql = [
        'host'     => '127.0.0.1',
        'port'     => '3306',
        'dbname'   => 'segmentfault',
        'dbuser'     => 'user',
        'dbpwd' => 'user',
        'charset'  => 'utf8',
    ];
}
```


**curl抓取页面的函数**
```php
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
```
这里要有两点要注意:  

第一,要开启`CURLOPT_FOLLOWLOCATION`301跟踪抓取,因为segmentfautl官方会做域名跳转,比如`http://www.segmentfault.com/`会跳转到到"http://segmentfault.com"等等.  
第二,指定UserAgent,否则会出现301重定向到浏览器升级页面.  


**crawler解析处理**  
```php
public function craw()
{
    $content = $this->getUrlContent($this->getUrl());
    $crawler = new Crawler();
    $crawler->addHtmlContent($content);

    $found = $crawler->filter(".stream-list__item");

    //判断是否页面已经结束
    if ($found->count()) {
        $data = $found->each(function (Crawler $node, $i) {
            //问答ID
            $href    = trim($node->filter(".author li a")->eq(1)->attr('href'));
            $a       = explode("/", $href);
            $post_id = isset($a[2]) ? $a[2] : 0;

            //检查该问答是否已经抓取过
            if ($post_id == 0 || !(new Redis())->checkPostExists($post_id)) {
                return $this->getPostData($node, $post_id, $href);
            }

            return false;
        });
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
        $tmp['tags'] = $tags->filter(".tagPopup")->each(function (Crawler $node, $i) {
            return $node->filter('.tag')->text();
        });
    }

    $tmp['tag_num'] = count($tmp['tags']);

    return $tmp;
}
```  

通过crawler将抓取的列表解析成待入库的二维数据,每次抓完,分页参数递增.  
这里要注意几点:  
1.有些问答已经抓取过了,入库时需要排除,因此此处加入了redis缓存判断.  
2.问答的创建时间需要根据"提问","解答","更新"状态来动态解析.  
3.需要把类似"5分钟前","12小时前","3天前"解析成标准的`Y-m-d`格式  

**入库操作**
```php
public function multiInsert($post)
{
    if (!$post || !is_array($post)) {
        return false;
    }

    $this->beginTransaction();
    try {
        //问答入库
        if (!$this->multiInsertPost($post)) {
            throw new Exception("failed(insert post)");
        }
        //标签入库
        if (!$this->multiInsertTag($post)) {
            throw new Exception("failed(insert tag)");
        }
        $this->commit();
        $this->pushPostIdToCache($post);

        $ret = true;
    } catch (Exception $e) {
        $this->rollBack();
        $ret = false;
    }

    return $ret;
}
```
采用事务+批量方式的一次提交入库,入库完成后将`post_id`加入redis缓存

**启动作业**
```php
require './vendor/autoload.php';

use helper\Spider;
use helper\Db;

$spider = new Spider();
while (true) {
    echo 'crawling from page:' . $spider->getUrl() . PHP_EOL;
    list($data, $ret) = $data = $spider->craw();
    if ($data) {
        $ret = (new Db)->multiInsert($data);
        echo count($data) . " new post crawled " . ($ret ? 'success' : 'failed') . PHP_EOL;
    } else {
        echo 'no new post crawled'.PHP_EOL;
    }
    echo PHP_EOL;

    if (!$ret) {
        exit("work done");
    }
};

```
运用while无限循环的方式执行抓取,遇到抓取失败时,自动退出,中途可以按`Ctrl + C`中断执行.  

### 四 效果展示  
___

**抓取执行中**  
![start](http://study.manongview.com/segmentfault/img/start.jpg)

**问答截图**  
![post](http://study.manongview.com/segmentfault/img/post.jpg)  

**标签截图**  
![tag](http://study.manongview.com/segmentfault/img/tag.jpg)  


### 五 总结
___
以上的设计思路和脚本基本上可以完成简单的抓取和统计分析任务了.  
我们先看下TOP25标签统计结果:   

![tag_stat.jpg](http://study.manongview.com/segmentfault/img/tag_statistics.jpg)  

可以看出segmentfault站点里,讨论最热的前三名是`javascript`,`php`,`java`,而且前25个标签里跟前端相关的(这里不包含移动APP端)居然有13个,占比50%以上了.  

每月标签统计一次标签,就可以很方便的掌握最新的技术潮流,哪些技术的关注度有所下降,又有哪些在上升.  
   
**有待完善或不足之处**   
1.单进程抓取,速度有些慢,如果开启多进程的,则需要考虑进程间避免重复抓取的问题  
2.暂不支持增量更新,每次抓取到从配置项的指定页码开始一直到结束,可以根据已抓取的`post_id`做终止判断(`post_id`虽不是连续自增,但是一直递增的)

[1]:http://segmentfault.com
[2]:http://symfony.com/doc/current/components/dom_crawler.html