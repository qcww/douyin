<?php

namespace App\Http\Controllers\v1;

use App\Models\Label;
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
 * @desc 设置
 * @author wangxiang
 * @Class SettingController
 * @package App\Http\Controllers\v1
 */
class SettingController extends Controller
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
     * @desc 获取设置模板列表
     * @author wangxiang
     * @return JsonResponse
     */
    public function index()
    {
        $limit = intval($this->request['limit'] ?? $this->limit);
        $data = DB::table('model')
            ->where(['user_id' => $this->userId])
            ->paginate($limit)
            ->toArray();
        $list = [];
        foreach ($data['data'] as $arr) {
            $temp = explode('|', $arr->content);
            $arr->presence = $temp[0] ?? '';
            $arr->answer_one = $temp[1] ?? '';
            $arr->answer_two = $temp[2] ?? '';
            $list[] = $arr;
        }
        return new JsonResponse([
            'code' => 200,
            'msg' => 'success',
            'data' => ['list' => $list, 'total' => $data['total']]
        ]);
    }


    /**
     * @desc 提交或修改模板数据
     * @author wangxiang
     * @return JsonResponse]
     */
    public function post()
    {
        try {
            $this->validate($this->request, [
                'name' => 'required',
                'presence' => 'required',
            ]);
        } catch (ValidationException $e) {
            return new JsonResponse([
                'code' => $e->getCode(),
                'msg' => $e->getMessage(),
                'data' => []
            ], 400);
        }
        try {
            $name = trim($this->request['name']);
            $presence = trim($this->request['presence']);
            $answer_one = trim($this->request['answer_one'] ?? '');
            $answer_two = trim($this->request['answer_two'] ?? '');
            $content = $presence;
            if ($answer_one) {
                $content .= '|' . $answer_one;
            }
            if ($answer_two) {
                $content .= '|' . $answer_two;
            }
            $id = intval($this->request['id'] ?? 0);
            $data = [
                'name' => $name,
                'content' => $content,
                'user_id' => $this->userId,
            ];
            if ($id) {
                # 更新
                $state = DB::table('model')->where(['id' => $id, 'user_id' => $this->userId])->update($data);
                $msg = '编辑成功';
            } else {
                $state = DB::table('model')->insert($data);
                $msg = '新增成功';
            }
            if ($state) {
                return new JsonResponse([
                    'code' => 200,
                    'msg' => $msg,
                    'data' => []
                ]);
            } else {
                return new JsonResponse([
                    'code' => 500,
                    'msg' => 'fail',
                    'data' => []
                ], 500);
            }
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
     * @desc 将模板设为默认
     * @author wangxiang
     * @return JsonResponse
     */
    public function default()
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
        $data = DB::table('model')->where(['id' => $id, 'user_id' => $this->userId])->first();
        if (!$data) {
            return new JsonResponse([
                'code' => 400,
                'msg' => 'id不存在',
                'data' => []
            ], 400);
        }
        DB::transaction(function() use ($id) {
            DB::table('model')->where(['user_id' => $this->userId])->update(['is_default' => 0]);
            DB::table('model')->where(['user_id' => $this->userId, 'id' => $id])->update(['is_default' => 1]);
        });
        return new JsonResponse([
            'code' => 200,
            'msg' => '设置成功',
            'data' => []
        ]);
    }

    /**
     * @desc 删除模板
     * @author wangxiang
     * @return JsonResponse
     */
    public function delete()
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
        $data = DB::table('model')->where(['id' => $id, 'user_id' => $this->userId])->first();
        if (!$data) {
            return new JsonResponse([
                'code' => 400,
                'msg' => 'id不存在',
                'data' => []
            ], 400);
        }
        $state = DB::table('model')->where(['user_id' => $this->userId, 'id' => $id])->delete();
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
     * @desc 获取标签相关列表
     * @author wangxiang
     * @return JsonResponse
     */
    public function labelIndex()
    {
        $list = DB::table('user_label_connection')
            ->select('label.name', 'label.id', 'user_label_connection.state')
            ->leftJoin('label', 'user_label_connection.label_id', '=', 'label.id')
            ->where(['user_label_connection.user_id' => $this->userId])
            ->get();
        $label = [];
        foreach ($list as $arr) {
            if ($arr->state == 1) {
                $label[] = $arr;
            }
        }

        return new JsonResponse([
            'code' => 200,
            'msg' => 'success',
            'data' => ['list' => $list, 'label' => $label]
        ]);
    }


    /**
     * @desc 不打招呼标签编辑
     * @author wangxiang
     * @return JsonResponse
     */
    public function labelUpdate()
    {
        try {
            $this->validate($this->request, [
                'label_id' => 'required',
            ]);
        } catch (ValidationException $e) {
            return new JsonResponse([
                'code' => $e->getCode(),
                'msg' => $e->getMessage(),
                'data' => []
            ], 400);
        }
        $labelId = trim($this->request['label_id']);
        $labelNew = array_unique(array_filter(explode(',', $labelId)));
        $labelOld = DB::table('user_label_connection')
            ->select('label.id')
            ->leftJoin('label', 'user_label_connection.label_id', '=', 'label.id')
            ->where([['user_label_connection.user_id', '=', $this->userId], ['user_label_connection.state', '=', 1]])
            ->pluck('label.id')
            ->toArray();

        $response = Label::presenceUpdate($labelNew, $labelOld, $this->userId);
        return new JsonResponse($response, $response['code']);

    }


    /**
     * @desc 不打招呼标签设置删除
     * @author wangxiang
     * @return JsonResponse
     */
    public function labelRecycle()
    {
        try {
            $this->validate($this->request, [
                'label_id' => 'required',
            ]);
        } catch (ValidationException $e) {
            return new JsonResponse([
                'code' => $e->getCode(),
                'msg' => $e->getMessage(),
                'data' => []
            ], 400);
        }
        $id = intval($this->request['label_id']);
        $label = DB::table('user_label_connection')->where(['user_id' => $this->userId, 'label_id' => $id])->first();
        if (!$label) {
            return new JsonResponse([
                'code' => 400,
                'msg' => 'id不存在',
                'data' => []
            ], 400);
        }
        $state = DB::table('user_label_connection')->where(['user_id' => $this->userId, 'label_id' => $id])->update(['state' => 0]);
        if ($state) {
            return new JsonResponse([
                'code' => 200,
                'msg' => 'success',
                'data' => []
            ]);
        } else {
            return new JsonResponse([
                'code' => 500,
                'msg' => 'fail',
                'data' => []
            ], 500);
        }
    }


    /**
     * @desc 粉丝标签类型新增
     * @author wangxiang
     * @return JsonResponse
     */
    public function labelPost()
    {
        try {
            $this->validate($this->request, [
                'name' => 'required',
            ]);
        } catch (ValidationException $e) {
            return new JsonResponse([
                'code' => $e->getCode(),
                'msg' => $e->getMessage(),
                'data' => []
            ], 400);
        }

        # 判断标签名称是否存在标签表中
        DB::transaction(function() {
            $name = trim($this->request['name']);
            $label = DB::table('label')->where(['name' => $name])->first();
            if (!$label) {
                $lableId = DB::table('label')->insertGetId(['name' => $name]);
            } else {
                $lableId = $label->id;
            }
            # 判断该标签是否已添加
            $userLabelConnection = DB::table('user_label_connection')->where(['user_id' => $this->userId, 'label_id' => $lableId])->first();
            if (!$userLabelConnection) {
                # 该标签在客户关联表中不存在
                DB::table('user_label_connection')->insert(['user_id' => $this->userId, 'label_id' => $lableId, 'state' => 0]);
            }
        });
        return new JsonResponse([
            'code' => 200,
            'msg' => '设置成功',
            'data' => []
        ]);

    }


    /**
     * @desc 粉丝标签类型删除
     * @author wangxiang
     * @return JsonResponse
     */
    public function labelDelete()
    {
        try {
            $this->validate($this->request, [
                'label_id' => 'required',
            ]);
        } catch (ValidationException $e) {
            return new JsonResponse([
                'code' => $e->getCode(),
                'msg' => $e->getMessage(),
                'data' => []
            ], 400);
        }
        $id = intval($this->request['label_id']);
        $state = DB::table('user_label_connection')->where(['user_id' => $this->userId, 'label_id' => $id])->delete();
        if ($state) {
            $response = ['code' => 200, 'msg' => '删除成功', 'data' => []];
        } else {
            $response = ['code' => 500, 'msg' => '删除失败', 'data' => []];
        }
        return new JsonResponse($response, $response['code']);

    }


    /**
     * @desc 打招呼重置
     * @author wangxiang
     * @return JsonResponse
     */
    public function reset()
    {
        DB::beginTransaction();
        try {
            #  获取禁用用户下面的粉丝抖音id集合
            $tiktokIdData = DB::table('fans')
                ->select('fans.tiktok_id')
                ->where(['v.state' => 1])
                ->join('video_user as v', 'fans.collect_id', '=', 'v.id')
                ->pluck('fans.tiktok_id')
                ->toArray();

            DB::table('user')->where(['id' => $this->userId])->increment('reset_time');
            # 获取当前用户对应的关键词
            $keywords = User::getKeywords($this->userId);
            $keywordsFansSet = [];


            if ($keywords) {
                $accountType = $this->request['userInfo']['type']?'_'.$this->request['userInfo']['type']:'';
                foreach ($keywords as $w) {
                    $keywordsFansSet[] = 'keywords_fans_set'.$accountType.':' . $w;
                }
            }
            if ($keywordsFansSet) {
                Redis::SUNIONSTORE('keywords_ask_set:' . $this->userId, $keywordsFansSet);
            }
            # 操作当前客户的打招呼粉丝集合, 将禁用用户下面的粉丝移除
            if ($tiktokIdData) {
                Redis::SREM('keywords_ask_set:' . $this->userId, $tiktokIdData);
            }
            DB::commit();
            $response = ['code' => 200, 'msg' => '重置成功', 'data' => []];
        } catch (\Exception $exception) {
            DB::rollback();
            $response = ['code' => 500, 'msg' => $exception, 'data' => []];
        }
        return new JsonResponse($response, $response['code']);
    }


    /**
     * @desc 获取基本信息
     * @author wangxiang
     * @return JsonResponse
     */
    public function info()
    {
        $user = DB::table('user')->where(['id' => $this->userId])->select('user_name', 'end_time', 'reset_time')->first();
        $kewords = DB::table('word_connection')
            ->select('keywords.name')
            ->leftJoin('keywords', 'word_connection.word_id', '=', 'keywords.id')
            ->where([['keywords.in_use', '=', 1], ['word_connection.user_id', '=', $this->userId]])
            ->pluck('keywords.name')
            ->toArray();
        $user->keywords = $kewords;
        return new JsonResponse([
            'code' => 200,
            'msg' => 'success',
            'data' => $user
        ]);
    }

}
