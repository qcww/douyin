<?php

namespace App\Http\Controllers\v1;

use App\Models\FansReply;
use function GuzzleHttp\Psr7\str;
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
class FansController extends Controller
{
    protected $request;
    protected $client;
    protected $auth;

    protected $limit = 10;
    protected $page = 1;
    protected $userId;  //当前登录者的id
    // protected $jwt;

    public function __construct(Request $request)
    {
        $this->request = $request;
        $this->client = new Client();
        $this->auth = new AuthController($this->request);
        $this->userId = $this->request['userInfo']['id'];
    }


    /**
     * @desc 获取粉丝列表数据
     * @author wangxiang
     * @return JsonResponse
     */
    public function index()
    {
        $keyword = trim($this->request['keyword'] ?? '');
        $limit = intval($this->request['limit'] ?? $this->limit);
        $label = intval($this->request['label'] ?? 0);
        $keyId = intval($this->request['key_id'] ?? 0);
        # 获取当前登录者的关键词id集合
        $keywordIdData = DB::table('word_connection')
            ->select('word_id')
            ->where(['user_id' => $this->userId])
            ->pluck('word_id')
            ->toArray();
        $fans = DB::table('fans')
            ->leftJoin('label', 'label.id', '=', 'fans.label')
            ->leftJoin('video_user as v', 'v.id', '=', 'fans.collect_id')
            ->whereIn('v.keyword_id', $keywordIdData);
        if ($label) {
            $fans = $fans->where(['fans.label' => $label]);
        }
        if ($keyId) {
            $fans = $fans->where(['v.keyword_id' => $keyId]);
        }
        if ($keyword) {
            $fans = $fans->where('fans.nickname', 'like', '%' . $keyword . '%');
        }

        if(isset($this->request['userInfo']['location']) && $location = $this->request['userInfo']['location']){
            $fans = $fans->where(function($query)use($location){
                return $query->orWhereIn('fans.city',[$location,$location.'市'])->orWhereIn('fans.province',[$location,$location.'省']);
            });
        }

        $fans = $fans
            ->select('fans.id', 'fans.nickname', 'fans.tiktok_id', 'fans.tiktok_uid', 'fans.keyword', 'fans.sex', 'label.name as label_name', 'fans.tiktok_index', 'fans.province', 'fans.city', 'fans.district', 'fans.signature')
            ->paginate($limit)
            ->toArray();
        $labelList = DB::table('user_label_connection')
            ->select('l.id', 'l.name')
            ->where(['user_label_connection.user_id' => $this->userId])
            ->join('label as l', 'l.id', '=', 'user_label_connection.label_id')
            ->get();
        //$labelList = DB::table('label')->get();
        $keywords = DB::table('keywords')->select('id', 'name')->where(['in_use' => 1])->get();
        return new JsonResponse([
            'code' => 200,
            'msg' => 'success',
            'data' => ['list' => $fans['data'], 'kewords' => $keywords, 'label' => $labelList, 'total' => $fans['total']]
        ]);
    }


    /**
     * @desc 我的粉丝
     * @author wyp
     * @return JsonResponse
     */
    public function myFans()
    {
        $keyword = trim($this->request['keyword'] ?? '');
        $limit = intval($this->request['limit'] ?? $this->limit);

        $type = intval($this->request['type'] ?? 0);
        $groupAsker = DB::table('user_ask_acount')->where(['type' => '1','user_id' => $this->userId])->pluck('tiktok_uid')
            ->toArray();
        $fansModel = DB::table('self_fans as s')
            ->leftJoin('user_ask_acount as a', 'a.tiktok_uid', '=', 's.uid')
            ->select('s.*', 'a.nickname as anickname')
            ->where('a.type','1')
            ->whereIn('uid',$groupAsker);

        if ($keyword) {
            $fansModel = $fansModel->where('s.nickname', 'like', '%' . $keyword . '%');
        }
        if ($type) {
            $fansModel = $fansModel->where('s.type',$type);
        }
        $fans = $fansModel->paginate($limit)
            ->toArray();

        return new JsonResponse([
            'code' => 200,
            'msg' => 'success',
            'data' => ['list' => $fans['data'], 'total' => $fans['total']]
        ]);
    }

    /**
     * @desc 修改粉丝标签
     * @author wangxiang
     * @return JsonResponse
     */
    public function myAccount()
    {
        $keyword = trim($this->request['keyword'] ?? '');
        $limit = intval($this->request['limit'] ?? $this->limit);

        if(is_null($this->request['in_use'])||$this->request['in_use'] == 'true'){
            $status = 1;
        }else{
            $status = 0;
        }

        $askModel = DB::table('user_ask_acount as u')
            ->leftJoin('model as m', 'u.ask_model', '=', 'm.id')
            ->where(['type' => 1,'u.user_id' => $this->userId])
            ->select('u.id','u.user_id','u.nickname','u.tiktok_uid','u.tiktok_id','u.remark','u.in_use','m.id as mid','m.name as mname');
        if ($keyword) {
            $askModel = $askModel->where('u.nickname', 'like', '%' . $keyword . '%');
        }

        $askModel = $askModel->where('u.in_use',$status);
        $account = $askModel->paginate($limit)->toArray();
        if (isset($account['data'])) {
            foreach ($account['data'] as $d) {
                $d->in_use = $d->in_use == 1?true:false;
                $d->fans = (int)Redis::SCARD('group_fans_data:fans:'.$d->tiktok_id);
                $d->zan = (int)Redis::SCARD('group_fans_data:zan:'.$d->tiktok_id);
                $ask = $d->fans + $d->zan - (int)Redis::LLEN('ask_ready_self:fans:'.$d->tiktok_id) - (int)Redis::LLEN('ask_ready_self:zan:'.$d->tiktok_id);
                $d->ask = $ask < 0?0:$ask;
            }
        }


        return new JsonResponse([
            'code' => 200,
            'msg' => 'success',
            'data' => ['list' => $account['data'], 'total' => $account['total']]
        ]);
    }

    /**
     * @desc 重置打招呼
     * @author wyp
     * @return JsonResponse
     */
    public function resetFans(Request $request)
    {
        $tiktokId = $request->input('tiktok_id');
        $exist = DB::table('user_ask_acount')->where(['tiktok_id' => $tiktokId,'user_id' => $this->userId])->first();
        if(!$exist) {
            return new JsonResponse([
                'code' => 400,
                'msg' => '未找到符合条件数据',
                'data' => []
            ]);
        }
        Redis::DEL('ask_ready_self:fans:'.$tiktokId);
        if((int)Redis::SCARD('group_fans_data:fans:'.$tiktokId) > 0)Redis::LPUSH('ask_ready_self:fans:'.$tiktokId,Redis::SMEMBERS('group_fans_data:fans:'.$tiktokId));
        return new JsonResponse([
            'code' => 200,
            'msg' => '操作成功',
            'data' => []
        ]);
    }

    /**
     * @desc 添加修改账号
     * @author wyp
     * @return JsonResponse
     */
    public function eaditAccount(Request $request)
    {
        $requestData = $request->except('tokenInfo','userInfo');
        try {
            $this->validate($this->request, [
                'tiktok_id' => 'required',
                'tiktok_uid' => 'required',
                'nickname' => 'required',
                'in_use' => 'required'
            ]);
        } catch (ValidationException $e) {
            return new JsonResponse([
                'code' => $e->getCode(),
                'msg' => $e->getMessage(),
                'data' => []
            ], 400);
        }
        $requestData['in_use'] = $requestData['in_use'] == 'true'?1:0;
        if (isset($requestData['id'])) {
            $res = DB::table('user_ask_acount')->where(['id' => $requestData['id']])->update($requestData);
        } else {
            $requestData['user_id'] = $this->userId;
            $requestData['type'] = 1;
            $res = DB::table('user_ask_acount')->insert($requestData);
        }
        return new JsonResponse([
            'code' => 200,
            'msg' => $res?'操作成功':'操作失败',
            'data' => []
        ]);
    }

    /**
     * @desc 模板列表
     * @author wyp
     * @return JsonResponse
     */
    public function myTemplate()
    {
        return DB::table('model')->where('user_id',$this->userId)->select('id','name')->get();
    }

    /**
     * @desc 删除账号
     * @author wyp
     * @return JsonResponse
     */
    public function delAccount(Request $request)
    {
        $res = DB::table('user_ask_acount')->where(['user_id' => $this->userId,'id' => $request->input('id')])->delete();
        return new JsonResponse([
            'code' => 200,
            'msg' => $res?'操作成功':'操作失败',
            'data' => []
        ]);
    }

    /**
     * @desc 修改粉丝标签
     * @author wangxiang
     * @return JsonResponse
     */
    public function updateLable()
    {
        try {
            $this->validate($this->request, [
                'id' => 'required',
                'label' => 'required',
            ]);
        } catch (ValidationException $e) {
            return new JsonResponse([
                'code' => $e->getCode(),
                'msg' => $e->getMessage(),
                'data' => []
            ], 400);
        }

        try {
            $id = intval($this->request['id'] ?? 0);
            $label = intval($this->request['label'] ?? 0);
            if (!$label) {
                return new JsonResponse([
                    'code' => 400,
                    'msg' => '标签id不能为空',
                    'data' => []
                ], 400);
            }
            $state = DB::table('fans')->where(['id' => $id])->update(['label' => $label]);
            if ($state) {
                $response = ['code' => 200, 'msg' => '修改成功', 'data' => []];
            } else {
                $response = ['code' => 500, 'msg' => '修改失败', 'data' => []];
            }
            return new JsonResponse($response, $response['code']);
        } catch (HttpResponseException $e) {
            Log::info('getTaskList:' . json_encode(['code' => $e->getCode(), 'msg' => $e->getMessage()]));
            return new JsonResponse([
                'code' => $e->getCode(),
                'msg' => '网络出现异常，请重试',
                'data' => new \StdClass
            ], 400);
        }

    }


    /**
     * @desc 获取粉丝回复列表
     * @author wangxiang
     * @return JsonResponse
     */
    public function reply()
    {
        $statusData = ['num_zero', 'num_one', 'num_two'];
        $todayBegin = strtotime(date('Y-m-d'));
        $todayEnd = $todayBegin + 86400;
        $limit = intval($this->request['limit'] ?? $this->limit);
        $status = intval($this->request['status'] ?? -1);
        $startTime = strtotime(trim($this->request['start_time'] ?? 0));
        $endTime = strtotime(trim($this->request['end_time'] ?? 0));
        $data = FansReply::query()->where(['user_id' => $this->userId]);
        if (!$startTime && !$endTime) {
            $data = $data->whereBetween('reply_time', [$todayBegin, $todayEnd]);
        } else {
            if ($startTime) {
                $data = $data->where('reply_time', '>=', $startTime);
            }
            if ($endTime) {
                $data = $data->where('reply_time', '<', $endTime);
            }
        }
        $query = clone $data;
        if ($status > -1) {
            $data = $data->where(['status' => $status]);
        }
        $num['num_all'] = 0;
        foreach ($statusData as $k=> $v) {
            $queryTemp = clone $query;
            $num[$v] = $queryTemp->where(['status' => intval($k)])->count();
            $num['num_all'] += $num[$v];
        }
        $data = $data
            ->paginate($limit)
            ->toArray();
        return new JsonResponse([
            'code' => 200,
            'msg' => 'success',
            'data' => ['list' => $data['data'], 'total' => $data['total'], 'num' => $num]
        ]);
    }


    /**
     * @desc 粉丝回复编辑
     * @author wangxiang
     * @return JsonResponse
     */
    public function updateReply()
    {
        try {
            $this->validate($this->request, [
                'id' => 'required',
                'status' => 'required',
            ]);
        } catch (ValidationException $e) {
            return new JsonResponse([
                'code' => $e->getCode(),
                'msg' => $e->getMessage(),
                'data' => []
            ], 400);
        }
        try {
            $id = intval($this->request['id'] ?? 0);
            $status = intval($this->request['status'] ?? 0);
            $deviceRemark = trim($this->request['remark'] ?? '');
            $updateData = [
                'status' => $status,
                'remark' => $deviceRemark,
            ];
            $state = DB::table('fans_reply')->where(['id' => $id])->update($updateData);
            if ($state) {
                $response = ['code' => 200, 'msg' => '修改成功', 'data' => []];
            } else {
                $response = ['code' => 500, 'msg' => '修改失败', 'data' => []];
            }
            return new JsonResponse($response, $response['code']);
        } catch (HttpResponseException $e) {
            Log::info('getTaskList:' . json_encode(['code' => $e->getCode(), 'msg' => $e->getMessage()]));
            return new JsonResponse([
                'code' => $e->getCode(),
                'msg' => '网络出现异常，请重试',
                'data' => new \StdClass
            ], 500);
        }
    }

    /**
     * @desc 添加我的粉丝
     * @author wyp
     * @return JsonResponse
     */
    public function addMyFans(Request $request,$type = 2)
    {
        $inputKey = array('1'=>'likes','2'=>'fans','3'=>'coms');
        $postData = $request->input($inputKey[strval($type)]);

        $fans = json_decode($postData,true);
        $uInfo = DB::table('user_ask_acount')->where(['tiktok_uid' => $fans['owner'],'type'=>1])->first();
        if(!$uInfo) {
            return new JsonResponse([
                'code' => 500,
                'msg' => '后台未添加该用户',
                'data' => []
            ], 400);
        }
        $setType = $type == 2?'fans':'zan';

        if (!Redis::EXISTS('max_self_fans_id')) {
            Redis::SET('max_self_fans_id', 1);
        }

        Redis::SADD('group_fans_data:'.$setType.':'.$uInfo->tiktok_id, array_keys($fans['fans']));
        foreach ($fans['fans'] as $key => $user) {
            $insertArray = array(
                'id' => Redis::get('max_self_fans_id'),
                'tiktok_uid' => $key,
                'nickname' => $this->js_unescape($user),
                'type' => $type,
                'uid' => $fans['owner']
            );

            try {
                $res = DB::table('self_fans')->insert($insertArray);
                if ($res) {
                    Redis::LPUSH('ask_ready_self:'.$setType.':'.$uInfo->tiktok_id,$key);
                    Redis::incr('max_self_fans_id');
                }
            } catch (\Exception $e) {

            }
        }
        return new JsonResponse(['code' => 200, 'msg' => '操作成功', 'data' => []]);


    }

    /**
     * @desc 添加我的点赞用户
     * @author wyp
     * @return JsonResponse
     */
    public function addLikes(Request $request)
    {
        return $this->addMyFans($request,1);
    }

    /**
     * @desc 添加我的点赞用户
     * @author wyp
     * @return JsonResponse
     */
    public function addComment(Request $request)
    {
        return $this->addMyFans($request,3);
    }

    public function js_unescape($str)
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

    /**
     * @desc 给我的关注粉丝打招呼
     * @author wyp
     * @return JsonResponse
     */
    public function getMyFocusList(Request $request)
    {
        $tiktok = $request->input('tiktok_id');
        $tiktok = str_replace(array('%20', ' ', ' '), "", $tiktok);
        if(!$tiktok) return [];
        return Redis::LRANGE('ask_ready_self:fans:' . $tiktok, 0, 199);
    }

    /**
     * @desc 完成给我的关注粉丝打招呼
     * @author wyp
     * @return JsonResponse
     */
    public function compMyFocusFans(Request $request)
    {
        $tiktok = $request->input('tiktok_id');
        $tiktok = str_replace(array('%20', ' ', ' '), "", $tiktok);
        if(!$tiktok) return [];
        return Redis::LPOP('ask_ready_self:fans:' . $tiktok);
    }

    /**
     * @desc 给我的点赞评论用户打招呼
     * @author wyp
     * @return JsonResponse
     */
    public function getMyZanList(Request $request)
    {
        $tiktok = $request->input('tiktok_id');
        $tiktok = str_replace(array('%20', ' ', ' '), "", $tiktok);
        if(!$tiktok) return [];
        return Redis::LRANGE('ask_ready_self:zan:' . $tiktok, 0, 199);
    }

    /**
     * @desc 完成给我的点赞评论用户打招呼
     * @author wyp
     * @return JsonResponse
     */
    public function compMyZanFans(Request $request)
    {
        $tiktok = $request->input('tiktok_id');
        $tiktok = str_replace(array('%20', ' ', ' '), "", $tiktok);
        if(!$tiktok) return [];
        return Redis::LPOP('ask_ready_self:zan:' . $tiktok);
    }

    /**
     * @desc 需要过滤粉丝地区的客户
     * @author wyp
     * @return Array
     */
    public function customCity()
    {
        return DB::table('user')->where('location','!=','')->pluck('id','user_name')->toArray();
    }

    /**
     * @desc 未获取粉丝详情数据列表
     * @author wyp
     * @return Array
     */
    public function fansLocationList(Request $request)
    {
        // 传递客户uid => 关键词 => 竞品用户 => 粉丝
        $userId = $request->input('user_id');
        $android = $request->input('android');
        if (!$userId || !$android) {
            return [];
        }
        $tiktokUid = DB::table('word_connection as w')
            ->leftJoin('video_user as v', 'v.keyword_id', '=', 'w.word_id')
            ->leftJoin('fans as f', 'f.collect_id', '=', 'v.id')
            ->limit(100)
            ->where(['w.user_id' => $userId,'f.city' => ''])
            ->where('f.fans_type',0)
            ->where(function($query)use($android){
                $query->where('android',$android)->orWhere('android','');
            })
            ->pluck('f.tiktok_uid')
            ->toArray();
        DB::table('fans')->whereIn('tiktok_uid',$tiktokUid)->update(['android' => $android]);
        return $tiktokUid;
    }

    /**
     * @desc 添加快手视频
     * @author wyp
     * @return JsonResponse
     */
    public function addKSVideos(Request $request)
    {
        $videoData = $this->js_unescape($request->input('seovideo'));
        $video = json_decode($videoData,true);
        if(!$video){
            return new JsonResponse(['code' => 500, 'msg' => '数据格式有问题', 'data' => []], 500);
        }

        $tag = $video['tag'];
        foreach($video['vdo'] as $k => $v){
            try{
                $res = DB::table('ks_videos')->insert(array(
                    'tag' => $tag,
                    'user_id' => $k,
                    'photo_id' => $v
                ));
            }catch (\Exception $e){
                $res = false;
            }
        }

        return new JsonResponse([
            'code' => 200,
            'msg' => $res?'添加成功':'重复添加',
            'data' => []
        ], 200);

    }

    /**
     * @desc 返回待采集的快手视频
     * @author wyp
     * @return JsonResponse
     */
    public function getKSVideo(Request $request)
    {
        $sign = $request->input('sign');
        $return = DB::table('ks_videos')
            ->limit(5)
            ->where('get_status','!=',2)->where(function($query)use($sign){
                $query->where('get_server',$sign)->orWhere('get_status',0);
            })
            ->select('id','user_id','photo_id')
            ->get()->map(function ($value) {
                return (array)$value;
            })->toArray();
        DB::table('ks_videos')->whereIn('id',array_column($return,'id'))->update(['get_server' => $sign,'get_status' => 1]);
        return $return;
    }

    /**
     * @desc 完成快手视频采集
     * @author wyp
     * @return JsonResponse
     */
    public function compKSVideo(Request $request)
    {
        $id = $request->input('id');
        if(!$id) return;
        $res = DB::table('ks_videos')->where('id',$id)->update(['get_status' => 2]);
        return new JsonResponse([
            'code' => 200,
            'msg' => $res?'操作成功':'操作失败',
            'data' => []
        ], 200);
    }

    /**
     * @desc 粉丝按地区重置
     * @author wyp
     * @return JsonResponse
     */
     public function resetByLocation()
     {
         $tiktokUid = DB::table('word_connection as w')
             ->leftJoin('video_user as v', 'v.keyword_id', '=', 'w.word_id')
             ->leftJoin('fans as f', 'f.collect_id', '=', 'v.id')
             ->where('f.fans_type',0)
             ->where('w.user_id',$this->userId)
             ->pluck('f.tiktok_uid')
             ->toArray();
         return $tiktokUid;
     }


	    /**
     * @desc 手动同步数据库数据到redis打招呼里
     * @author wyp
     * @return JsonResponse
     */
    public function fansSqlToRedis(Request $request)
    {
        $collectionTime = $request->input('collection_time');
        $province = $request->input('province');
        $userId = $request->input('user_id');
        $keyword = $request->input('keyword');
        $updateTime = $request->input('update_time');
        $city = $request->input('city');

        $fansDB = DB::table('fans as f')->leftJoin('video_user as v', 'v.tiktok_uid', '=', 'f.collect_uid');
        if ($province) {
            $fansDB = $fansDB->where(['f.province' => $province]);
        }

        if ($city) {
            $fansDB = $fansDB->where(['f.city' => $city]);
        }

        if ($collectionTime) {
            $fansDB = $fansDB->where(['f.collection_time' => $collectionTime]);
        }

        if ($updateTime) {
            $fansDB = $fansDB->where('f.update_time' ,'>=', strtotime($updateTime));
        }

        if ($keyword) {
            $fansDB = $fansDB->where(['v.keyword' => $keyword]);
        }
        $fans = $fansDB->distinct('tiktok_uid')->pluck('f.tiktok_uid')->toArray();
        if ($fans) Redis::SADD('keywords_ask_set:'.$userId,$fans);
//        return count($fans);
        $count = $fansDB->distinct('tiktok_uid')->count();

        return $count;
    }

}
