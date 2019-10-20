<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class UserAskAcount extends Model
{
    protected $table = 'user_ask_acount';


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
        $userAskAcount = DB::table('user_ask_acount')->where(['type' => 0,'tiktok_id' => trim($request['tiktok_id'])])->first();
        if (!$userAskAcount) {
            # 不存在,直接插入
            $state = DB::table('user_ask_acount')->insert($data);
        } else if ($userAskAcount->in_use == 0) {
            # 存在,处于停用状态
            $data['in_use'] = 1;
            $state = DB::table('user_ask_acount')->where(['id' => $userAskAcount->id])->update($data);
        } else {
            return [
                'code' => 400,
                'msg' => '抖音id已存在',
                'data' => $userAskAcount
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
        $userAskAcount = DB::table('user_ask_acount')->where(['id' => $id])->first();
        if (!$userAskAcount) {
            return [
                'code' => 400,
                'msg' => 'id不存在',
                'data' => []
            ];
        }
        # 更新时判断抖音id是否存在
        $userAskAcount = DB::table('user_ask_acount')->where([['id', '!=', $id], ['tiktok_id', '=', trim($request['tiktok_id'])]])->first();
        if (!$userAskAcount) {
            # 抖音id不存在, 直接更新
            $state = DB::table('user_ask_acount')->where(['id' => $id])->update($data);
        } else {
            # 抖音id存在
            return [
                'code' => 400,
                'msg' => '抖音id已存在',
                'data' => $userAskAcount
            ];
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
}