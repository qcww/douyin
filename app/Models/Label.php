<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Label extends Model
{

    protected $table = 'video';

    public static function list()
    {
        return DB::table('label')->get();
    }


    /**
     * @desc 不打招呼标签设置
     * @author wangxiang
     * @param $labelNew
     * @param $labelOld
     * @param $userId
     * @return array
     */
    public static function presenceUpdate($labelNew, $labelOld, $userId)
    {
        $presence = [];
        $noPresence = [];
        # 需要打招呼的
        foreach ($labelNew as $value) {
            if (!in_array(intval($value), $labelOld)) {
                $noPresence[] = intval($value);
            }
        }
        # 不需要打招呼的
        foreach ($labelOld as $value) {
            if (!in_array(intval($value), $labelNew)) {
                $presence[] = intval($value);
            }
        }
        DB::transaction(function() use ($presence, $noPresence, $userId) {
            DB::table('user_label_connection')->where(['user_id' => $userId])->whereIn('label_id', $presence)->update(['state' => 0]);
            DB::table('user_label_connection')->where(['user_id' => $userId])->whereIn('label_id', $noPresence)->update(['state' => 1]);
        });
        return ['code' => 200, 'msg' => '设置成功', 'data' => []];
    }


}