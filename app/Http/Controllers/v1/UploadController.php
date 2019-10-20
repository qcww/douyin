<?php

namespace App\Http\Controllers\v1;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use GuzzleHttp\Client;
use Symfony\Component\HttpKernel\Exception\HttpException;


/**
 * @desc 上传
 * @author wangxiang
 * @Class UserController
 * @package App\Http\Controllers\v1
 */
class UploadController extends Controller
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
     * @desc 上传图片
     * @author wangxiang
     * @time 2019/7/5 13:46
     * @return JsonResponse
     */
    public function index()
    {
        try {
            $this->validate($this->request, [
                'thumb' => 'required|image'
            ]);
        } catch (ValidationException $e) {
            throw new HttpException(400, '文件格式不正确');
        }
        $file = $this->request['thumb'] ?? null;

        if (!$file) {
            throw new HttpException(400, '上传失败');
        }
        if (!$file->isValid()) {
            throw new HttpException(400, '上传失败');
        }
        if ($file->getSize() > 100 * 1024) {
            throw new HttpException(400, '请上传低于100kb的图片');
        }
        $path = 'upload/operate/';
        $fileName = $this->userId . date('YmdHis') . '.' . $file->getClientOriginalExtension();
        if (!$file->move($path, $fileName)) {
            throw new HttpException(400, '上传失败');
        }

        return new JsonResponse([
            'code' => 200,
            'msg' => 'success',
            'data' => ['path' => $path . $fileName]
        ]);
    }

}
