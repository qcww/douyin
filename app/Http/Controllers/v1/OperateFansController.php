<?php

namespace App\Http\Controllers\v1;

use App\Models\User;
use App\Services\Ding;
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
use Symfony\Component\HttpKernel\Exception\HttpException;


/**
 * @desc 客户
 * @author wangxiang
 * @Class UserController
 * @package App\Http\Controllers\v1
 */
class OperateFansController extends Controller
{
    protected $request;
    protected $client;
    protected $auth;
    protected $userId;  //当前登录者的id

    protected $limit = 10;
    protected $page = 1;

    public function __construct(Request $request)
    {
        $this->request = $request;
        $this->client = new Client();
        $this->auth = new AuthController($this->request);
        $this->userId = $this->request['userInfo']['id'];
    }


    /**
     * @desc 获取代运营粉丝列表
     * @author wangxiang
     * @time 2019/7/5 11:05
     * @return JsonResponse
     */
    public function index()
    {
        $limit = $this->request['limit'] ?? $this->limit;
        $tiktokId = trim($this->request['tiktok_id'] ?? '');
        $query = DB::table('operate_fans')
            ->where(['add_user_id' => $this->userId])
            ->select(DB::raw("FROM_UNIXTIME(created_at, '%Y-%m-%d %H:%i:%s') created_at"), 'name', 'contact', 'status', 'pic', 'tiktok_id', 'id');
        if ($tiktokId) {
            $query = $query->where('tiktok_id', 'like', '%' . $tiktokId . '%');
        }
        $model = $query->paginate($limit)->toArray();

        return new JsonResponse([
            'code' => 200,
            'msg' => 'success',
            'data' => ['list' => $model['data'], 'total' => $model['total']]
        ]);
    }


    /**
     * @desc 编辑或增加
     * @author wangxiang
     * @return JsonResponse
     */
    public function post()
    {
        try {
            $id = intval($this->request['id'] ?? 0);
            $this->validate($this->request, [
                'name' => 'required',
                'tiktok_id' => 'required',
                'contact' => 'required',
                # 'pic' => 'required',
            ]);
            $data = [
                'name' => trim($this->request['name']),
                'contact' => trim($this->request['contact']),
                'tiktok_id' => trim($this->request['tiktok_id']),
                'pic' => trim($this->request['pic'] ?? ''),
            ];

            if (!$id) {
                # 新增
                // 判断当天添加者是否已存在需要添加的抖音粉丝
                $isUser = DB::table('operate_fans')->where(['add_user_id' => $this->userId, 'tiktok_id' => $data['tiktok_id']])->first();
                if ($isUser) {
                    throw new HttpException(400, '添加失败， 该抖音粉丝已存在');
                }
                $data['created_at'] = time();
                $data['add_user_id'] = $this->userId;
                $state = DB::table('operate_fans')->insert($data);
            } else {
                # 修改
                // 判断当天添加者是否已存在需要添加的抖音粉丝
                $isUser = DB::table('operate_fans')->where(['add_user_id' => $this->userId, 'tiktok_id' => $data['tiktok_id']])->where('id', '!=', $id)->first();
                if ($isUser) {
                    throw new HttpException(400, '修改失败， 该抖音粉丝已存在');
                }
                $state = DB::table('operate_fans')->where(['id' => $id])->update($data);
            }
            if ($state) {
                if (!$id) {
                    try {
                        $user = DB::table('user')->select('dd_token')->where(['id' => $this->userId])->first();
                        if ($user && $user->dd_token) {
                            $webHook = 'https://oapi.dingtalk.com/robot/send?access_token=' . $user->dd_token;
                            $templateText = "### ".date('y/m/d H:i',time()) . PHP_EOL;
                            $templateText .= PHP_EOL . '姓名： ' . $data['name'] . PHP_EOL;
                            $templateText .= PHP_EOL . '抖音号： ' . $data['tiktok_id'] . PHP_EOL;
                            $templateText .= PHP_EOL . '联系方式： ' . $data['contact'] . PHP_EOL;
                            Ding::ddRemindMarkdown($webHook, 'markdown', '添加代运营粉丝成功', $templateText);
                        }
                    } catch (\Exception $exception) {};
                }

                return new JsonResponse([
                    'code' => 200,
                    'msg' => '操作成功',
                    'data' => $data
                ]);
            } else {
                throw new HttpException(500, '操作失败');
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
            $model = DB::table('operate_fans')->where(['id' => $id])->first();
            if (!$model) {
                throw new HttpException(400, 'id不存在');
            }
            $state = DB::table('operate_fans')->where(['id' => $id])->delete();
            if ($state) {
                return new JsonResponse([
                    'code' => 200,
                    'msg' => '删除成功',
                    'data' => $model
                ]);
            } else {
                throw new HttpException(500, '删除失败');
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
     * @desc 修改处理状态
     * @author wangxiang
     * @time 2019/7/5 11:38
     * @return JsonResponse
     */
    public function changeState()
    {
        try {
            $this->validate($this->request, [
                'id' => 'required'
            ]);
            # 判断id是否存在
            $id = intval($this->request['id'] ?? 0);
            $model = DB::table('operate_fans')->where(['id' => $id])->first();
            if (!$model) {
                throw new HttpException(400, 'id不存在');
            }
            $state = DB::table('operate_fans')->where(['id' => $id])->update(['status' => 1]);
            if ($state) {
                return new JsonResponse([
                    'code' => 200,
                    'msg' => '操作成功',
                    'data' => $model
                ]);
            } else {
                throw new HttpException(500, '操作失败');
            }
        } catch (ValidationException $e) {
            return new JsonResponse([
                'code' => $e->getCode(),
                'msg' => $e->getMessage(),
                'data' => []
            ], 400);
        }
    }

}
