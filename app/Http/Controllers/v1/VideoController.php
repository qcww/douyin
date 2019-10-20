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
 * @desc 视频
 * @author wangxiang
 * @Class VideoController
 * @package App\Http\Controllers\v1
 */
class VideoController extends Controller
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
     * @desc 获取视频列表
     * @author wangxiang
     * @return JsonResponse
     */
    public function index()
    {
        $keyword = trim($this->request['keyword'] ?? '');
        $limit = intval($this->request['limit'] ?? $this->limit);
        $keyId = intval($this->request['key_id'] ?? 0);
        $keyName = trim($this->request['key_id'] ?? ''); //调整为传关键词名称匹配搜索
        $data = DB::table('video');
        if ($keyword) {
            $data = $data->where('video_name', 'like', '%' . $keyword . '%');
        }
       /* if ($keyId) {
            $data = $data->where(['keyword_id' => $keyId]);
        }*/
        if ($keyName && $keyName != '全部') {
            $data = $data->where(['keyword' => $keyName]);
        }
        $keywords = DB::table('word_connection')
            ->select('k.id', 'k.name')
            ->where(['k.in_use' => 1])
            ->where(['word_connection.user_id' => $this->userId])
            ->join('keywords as k', 'k.id', '=', 'word_connection.word_id')
            ->get();
        $myKeywordsId = [];
        foreach ($keywords as $arr) {
            $myKeywordsId[] = $arr->name;
        }

        $data = $data
            ->select('id', 'video_id', 'video_name', 'great_num', 'comment_num', 'video_user', 'video_user_id', 'keyword')
            ->whereIn('keyword', $myKeywordsId)
            ->paginate($limit)
            ->toArray();
        $list = $data['data'];


        //$keywords = DB::table('keywords')->select('id', 'name')->where(['in_use' => 1])->get();
        foreach ($keywords as &$arr) {
            # 处理返回的关键词列表,将id更改为关键词名称
            $arr->id = $arr->name;
        }

        return new JsonResponse([
            'code' => 200,
            'msg' => 'success',
            'data' => ['list' => $list, 'total' => $data['total'], 'keywords' => $keywords]
        ]);
    }
}
