<?php
/**
 * Db.php
 *
 * @User    : wsj6563@gmail.com
 * @Date    : 16/2/2 15:10
 * @Encoding: UTF-8
 * @Created by PhpStorm.
 */
namespace helper;

use config\Config;
use yii\base\Exception;
use \PDO;

class Db extends PDO
{
    public function __construct()
    {
        parent::__construct(
            "mysql:host=" . Config::$mysql['host'] . ";port=" . Config::$mysql['port'] . ";dbname=" . Config::$mysql['dbname'],
            Config::$mysql['dbuser'],
            Config::$mysql['dbpwd'],
            [
                \PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . Config::$mysql['charset'] . ";",
                \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC
            ]
        );
    }

    /****
     *
     * 批量插入数据库
     *
     * @param $post
     * @return bool
     */
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

    /***
     *
     * 批量插入问答
     *
     * @param $post
     * @return bool
     */
    private function multiInsertPost($post)
    {
        //拼接问答SQL插入语句
        $sql = 'INSERT INTO `post`(`post_id`,`author`,`title`,`view_num`,`reply_num`,`collect_num`,`tag_num`,`vote_num`,`post_time`) VALUES ';
        $dot = '';
        foreach ($post as $i => $item) {
            $sql .= "$dot(:post_id{$i},:author{$i},:title{$i},:view_num{$i},:reply_num{$i},:collect_num{$i},:tag_num{$i},:vote_num{$i},:post_time{$i})";
            $dot = ',';
        }
        $sql .= ';';

        /***
         * 批量绑定变量,注意绑定变量是基于引用的,千万不要用$item取值
         * [详细见鸟哥论坛:http://www.laruence.com/2012/10/16/2831.html]
         */
        $stmt = $this->prepare($sql);
        foreach ($post as $i => $item) {
            $stmt->bindParam(':' . 'post_id' . $i, $post[$i]['post_id']);
            $stmt->bindParam(':' . 'author' . $i, $post[$i]['author']);
            $stmt->bindParam(':' . 'title' . $i, $post[$i]['title']);
            $stmt->bindParam(':' . 'view_num' . $i, $post[$i]['view_num']);
            $stmt->bindParam(':' . 'reply_num' . $i, $post[$i]['reply_num']);
            $stmt->bindParam(':' . 'collect_num' . $i, $post[$i]['collect_num']);
            $stmt->bindParam(':' . 'tag_num' . $i, $post[$i]['tag_num']);
            $stmt->bindParam(':' . 'vote_num' . $i, $post[$i]['vote_num']);
            $stmt->bindParam(':' . 'post_time' . $i, $post[$i]['post_time']);
        }

        return $stmt->execute();
    }


    /****
     *
     *
     * 批量插入问答标签
     *
     * @param $post
     * @return bool
     */
    private function multiInsertTag($post)
    {
        //拼接标签SQL插入语句
        $sql = "INSERT INTO `post_tag`(`post_id`,`tag_name`) VALUES ";
        $dot = '';
        foreach ($post as $i => $item) {
            if ($item['tag_num'] > 0 && $item['tags']) {
                foreach ($item['tags'] as $j => $tag_name) {
                    $sql .= "$dot(:post_id{$i}_{$j},:tag_name{$i}_{$j})";
                    $dot = ',';
                }
            }
        }
        $sql .= ';';

        $stmt = $this->prepare($sql);
        foreach ($post as $i => $item) {
            if ($item['tag_num'] > 0 && $item['tags']) {
                foreach ($item['tags'] as $j => $tag_name) {
                    $stmt->bindParam(":post_id{$i}_{$j}", $post[$i]['post_id']);
                    $stmt->bindParam(":tag_name{$i}_{$j}", $post[$i]['tags'][$j]);
                }
            }
        }

        return $stmt->execute();
    }

    /***
     *
     * 添加已入库的问答ID到缓存中
     *
     * @param $post
     */
    private function pushPostIdToCache($post)
    {
        $redis = new Redis();
        foreach ($post as $item) {
            $redis->setPost($item['post_id']);
        }
    }
}