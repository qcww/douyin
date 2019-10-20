<?php

namespace App\Http\Controllers\v1;

use App\Models\CollectAcount;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;

// use Zttp\Zttp;
use Log;
use GuzzleHttp\Client;

/**
 * @desc 采集账号
 * @author wangxiang
 * @Class CollectAcountController
 * @package App\Http\Controllers\v1
 */
class CollectAcountController extends Controller
{
    protected $request;
    protected $client;
    protected $auth;

    protected $limit = 10;
    protected $page = 1;

    // protected $jwt;

    public function __construct(Request $request)
    {
        $this->request = $request;
        $this->client = new Client();
        $this->auth = new AuthController($this->request);
    }


    /**
     * @desc 获取列表数据
     * @author wangxiang
     * @return JsonResponse
     */
    public function index()
    {
        try {
            $inUse = isset($this->request['in_use']) ? intval($this->request['in_use']) : null;
            $keyword = $this->request['keyword'] ?? '';
            $limit = $this->request['limit'] ?? $this->limit;
            $collectAcount = DB::table('collect_acount');
            if (!is_null($inUse)) {
                $inUse = $inUse ? 1 : 0;
                $collectAcount = $collectAcount->where(['in_use' => $inUse]);
            }
            if ($keyword) {
                $collectAcount = $collectAcount->where('douyin_nickname', 'like', '%' . $keyword . '%');
            }
            $collectAcount = $collectAcount->paginate($limit)->toArray();
            $list = [];
            foreach ($collectAcount['data'] as $arr) {
                $arr->kewords =CollectAcount::getKeywords($arr->id);
                $list[] = $arr;
            }
            return new JsonResponse([
                'code' => 200,
                'msg' => 'success',
                'data' => ['list' => $list, 'total' => $collectAcount['total']]
            ]);
        } catch (HttpResponseException $e) {
            Log::info('getTaskList:' . json_encode(['code' => $e->getCode(), 'msg' => $e->getMessage()]));
            return new JsonResponse([
                'code' => $e->getCode(),
                'msg' => '网络出现异常，请重试',
                'data' => []
            ], 500);
        }
    }


    /**
     * @desc 提交新增或编辑数据
     * @author wangxiang
     * @return JsonResponse
     */
    public function post()
    {
        try {
            $id = intval($this->request['id'] ?? 0);
            $tiktokUid = trim($this->request['tiktok_uid']);
            $tiktokId = trim($this->request['tiktok_id']);
            $douyinNickname = trim($this->request['douyin_nickname'] ?? '');
            $remark = trim($this->request['remark'] ?? '');
            $this->validate($this->request, [
                'tiktok_uid' => 'required',
                'tiktok_id' => 'required',
            ]);
        } catch (ValidationException $e) {
            return new JsonResponse([
                'code' => $e->getCode(),
                'msg' => $e->getMessage(),
                'data' => []
            ], 400);
        }

        try {
            $data = [
                'douyin_nickname' => $douyinNickname,
                'remark' => $remark,
                'tiktok_uid' => $tiktokUid,
                'tiktok_id' => $tiktokId,
            ];
            if (!$id) {   # 插入
                $response = CollectAcount::insertData($data, $this->request);
            } else {    # 更新
                $response = CollectAcount::updateData($data, $this->request, $id);
            }
            return new JsonResponse($response);
        } catch (HttpResponseException $e) {
            Log::info('getTaskList:' . json_encode(['code' => $e->getCode(), 'msg' => $e->getMessage()]));
            return new JsonResponse([
                'code' => $e->getCode(),
                'msg' => '网络出现异常，请重试',
                'data' => []
            ], 500);
        }
    }


    /**
     * @desc 逻辑删除
     * @author wangxiang
     * @return JsonResponse
     */
    public function delete()
    {
        try {
            $this->validate($this->request, [
                'id' => 'required'
            ]);
            # 判断id是否存在
            $id = intval($this->request['id'] ?? 0);
            $collectAcount = CollectAcount::find($id);
            if (!$collectAcount) {
                return new JsonResponse([
                    'code' => 400,
                    'msg' => 'id不存在',
                    'data' => []
                ], 400);
            }
            $state = DB::table('collect_acount')->where(['id' => $id])->update(['in_use' => 0]);
            if ($state) {
                return new JsonResponse([
                    'code' => 200,
                    'msg' => '删除成功',
                    'data' => $collectAcount
                ]);
            } else {
                return new JsonResponse([
                    'code' => 500,
                    'msg' => 'fail',
                    'data' => $collectAcount
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
     * @desc 获取单个信息
     * @author wangxiang
     * @return JsonResponse
     */
    public function view()
    {
        try {
            $this->validate($this->request, [
                'id' => 'required'
            ]);
            # 判断id是否存在
            $id = intval($this->request['id'] ?? 0);
            $collectAcount = CollectAcount::find($id)->toArray();
            if (!$collectAcount) {
                return new JsonResponse([
                    'code' => 400,
                    'msg' => 'id不存在',
                    'data' => []
                ], 400);
            }

            $collectAcount['kewords'] = CollectAcount::getKeywords($id);
            return new JsonResponse([
                'code' => 200,
                'msg' => 'success',
                'data' => $collectAcount
            ]);
        } catch (ValidationException $e) {
            return new JsonResponse([
                'code' => $e->getCode(),
                'msg' => $e->getMessage(),
                'data' => []
            ], 400);
        }
    }


    /**
     * @desc 获取采集账号关键词
     * @author wangxiang
     * @return JsonResponse
     */
    public function keywords()
    {
        try {
            $this->validate($this->request, [
                'id' => 'required'
            ]);
            # 判断id是否存在
            $id = intval($this->request['id'] ?? 0);
            $collectAcount = CollectAcount::find($id);
            if (!$collectAcount) {
                return new JsonResponse([
                    'code' => 400,
                    'msg' => 'id不存在',
                    'data' => []
                ], 400);
            }

            $list = DB::table('keywords')->select('name', 'id')->where(['in_use' => 1])->get();
            $kewords = DB::table('collect_keywords_connection')
                ->select('keywords.name', 'keywords.id')
                ->leftJoin('keywords', 'collect_keywords_connection.keywords_id', '=', 'keywords.id')
                ->where([['keywords.in_use', '=', 1], ['collect_keywords_connection.collect_acount_id', '=', $id]])
                ->get('keywords.name');

            return new JsonResponse([
                'code' => 200,
                'msg' => 'success',
                'data' => ['list' => $list, 'kewords' => $kewords]
            ]);
        } catch (ValidationException $e) {
            return new JsonResponse([
                'code' => $e->getCode(),
                'msg' => $e->getMessage(),
                'data' => []
            ], 400);
        }
    }


    /**
     * @desc 采集账号关键词编辑
     * @author wangxiang
     * @return JsonResponse
     */
    public function keywordUpdate()
    {
        try {
            $this->validate($this->request, [
                'id' => 'required',
            ]);
            # 判断id是否存在
            $id = intval($this->request['id'] ?? 0);
            $collectAcount = CollectAcount::find($id);
            if (!$collectAcount) {
                return new JsonResponse([
                    'code' => 400,
                    'msg' => 'id不存在',
                    'data' => []
                ], 400);
            }
            $kewords = trim($this->request['kewords']);
            $response = CollectAcount::keywordUpdate($id, $kewords);
            return new JsonResponse($response, $response['code']);
        } catch (ValidationException $e) {
            return new JsonResponse([
                'code' => $e->getCode(),
                'msg' => $e->getMessage(),
                'data' => []
            ], 400);
        }
    }

}
