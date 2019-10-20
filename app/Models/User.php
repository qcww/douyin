<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class User extends Model
{
    protected $table = 'collect_acount';


    /**
     * @desc 新增用户
     * @author wangxiang
     * @param $data
     * @param $request
     * @return array
     */
    public static function insertData($data, $request)
    {
        # 判断登录账号是否唯一
        $user = DB::table('user')->where(['user_name' => trim($data['user_name'])])->first();
        if ($user) {
            return [
                'code' => 400,
                'msg' => '用户已存在',
                'data' => $user
            ];
        } else {
            $data['is_admin'] =  trim($request['is_admin'] ?? 0);
            $data['in_use'] =  trim($request['in_use'] ?? 1);
            $data['create_time'] =  date('Y-m-d');

            # 根据传过来的关键词,判断关键词库中是否都存在
            $kewords = trim($request['keywords']);
            $kewordsArr = array_unique(array_filter(explode(',', $kewords)));   //需要新增的关键词
            $kewordsArrNew = [];    //新增的关键词并且关键词名称在库中
            $kewordsNewId = [];    //新增的关键词id集合
            $kewordsDara = DB::table('keywords')->pluck('name')->toArray();             //关键词库中存在的关键词
            $insertKeywords = [];   //需要插入关键词库中
            $insertWordsId = [];   //插入关键词到关键词库中返回的id集合
            foreach ($kewordsArr as $val) {
                if (!in_array(trim($val), $kewordsDara)) {
                    $insertKeywords[] = trim($val);
                } else {
                    $kewordsArrNew[] = trim($val);
                }
            }


            if ($kewordsArrNew) {
                $kewordsNewId = DB::table('keywords')->whereIn('name', $kewordsArrNew)->pluck('id')->toArray();
            }
            if ($insertKeywords) {
                DB::beginTransaction();
                try {
                    foreach ($insertKeywords as $val) {
                        $insertWordsId[] = DB::table('keywords')->insertGetId(['name' => $val, 'create_time' => date('Y-m-d')]);
                    }
                    DB::commit();
                } catch (\Exception $exception) {
                    DB::rollback();
                    return ['code' => 500, 'msg' => '编辑失败', 'data' => []];
                }
            }
            # 得到新的需要插入的关键词id集合
            $insertKeywordsId = array_values(array_unique(array_merge($insertWordsId, $kewordsNewId)));

            DB::transaction(function() use ($data, $insertKeywordsId, $kewordsArr) {
                #  获取禁用用户下面的粉丝抖音id集合
                $tiktokIdData = DB::table('fans')
                    ->select('fans.tiktok_id')
                    ->where(['v.state' => 1])
                    ->join('video_user as v', 'fans.collect_id', '=', 'v.id')
                    ->pluck('fans.tiktok_id')
                    ->toArray();

                $insertId = DB::table('user')->insertGetId($data);
                $inetrData = [];
                foreach ($insertKeywordsId as $key=> $v) {
                    $inetrData[$key] = [
                        'user_id' => $insertId,
                        'word_id' => intval($v)
                    ];
                }
                $keywordsFansSet = [];
                foreach ($kewordsArr as $w) {
                    $keywordsFansSet[] = 'keywords_fans_set:' . $w;
                }
                if ($keywordsFansSet) {
                    Redis::SUNIONSTORE('keywords_ask_set:' . $insertId, $keywordsFansSet);
                }
                # 操作当前客户的打招呼粉丝集合, 将禁用用户下面的粉丝移除
                if ($tiktokIdData) {
                    Redis::SREM('keywords_ask_set:' . $insertId, $tiktokIdData);
                }
                DB::table('word_connection')->insert($inetrData);
            });
            return [
                'code' => 200,
                'msg' => '新增成功',
                'data' => []
            ];
        }
    }


    /**
     * @desc 编辑用户
     * @author wangxiang
     * @param $data
     * @param $request
     * @param $userId
     * @return JsonResponse|array
     */
    public static function updateData($data, $request, $userId)
    {

        $user = DB::table('user')->where([['id', '!=', $userId], ['user_name', '=', trim($request['user_name'])]])->first();
        if ($user) {
            return [
                'code' => 400,
                'msg' => '用户已存在',
                'data' => $user
            ];
        }
        if (isset($request['is_admin'])) {
            $data['is_admin'] =  intval($request['is_admin']);
        }
        if (isset($request['in_use'])) {
            $data['in_use'] =  intval($request['in_use']);
        }
        $password = trim($request['password'] ?? '');
        if ($password) {
            $data['password'] = password_hash($password,PASSWORD_BCRYPT, ['cost' => 10]);
        }

        # 修改开始

        # 根据传过来的关键词,判断关键词库中是否都存在
        $kewords = trim($request['keywords']);
        $kewordsArr = array_unique(array_filter(explode(',', $kewords)));   //需要新增的关键词
        $kewordsArrNew = [];    //新增的关键词并且关键词名称在库中
        $kewordsNew = [];    //新增的关键词id集合
        $kewordsDara = DB::table('keywords')->pluck('name')->toArray();             //关键词库中存在的关键
        $insertKeywords = [];   //需要插入关键词库中
        $insertWordsId = [];   //插入关键词到关键词库中返回的id集合
        $delKeywordsId = [];  //需要删除的关键词id集合
        $insertKeywordsId = [];   //需要新插入的关键词id集合
        $inetrData = [];
        foreach ($kewordsArr as $val) {
            if (!in_array(trim($val), $kewordsDara)) {
                $insertKeywords[] = trim($val);
            } else {
                $kewordsArrNew[] = trim($val);
            }
        }
        if ($kewordsArrNew) {
            $kewordsNew = DB::table('keywords')->whereIn('name', $kewordsArrNew)->pluck('id')->toArray();
        }
        $kewordsOld = DB::table('word_connection')
            ->where(['user_id' => $userId])
            ->pluck('word_id')
            ->toArray();

        # 需要插入的关键词
        foreach ($kewordsNew as $value) {
            if (!in_array(intval($value), $kewordsOld)) {
                $insertKeywordsId[] = intval($value);
            }
        }
        # 需要删除的关键词
        foreach ($kewordsOld as $value) {
            if (!in_array(intval($value), $kewordsNew)) {
                $delKeywordsId[] = intval($value);
            }
        }
        # 如果需要插入关键词到数据中
        if ($insertKeywords) {
            DB::beginTransaction();
            try {
                foreach ($insertKeywords as $val) {
                    $insertWordsId[] = DB::table('keywords')->insertGetId(['name' => $val, 'create_time' => date('Y-m-d')]);
                }
                DB::commit();
            } catch (\Exception $exception) {
                DB::rollback();
                return ['code' => 500, 'msg' => '编辑失败', 'data' => []];
            }
        }
        # 得到新的需要插入的关键词id集合
        $insertKeywordsId = array_values(array_unique(array_merge($insertWordsId, $insertKeywordsId)));
        $insertKeywordsName = [];
        if ($insertKeywordsId) {
            $insertKeywordsName = DB::table('keywords')->whereIn('id', $insertKeywordsId)->pluck('name')->toArray();
        }
        foreach ($insertKeywordsId as $key=> $v) {
            $inetrData[$key] = [
                'user_id' => $userId,
                'word_id' => intval($v)
            ];
        }
        DB::transaction(function() use ($inetrData, $delKeywordsId, $userId, $data, $insertKeywordsName) {
            #  获取禁用用户下面的粉丝抖音id集合
            $tiktokIdData = DB::table('fans')
                ->select('fans.tiktok_id')
                ->where(['v.state' => 1])
                ->join('video_user as v', 'fans.collect_id', '=', 'v.id')
                ->pluck('fans.tiktok_id')
                ->toArray();
            # 操作redis
            if ($insertKeywordsName) {
                $keywordsFansSet = [];
                foreach ($insertKeywordsName as $w) {
                    $keywordsFansSet[] = 'keywords_fans_set:' . $w;
                }
                $keywordsFansSet[] = 'keywords_ask_set:' . $userId;
                if ($keywordsFansSet) {
                    Redis::SUNIONSTORE('keywords_ask_set:' . $userId, $keywordsFansSet);
                }
                # 操作当前客户的打招呼粉丝集合, 将禁用用户下面的粉丝移除
                if ($tiktokIdData) {
                    Redis::SREM('keywords_ask_set:' . $userId, $tiktokIdData);
                }
            }
            if ($inetrData) {
                DB::table('word_connection')->insert($inetrData);
            }
            if ($delKeywordsId) {
                DB::table('word_connection')->where(['user_id' => $userId])->whereIn('word_id', $delKeywordsId)->delete();
            }
            DB::table('user')->where(['id' => $userId])->update($data);
        });
        return [
            'code' => 200,
            'msg' => '编辑成功',
            'data' => []
        ];
    }


    /**
     * @desc 获取用户的关键词
     * @author wangxiang
     * @param $userId
     * @return mixed
     */
    public static function getKeywords($userId)
    {
        $kewords = DB::table('word_connection')
            ->select('keywords.name')
            ->leftJoin('keywords', 'word_connection.word_id', '=', 'keywords.id')
            ->where([['keywords.in_use', '=', 1], ['word_connection.user_id', '=', $userId]])
            ->pluck('keywords.name');

        return $kewords;
    }

}