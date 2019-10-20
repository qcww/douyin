<?php

namespace App\Http\Controllers\v1;

use App\Models\Keywords;
use function GuzzleHttp\Psr7\str;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Redis;
use Illuminate\Validation\ValidationException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;
use Log;
use GuzzleHttp\Client;

/**
 * @desc 用户
 * @author wangxiang
 * @Class VideoUserController
 * @package App\Http\Controllers\v1
 */
class VideoUserController extends Controller
{
    protected $request;
    protected $client;
    protected $auth;

    protected $limit = 10;
    protected $page = 1;
    public $fansData = [];
    protected $userId;  //当前登录者的id

    public function __construct(Request $request)
    {
        $this->request = $request;
        $this->client = new Client();
        $this->auth = new AuthController($this->request);
        $this->fansData = ['全部', '1000以下', '1000~5000', '5000以上'];
        $this->userId = $this->request['userInfo']['id'];
    }


    /**
     * @desc 获取用户列表
     * @author wangxiang
     * @return JsonResponse
     */
    public function index()
    {
        $keyword = trim($this->request['keyword'] ?? '');
        $limit = intval($this->request['limit'] ?? $this->limit);
        $keyId = intval($this->request['key_id'] ?? 0);
        $fansNum = intval($this->request['fans_num'] ?? 0);
        $data = DB::table('video_user');
        if ($keyword) {
            $data = $data->where('nickname', 'like', '%' . $keyword . '%');
        }
        if ($keyId) {
            $data = $data->where(['keyword_id' => $keyId]);
        }
        if ($fansNum > 0) {
            $data = $this->dellFansNum($data, $fansNum);
        }
        $keywords = DB::table('word_connection')
            ->select('k.id', 'k.name')
            ->where(['k.in_use' => 1])
            ->where(['word_connection.user_id' => $this->userId])
            ->join('keywords as k', 'k.id', '=', 'word_connection.word_id')
            ->get();
        $myKeywordsId = [];
        foreach ($keywords as $arr) {
            $myKeywordsId[] = $arr->id;
        }
        $data = $data
            ->select('id', 'nickname', 'tiktok_id', 'collect_fans', 'sex', 'tiktok_uid', 'province', 'city', 'district', 'comment', 'keyword', 'state')
            ->whereIn('keyword_id', $myKeywordsId)
            ->paginate($limit)
            ->toArray();
        $list = $data['data'];
        $fansNum = $this->fansData;


        return new JsonResponse([
            'code' => 200,
            'msg' => 'success',
            'data' => ['list' => $list, 'total' => $data['total'], 'fansNum' => $fansNum, 'keywords' => $keywords]
        ]);
    }


    /**
     * @desc 新增用户
     * @author wangxiang
     * @return JsonResponse
     */
    public function add()
    {
        /*try {
            $this->validate($this->request, [
                'nickname' => 'required',
                'tiktok_id' => 'required',
                'sex' => 'required',
                'user_index' => 'required',
                'province' => 'required',
                'city' => 'required',
                'district' => 'required',
                'comment' => 'required',
                'keyword_id' => 'required',
                'keyword' => 'required',
            ]);
        } catch (ValidationException $e) {
            return new JsonResponse([
                'code' => $e->getCode(),
                'msg' => $e->getMessage(),
                'data' => []
            ], 400);
        }*/
        $insertData = [
            'keyword_id' => intval($this->request['keyword_id'] ?? 0),
            'video_id' => trim($this->request['video_id'] ?? ''),
            'nickname' => trim($this->request['nickname'] ?? ''),
            'tiktok_id' => trim($this->request['tiktok_id'] ?? ''),
            'collect_fans' => intval($this->request['collect_fans'] ?? 0),
            'sex' => trim($this->request['sex'] ?? '未知'),
            'tiktok_uid' => strval((trim($this->request['tiktok_uid'] ?? ''))),
            'user_index' => trim($this->request['user_index'] ?? ''),
            'province' => trim($this->request['province'] ?? ''),
            'city' => trim($this->request['city'] ?? ''),
            'district' => trim($this->request['district'] ?? ''),
            'comment' => trim($this->request['comment'] ?? ''),
            'keyword' => trim($this->request['keyword'] ?? ''),
            'state' => intval($this->request['state'] ?? 0),
        ];

        $state = DB::table('video_user')->insert($insertData);
        if ($state) {
            $response = ['code' => 200, 'msg' => '新增成功', 'data' => []];
        } else {
            $response = ['code' => 500, 'msg' => '新增失败', 'data' => []];
        }
        return new JsonResponse($response, $response['code']);
    }


    /**
     * @desc 用户启用或禁用
     * @author wangxiang
     * @return JsonResponse
     */
    public function prohibit()
    {
        try {
            $id = intval($this->request['id'] ?? 0);
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
        $videoUser = DB::table('video_user')->where(['id' => $id])->first();
        if (!$videoUser) {
            return new JsonResponse([
                'code' => 400,
                'msg' => 'id不存在',
                'data' => []
            ], 400);
        }

        $videoUserState = $videoUser->state ? 0 : 1;
        $msg = $videoUserState ? '禁用' : '启用';
        DB::beginTransaction();
        try {
            $state = DB::table('video_user')->where(['id' => $id])->update(['state' =>$videoUserState]);
            if ($videoUserState == 1) {
                # 如果禁用, 获取该用户下面的粉丝抖音id集合
                $tiktokIdData = DB::table('fans')
                    ->select('fans.tiktok_id')
                    ->where(['v.id' => $id])
                    ->join('video_user as v', 'fans.collect_id', '=', 'v.id')
                    ->pluck('fans.tiktok_id')
                    ->toArray();
                # 获取客户的id
                $userIdData = DB::table('user')
                    ->select('id')
                    ->pluck('id')
                    ->toArray();
                foreach ($userIdData as $userId) {
                    Redis::SREM('keywords_ask_set:' . $userId, $tiktokIdData);
                }
            }
            DB::commit();
        } catch (HttpResponseException $e) {
            DB::rollback();
            return new JsonResponse([
                'code' => $e->getCode(),
                'msg' => '网络出现异常，请重试',
                'data' => []
            ], 500);
        }

        if ($state) {
            return new JsonResponse([
                'code' => 200,
                'msg' => $msg . '成功',
                'data' => []
            ]);
        } else {
            return new JsonResponse([
                'code' => 500,
                'msg' => $msg . '失败',
                'data' => []
            ], 500);
        }
    }

    /**
     * @desc 处理粉丝数量筛选条件
     * @author wangxiang
     * @param $data
     * @param $fansNum
     * @return mixed
     */
    protected function dellFansNum($data, $fansNum)
    {
        switch ($fansNum) {
            case 1:
                $data = $data->where('collect_fans', '<', 1000);
                break;
            case 2:
                $data = $data->whereBetween('collect_fans', [1000, 5000]);
                break;
            case 3:
                $data = $data->where('collect_fans', '>', 5000);
                break;
        }
        return $data;
    }
}
