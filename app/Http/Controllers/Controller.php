<?php

namespace App\Http\Controllers;

use Laravel\Lumen\Routing\Controller as BaseController;

class Controller extends BaseController
{
    public function __construct()
    {

    }

    // 创建http header参数
    public function createHttpHeader() 
    {
        srand((double)microtime()*1000000);
        $nonce = mt_rand();
        $timeStamp = time();
        $sign = sha1(env('RY_APPSECRET').$nonce.$timeStamp);
        return [
            'RC-App-Key'    => env('RY_APPKEY'),
            'RC-Nonce'      => $nonce,
            'RC-Timestamp'  => $timeStamp,
            'RC-Signature'  => $sign,
            'Content-Type'  => 'application/x-www-form-urlencoded',
        ];
    }
}
