<?php
/**
 * Util.php
 *
 * @User    : wsj6563@gmail.com
 * @Date    : 16/2/2 16:59
 * @Encoding: UTF-8
 * @Created by PhpStorm.
 */
namespace helper;
class Util
{
    /***
     *
     * segment的时间格式主要有以下六种格式:
     *
     * @case1:"xx刚刚"
     * @case2:"xx分钟前"
     * @case3:"xx小时前"
     * @case4:"xx天前"
     * @case5:"m月d日前"
     * @case6:"Y年m月d日前"
     *
     * 由于获取不到详细的H:i:s,只能解析成Y-m-d就够用了
     *
     * @param $str  string "segment原始时间"
     * @return string
     */
    public static function parseDate($str)
    {
        $date      = '';
        $force_int = intval(trim($str));
        if (false !== stripos($str, '刚刚')) {
            $date = date('Y-m-d');
        } elseif (false !== stripos($str, '分钟')) {
            $date = date('Y-m-d', time() - 60 * $force_int);
        } elseif (false !== stripos($str, '小时')) {
            $date = date('Y-m-d', time() - 3600 * $force_int);
        } elseif (false !== stripos($str, '天')) {
            $date = date('Y-m-d', time() - 86400 * $force_int);
        } else {
            if (preg_match("/((\d{4})年)?(\d{1,2})月(\d{1,2})[号日]/i", $str, $matches)) {
                list(, , $year, $month, $day) = $matches;
                $date = ($year ? $year : date('Y')) . '-' . (strlen($month) == 1 ? '0' . $month : $month) . '-' . (strlen($day) == 1 ? '0' . $day : $day);
            }
        }

        return $date;
    }
}