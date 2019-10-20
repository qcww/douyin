<?php

namespace App\Http\Controllers\v1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

// 测试控制器
class TaskController extends Controller
{
    // 定时执行将临时采集数据添加到集合里
    public function fansToSet()
    {
        $keyword = DB::table('keywords')->select('id','name')->get()->toArray();
        $filterUser = DB::table('user')->where('location','!=','')->pluck('id')->toArray();
        foreach($keyword as $list){
            $groupUser = Db::table('word_connection')->where('word_id',$list->id)->whereNotIn('user_id',$filterUser)->select('user_id')->get()->map(function ($value) {
                return (array)$value;
            })->toArray();
            if(Redis::EXISTS('keyrowd_to_user_setting:'.$list->name)) Redis::DEL('keyrowd_to_user_setting:'.$list->name);
            if(!empty($groupUser)) Redis::LPUSH('keyrowd_to_user_setting:'.$list->name,array_column($groupUser,'user_id'));
        }


        $news = Redis::KEYS('temp_new_data_0:*');
        $news1 = Redis::KEYS('temp_new_data_1:*');

        set_time_limit(0);

        $keywordArray = array_slice($news,0,100);
        $keywordArray1 = array_slice($news1,0,100);

        $keywordId = array_map(function($v){
            return trim(strstr($v,':'),':');
        },$keywordArray);
        $keywordId1 = array_map(function($v){
            return trim(strstr($v,':'),':');
        },$keywordArray1);


        $search = DB::table('video_user')->select('id','keyword')->whereIn('id',$keywordId)->get();
        $search1 = DB::table('ks_videos')->select('id','tag as keyword')->whereIn('id',$keywordId1)->get();

        $mergeArray = [];

        foreach($search as $user){
            $mergeArray[$user->keyword] = isset($mergeArray[$user->keyword])?$mergeArray[$user->keyword]:[];
            array_push($mergeArray[$user->keyword],'temp_new_data_0:'.$user->id);
        }

        foreach($search1 as $user){
            $mergeArray[$user->keyword] = isset($mergeArray[$user->keyword])?$mergeArray[$user->keyword]:[];
            array_push($mergeArray[$user->keyword],'temp_new_data_1:'.$user->id);
        }

        // 遍历关键词
        foreach($mergeArray as $k => $m){
            $userList = Redis::LRANGE('keyrowd_to_user_setting:'.$k,0,-1);
            foreach($userList as $ul){
                Redis::SUNIONSTORE('keywords_ask_set:'.$ul,array_merge($m,['keywords_ask_set:'.$ul]));
            }
        }
        sleep(2);
        foreach($keywordArray as $new){
            Redis::DEL($new);
        }
        foreach($keywordArray1 as $new){
            Redis::DEL($new);
        }


        return $keywordArray;

    }

    // 采集数据入库与redis同步
    public function addFansAll()
    {
        $keys = Redis::KEYS('temp_fans_data_0:*');
        $keys1 = Redis::KEYS('temp_fans_data_1:*');
        set_time_limit(0);
        // 遍历临时数据到keyword
        $keywordArray = array_slice($keys,0,100);
        $keywordArray1 = array_slice($keys1,0,100);
        $updateArray = [];
        $updateArray1 = [];
        foreach ($keywordArray as $word){
            array_push($updateArray,array('id' => trim(strstr($word,':'),':'),'collect_fans' => Redis::SCARD($word)));
        }
        foreach ($keywordArray1 as $word){
            array_push($updateArray1,array('id' => trim(strstr($word,':'),':'),'collect_fans' => Redis::SCARD($word)));
        }
        $this->updateBatch($updateArray,'video_user');
        $this->updateBatch($updateArray1,'ks_videos');

        $keywordId = array_map(function($v){
            return trim(strstr($v,':'),':');
        },$keywordArray);
        $keywordId1 = array_map(function($v){
            return trim(strstr($v,':'),':');
        },$keywordArray1);

        $search = DB::table('video_user')->select('id','keyword')->whereIn('id',$keywordId)->get();
        $search1 = DB::table('ks_videos')->select('id','tag as keyword')->whereIn('id',$keywordId1)->get();

        $mergeArray = [];
        $mergeArray1 = [];

        foreach($search as $user){
            $mergeArray[$user->keyword] = isset($mergeArray[$user->keyword])?$mergeArray[$user->keyword]:[];
            array_push($mergeArray[$user->keyword],'temp_fans_data_0:'.$user->id);
        }
        foreach($search1 as $user){
            $mergeArray1[$user->keyword] = isset($mergeArray1[$user->keyword])?$mergeArray1[$user->keyword]:[];
            array_push($mergeArray1[$user->keyword],'temp_fans_data_1:'.$user->id);
        }

        foreach($mergeArray as $k => $m){
            $m = array_merge($m,['keywords_fans_set:'.$k]);
            Redis::SUNIONSTORE('keywords_fans_set:'.$k,$m);
        }
        foreach($mergeArray1 as $k => $m){
            $m = array_merge($m,['keywords_fans_set_1:'.$k]);
            Redis::SUNIONSTORE('keywords_fans_set_1:'.$k,$m);
        }
        sleep(2);
        foreach($keywordArray as $delKey){
            Redis::DEL($delKey);
        }
        foreach($keywordArray1 as $delKey){
            Redis::DEL($delKey);
        }
        return $keywordArray;

    }

    //批量更新
    public function updateBatch($multipleData = [],$table)
    {
        try {
            if (empty($multipleData)) {
                throw new \Exception("数据不能为空");
            }
            $tableName = DB::getTablePrefix() . $table; // 表名
            $firstRow  = current($multipleData);

            $updateColumn = array_keys($firstRow);
            // 默认以id为条件更新，如果没有ID则以第一个字段为条件
            $referenceColumn = isset($firstRow['id']) ? 'id' : current($updateColumn);
            unset($updateColumn[0]);
            // 拼接sql语句
            $updateSql = "UPDATE " . $tableName . " SET ";
            $sets      = [];
            $bindings  = [];
            foreach ($updateColumn as $uColumn) {
                $setSql = "`" . $uColumn . "` = CASE ";

                foreach ($multipleData as $data) {
                    $setSql .= "WHEN `" . $referenceColumn . "` = ? THEN ? ";
                    $bindings[] = $data[$referenceColumn];
                    $bindings[] = $data[$uColumn];
                }
                $setSql .= "ELSE `" . $uColumn . "` END ";
                $sets[] = $setSql;
            }
            $updateSql .= implode(', ', $sets);
            $whereIn   = collect($multipleData)->pluck($referenceColumn)->values()->all();
            $bindings  = array_merge($bindings, $whereIn);
            $whereIn   = rtrim(str_repeat('?,', count($whereIn)), ',');
            $updateSql = rtrim($updateSql, ", ") . " WHERE `" . $referenceColumn . "` IN (" . $whereIn . ")";
            // 传入预处理sql语句和对应绑定数据
            return DB::update($updateSql, $bindings);
        } catch (\Exception $e) {
            return false;
        }
    }

    // 零点执行统计数据入库
    public function task()
    {
        date_default_timezone_set('Asia/Shanghai');
        $yesterday = date('d',strtotime('-1 days'));
        $keys = Redis::KEYS('ask_account_group:*:total');
        foreach($keys as $k){
            $uid = trim(trim($k,'ask_account_group:'),':total');

            $ref = Redis::LINDEX('ask_account_group:'.$uid.':count_list',0);
            if($ref){
                $newDay = json_decode($ref,true);
                if($newDay['x'] == $yesterday) continue;
            }
            Redis::LPUSH('ask_account_group:'.$uid.':count_list',json_encode(array('x' => (int)$yesterday ,'y' => (int)Redis::get('ask_account_group:'.$uid.':today'))));
            Redis::set('ask_account_group:'.$uid.':today',0);

        }



        $ref = Redis::LINDEX('ask_account_total:count_list',0);
        if($ref){
            $newDay = json_decode($ref,true);
            if($newDay['x'] == $yesterday) return;
        }

        Redis::LPUSH('ask_account_total:count_list',json_encode(array('x' => (int)$yesterday, 'y' => (int)Redis::get('ask_account_total:today'))));
        Redis::set('ask_account_total:today',0);

    }

    // 钉钉提醒
    public function notice(Request $request)
    {
        $scrapUser = DB::table('user_ask_acount')->where(['tiktok_id' => $request->input('uid'),'type' => 0])->first();
        if (!$scrapUser) return;
        $scrapUser = get_object_vars($scrapUser);
        $type = $request->input('type');
        $msg = $request->input('msg');
        $rep = $request->input('rep');
        $uid = $request->input('uid');

        $status = 0;
        $temp = Redis::LRANGE('filter',0,-1);
        foreach($temp as $t){
            if(strpos($rep,$t) !== false){
                $status = 2;
                break;
            }
        }

        $insertData = array(
            'user_id' => $scrapUser['user_id'],
            'nickname' => $msg,
            'reply' => $rep,
            'reply_time' => time(),
            'device_name' => $scrapUser['nickname'],
            'device_remark' => $scrapUser['remark'],
            'status' => $status
        );
        if($type == 2) DB::table('fans_reply')->insert($insertData);


        $dingId = DB::table('user')->where('id',$scrapUser['user_id'])->value('dd_token');
        if ($dingId && !in_array($msg, array('消息', '消息助手')) && $status != 2) {
            Redis::LPUSH('notice:'.$dingId,json_encode(array('type'=>$type,'nickname'=>$msg,'rep'=>$rep,'uid'=>$uid)));
        }

        return array('msg' => '操作成功');
    }

    public function sendNotice()
    {
        set_time_limit(0);
        $keys = Redis::KEYS('notice:*');
        foreach($keys as $key){
            $dingId = str_replace('notice:','',$key);
            while($m = Redis::LPOP($key)){
                $msgData = json_decode($m);
                $type = (int)$msgData->type;
                $rep = $msgData->rep;
                $nickname = $msgData->nickname;
                $scrapUser = DB::table('user_ask_acount')->where(['tiktok_id' => $msgData->uid,'type' => 0])->first();
                if (!$scrapUser) return;
                $scrapUser = get_object_vars($scrapUser);
                $json = array("msgtype" => "markdown", "markdown" => array());
                if ($type == 1) {
                    $json["markdown"]['title'] = '脚本异常';
                    $json["markdown"]['text'] = "### 请检查手机脚本\n" .
                        "> - 抖音号：" . $scrapUser['tiktok_id'] . "\n" .
                        "> - 设备备注：" . $scrapUser['remark'] . "\n";
                } else if ($type == 2) {
                    $json["markdown"]['title'] = '新的回复内容';
                    $json["markdown"]['text'] = "### 消息自动提醒\n" .
                        "> - 回复人昵称：" . $nickname . "\n" .
                        "> - 回复内容：" . $rep . "\n" .
                        "> - 设备抖音号：" . $scrapUser['tiktok_id'] . "\n" .
                        "> - 设备备注：" . $scrapUser['remark'] . "\n" .
                        "> - 当前时间：" . date('Y-m-d H:i') . "\n";
                }else if($type == 3){
                    $json["markdown"]['title']='消息发送频繁';
                    $json["markdown"]['text']="### 消息自动提醒\n".
                        "> - 设备抖音号：".$scrapUser['tiktok_id']."\n".
                        "> - 设备备注：".$scrapUser['remark']."\n".
                        "> - 当前时间：".date('Y-m-d H:i')."\n";
                }

                $aurl = 'https://oapi.dingtalk.com/robot/send?access_token=' . $dingId;
                $json = json_encode($json);
                return $this->send_post($aurl, $json);
            }
        }
    }

    function send_post($url, $post_data)
    {
        // $postdata = http_build_query($post_data);
        $options = array(
            'http' => array(
                'method' => 'POST',
                'header' => 'Content-type:application/json; charset=utf-8',
                'content' => $post_data,
                'timeout' => 15 * 60
            )
        );
        $context = stream_context_create($options);
        $result = file_get_contents($url, false, $context);
        return $result;
    }


}
