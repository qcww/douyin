<?php

namespace App\Http\Controllers\v1;

use App\Models\Keywords;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;
use Log;
use GuzzleHttp\Client;

/**
 * @desc 关键词
 * @author wangxiang
 * @Class KeywordsController
 * @package App\Http\Controllers\v1
 */
class KeywordsController extends Controller
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
     * @desc 获取关键词列表
     * @author wangxiang
     * @return JsonResponse
     */
    public function index()
    {
        $keyword = trim($this->request['keyword'] ?? '');
        $limit = intval($this->request['limit'] ?? $this->limit);
        $keywords = DB::table('keywords')->where(['keywords.in_use' => 1]);
        if ($keyword) {
            $keywords = $keywords->where('keywords.name', 'like', '%' . $keyword . '%');
        }
        $list = [];
        $data = $keywords
            ->select('keywords.id', 'keywords.name', 'keywords.create_time', 'keywords.colletc_fans', 'keywords.collect_latest', 'keywords.collect_limit')
            ->paginate($limit)
            ->toArray();
        foreach ($data['data'] as $arr) {
            $arr->user_num = Keywords::getUserNumBykeywords($arr->id);
            $arr->video_user_num = Keywords::getVideoUserNumBykeywords($arr->name);
            $arr->fans_num = Keywords::getFansNumBykeywords($arr->name);
            $arr->collect_acount_num = Keywords::getCollectAcountNumBykeywords($arr->id);
            $list[] = $arr;
        }
        return new JsonResponse([
            'code' => 200,
            'msg' => 'success',
            'data' => ['list' => $list, 'total' => $data['total']]
        ]);
    }


    /**
     * @desc 新增关键词
     * @author wangxiang
     * @return JsonResponse
     */
    public function post()
    {
        try {
            $id = intval($this->request['id'] ?? 0);
            $name = trim($this->request['name']);
            $collectLimit = intval($this->request['collect_limit']);
            $this->validate($this->request, [
                'name' => 'required',
                'collect_limit' => 'required',
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
                'name' => $name,
                'collect_limit' => $collectLimit,
                'create_time' => date('y-m-d'),
            ];
            $response = Keywords::insertData($data);
            /*if (!$id) {   # 插入
                $response = Keywords::insertData($data);
            } else {    # 更新
                $response = Keywords::updateData($data, $id);
            }*/
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
     * @desc 删除关键词
     * @author wangxiang
     * @return JsonResponse
     */
    public function delete()
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
        $keywords = DB::table('keywords')->where(['id' => $id])->first();
        if (!$keywords) {
            return new JsonResponse([
                'code' => 400,
                'msg' => '关键词不存在',
                'data' => []
            ], 400);
        }
        $state = DB::table('keywords')->where(['id' => $id])->update(['in_use' => 0]);
        if ($state) {
            return new JsonResponse([
                'code' => 200,
                'msg' => '删除成功',
                'data' => []
            ]);
        } else {
            return new JsonResponse([
                'code' => 500,
                'msg' => '删除失败',
                'data' => []
            ], 500);
        }
    }

    /**
     * @desc 获取关键词下拉数据
     * @author wangxiang
     * @return JsonResponse
     */
    public function select()
    {
        $keywords = DB::table('keywords')->select('id', 'name')->where(['in_use' => 1])->get();
        return new JsonResponse([
            'code' => 200,
            'msg' => 'success',
            'data' => $keywords
        ]);
    }

    public function resetKeyword(Request $request)
    {
        $id = (int)$request->input('id');
        if (!$id) {
            return new JsonResponse([
                'code' => 500,
                'msg' => '请传递正确数值'
            ]);
        }
        $res = DB::table('video_user')->where('keyword_id',$id)->update(['collection' => 0,'run_tiktok_uid' => '']);
        return new JsonResponse([
            'code' => 200,
            'msg' => $res?'操作成功':'操作失败'
        ]);
    }


}
