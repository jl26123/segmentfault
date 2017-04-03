<?php
/**
 * config.php
 *
 * @User    : wsj6563@gmail.com
 * @Date    : 16/2/2 15:14
 * @Encoding: UTF-8
 * @Created by PhpStorm.
 */
namespace config;


class Config
{
    public static $spider = [
        'base_url'  => 'http://segmentfault.com/questions?',
        'from_page' => 1,
        'end_page'  => 100,
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
        'dbuser'     => 'root',
        'dbpwd' => 'root',
        'charset'  => 'utf8',
    ];
}
