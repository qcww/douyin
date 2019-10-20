function main()
    flag = ftpGet("ftp://39.98.59.82/抖音打招呼点赞.lua", "/var/touchelf/scripts/抖音打招呼点赞.lua", "douyin_jinpin", "gudujian555");
    if flag then
        notifyMessage("下载成功")
    else
        notifyMessage("下载失败")
    end
end