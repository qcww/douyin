<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class CollectAcount extends Model
{
    protected $table = 'collect_acount';


    public static function getKeywords($id)
    {
        $kewords = DB::table('collect_keywords_connection')
            ->select('keywords.name')
            ->leftJoin('keywords', 'collect_keywords_connection.keywords_id', '=', 'keywords.id')
            ->where([['keywords.in_use', '=', 1], ['collect_keywords_connection.collect_acount_id', '=', $id]])
            ->pluck('keywords.name');

        return $kewords;
    }


    /**
     * @desc 新增账号
     * @author wangxiang
     * @param $data
     * @param $request
     * @return array
     */
    public static function insertData($data, $request)
    {
        # 判断抖音id是否已存在
        $collectAcount = DB::table('collect_acount')->where(['tiktok_id' => trim($request['tiktok_id'])])->first();
        if (!$collectAcount) {
            # 不存在,直接插入
            $state = DB::table('collect_acount')->insert($data);
        } else if ($collectAcount->in_use == 0) {
            # 存在,处于停用状态
            $data['in_use'] = 1;
            $state = DB::table('collect_acount')->where(['id' => $collectAcount->id])->update($data);
        } else {
            return [
                'code' => 400,
                'msg' => '抖音id已存在',
                'data' => $collectAcount
            ];
        }
        if ($state) {
            return [
                'code' => 200,
                'msg' => '新增成功',
                'data' => []
            ];
        } else {
            return [
                'code' => 500,
                'msg' => 'fail',
                'data' => []
            ];
        }
    }


    /**
     * @desc 修改账号信息
     * @author wangxiang
     * @param $data
     * @param $request
     * @param $id
     * @return array
     */
    public static function updateData($data, $request, $id)
    {
        $collectAcount = CollectAcount::find($id);
        if (!$collectAcount) {
            return [
                'code' => 400,
                'msg' => 'id不存在',
                'data' => []
            ];
        }
        # 更新时判断抖音id是否存在
        $collectAcount = DB::table('collect_acount')->where([['id', '!=', $id], ['tiktok_id', '=', trim($request['tiktok_id'])]])->first();
        if (!$collectAcount) {
            # 抖音id不存在, 直接更新
            $state = DB::table('collect_acount')->where(['id' => $id])->update($data);
        } else if ($collectAcount->in_use == 1) {
            # 抖音id存在,并且该账户是使用状态
            return [
                'code' => 400,
                'msg' => '抖音id已存在',
                'data' => $collectAcount
            ];
        } else {
            $force = intval($request['force'] ?? 0);
            if ($force) {
                # 直接将处于关闭的采集账号开启,并更新信息
                $data['in_use'] = 1;
                $state = DB::table('collect_acount')->where(['tiktok_id' => trim($request['tiktok_id'])])->update($data);
            } else {
                # 返回信息给客户端,询问客户端
                return [
                    'code' => 201,
                    'msg' => '该抖音id对应的账号已存在,处于关闭状态,是否开启并更新信息',
                    'data' => $data
                ];
            }
        }
        if ($state) {
            return [
                'code' => 200,
                'msg' => '修改成功',
                'data' => []
            ];
        } else {
            return [
                'code' => 500,
                'msg' => 'fail',
                'data' => []
            ];
        }
    }


    /**
     * @desc 采集账号关键词编辑
     * @author wangxiang
     * @param $id
     * @param $kewords
     * @return array
     */
    public static function keywordUpdate($id, $kewords)
    {
        # 根据传过来的关键词,判断关键词库中是否都存在
        $kewordsArr = array_unique(array_filter(explode(',', $kewords)));   //需要新增的关键词
        $kewordsArrNew = [];    //新增的关键词并且关键词名称在库中
        $kewordsNew = [];    //新增的关键词id集合
        $kewordsDara = DB::table('keywords')->pluck('name')->toArray();             //关键词库中存在的关键
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
            $kewordsNew = DB::table('keywords')->whereIn('name', $kewordsArrNew)->pluck('id')->toArray();
        }

        # 获取该采集账号已设置的关键
        $kewordsOld = DB::table('collect_keywords_connection')
            ->where(['collect_acount_id' => $id])
            ->pluck('keywords_id')
            ->toArray();

        $delKeywordsId = [];  //需要删除的关键词id集合
        $insertKeywordsId = [];   //需要新插入的关键词id集合
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

        $inetrData = [];    # 需要插入到采集账号和关键词关联表数据集合
        foreach ($insertKeywordsId as $key=> $v) {
            $inetrData[$key] = [
                'collect_acount_id' => $id,
                'keywords_id' => intval($v)
            ];
        }
        DB::transaction(function() use ($inetrData, $delKeywordsId, $id) {
            if ($inetrData) {
                DB::table('collect_keywords_connection')->insert($inetrData);
            }
            if ($delKeywordsId) {
                DB::table('collect_keywords_connection')->where(['collect_acount_id' => $id])->whereIn('keywords_id', $delKeywordsId)->delete();
            }
        });
        return ['code' => 200, 'msg' => '编辑成功', 'data' => []];
    }


}