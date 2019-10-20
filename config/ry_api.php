<?php
    // 融云api配置
    
    return [
        // 用户服务
        'user_gettoken' => 'user/getToken.json', // 获取token
        'user_refresh' => 'user/refresh.json', // 刷新用户信息
        'user_checkonline' => 'user/checkOnline.json', // 检查用户在线状态
        'user_block' => 'user/block.json', // 用户封禁
        'user_unblock' => 'user/unblock.json', // 解除用户封禁
        'user_blockquery' => 'user/block/query.json', // 获取被封禁用户方法
        // 聊天室服务
        'chatroom_create' => 'chatroom/create.json', // 创建聊天室
        'chatroom_destroy' => 'chatroom/destroy.json', // 销毁聊天室
        'chatroom_query' => 'chatroom/query.json', // 查询聊天室信息方法
        'chatroom_userquery' => 'chatroom/user/query.json', // 查询聊天室内用户方法
        'chatroom_userexist' => 'chatroom/user/exist.json', // 查询用户是否在聊天室方法
        'chatroom_usersexist' => 'chatroom/users/exist.json', // 批量查询用户是否在聊天室方法

    ];
?>