<?php

namespace App\Http\Controllers\v1;

use App\Models\Keywords;
use App\Models\UserAskAcount;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;
use Log;
use GuzzleHttp\Client;

/**
 * @desc 打招呼账号
 * @author wangxiang
 * @Class VideoController
 * @package App\Http\Controllers\v1
 */
class UserAskAcountController extends Controller
{
    protected $request;
    protected $client;
    protected $auth;

    protected $limit = 10;
    protected $page = 1;
    protected $userId;  //当前登录者的id

    public function __construct(Request $request)
    {
        $this->request = $request;
        $this->client = new Client();
        $this->auth = new AuthController($this->request);
        $this->userId = $this->request['userInfo']['id'];
    }


    /**
     * @desc 获取列表数据
     * @author wangxiang
     * @return JsonResponse
     */
    public function index()
    {
        $keyword = trim($this->request['keyword'] ?? '');
        $inUse = isset($this->request['in_use']) ? intval($this->request['in_use']) : null;
        $limit = intval($this->request['limit'] ?? $this->limit);
        $data = DB::table('user_ask_acount')->where(['user_id' => $this->userId,'type'=> 0]);
        if ($keyword) {
            $data = $data->where('nickname', 'like', '%' . $keyword . '%');
        }
        if (!is_null($inUse)) {
            $inUse = $inUse ? 1 : 0;
            $data = $data->where(['in_use' => $inUse]);
        }
        $list = [];
        $data = $data
            ->select('id', 'tiktok_uid', 'tiktok_id', 'nickname', 'ask_time', 'remark', 'time_range', 'ask_model', 'action')
            ->paginate($limit)
            ->toArray();

        foreach ($data['data'] as $arr) {
            $arr->num = count(array_unique(array_filter(explode(',', $arr->time_range)))) * $arr->ask_time;
            $list[] = $arr;
        }

        return new JsonResponse([
            'code' => 200,
            'msg' => 'success',
            'data' => ['list' => $list, 'total' => $data['total']]
        ]);
    }

    /**
     * @desc 获取模板下拉数据
     * @author wangxiang
     * @return JsonResponse
     */
    public function model()
    {
        $model = DB::table('model')
            ->select('id', 'name')
            ->where(['user_id' => $this->userId])
            ->get();
        return new JsonResponse([
            'code' => 200,
            'msg' => 'success',
            'data' => $model
        ]);
    }


    /**
     * @desc 数据提交
     * @author wangxiang
     * @return JsonResponse
     */
    public function post()
    {
        try {
            $id = intval($this->request['id'] ?? 0);
            $tiktokUid = trim($this->request['tiktok_uid']) ?? '';
            $tiktokId = trim($this->request['tiktok_id']);
            $nickname = trim($this->request['nickname'] ?? '');
            $remark = trim($this->request['remark'] ?? '');
            $this->validate($this->request, [
                //'tiktok_uid' => 'required',
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
                'nickname' => $nickname,
                'remark' => $remark,
                'tiktok_uid' => $tiktokUid,
                'tiktok_id' => $tiktokId,
            ];
            if (!$id) {   # 插入
                $data['user_id'] = $this->userId;
                $response = UserAskAcount::insertData($data, $this->request);
            } else {    # 更新
                $response = UserAskAcount::updateData($data, $this->request, $id);
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
     * @desc 删除
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
        $userAskAcount = DB::table('user_ask_acount')->where(['id' => $id])->first();
        if (!$userAskAcount) {
            return new JsonResponse([
                'code' => 400,
                'msg' => 'id不存在',
                'data' => []
            ], 400);
        }
        $state = DB::table('user_ask_acount')->where(['id' => $id])->update(['in_use' =>0]);
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
     * @desc 打招呼设置
     * @author wangxiang
     * @return JsonResponse
     */
    public function greetSet()
    {
        try {
            $id = trim($this->request['id'] ?? 0);
            $timeRange = trim($this->request['time_range']);
            $askTime = intval($this->request['ask_time'] ?? 0);
            $askModel = intval($this->request['ask_model'] ?? 0);
            $action = intval($this->request['action'] ?? 1);
            if ($action == 1) {
                $this->validate($this->request, [
                    'id' => 'required',
                    'time_range' => 'required',
                ]);
            } else {
                $this->validate($this->request, [
                    'id' => 'required',
                ]);
            }

        } catch (ValidationException $e) {
            return new JsonResponse([
                'code' => $e->getCode(),
                'msg' => $e->getMessage(),
                'data' => []
            ], 400);
        }
        $idArr = array_unique(array_filter(explode(',', $id)));
        $userAskAcount = DB::table('user_ask_acount')->whereIn('id', $idArr)->first();
        if (!$userAskAcount) {
            return new JsonResponse([
                'code' => 400,
                'msg' => 'id不存在',
                'data' => []
            ], 400);
        }
        if ($action == 1) {
            $data = [
                'action' => $action,
                'time_range' => $timeRange,
                'ask_time' => $askTime,
                'ask_model' => $askModel,
            ];
        } else {
            $data = ['action' => $action];
        }

        $state = DB::table('user_ask_acount')->whereIn('id', $idArr)->update($data);
        if ($state) {
            return new JsonResponse([
                'code' => 200,
                'msg' => '设置成功',
                'data' => []
            ]);
        } else {
            return new JsonResponse([
                'code' => 500,
                'msg' => '设置失败',
                'data' => []
            ], 500);
        }

    }

}
