<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Admin extends Model {

    protected $table = 'admin';
    protected $connection = 'mysql_center';
    public $timestamps = false;

    // 根据权限获取回访员列表
    static function getFollowAll($sessionHospt,&$return,$groupId)
    {
        $return = self::where(array('job_id' => 3,'status' => 1))->when($groupId != '',function($req)use($groupId){
            return $req->where('group_id',$groupId);
        })->whereRaw("FIND_IN_SET($sessionHospt,hospital_ids)")->pluck('user_name','id')->toArray();
    }


}
