<?php

namespace App\Http\Controllers\v1;

use App\Models\Keywords;
use App\Models\Video;
use App\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Log;
use GuzzleHttp\Client;
use App\Common;

// 测试控制器
class FansAddController extends Controller
{
    protected $request;
    protected $client;
    protected $auth;

    // protected $jwt;

    public function __construct(Request $request)
    {
        $this->request = $request;
        $this->client = new Client();
        $this->auth = new AuthController($this->request);
        // parent::__construct();
    }

    // 搜索词获取的视频与用户存储
    public function keywordSearch(Request $request)
    {
        $requestData = $request->input('seovideo');
        $postData = json_decode($requestData, true);
        if (!$postData) {
            Log::info(var_export($_POST['seovideo'], true));
            Log::info(var_export($requestData, true));
            return;
        }

        $keyword = $this->js_unescape($postData['kw']);

        $keywordId = DB::table('keywords')->where('name', $keyword)->value('id');
        $videoUserId = array_keys($postData['user']);
        $videoUser = array_values($postData['user']);
        $desc = array_values($postData['desc']);

        foreach ($postData['vdo'] as $key => $video) {
            $vInfo = explode(',', $video);
            $tiktokUid = array_shift($videoUserId);
            if (!$tiktokUid) break;
            $insertVideoArray = array(
                'video_id' => (string)$key,
                'great_num' => $vInfo[1],
                'comment_num' => $vInfo[0],
                'video_user' => array_shift($videoUser),
                'video_name' => array_shift($desc),
                'video_user_id' => (string)$tiktokUid,
                'keyword' => $keyword
            );
            $videoModel = new Video();
            try {
                $res = $videoModel->insert($insertVideoArray);
            } catch (\Exception $e) {

            }

            $insertUserArray = array(
                'keyword_id' => $keywordId,
                'video_id' => (string)$key,
                'nickname' => $insertVideoArray['video_user'],
                'tiktok_uid' => (string)$tiktokUid,
                // 'tiktok_id' => $insertVideoArray['video_user'],
                'keyword' => $keyword
            );
            try {
                $id = DB::table('video_user')->insertGetId($insertUserArray);
                if ($id) {
                    Redis::lpush('keywords_user:' . $keyword, $insertVideoArray['video_user_id']);
                }
            } catch (\Exception $e) {

            }


        }
        return new JsonResponse(['code' => 200, 'msg' => '操作成功', 'data' => []]);

    }

    // 视频评论用户的粉丝
    public function addCommentUser(Request $request)
    {
        $requestData = $request->input('comment');
        $postData = json_decode($requestData, true);
        if (!$postData) {
            Log::info(var_export($_POST, true));
            return;
        }
        $videoUser = DB::table('video_user')->where('video_id',$postData['vdo'])->first();
        if(!$videoUser) return new JsonResponse(['code' => 500, 'msg' => '视频未采集', 'data' => []]);

        foreach ($postData['fans'] as $key => $user) {
            $insertArray = array(
                'tiktok_uid' => $key,
                'nickname' => $user,
                'collect_uid' => $videoUser->id,
                'collection_time' => date('Y-m-d'),
                'tiktok_uid' => $key,
                'day' => time()
            );
            Redis::SADD('temp_fans_data:' . $videoUser->id, $key);
            try {
                $res = DB::table('fans')->insert($insertArray);
                if ($res) {
                    Redis::SADD('temp_new_data:' . $videoUser->id, $key);
                }
            } catch (\Exception $e) {

            }
        }

        return new JsonResponse(['code' => 200, 'msg' => '操作成功', 'data' => []]);
    }

    // 粉丝主页详情
    public function fansHome(Request $request)
    {
        $requestData = $request->input('user');
        $postData = json_decode($requestData, true);
        if (!$postData) {
            Log::info(var_export($_POST, true));
            return;
        }
        DB::table('fans')
            ->where('tiktok_uid', $postData['uid'])
            ->update([
                    'province' => isset($postData['province'])?$postData['province']:'',
                    'city' => $postData['city'],
                    'district' => isset($postData['district'])?$postData['district']:'',
                    // 'comment' => $postData['signature'],
                    'sex' => isset($postData['gender'])?$postData['gender']:0,
                    'update_time' => time()
                ]
            );
        return new JsonResponse(['code' => 200, 'msg' => '操作成功', 'data' => []]);

    }

    // 录入粉丝数据
    public function addFans(Request $request)
    {
        if(is_string($request->input('userfans'))){
            $requestData = $this->js_unescape($request->input('userfans'));
            $requestData = str_replace('\\','',$requestData);
            $postData = json_decode($requestData, true);

        }else{
            $postData = $request->input('userfans');
        }

        if (!$postData) {
            return new JsonResponse(['code' => 500, 'msg' => '数据格式有问题', 'data' => []], 500);
        }

        $fansType = isset($postData['fansType'])?$postData['fansType']:0;

        if ($fansType == 0) {
            $cid = DB::table('video_user')->where('tiktok_uid', $postData['from'])->first();
        } else if($fansType == 1) {
            $cid = DB::table('ks_videos')->where('photo_id', $postData['from'])->first();
        }

        $asker = DB::table('user_ask_acount')->where(['type' => 1,'tiktok_uid' => $postData['from']])->first();

        if (!$cid) return new JsonResponse(['code' => 500, 'msg' => '竞品粉未采集或添加', 'data' => []], 500);
        $cid->tiktok_id = isset($cid->tiktok_id)?$cid->tiktok_id:$cid->user_id;
        $cid->tiktok_uid = isset($cid->tiktok_uid)?$cid->tiktok_uid:$cid->photo_id;

        if(empty($postData['fans'])) {
            return new JsonResponse(['code' => 500, 'msg' => '粉丝数据为空', 'data' => []], 500);
        }

        if ($asker) {
            Redis::SADD('group_fans_data:fans:'.$cid->tiktok_id, array_keys($postData['fans']));
        } else {
            Redis::SADD('temp_fans_data_'.$fansType.':'. $cid->id, array_keys($postData['fans']));
        }
        $successed = 0;
        foreach ($postData['fans'] as $key => $user) {
            if (!$asker) {
                $insertArray = array(
                    'tiktok_uid' => (string)$key,
                    'nickname' => $user['nickname'],
                    'collect_id' => (string)$cid->id,
                    'collect_uid' => (string)$cid->tiktok_uid,
                    'collection_time' => date('Y-m-d'),
                    'tiktok_id' => (string)$user['id'],
                    'sex' => $user['gender'],
                    'nickname' => $user['nickname'],
                    'signature' => $user['signature'],
                    'fans_type' => $fansType,
                    'day' => time()
                );
                try {
                    $res = DB::table('fans')->insert($insertArray);
                    if ($res) {
                        $successed += 1;
                        Redis::SADD('temp_new_data_'.$fansType.':' . $cid->id, $key);
                    }
                } catch (\Exception $e) {
                }
            } else {
                $insertFans = array(
                    'nickname' => $user['nickname'],
                    'tiktok_uid' => (string)$key,
                    'uid' => (string)$postData['from'],
                    'type' => 2,
                    'signature' => $user['signature']
                );
                try {
                    $res = DB::table('self_fans')->insert($insertFans);
                    if ($res) {
                        $successed += 1;
                        Redis::LPUSH('ask_ready_self:fans:'.$cid->tiktok_id,$key);
                        Redis::incr('max_self_fans_id');
                    }
                } catch (\Exception $e) {
                }
            }
        }
        return new JsonResponse(['code' => 200, 'msg' => '操作成功,添加'.$successed, 'data' => $postData['fans']]);
    }

    // 返回当前账号的搜索关键字
    public function searchKeywords(Request $request)
    {
        $tiktok = $request->input('tiktok_id');
        if (!$tiktok) return;

        $accountId = DB::table('collect_acount')->where('tiktok_id', $tiktok)->value('id');
        if (!$accountId) return [];
        $keywordId = DB::table('collect_keywords_connection as cc')
            ->select('k.name')
            ->join('keywords as k', 'k.id', '=', 'cc.keywords_id')
            ->where('cc.collect_acount_id', $accountId)->get()->map(function ($value) {
                return (array)$value;
            })->toArray();
        return array_column($keywordId, 'name');
    }

    // 返回当前账号的搜索关键字
    public function askKeywords(Request $request)
    {
        $tiktok = $request->input('tiktok_id');
        if (!$tiktok) return;

        $accountId = DB::table('user_ask_acount')->where(['tiktok_id' => $tiktok,'type' => 0])->value('id');
        if (!$accountId) return [];
        $keywordId = DB::table('word_connection as cc')
            ->select('k.name')
            ->join('keywords as k', 'k.id', '=', 'cc.word_id')
            ->where('cc.user_id', $accountId)->get()->map(function ($value) {
                return (array)$value;
            })->toArray();
        return array_column($keywordId, 'name');
    }

    // redis测试
    public function redis()
    {

    }

    function js_unescape($str)
    {
        $ret = '';
        $len = strlen($str);
        for ($i = 0; $i < $len; $i++) {
            if ($str[$i] == '%' && $str[$i + 1] == 'u') {
                $val = hexdec(substr($str, $i + 2, 4));
                if ($val < 0x7f) $ret .= chr($val);
                else if ($val < 0x800) $ret .= chr(0xc0 | ($val >> 6)) . chr(0x80 | ($val & 0x3f));
                else $ret .= chr(0xe0 | ($val >> 12)) . chr(0x80 | (($val >> 6) & 0x3f)) . chr(0x80 | ($val & 0x3f));
                $i += 5;
            } else if ($str[$i] == '%') {
                $ret .= urldecode(substr($str, $i, 3));
                $i += 2;
            } else $ret .= $str[$i];
        }
        return $ret;
    }

    // 返回采集列表
    function scrapList(Request $request)
    {
        $tiktok = $request->input('tiktok_id');
        $keyword = $this->searchKeywords($request);
        return $this->getListByKeyword($tiktok, $keyword);

    }

    // 完成采集
    function doGetComp(Request $request)
    {
        $tiktokUid = $request->input('tiktok_uid');
        $myUid = $request->input('my_uid');
        Redis::SREM('ready_get_fans:' . $myUid, $tiktokUid);
        return 'success';

    }

    // 遍历获取关键词数据
    function getListByKeyword($tiktok, $keywordArray)
    {
        if (!$keywordArray || empty($keywordArray)) return [];
        $keyword = array_pop($keywordArray);
        $redisKey = 'ready_get_fans:' . $tiktok;
        if (!Redis::EXISTS('get_fans:' . $keyword)) {
            Redis::set('get_fans:' . $keyword, 0);
        }

        $start = (integer)Redis::get('get_fans:' . $keyword);

        $return = [];
        if (!Redis::EXISTS($redisKey) || count(Redis::SMEMBERS($redisKey)) == 0) {
            // 不足20条，重新拉数据
            $limit = DB::table('keywords')->where('name',$keyword)->value('collect_limit');
            $pushData = Redis::LRANGE('keywords_user:' . $keyword, $start, $start + 20);
            Redis::INCRBY('get_fans:' . $keyword, count($pushData));
            $pushData = array_map(function($v)use($limit){
                return json_encode([$v=>$limit]);
            },$pushData);
            if (!empty($pushData)) {
                Redis::SADD($redisKey, $pushData);
            } else {
                return $this->getListByKeyword($tiktok, $keywordArray);
            }
        }
        $return = Redis::SMEMBERS($redisKey);
        return $return;

    }


    // 配置信息
    public function getScrapUser(Request $request)
    {
        $uid = $request->input('tiktok_id');
        $uid = str_replace(array('%20', ' ', ' '), "", $uid);
        $type = $request->input('type');
        $type = is_null($type)||$type==2?0:1;
        $info = DB::table('user_ask_acount')->where(['tiktok_id' => $uid,'type' => $type])->select('id', 'tiktok_id', 'time_range', 'user_id', 'ask_time', 'in_use', 'ask_model','action')->first();
        $asker = $info ? get_object_vars($info) : [];
        if (!empty($asker)) {
            $asker['said'] = (string)DB::table('model')->where('id',$info->ask_model)->value('content');
        }
        return json_encode($asker);
    }

    // 获取打招呼列表
    public function getReadyFans(Request $request)
    {
        $tiktok = $request->input('tiktok_id');
        if (!$tiktok) return;
        $uInfo = DB::table('user_ask_acount')->where(['tiktok_id' => $tiktok,'type' => 0])->first();

        if (!$uInfo) return [];

        $getFansNum = $uInfo->ask_time - (int)Redis::LLEN('ask_ready_list:' . $tiktok);
        

        // 从集合里取数据到列表里
        for ($i = 0; $i < $getFansNum; $i++) {
            $uid = Redis::SPOP('keywords_ask_set:' . $uInfo->user_id);
            if($uid) Redis::LPUSH('ask_ready_list:' . $tiktok,$uid);
        }
        
        $return = Redis::LRANGE('ask_ready_list:' . $tiktok, 0, ((int)$uInfo->ask_time)-1);
        Redis::LPUSH('run_log:'.$uInfo->user_id.':'.$tiktok,json_encode(array('tiktok' => $return,'time' => date('Y-m-d H:i:s'))));
        return $return;
    }

    // 完成打招呼
    public function doCompleteAsk(Request $request)
    {
        date_default_timezone_set('Asia/Shanghai');
        $tiktok = $request->input('tiktok');
        if (!$tiktok) return;
        $uInfo = DB::table('user_ask_acount')->where(['tiktok_id' => $tiktok,'type' => 0])->first();
        $uid = Redis::LPOP('ask_ready_list:' . $tiktok);
        if (!$uid) return;

        $month = date('m');
        $day = date('d');
        $h = date('H');

        Redis::LPUSH('run_log:'.$uInfo->user_id.':'.$tiktok,json_encode(array('tiktok' => $uid,'time' => date('Y-m-d H:i:s'))));
        // Redis::incr("ask_account_total:" . $day . ":" . $h);
        // Redis::incr("ask_account_total:total");
        // Redis::incr("ask_account_total:today");

        Redis::HINCRBY("ask_account_group:" . $uInfo->user_id . ":" . $month . "." . $day . ":group", $h, 1);
        Redis::HINCRBY("ask_account_group:" . $uInfo->user_id . ":" . $month . "." . $day . ":user:" . $tiktok, $h, 1);

        Redis::incr("ask_account_group:" . $uInfo->user_id . ":total");
        Redis::incr("ask_account_group:" . $uInfo->user_id . ":today");
        return $uid;
    }

    // 获取没有地区信息的抖音用户信息(已停用)
    public function getNoLocationUser(Request $request)
    {
        $pyUid = $request->input('py_uid');
        if (empty($pyUid)) {
            return response([
                'code' => 400,
                'msg' => '缺失参数',
                'data' => []
            ], 400);
        }
        $userData = DB::table("fans")->where("tiktok_uid", ">", 0)->whereNull('city')->whereOr('city', '')->whereIn('py_uid', [0, $pyUid])->limit(50)->select('tiktok_uid')->get();
        $tiktokUid = [];
        foreach ($userData as $value) {
            $tiktokUid[] = $value->tiktok_uid;
        }
        $response = ['code' => 200, 'msg' => '获取成功', 'data' => $tiktokUid];
        DB::table("fans")->whereIn('tiktok_uid', $tiktokUid)->update(['py_uid' => $pyUid]);
        return response($response, $response['code']);
    }
    // 获取没有范围信息的抖音用户信息
    public function getNoLocationUsers(Request $request)
    {
        $pyUid = $request->input('py_uid');
        if (empty($pyUid)) {
            return response([
                'code' => 400,
                'msg' => '缺失参数',
                'data' => []
            ], 400);
        }
        $userData = DB::table("fans")
            ->where("tiktok_uid", ">", 0)
            ->where("fans_range",0)
            ->where(function ($query)use ($pyUid){
                $query->where('py_uid',0)
                    ->orWhere('py_uid',$pyUid);
            })
//            ->whereIn('py_uid', [0, $pyUid])
            ->limit(50)
            ->select('tiktok_uid')
            ->get();
        $tiktokUid = [];
        foreach ($userData as $value) {
            $tiktokUid[] = $value->tiktok_uid;
        }
        $response = ['code' => 200, 'msg' => '获取成功', 'data' => $tiktokUid];
        DB::table("fans")->whereIn('tiktok_uid', $tiktokUid)->update(['py_uid' => $pyUid]);
        return response($response, $response['code']);
    }

    //更新粉丝范围信息
    public function saveUserLocations(Request $request)
    {
        $requestData = $request->input();
        if (empty($requestData)) {
            return response([
                'code' => 400,
                'msg' => '非法请求',
                'data' => []
            ], 400);
        }
        $res = DB::table("fans")->where('tiktok_uid', $requestData['uid'])->update(["fans_range"=>$requestData['fans_range']]);
        $res = $res ? '成功' : '失败';
        return response([
            'code' => 200,
            'msg' => '操作' . $res,
            'data' => []
        ], 200);
    }
    //更新地区
    public function saveUserLocation(Request $request)
    {
        $requestData = $request->input();
        if (empty($requestData)) {
            return response([
                'code' => 400,
                'msg' => '非法请求',
                'data' => []
            ], 400);
        }
        $res = DB::table("fans")->where('tiktok_uid', $requestData['uid'])->update(['province' => $requestData['province'], 'city' => str_replace('市', '', $requestData['city'])]);
        $res = $res ? '成功' : '失败';
        return response([
            'code' => 200,
            'msg' => '操作' . $res,
            'data' => []
        ], 200);
    }

    // 获取要采集的视频列表
    public function getVideoList(Request $request)
    {
        $tiktok_id = $request->input('tiktok_id');
        if (empty($tiktok_id)) {
            return response([
                'code' => 400,
                'msg' => '缺失参数',
                'data' => []
            ], 400);
        }

        $comment_num = DB::table('system_setting')->where('id',1)->first();
        if ($comment_num && $comment_num->id){
            $minConlectNum = $comment_num->min_conllect_limit;
            $maxConlectNum = $comment_num->collect_limit;
        }else{
            $minConlectNum = 0;
            $maxConlectNum = 9999999999999999999999;
        }
        $userData = DB::table("video")
            ->where([["collection", 0],["comment_num",">=",$minConlectNum],["comment_num","<=",$maxConlectNum]])
            ->orWhere(function ($query)use($minConlectNum,$maxConlectNum,$tiktok_id){
                $query->where('collection',1)
                      ->where([["comment_num",">=",$minConlectNum],["comment_num","<=",$maxConlectNum]])
                      ->where('run_tiktok_id',$tiktok_id);
            })
            ->limit(50)
            ->select('video_id')
            ->get();
//        $sql = app('db')->getQueryLog();
//        var_dump($userData);
//        return;
        $videoList = [];
        foreach ($userData as $value) {
            $videoList[] = $value->video_id;
        }
        $response = ['code' => 200, 'msg' => '获取成功', 'data' => $videoList];
        DB::table("video")->whereIn('video_id', $videoList)->update(['collection' => 1,'run_tiktok_id'=>$tiktok_id]);
        return response($response, $response['code']);
    }

    //更新视频采集状态
    public function updateVideoList(Request $request)
    {
        $video_id = $request->input('video_id');
        if (empty($video_id)) {
            return response([
                'code' => 400,
                'msg' => '缺失参数',
                'data' => []
            ], 400);
        }
        $res = DB::table("video")->where('video_id', $video_id)->update(['collection' => 2]);
        $res = $res ? '成功' : '失败';
        return response([
            'code' => 200,
            'msg' => '操作' . $res,
            'data' => []
        ], 200);
    }

    //竞品粉采集脚本
    public function getScrapList(Request $request){
        $tiktok_id = $request->input('tiktok_id');
        if (empty($tiktok_id)) {
            return response([
                'code' => 400,
                'msg' => '缺失参数',
                'data' => []
            ], 400);
        }
        $uid = DB::table("collect_acount")->where([["tiktok_id",$tiktok_id],['in_use',1]])->first();
        if ($uid && $uid->id){
            $keywordList = DB::table("collect_keywords_connection")->where('collect_acount_id',$uid->id)->select('keywords_id')->get();
            if (count($keywordList)<=0){
                return [];
//                return new JsonResponse(['code' => 400, 'msg' => '当前账号未配置关键词！', 'data' => []]);
            }
            $kwArr = [];
            foreach ($keywordList as $value){
                $kwArr[] = $value->keywords_id;
            }
            $kwList = DB::table("keywords")
                ->join('video_user','video_user.keyword_id','=','keywords.id')
                ->where([
                    ['run_tiktok_uid',$tiktok_id],
                    ['collection',1],
                    ['video_user.state',0]
                ])
                ->orWhere(function ($query)use ($kwArr){
                    $query->whereIn('keywords.id',$kwArr)
                        ->where([['keywords.in_use',1],['collection','<',1]]);
                })
                ->select("keywords.collect_limit",'video_user.tiktok_uid')
                ->distinct()
                ->limit(50)
                ->get();
            $keywordLists = [];
            $updateTiktokUid = [];
            foreach ($kwList as $key=>$val){
                $keywordLists[] = $val->tiktok_uid;
                $updateTiktokUid[] = $val->tiktok_uid;
            }
            DB::table('video_user')->whereIn('tiktok_uid',$updateTiktokUid)->update(['run_tiktok_uid'=>$tiktok_id,'collection'=>1]);
            return $keywordLists;
//            return new JsonResponse(['code' => 200, 'msg' => 'success', 'data' => $keywordLists]);
        }else{
            return [];
//            return new JsonResponse(['code' => 400, 'msg' => '未找到当前账号或当前账号已被停用！', 'data' => []]);
        }
    }

    //完成竞品粉采集回调
    public function scrapListCannel(Request $request)
    {
        $tiktokUid = $request->input('tiktok_uid');
        if (empty($tiktokUid)) {
            return response([
                'code' => 400,
                'msg' => '缺失参数',
                'data' => []
            ], 400);
        }
        $res = DB::table("video_user")->where('tiktok_uid', $tiktokUid)->update(['collection' => 2]);
        $res = $res ? '成功' : '失败';
        return response([
            'code' => 200,
            'msg' => '操作' . $res,
            'data' => []
        ], 200);
    }
}
