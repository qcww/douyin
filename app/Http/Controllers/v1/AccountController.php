<?php

namespace App\Http\Controllers\v1;

use App\Models\Keywords;
use App\Models\Video;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Log;
use GuzzleHttp\Client;
use App\Common;

// 测试控制器
class AccountController extends Controller
{
    protected $request;
    protected $client;
    protected $auth;

    protected $userId;  //当前登录者的id

    public function __construct(Request $request)
    {
        $this->request = $request;
        $this->client = new Client();
        $this->auth = new AuthController($this->request);
        $this->userId = $this->request['userInfo']['id'];
    }

    // 用户数据统计
    public function countUser($userId = 0)
    {
        $userId = $userId?$userId:$this->userId;

        $keywordsArray = DB::table('word_connection as cc')
            ->select('k.name')
            ->join('keywords as k', 'k.id', '=', 'cc.word_id')
            ->where('cc.user_id',$userId)->get()->map(function ($value) {
                return (array)$value;
            })->toArray();
        $keywords = array_column($keywordsArray,'name');
        $fansNum = $userNum = 0;
        $accountType = $this->request['userInfo']['type']?'_'.$this->request['userInfo']['type']:'';
        foreach($keywords as $k){
            if($accountType) $fansNum += Redis::SCARD('keywords_fans_set'.$accountType.':'.$k); // 用于快手的客户粉丝数量统计
            $userNum += Redis::LLEN('keywords_user:'.$k);
        }

        // 用于抖音的粉丝数量统计
        if(!$accountType){
            $admin = DB::table('user')->where('id',$userId)->first();
            $keywordIdData = DB::table('word_connection')
                ->select('word_id')
                ->where(['user_id' => $userId])
                ->pluck('word_id')
                ->toArray();
            $fans = DB::table('fans')
                ->leftJoin('label', 'label.id', '=', 'fans.label')
                ->leftJoin('video_user as v', 'v.id', '=', 'fans.collect_id')
                ->whereIn('v.keyword_id', $keywordIdData);

            if($location = $admin->location){
                $fans = $fans->where(function($query)use($location){
                    return $query->orWhereIn('fans.city',[$location,$location.'市'])->orWhereIn('fans.province',[$location,$location.'省']);
                });
            }
            $fansNum = $fans->count();
        }

        $list = Redis::LRANGE('ask_account_group:'.$userId.':count_list',0,29);
        $list = array_map(function($v){
            $li = json_decode($v);
            $li->x .= '日';
            return $li;
        },$list);

        array_unshift($list,['x' => date('d').'日','y' => (int)Redis::get('ask_account_group:'.$userId.':today')]);

        // $listLen = 30 - count($list);
        // for($i=1;$i<$listLen;$i++){
        //     array_push($list,['x' => (int)date('d',strtotime('+'.$i.' days')),'y' => 0]);
        // }
        if($this->request['userInfo']['type'] != '0'){
            $video = DB::table('ks_videos')->whereIn('tag',$keywords)->count();
        }else{
            $video = DB::table('video')->whereIn('keyword',$keywords)->count();
        }

        return array(
            'fans_num' => $fansNum,
            'user' => $userNum,
            'ask' => (int)Redis::get('ask_account_group:'.$userId.':total'),
            'video' => $video,
            'count_list' => $list
        );
    }

    // 总统计
    public function countTotal(Request $request)
    {
        $uid = $request->input('uid');
        if($uid) return $this->countUser($uid);
        $list = Redis::LRANGE('ask_account_total:count_list',0,29);
        $list = array_map(function($v){
            $li = json_decode($v);
            $li->x .= '日';
            return $li;
        },$list);

        array_unshift($list,['x' => date('d').'日','y' => (int)Redis::get('ask_account_total:today')]);
        // $listLen = 30 - count($list);
        // for($i=1;$i<$listLen;$i++){
        //     array_push($list,['x' => (int)date('d',strtotime('+'.$i.' days')),'y' => 0]);
        // }
        return array(
            'fans_num' => (int)Redis::get('max_fans_id'),
            'user' => (int)Redis::get('max_video_user_id'),
            'ask' => (int)Redis::get('ask_account_total:total'),
            'video' => 50,
            'count_list' =>$list
        );

    }

    // 用户列表
    public function userList()
    {
        return DB::table('user')->where(['in_use' => '1'])->select('id','user_name')->get();
    }

    // 每日统计
    public function countToday()
    {
        date_default_timezone_set('Asia/Shanghai');
        $userId = $this->userId;
        $askAcount = DB::table('user_ask_acount')->where(['user_id' => $userId,'in_use' => '1','type' => 0])->where('time_range','!=','')->select('tiktok_id','remark','time_range','ask_time')->get()->map(function ($value) {
            return (array)$value;
        })->toArray();

        $nowHours = date('H');
        $return = [];
        foreach($askAcount as $k => $a){
            $time = explode(',',$a['time_range']);
            sort($time,1);
            $hashKey = sprintf("ask_account_group:%s:%s:user:%s",$userId,date('m').'.'.date('d'),$a['tiktok_id']);

            $show = false;
            $askAccount = Redis::HGETALL($hashKey);

            for($i=1;$i<24;$i++){
                $a['t_'.$i] = '';

                if(in_array($i,$time)){
                    if($i <= $nowHours){
                        $askNum = isset($askAccount[$i])?$askAccount[$i]:0;
                        $a['t_'.$i] = (int)$askNum - (int)$a['ask_time'];
                        if($a['t_'.$i] < 0){
                            $show = true;
                        }
                    }else{
                        $a['t_'.$i] = 0;
                    }
                }
            }

            if(!$show) continue;
            $return[] = $a;
        }
        return $return;
    }


}
