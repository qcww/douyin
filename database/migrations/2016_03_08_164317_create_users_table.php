<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateUsersTable extends Migration
{
    public $table = 'users';

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create($this->table, function (Blueprint $table) {
            $table->increments('id');
            $table->string('openid', 128)->default('')->comment('用户唯一识别');
            $table->string('real_name', 64)->default('')->comment('真实姓名');
            $table->string('nick_name', 64)->default('')->comment('用户昵称');
            $table->string('avatar_url', 255)->default('')->comment('用户头像');
            $table->tinyInteger('gender')->default(0)->comment('性别 0：未知 1：男 2：女');
            $table->integer('age')->default(18)->comment('年龄');
            $table->integer('like_num')->default(0)->comment('点赞数');
            $table->integer('from_flower_num')->default(0)->comment('接收桃花数');
            $table->integer('to_flower_num')->default(10)->comment('可送桃花数');
            $table->integer('from_invite_num')->default(0)->comment('被邀约数');
            $table->integer('to_invite_num')->default(5)->comment('剩余邀约数');
            $table->string('tags', 32)->default('')->comment('标签');
            $table->string('attach_ids', 255)->default('')->comment('照片');
            $table->tinyInteger('pair_type')->default(0)->comment('配对状态 0：初始状态 1：配对成功 2：配对失败');
            $table->integer('pair_user_id')->unsigned()->default(0)->comment('配对成功用户id');
            $table->string('intrduction', 200)->default('')->comment('交友宣言');
            $table->timestamps();
        });
        DB::statement("ALTER TABLE `".env('DB_PREFIX').$this->table."` comment '用户表'");//表注释
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop($this->table);
    }
}
