<?php

namespace App\Http\Controllers\v1;

use App\Models\User;
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


/**
 * @desc 客户
 * @author wangxiang
 * @Class UserController
 * @package App\Http\Controllers\v1
 */
class UserController extends Controller
{
    protected $request;
    protected $client;
    protected $auth;

    protected $limit = 10;
    protected $page = 1;

    public function __construct(Request $request)
    {
        $this->request = $request;
        $this->client = new Client();
        $this->auth = new AuthController($this->request);
    }

    /**
     * @desc 获取用户列表
     * @author wangxiang
     * @return JsonResponse
     */
    public function index()
    {
        try {
            $inUse = isset($this->request['in_use']) ? intval($this->request['in_use']) : null;
            $keyword = $this->request['keyword'] ?? '';
            $limit = $this->request['limit'] ?? $this->limit;
            $user = DB::table('user');
            if (!is_null($inUse)) {
                $user = $user->where(['in_use' => $inUse]);
            }
            if ($keyword) {
                $kewordId = DB::table('word_connection')
                    ->select('word_connection.user_id')
                    ->leftJoin('keywords', 'word_connection.word_id', '=', 'keywords.id')
                    ->where([['keywords.in_use', '=', 1], ['keywords.name', 'like', '%' . $keyword . '%']])
                    ->pluck('word_connection.user_id')
                    ->toArray();
                $kewordId = array_unique($kewordId);
                $user = $user->whereIn('id', $kewordId);

            }
            $user = $user->paginate($limit)->toArray();
            $list = [];
            foreach ($user['data'] as $arr) {
                $arr->kewords =User::getKeywords($arr->id);
                $arr->yesterday_ask_num += Redis::GET('ask_account_group:' . $arr->id . ':today');
                $list[] = $arr;
            }
            return new JsonResponse([
                'code' => 200,
                'msg' => 'success',
                'data' => ['list' => $list, 'total' => $user['total']]
            ]);
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
     * @desc 编辑或增加用户
     * @author wangxiang
     * @return JsonResponse
     */
    public function post()
    {
        try {
            $id = intval($this->request['id'] ?? 0);
            $this->validate($this->request, [
                'user_name' => 'required',
                //'keywords' => 'required',
                'start_time' => 'required',
                'end_time' => 'required',
            ]);
            $data = [
                'user_name' => trim($this->request['user_name']),
                'industry' => trim($this->request['industry'] ?? ''),
                'start_time' => trim($this->request['start_time']),
                'end_time' => trim($this->request['end_time']),
                'connector' => trim($this->request['connector'] ?? ''),
                'phone' => trim($this->request['phone'] ?? ''),
                'price' => trim($this->request['price'] ?? 0),
                'dd_token' => trim($this->request['dd_token'] ?? ''),
                'location' => trim($this->request['location'] ?? ''),
                'type' => trim($this->request['type'] ?? 0),
            ];
            if (!$id) {
                # 新增
                $password = trim($this->request['password'] ?? '');
                if (!$password) {
                    return new JsonResponse([
                        'code' => 400,
                        'msg' => '密码不能为空',
                        'data' => $data
                    ], 400);
                }
                $data['password'] = password_hash($password,PASSWORD_BCRYPT, ['cost' => 10]);
                $response = User::insertData($data, $this->request);
            } else {
                # 修改
                $response = User::updateData($data, $this->request, $id);
            }
            return new JsonResponse($response);
        } catch (ValidationException $e) {
            return new JsonResponse([
                'code' => $e->getCode(),
                'msg' => $e->getMessage(),
                'data' => []
            ], 400);
        }
    }


    /**
     * @desc 获取单个用户信息
     * @author wangxiang
     * @return JsonResponse
     */
    public function view()
    {
        try {
            $this->validate($this->request, [
                'id' => 'required',
            ]);
        } catch (ValidationException $e) {
            return new JsonResponse([
                'code' => $e->getCode(),
                'msg' => $e->getMessage(),
                'data' => []
            ], 400);
        }
        $id = intval($this->request['id']);
        $user = DB::table('user')->where(['id' => $id])->first();
        if (!$user) {
            return new JsonResponse([
                'code' => 400,
                'msg' => '用户不存在',
                'data' => []
            ], 400);
        }
        $user->kewords = User::getKeywords($id);
        return new JsonResponse([
            'code' => 200,
            'msg' => 'success',
            'data' => $user
            ]);
    }


    /**
     * @desc 删除单个用户
     * @author wangxiang
     * @return JsonResponse
     */
    public function delete(){
        try {
            $this->validate($this->request, [
                'id' => 'required'
            ]);
            # 判断id是否存在
            $id = intval($this->request['id'] ?? 0);
            $user = User::find($id);
            if (!$user) {
                return new JsonResponse([
                    'code' => 400,
                    'msg' => 'id不存在',
                    'data' => []
                ], 400);
            }
            $state = DB::table('user')->where(['id' => $id])->update(['in_use' => 0]);
            if ($state) {
                return new JsonResponse([
                    'code' => 200,
                    'msg' => '删除成功',
                    'data' => $user
                ]);
            } else {
                return new JsonResponse([
                    'code' => 500,
                    'msg' => 'fail',
                    'data' => $user
                ], 500);
            }
        } catch (ValidationException $e) {
            return new JsonResponse([
                'code' => $e->getCode(),
                'msg' => $e->getMessage(),
                'data' => []
            ], 400);
        }
    }

    /**
     * @desc 重置用户粉丝采集
     * @author wyp
     * @return JsonResponse
     */
    public function resetFans(Request $request)
    {
        $tiktokArray = DB::table('user_ask_acount')->where('user_id',$request->input('id'))->pluck('tiktok_id')->toArray();

        $res = DB::table('video_user')->whereIn('tiktok_id',$tiktokArray)->update(array('collection' => 0,'run_tiktok_uid' => ''));
        return new JsonResponse([
            'code' => 200,
            'msg' => $res?'操作成功':'操作失败',
            'data' => []
        ], 200);
    }

    /**
     * @desc 设备信息记录
     * @author wyp
     * @return JsonResponse
     */
    public function logIt(Request $request)
    {
        try {
            $this->validate($this->request, [
                'width' => 'required',
                'height' => 'required',
                'product' => 'required',
                'brand' => 'required',
                'release' => 'required',
                'android_Id' => 'required',
                'tiktok_id' => 'required',
            ]);
        } catch (ValidationException $e) {
            return new JsonResponse([
                'code' => $e->getCode(),
                'msg' => $e->getMessage(),
                'data' => []
            ], 400);
        }
        $formData = $request->only('width','height','product','brand','release','android_Id','tiktok_id');
        $res = DB::table('user_ask_acount')->where('tiktok_id',$formData['tiktok_id'])->first();
        $uid = $res?$res->user_id:0;
        Redis::LPUSH('run_log:'.$uid.':'.$formData['tiktok_id'],json_encode(array_merge($formData,array('time' => date('Y-m-d H:i:s')))));
        Redis::HMSET('device:'.date('Y-m-d').':'.$uid.':'.$formData['tiktok_id'],array_merge($formData,array('time' => date('Y-m-d H:i:s'))));
    }

    /**
     * @desc 粉丝打招呼按地区重置
     * @author wyp
     * @return JsonResponse
     */
    public function resetByLocation(Request $request)
    {
        set_time_limit(0);
        $uid = $request->input('userId');
        $admin = DB::table('user')->where('id',$uid)->first();
        if(!$admin->location) return new JsonResponse([
            'code' => 500,
            'msg' => '地区未设置',
            'data' => []
        ], 500);

        $keywordIdData = DB::table('word_connection')
            ->select('word_id')
            ->where(['user_id' => $uid])
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

        $fansArray = $fans
            ->select('fans.tiktok_uid')
            // ->limit(2)
            ->get()
            ->toArray();


        Redis::DEL('keywords_ask_set:'.$uid);
        Redis::SADD('keywords_ask_set:'.$uid,array_column($fansArray,'tiktok_uid'));
        return new JsonResponse([
            'code' => 200,
            'msg' => '操作成功',
            'data' => []
        ], 200);

    }

}
