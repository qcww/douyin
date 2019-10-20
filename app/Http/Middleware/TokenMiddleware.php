<?php
/**
 * Created on 2019/5/16 9:50
 * Created by wangxiang
 */

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Http\Request;
use App\Http\Controllers\v1\AuthController;
use Illuminate\Support\Facades\Redis;

class TokenMiddleware
{
    public $router = [
        'api/admin',
        'api/user/getUser',
        'api/user/saveUser',
        'api/user/logIt',
        'api/getScrapUser',
        'api/accountKeywords',
        'api/scrapList',
        'api/doGetComp',
        'api/keywordSearch',
        'api/addCommentUser',
        'api/fansHome',
        'api/addFans',
        'api/fansToSet',
        'api/task',
        'api/notice',
        'api/sendNotice',
        'api/addFansAll',
        'api/nolocationfans',
        'api/savelocation',
        'api/getvideolist',
        'api/updatevideolist',
        'api/getscraplist',
        'api/scraplistcannel',
        'api/fans/addMyFans',
        'api/likes/addLikes',
        'api/comment/addComment',
        'api/getMyFocusList',
        'api/compMyFocusFans',
        'api/getMyZanList',
        'api/compMyZanFans',
        'api/getnolocationfans',
        'api/savelocations',
        'api/fans/fansLocationList',
        'api/fans/customCity',
        'api/fans/addKSVideos',
        'api/fans/addKSFans',
        'api/fans/getKSVideo',
        'api/fans/compKSVideo',
        'api/fansSqlToRedis',
    ]; // 不需要token验证的方法
    public $adminRouter = ['keywords', 'keyword', 'users', 'user', 'collectAcount', 'collectAcounts', 'accounts'];    //限制管理员只能进入的控制器
    public $request;
    public $pattern = '/^Bearer\s+(.*?)$/';
    public $header = 'authorization';
    public $authHeader = '';
    public $auth = '';
    public $tokenInfo = []; // token解析过后的信息

    public function __construct()
    {
        $this->request = new Request();
        $this->auth = new AuthController();
    }

    public function handle($request, Closure $next)
    {
        // token拦截
        $request['tokenInfo'] = $this->tokenInfo;
        //$router = explode("?", $request->getRequestUri())[0];
        $router = $request->path();
        $controller = explode('/', $router)[1];
        if (in_array($router, $this->router)) {
            return $next($request);
        }
        try {
            if ($this->authenticate($request->headers->get($this->header))) {
                if (in_array($controller, $this->adminRouter) && $this->tokenInfo['is_admin'] == 0) {
                    return new JsonResponse([
                        'code' => 403,
                        'msg' => '无权限访问',
                        'data' => []
                    ], 403);
                }
                # 判断登录状态
                $loginStatus = Redis::GET('login:' . $this->tokenInfo['id'] . ':status');
                if (!$loginStatus) {
                    return new JsonResponse([
                        'code' => 401,
                        'msg' => '登录超时,请重新登录',
                        'data' => []
                    ], 401);
                }
                $request['userInfo'] = $this->tokenInfo;
                return $next($request);
            } else {
                return new JsonResponse([
                    'code' => 401,
                    'msg' => '登录超时,请重新登录',
                    'data' => []
                ], 401);
            }
        } catch (\Exception $exception) {
            return new JsonResponse([
                'code' => 401,
                'msg' => '登录超时,请重新登录',
                'data' => []
            ], 401);
        }

    }


    /**
     * @desc 验证header头
     * @author wangxiang
     * @param $authHeader
     * @return bool
     */
    protected function authenticate($authHeader)
    {
        if ($authHeader) {
            if ($this->pattern !== null) {
                if (!preg_match($this->pattern, $authHeader, $matches)) {
                    return false;
                }
                $authHeader = $matches[1];
                $verifyToken = $this->auth->verifyToken($authHeader);
                if (!$verifyToken) {
                    return false;
                }
                $this->tokenInfo = $verifyToken;
            }
            $this->authHeader = $authHeader;
            return true;
        }
        return false;
    }

}