<?php

namespace App\Http\Controllers\Auth;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\JsonResponse;
use Tymon\JWTAuth\JWTAuth;
// use Tymon\JWTAuth\Facades\JWTAuth;
use App\Http\Controllers\Controller;
use Tymon\JWTAuth\Exceptions\JWTException;
use Illuminate\Validation\ValidationException;
use Illuminate\Http\Exception\HttpResponseException;

class AuthController_bak extends Controller
{
    /**
     * @var \Tymon\JWTAuth\JWTAuth
     */
    protected $jwt;
    
    public function __construct(JWTAuth $jwt)
    {
            $this->jwt = $jwt;
    }

    /**
     * Handle a login request to the application.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return \Illuminate\Http\Response
     */
    public function postLogin(Request $request)
    {
        try {
            // $input = $request->intersect(['password']); // 获取请求中实际存在的输入数据
            // $all = $request->all(); // 获取所有
            // var_dump($all);
            // echo $request->input('email');
            $this->validate($request, [
                'email' => 'required|email|max:255',
                'password' => 'required',
            ]);
        } catch (ValidationException $e) {
            return $e->getResponse();
        }

        try {
            // Attempt to verify the credentials and create a token for the user
            // if (!$token = $this->jwt->attempt(
            //     $this->getCredentials($request)
            // )) {
            //     return $this->onUnauthorized();
            // }
            if (!$token = $this->jwt->attempt(
                $this->getCredentials($request)
            )) {
                return $this->onUnauthorized();
            }
        } catch (JWTException $e) {
            // Something went wrong whilst attempting to encode the token
            return $this->onJwtGenerationError();
        }

        // All good so return the token
        return $this->onAuthorized($token);
    }

    /**
     * What response should be returned on invalid credentials.
     *
     * @return JsonResponse
     */
    protected function onUnauthorized()
    {
        return new JsonResponse([
            'code' => 401,
            'msg' => 'invalid_credentials',
            'data' => []
        ], Response::HTTP_UNAUTHORIZED);
    }

    /**
     * What response should be returned on error while generate JWT.
     *
     * @return JsonResponse
     */
    protected function onJwtGenerationError()
    {
        return new JsonResponse([
            'code' => 401,
            'msg' => 'could_not_create_token',
            'data' => []
        ], Response::HTTP_INTERNAL_SERVER_ERROR);
    }

    /**
     * What response should be returned on authorized.
     *
     * @return JsonResponse
     */
    protected function onAuthorized($token)
    {
        return new JsonResponse([
            'code'=> 200,
            'msg' => 'token_generated',
            'data' => [
                'token' => $token,
            ]
        ]);
    }

    /**
     * Get the needed authorization credentials from the request.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return array
     */
    protected function getCredentials(Request $request)
    {
        return $request->only('email', 'password');
    }

    /**
     * Invalidate a token.
     *
     * @return \Illuminate\Http\Response
     */
    public function deleteInvalidate()
    {
        $token = $this->jwt->parseToken();

        $token->invalidate();

        return new JsonResponse([
            'code' => 200,
            'msg' => 'token_invalidated',
            'data' => []
        ]);
    }

    /**
     * Refresh a token.
     *
     * @return \Illuminate\Http\Response
     */
    public function patchRefresh()
    {
        $token = $this->jwt->parseToken();

        $newToken = $token->refresh();

        return new JsonResponse([
            'code' => 200,
            'msg' => 'token_refreshed',
            'data' => [
                'token' => $newToken
            ]
        ]);
    }

    /**
     * Get authenticated user.
     *
     * @return \Illuminate\Http\Response
     */
    public function getUser()
    {
        return 'KO';
        return new JsonResponse([
            'code' => 200,
            'msg' => 'authenticated_user',
            'data' => $this->jwt->parseToken()->authenticate()
        ]);
    }
}
