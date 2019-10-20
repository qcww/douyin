<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class Keywords extends Model
{

    protected $table = 'keywords';

    public static function test()
    {


    }

    /**
     * @desc 获取关键词对应的客户数量
     * @author wangxiang
     * @param $id
     * @return mixed
     */
    public static function getUserNumBykeywords($id)
    {
        return DB::table('word_connection')->where(['word_id' => $id])->count();
    }

    /**
     * @desc 获取关键词对应的采集用户数量
     * @author wangxiang
     * @param $name
     * @return mixed
     */
    public static function getVideoUserNumBykeywords($name)
    {
        return Redis::LLEN('keywords_user:' . $name);
    }

    /**
     * @desc 获取关键词对应的已采集粉丝数量
     * @author wangxiang
     * @param $name
     * @return mixed
     */
    public static function getFansNumBykeywords($name)
    {
        return Redis::SCARD('keywords_fans_set:' . $name);
    }

    /**
     * @desc 获取关键词对应的采集抖音号数量
     * @author wangxiang
     * @param $id
     * @return mixed
     */
    public static function getCollectAcountNumBykeywords($id)
    {
        return DB::table('collect_keywords_connection')->where(['keywords_id' => $id])->count();
    }


    /**
     * @desc 新增关键词
     * @author wangxiang
     * @param $data
     * @return array
     */
    public static function insertData($data)
    {
        # 判断关键词是否存在
        $keywords = DB::table('keywords')->where(['name' => $data['name']])->first();
        if (!$keywords) {
            # 不存在,直接插入
            $state = DB::table('keywords')->insert($data);
        } else if ($keywords->in_use == 0) {
            $data['in_use'] = 1;
            $state = DB::table('keywords')->where(['id' => $keywords->id])->update($data);
        } else {
            return [
                'code' => 400,
                'msg' => '关键词已存在',
                'data' => $keywords
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

    public static function updateData($data, $id)
    {

    }


}