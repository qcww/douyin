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
use Tymon\JWTAuth\JWTAuth;


/**
 * @desc 用户登录相关
 * @author wangxiang
 * @Class UserController
 * @package App\Http\Controllers\v1
 */
class AdminController extends Controller
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
     * @desc 用户登录
     * @author wangxiang
     * @return JsonResponse
     */
    public function login()
    {
        try {
            $this->validate($this->request, [
                'user_name' => 'required',
                'password' => 'required',
            ]);
        } catch (ValidationException $e) {
            return new JsonResponse([
                'code' => $e->getCode(),
                'msg' => $e->getMessage(),
                'data' => []
            ], 400);
        }
        try {
            $username = trim($this->request['user_name']);
            $password = trim($this->request['password']);
            # 判断用户是否存在
            $user = DB::table('user')->where(['user_name' => $username])->first();
            if (!$user) {
                return new JsonResponse([
                    'code' => 400,
                    'msg' => '账号或密码不正确!',
                    'data' => []
                ], 400);
            }
            # 验证密码
            if (!password_verify($password, $user->password)) {
                return new JsonResponse([
                    'code' => 400,
                    'msg' => '账号或密码不正确!',
                    'data' => []
                ], 400);
            }
            # 验证账号是否被禁用
            if (!$user->in_use) {
                return new JsonResponse([
                    'code' => 400,
                    'msg' => '该账号已被禁用, 请联系管理员!',
                    'data' => []
                ], 400);
            }
            # 验证合同是否到期
            if ($user->end_time < date('Y-m-d')) {
                return new JsonResponse([
                    'code' => 400,
                    'msg' => '该账户合同已到期,请联系客服!',
                    'data' => []
                ], 400);
            }

            # 登录成功,处理业务逻辑
            $payload = [
                'id' => $user->id,
                'is_admin' => $user->is_admin,
                'user_name' => $user->user_name,
                'type' => $user->type,
                'location' => $user->location
            ];
            Redis::SET('login:' . $user->id . ':status', 1);
            $token = $this->auth->getToken($payload);
            $verify_token = $this->auth->verifyToken($token);
            $config = $this->auth->getJWTConfig();
            if ($verify_token) {
                return new JsonResponse([
                    'code' => 200,
                    'msg' => '登录成功!',
                    'data' => ['user_info' => $user, 'token' => $token, 'expire' => $config['exp']]
                ]);
            } else {
                return new JsonResponse([
                    'code' => 500,
                    'msg' => '登录失败!',
                    'data' => $user
                ], 500);
            }
        } catch (HttpResponseException $e) {
            Log::info('getProfile:' . json_encode(['code' => $e->getCode(), 'msg' => $e->getMessage()]));
            return new JsonResponse([
                'code' => $e->getCode(),
                'msg' => '网络出现异常，请重试',
                'data' => new \StdClass
            ], 400);
        }
    }


    /**
     * @desc 修改用户密码
     * @author wangxiang
     * @return JsonResponse
     */
    public function changePassword()
    {
        try {
            $this->validate($this->request, [
                'old_password' => 'required',
                'password' => 'required',
                're_password' => 'required',
            ]);
        } catch (ValidationException $e) {
            return new JsonResponse([
                'code' => $e->getCode(),
                'msg' => $e->getMessage(),
                'data' => []
            ], 400);
        }
        try {
            $oldPassword = trim($this->request['old_password']);
            $password = trim($this->request['password']);
            $rePassword = trim($this->request['re_password']);
            if ($password != $rePassword) {
                return new JsonResponse([
                    'code' => 400,
                    'msg' => '两次密码不一致',
                    'data' => []
                ], 400);
            }
            if ($oldPassword == $password) {
                return new JsonResponse([
                    'code' => 400,
                    'msg' => '新密码不能和原密码相同',
                    'data' => []
                ], 400);
            }
            $user = DB::table('user')->where(['id' => $this->userId])->first();
            if (!$user) {
                return new JsonResponse([
                    'code' => 400,
                    'msg' => '用户不存在',
                    'data' => []
                ], 400);
            }
            if (!password_verify($oldPassword, $user->password)) {
                return new JsonResponse([
                    'code' => 400,
                    'msg' => '原密码不正确',
                    'data' => []
                ], 400);
            }
            $state = DB::table('user')->where(['id' => $this->userId])->update(['password' => password_hash($password,PASSWORD_BCRYPT, ['cost' => 10])]);
            if ($state) {
                return new JsonResponse([
                    'code' => 200,
                    'msg' => '修改成功',
                    'data' => []
                ]);
            } else {
                return new JsonResponse([
                    'code' => 500,
                    'msg' => '修改失败',
                    'data' => []
                ], 500);
            }
        } catch (HttpResponseException $e) {
            Log::info('getProfile:' . json_encode(['code' => $e->getCode(), 'msg' => $e->getMessage()]));
            return new JsonResponse([
                'code' => $e->getCode(),
                'msg' => '网络出现异常，请重试',
                'data' => new \StdClass
            ], 400);
        }
    }


    public function logout()
    {
        $loginStatus = Redis::SET('login:' . $this->userId . ':status', 0);
        if ($loginStatus) {
            return new JsonResponse([
                'code' => 200,
                'msg' => '退出成功',
                'data' => []
            ]);
        } else {
            return new JsonResponse([
                'code' => 500,
                'msg' => '退出失败',
                'data' => []
            ], 500);
        }
    }

    /**
     * @desc 解析token
     * @author wangxiang
     * @return JsonResponse
     */
    public function info()
    {
        return new JsonResponse([
            'code' => 200,
            'msg' => 'success',
            'data' => $this->request['tokenInfo']
        ]);
    }


    /**
     * @desc 刷新token
     * @author wangxiang
     * @return JsonResponse
     */
    public function refresh()
    {
        $token = $this->request->headers->get('authorization');
        $token = $this->auth->refresh($token);
        if ($token) {
            return new JsonResponse([
                'code' => 200,
                'msg' => '刷新成功',
                'data' => ['token' => $token]
            ]);
        } else {
            return new JsonResponse([
                'code' => 500,
                'msg' => '刷新失败',
                'data' => []
            ], 500);
        }

    }

}
