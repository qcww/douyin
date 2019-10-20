<?php

namespace App\Services;


/**
 * @desc 钉钉相关
 * @author wangxiang
 * @Class UserController
 * @package App\Http\Controllers\v1
 */
class Ding
{

    /**
     * @desc 钉钉提醒
     * @author wangxiang
     * @time 2019/7/5 14:35
     * @param $webHook
     * @param $type
     * @param string $title
     * @param string $text
     * @param array $telArr
     * @param bool $atAll
     * @return array
     */
    public static function ddRemindMarkdown($webHook, $type, $title = '', $text = '', $telArr = [], $atAll = false)
    {
        if ($webHook != '' && $type=='markdown'){
            $data['msgtype'] = $type;
            $data['markdown']=[
                'title' => $title,
                'text' => $text
            ];
            $data['at']=[
                'atMobiles' => $telArr,
                'isAtAll' => $atAll
            ];

            $msgStr = json_encode($data);
            $result = self::_curl($webHook, $msgStr);
            if ($result) {
                return ['code' => 200, 'msg' => 'success', 'res' => $result];
            } else {
                return ['code' => 500, 'msg' => 'fail'];
            }
        }
    }


    /**
     * @desc curl请求
     * @author wangxiang
     * @time 2019/7/5 14:34
     * @param $url
     * @param string $post_string
     * @return bool|string
     */
    private static function _curl($url, $post_string = '')
    {
        $ch = curl_init();
        $timeout = 5;
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
        if($post_string!=''){
            curl_setopt($ch, CURLOPT_HTTPHEADER, array ('Content-Type: application/json;charset=utf-8'));
            curl_setopt($ch, CURLOPT_POSTFIELDS, $post_string);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        }
        // 线下环境不用开启curl证书验证, 未调通情况可尝试添加该代码
        curl_setopt ($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt ($ch, CURLOPT_SSL_VERIFYPEER, 0);
        $contents = curl_exec($ch);
        curl_close($ch);
        return $contents;
    }

}
