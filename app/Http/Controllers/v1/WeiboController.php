<?php

namespace App\Http\Controllers\v1;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Redis;
use Illuminate\Validation\ValidationException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;
// use Tymon\JWTAuth\JWTAuth;

use App\Http\Controllers\v1\AuthController;

// use Zttp\Zttp;
use Log;
use GuzzleHttp\Client;

// 测试控制器
class WeiboController extends Controller
{
    protected $request;
    protected $client;
    protected $auth;
    protected $userId;  //当前登录者的id
    // protected $jwt;

    public function __construct(Request $request)
    {
        $this->request = $request;
        $this->client = new Client();
        $this->auth = new AuthController($this->request);
        //DB::connection()->enableQueryLog();
        //$log = DB::getQueryLog();
        // parent::__construct();
        $this->userId = $this->request['userInfo']['id'];
    }

    public function getTaskList()
    {
        $data = Redis::SRANDMEMBER('keywords_ask_set:2');
        echo '<pre>';
        print_r($data);exit;
        # 迭代测试
        $page = 1;
        $limit = 10;
        $offset = ($page - 1) * $limit;
        $key = 'keywords_fans_set:白癜风';
        $data = Redis::SSCAN($key, 1, 'COUNT', $limit);
        echo '<pre>';
        print_r($data);exit;

        # 获取当前登录者的关键词名称
        $keywordsData = DB::table('word_connection')
            ->join('keywords as k', 'k.id', '=', 'word_connection.word_id')
            ->where(['word_connection.user_id' => $this->userId])
            ->pluck('k.name')
            ->toArray();
        $keywordsData[] = '白癜风';
        $redisKeySet = [];
        foreach ($keywordsData as $v) {
            $redisKeySet[] = 'keywords_fans_set:' . $v;
        }
        Redis::SUNIONSTORE('keywords_fans_set:all', $redisKeySet);
        echo Redis::SCARD('keywords_fans_set:all');exit;
        $data = Redis::SUNION($redisKeySet);
        echo count($data);exit;
        echo '<pre>';
        print_r($redisKeySet);exit;


        $tiktokIdData = DB::table('fans')
            ->select('fans.tiktok_id')
            ->where(['v.state' => 1])
            ->join('video_user as v', 'fans.collect_id', '=', 'v.id')
            ->pluck('fans.tiktok_id')
            ->toArray();
        var_dump(count($tiktokIdData));exit;

        $keywords = ['全省白癜风'];
        $data = [];
        foreach ($keywords as $w) {
            $list = Redis::SMEMBERS('keywords_fans_set:' . $w);
            $data = array_merge($data, $list);
        }
        $data = array_unique($data);
        print_r($data);exit;
        return Redis::SADD('keywords_ask_set:5', $data);
        $data = Redis::incr('max_fans_id');


    }

}
