<?php

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It is a breeze. Simply tell Lumen the URIs it should respond to
| and give it the Closure to call when that URI is requested.
|
*/

$api = $app->make(Dingo\Api\Routing\Router::class);

$api->version('v1', function ($api) {

    $api->group(['namespace' => 'App\Http\Controllers\v1'], function ($api) {

        // 任务
        $api->get('/tasks', ['as'=> 'api.test.list', 'uses'=> 'TestController@getTaskList']); // 获取任务列表
        $api->post('/keywordSearch', ['as'=> 'api.fans.video', 'uses'=> 'FansAddController@keywordSearch']); // 添加视频及用户
        $api->post('/addCommentUser', ['as'=> 'api.fans.comment', 'uses'=> 'FansAddController@addCommentUser']); // 添加评论用户
        $api->post('/fansHome', ['as'=> 'api.fans.home', 'uses'=> 'FansAddController@fansHome']); // 竞品粉主页
        $api->post('/addFans', ['as'=> 'api.fans.add', 'uses'=> 'FansAddController@addFans']); // 添加粉丝

        // 粉丝采集接口
        $api->post('/scrapList', ['as'=> 'api.fans.scrap', 'uses'=> 'FansAddController@scrapList']); // 返回视频粉丝id
        $api->post('/doGetComp', ['as'=> 'api.fans.scrap', 'uses'=> 'FansAddController@doGetComp']); // 待打招呼列表

        // 采集配置
        $api->post('/accountKeywords', ['as'=> 'api.fans.keywords', 'uses'=> 'FansAddController@searchKeywords']); // 添加粉丝
        $api->get('/fansToSet', ['as'=> 'api.fans.fansToSet', 'uses'=> 'TaskController@fansToSet']); // 数据同步
        $api->get('/task', ['as'=> 'api.fans.task', 'uses'=> 'TaskController@task']); // 数据同步
        $api->get('/addFansAll', ['as'=> 'api.fans.task', 'uses'=> 'TaskController@addFansAll']); // 数据同步

        // 打招呼
        $api->get('/getScrapUser', ['as'=> 'api.fans.config', 'uses'=> 'FansAddController@getScrapUser']); // 配置信息
        $api->any('/user/getUser', ['as'=> 'api.fans.getReadyFans', 'uses'=> 'FansAddController@getReadyFans']); // 打招呼列表
        $api->any('/user/saveUser', ['as'=> 'api.fans.doCompleteAsk', 'uses'=> 'FansAddController@doCompleteAsk']); // 完成打招呼
        $api->post('/gather', ['as'=> 'api.gather', 'uses'=> 'FansAddController@gather']); // 定时执行统计清空与汇总

        // 统计数据
        $api->post('/accounts/countTotal', ['as'=> 'api.account.countTotal', 'uses'=> 'AccountController@countTotal']); // 总的统计
        $api->get('/account/countUser', ['as'=> 'api.account.countUser', 'uses'=> 'AccountController@countUser']); // 用户的统计
        $api->get('/account/countToday', ['as'=> 'api.account.countToday', 'uses'=> 'AccountController@countToday']); // 用户的统计
        $api->get('/account/userList', ['as'=> 'api.account.countToday', 'uses'=> 'AccountController@userList']); // 用户的统计

        // 消息提醒
        $api->any('/notice', ['as'=> 'api.account.notice', 'uses'=> 'TaskController@notice']); // 添加钉钉提醒
        $api->post('/sendNotice', ['as'=> 'api.account.sendNotice', 'uses'=> 'TaskController@sendNotice']); // 添加钉钉提醒
        # 采集的抖音号相关接口路由
        $api->post('/collectAcount', ['as'=> 'api.collectAcount.add', 'uses'=> 'CollectAcountController@post']); // 采集账号提交编辑和新增
        $api->get('/collectAcounts', ['as'=> 'api.collectAcount.add', 'uses'=> 'CollectAcountController@index']); // 获取采集账号列表
        $api->get('/collectAcount', ['as'=> 'api.collectAcount.add', 'uses'=> 'CollectAcountController@view']); // 获取单个采集账号信息
        $api->get('/collectAcount/getKeywords', ['as'=> 'api.collectAcount.keywords', 'uses'=> 'CollectAcountController@keywords']); // 获取采集账号的关键词
        $api->post('/collectAcount/keywords', ['as'=> 'api.collectAcount.keywords', 'uses'=> 'CollectAcountController@keywordUpdate']); // 采集账号关键词编辑
        $api->delete('/collectAcount/delete', ['as'=> 'api.collectAcount.delete', 'uses'=> 'CollectAcountController@delete']); // 逻辑删除单个账号

        # 客户相关接口路由
        $api->get('/users', ['as'=> 'api.user.index', 'uses'=> 'UserController@index']); // 获取用户列表
        $api->get('/user', ['as'=> 'api.user.view', 'uses'=> 'UserController@view']); // 获取单个用户信息
        $api->post('/user', ['as'=> 'api.user.post', 'uses'=> 'UserController@post']); // 提交新增或编辑数据
        $api->delete('/user/delete', ['as'=> 'api.user.delete', 'uses'=> 'UserController@delete']); // 删除单个用户
        $api->post('/user/resetFans', ['as'=> 'api.user.resetFans', 'uses'=> 'UserController@resetFans']); // 重置客户粉丝采集
        $api->post('/user/resetByLocation', ['as'=> 'api.user.resetByLocation', 'uses'=> 'UserController@resetByLocation']); // 按地区给用户粉丝重置

        # 粉丝相关接口路由
        $api->get('/fans', ['as'=> 'api.fans.index', 'uses'=> 'FansController@index']); // 获取粉丝列表
        $api->post('/fans/label', ['as'=> 'api.fans.label', 'uses'=> 'FansController@updateLable']); // 修改粉丝标签
        $api->get('/fans/reply', ['as'=> 'api.fans.reply', 'uses'=> 'FansController@reply']); // 获取粉丝回复列表
        $api->post('/fans/reply', ['as'=> 'api.fans.updateReply', 'uses'=> 'FansController@updateReply']); // 粉丝回复编辑

        # 设置相关接口路由
        $api->get('/setting', ['as'=> 'api.setting.index', 'uses'=> 'SettingController@index']); // 获取设置模板列表
        $api->post('/setting', ['as'=> 'api.setting.post', 'uses'=> 'SettingController@post']); // 提交或修改模板数据
        $api->put('/setting', ['as'=> 'api.setting.post', 'uses'=> 'SettingController@default']); // 将模板设为默认
        $api->delete('/setting', ['as'=> 'api.setting.post', 'uses'=> 'SettingController@delete']); // 删除模板
        $api->get('/setting/label', ['as'=> 'api.setting.labelIndex', 'uses'=> 'SettingController@labelIndex']); // 获取标签相关列表
        $api->put('/setting/label', ['as'=> 'api.setting.labelUpdate', 'uses'=> 'SettingController@labelUpdate']); // 不打招呼标签编辑
        $api->delete('/setting/label/recycle', ['as'=> 'api.setting.labelRecycle', 'uses'=> 'SettingController@labelRecycle']); // 不打招呼标签设置删除
        $api->post('/setting/label', ['as'=> 'api.setting.labelPost', 'uses'=> 'SettingController@labelPost']); // 粉丝标签类型新增
        $api->delete('/setting/label', ['as'=> 'api.setting.labelDelete', 'uses'=> 'SettingController@labelDelete']); // 粉丝标签类型删除
        $api->put('/setting/reset', ['as'=> 'api.setting.reset', 'uses'=> 'SettingController@reset']); // 打招呼重置
        $api->get('/setting/info', ['as'=> 'api.setting.info', 'uses'=> 'SettingController@info']); // 获取用户基本信息

        # 关键词相关接口路由
        $api->get('/keywords', ['as'=> 'api.keywords.index', 'uses'=> 'KeywordsController@index']); // 获取关键词列表
        $api->post('/keywords', ['as'=> 'api.keywords.post', 'uses'=> 'KeywordsController@post']); // 新增关键词
        $api->delete('/keywords', ['as'=> 'api.keywords.delete', 'uses'=> 'KeywordsController@delete']); // 删除关键词
        $api->get('/keywords/select', ['as'=> 'api.keywords.select', 'uses'=> 'KeywordsController@select']); // 获取关键词下拉选择数据
        $api->post('/resetKeyword', ['as'=> 'api.keywords.resetKeyword', 'uses'=> 'KeywordsController@resetKeyword']); // 关键词采集重置

        # 用户相关接口路由
        $api->get('/videoUser', ['as'=> 'api.videoUser.index', 'uses'=> 'VideoUserController@index']); // 获取用户列表
        $api->put('/videoUser', ['as'=> 'api.videoUser.prohibit', 'uses'=> 'VideoUserController@prohibit']); // 禁用或启用用户
        $api->post('/videoUser', ['as'=> 'api.videoUser.add', 'uses'=> 'VideoUserController@add']); // 新增用户

        # 视频相关接口路由
        $api->get('/video', ['as'=> 'api.video.index', 'uses'=> 'VideoController@index']); // 获取视频列表

        # 打招呼账号相关接口路由
        $api->get('/userAskAcount', ['as'=> 'api.userAskAcount.index', 'uses'=> 'UserAskAcountController@index']); // 获取打招呼账号列表
        $api->get('/userAskAcount/model', ['as'=> 'api.userAskAcount.model', 'uses'=> 'UserAskAcountController@model']); // 获取模板列表
        $api->post('/userAskAcount', ['as'=> 'api.userAskAcount.post', 'uses'=> 'UserAskAcountController@post']); // 新增或提交打招呼账号
        $api->delete('/userAskAcount', ['as'=> 'api.userAskAcount.delete', 'uses'=> 'UserAskAcountController@delete']); // 删除打招呼账号
        $api->put('/userAskAcount', ['as'=> 'api.userAskAcount.greetSet', 'uses'=> 'UserAskAcountController@greetSet']); // 打招呼设置

        # 登录账号相关接口路由
        $api->post('/admin', ['as'=> 'api.user.login', 'uses'=> 'AdminController@login']); // 用户登录
        $api->post('/admin/changePassword', ['as'=> 'api.admin.changePassword', 'uses'=> 'AdminController@changePassword']); // 修改密码
        $api->get('/admin', ['as'=> 'api.user.info', 'uses'=> 'AdminController@info']); // 解析token
        $api->get('/admin/refresh', ['as'=> 'api.user.refresh', 'uses'=> 'AdminController@refresh']); // 刷新token
        $api->get('/admin/logout', ['as'=> 'api.user.logout', 'uses'=> 'AdminController@logout']); // 退出

        # 获取省市、更新省市相关接口路由
        $api->get('/nolocationfans', ['as'=> 'api.fans.location', 'uses'=> 'FansAddController@getNoLocationUser']); // 获取无省市的抖音号
        $api->post('/savelocation', ['as'=> 'api.fans.location', 'uses'=> 'FansAddController@saveUserLocation']); // 更新粉丝的省市
        $api->get('/getnolocationfans', ['as'=> 'api.fans.location', 'uses'=> 'FansAddController@getNoLocationUsers']); // 获取无省市的抖音号
        $api->post('/savelocations', ['as'=> 'api.fans.location', 'uses'=> 'FansAddController@saveUserLocations']); // 更新粉丝的省市
        # 获取评论粉
        $api->post('/getvideolist', ['as'=> 'api.video.comment', 'uses'=> 'FansAddController@getVideoList']); // 获取要采集评论粉的视频列表
        $api->post('/updatevideolist', ['as'=> 'api.video.comment', 'uses'=> 'FansAddController@updateVideoList']); // 更新采集评论粉的视频采集状态
        # 获取竞品粉列表
        $api->post('/getscraplist', ['as'=> 'api.videouser.scraplist', 'uses'=> 'FansAddController@getScrapList']); // 获取要采集评论粉的视频列表
        $api->post('/scraplistcannel', ['as'=> 'api.videouser.scraplistcannel', 'uses'=> 'FansAddController@scrapListCannel']); // 获取要采集评论粉的视频列表

        # 添加粉丝点赞评论用户
        $api->post('/fans/addMyFans', ['as'=> 'api.fans.addMyFans', 'uses'=> 'FansController@addMyFans']); // 添加我的粉丝
        $api->post('/likes/addLikes', ['as'=> 'api.fans.addLikes', 'uses'=> 'FansController@addLikes']); // 添加我的点赞粉丝
        $api->post('/comment/addComment', ['as'=> 'api.fans.addComment', 'uses'=> 'FansController@addComment']); // 添加我的点赞粉丝

        $api->post('/getMyFocusList', ['as'=> 'api.fans.getMyFocusList', 'uses'=> 'FansController@getMyFocusList']); // 获取我的关注粉丝列表
        $api->post('/compMyFocusFans', ['as'=> 'api.fans.compMyFocusFans', 'uses'=> 'FansController@compMyFocusFans']); // 完成给自己粉丝打招呼
        $api->post('/getMyZanList', ['as'=> 'api.fans.getMyZanList', 'uses'=> 'FansController@getMyZanList']); // 获取给我点赞用户列表
        $api->post('/compMyZanFans', ['as'=> 'api.fans.compMyZanFans', 'uses'=> 'FansController@compMyZanFans']); // 完成给点赞粉丝点赞


        $api->post('/fans/myFans', ['as'=> 'api.fans.myFans', 'uses'=> 'FansController@myFans']); // 推广-我的粉丝
        $api->any('/fans/myAccount', ['as'=> 'api.fans.myAccount', 'uses'=> 'FansController@myAccount']); // 推广-我的账号
        $api->post('/fans/eaditAccount', ['as'=> 'api.fans.eaditAccount', 'uses'=> 'FansController@eaditAccount']); // 推广-我的账号
        $api->get('/fans/myTemplate', ['as'=> 'api.fans.myTemplate', 'uses'=> 'FansController@myTemplate']); // 推广-模板列表
        $api->post('/fans/delAccount', ['as'=> 'api.fans.delAccount', 'uses'=> 'FansController@delAccount']); // 推广-删除账号
        $api->post('/fans/resetFans', ['as'=> 'api.fans.resetFans', 'uses'=> 'FansController@resetFans']); // 推广-重置打招呼
        $api->get('/fans/resetByLocation', ['as'=> 'api.fans.resetByLocation', 'uses'=> 'FansController@resetByLocation']);

        $api->post('/user/logIt', ['as'=> 'api.user.logIt', 'uses'=> 'UserController@logIt']); // 记录设备信息
        $api->get('/fans/customCity', ['as'=> 'api.fans.customCity', 'uses'=> 'FansController@customCity']); // 需要过滤粉丝地区的客户
        $api->post('/fans/fansLocationList', ['as'=> 'api.fans.fansLocationList', 'uses'=> 'FansController@fansLocationList']); // 未获取粉丝地区详情信息列表

        //快手数据入库部分
        $api->post('/fans/getKSVideo', ['as'=> 'api.fans.getKSVideo', 'uses'=> 'FansController@getKSVideo']); // 采集快手视频
        $api->post('/fans/addKSVideos', ['as'=> 'api.fans.addKSVideos', 'uses'=> 'FansController@addKSVideos']); // 添加快手视频
        $api->post('/fans/addKSFans', ['as'=> 'api.fans.addKSFans', 'uses'=> 'FansController@addKSFans']); // 添加快手视频
        $api->post('/fans/compKSVideo', ['as'=> 'api.fans.compKSVideo', 'uses'=> 'FansController@compKSVideo']); // 完成快手视频采集

        # 代运营粉丝相关接口
        $api->get('/operate', ['as'=> 'api.operate.index', 'uses'=> 'OperateFansController@index']); // 获取代运营粉丝列表
        $api->post('/operate', ['as'=> 'api.operate.post', 'uses'=> 'OperateFansController@post']); // 代运营粉丝编辑或增加
        $api->delete('/operate', ['as'=> 'api.operate.delete', 'uses'=> 'OperateFansController@delete']); // 代运营粉丝删除
        $api->put('/operate', ['as'=> 'api.operate.changeState', 'uses'=> 'OperateFansController@changeState']); // 修改状态

        # 上传接口
        $api->post('/upload', ['as'=> 'api.upload.index', 'uses'=> 'UploadController@index']); // 上传接口

        # 手动同步mysql数据到redis
        $api->post('/fansSqlToRedis', ['as'=> 'api.fans.fansSqlToRedis', 'uses'=> 'FansController@fansSqlToRedis']); // 上传接口

        $api->get('/weibo/fans', ['as'=> 'api.weibo.fans', 'uses'=> 'FansController@index']); // 获取粉丝列表

    });
});

