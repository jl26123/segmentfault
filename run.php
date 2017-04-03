#!/usr/bin/php
<?php
/**
 * run.php
 *
 * @User    : wsj6563@gmail.com
 * @Date    : 16/2/2 11:32
 * @Encoding: UTF-8
 * @Created by PhpStorm.
 */

require dirname(__FILE__).'/vendor/autoload.php';

use helper\Spider;
use helper\Db;
use config\Config;

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

    if (!$ret || $spider->getCurrentPage() > Config::$spider['end_page']) {
        exit("work done");
    }
};

