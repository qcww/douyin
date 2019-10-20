<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class FansReply extends Model
{

    protected $table = 'fans_reply';

    public function getReplyTimeAttribute()
    {
        return date('Y-m-d H:i', $this->attributes['reply_time']);
    }
}
