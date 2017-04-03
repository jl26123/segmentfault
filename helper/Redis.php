<?php
/**
 * Redis.php
 *
 * @User    : wsj6563@gmail.com
 * @Date    : 16/2/2 15:11
 * @Encoding: UTF-8
 * @Created by PhpStorm.
 */
namespace helper;

use config\Config;
use \Redis as R;

class Redis extends R
{
    public function __construct()
    {
        $this->connect(Config::$redis['host'], Config::$redis['port'], Config::$redis['timeout']);
    }

    private function getPostKey($post_id)
    {
        return 'p_' . $post_id;
    }

    public function checkPostExists($post_id)
    {
        return $this->exists($this->getPostKey($post_id));
    }

    public function setPost($post_id)
    {
        return $this->set($this->getPostKey($post_id), '1');
    }
}