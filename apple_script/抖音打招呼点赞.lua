function main()
    init();
    getAskUser();
    --openFansHome();
    --doSleep()
end

function init()
    hostAddr = 'http://dy_api.jgyljt.com';
    getScrapUserUrl = hostAddr..'/api/getScrapUser';
    getAskListUrl = hostAddr..'/api/user/getUser';
    doCallBackUrl = hostAddr..'/api/user/saveUser';
    
    fanHomeUrl = 'https://www.iesdouyin.com/share/user/';
    scrapUser = {}
    backNum = 2;
    
    xh1 = 30; --“首页”文字位置
    yh1 = 1260;
    xh2 = 115;
    yh2 = 1300;
    
    xn = 525;
    yn = 1283;
    
    xp1 = 111; --获取抖音号位置
    yp1 = 488;
    xp2 = 285;
    yp2 = 545;
    
    x1 = 515;
    y1 = 900;
    x2 = 694;
    y2 = 84;
    x3 = 682;
    y3 = 230;
    
    x4 = 54; --回退按钮位置
    y4 = 84;
    
    x5 = 110; --第一个视频位置
    y5 = 980;
    
    x6 = 380; --打开看看位置
    y6 = 850;
    x7 = 640;
    y7 = 930;
    
end

function click(x, y)
    touchDown(0, x, y);
    mSleep(200);
    touchUp(0);
end

function back(num)
    for i=num+1,1,-1 do
        click(x4,y4);
        mSleep(300);
    end
    mSleep(1500);
    local checkHome = checkAtHome();
    if checkHome == false then
        click(x4,y4);
    end
end

--检查当前页面是否在主页
function checkAtHome()
    if ocrText(xh1,yh1,xh2,yh2,'chi_sim') == '首页' then
        return true;
    end
    return false;
end

--图片文字识别
function ocrText(p1,p2,p3,p4,type)
    local text = localOcrText("/Developer/tessdata",type,p1,p2,p3,p4);
    if code == "" then
        notifyMessage("识别失败");
    else
        --notifyMessage(text);
        return text;
    end
end

--获取当前脚本都晕账号信息
function getAskUser()
    runApp(first)
    mSleep(8000);
    click(675,1280);
    mSleep(3000);
    local tiktokId = ocrText(xp1,yp1,xp2,yp2,'eng');
    --local tiktokId = '925910860';
    local sendRes = httpGet(getScrapUserUrl.."?tiktok_id="..tiktokId);
    
    scrapUser = jsonDecode(sendRes);
    --notifyMessage(scrapUser.time_range)
    mSleep(2000);
    click(xn,yn);
end

--打开粉丝主页
function openFansHome()
    local askList = getAskList();
    if askList then
        for key, value in ipairs(askList) do
            keyDown('HOME');
            mSleep(1500);
            copyText(fanHomeUrl..value);
            runApp(false);
            mSleep(4000);
            
            local look = ocrText(x6,y6,x7,y7,'chi_sim'); --检测打开看看是否弹出，没有的话继续等待
            if look ~= '打开看看' then
                mSleep(5000);
            end
            click(x1,y1); --打开看看
            mSleep(4000);
        
                
            -- 根据账号action判断脚本该做的动作
            if scrapUser.action == 1 then
                doAsk();
            else
                doZan();
            end
            mSleep(2000);
            doCallBack(backNum);
        end
    else
        notifyMessage('暂无数据');
        mSleep(5000);
    end
    getAskUser();
    doSleep();
    return openFansHome();
end

--打招呼操作
function doAsk()
    click(x2,y2); --右上角
    mSleep(2000);
    click(x3,y3); --发送消息按钮
    mSleep(1000);
    inputText(getReply(1));
    mSleep(3000);
    click(660,1290);
end

--点赞
function doZan()
    click(x5,y5); --点击视频位置
    mSleep(5000); --三点判断是否有作品
    x, y = findMultiColorInRegionFuzzy({ 0x161823, 392, -24, 0x161823, 607, -38, 0x161823 }, 90, 101, 1087, 708, 1125);
    if x ~= -1 and y ~= -1 then  -- 如果找到了
        backNum = 2;
        return true;
    end
    mSleep(10000);
    for i=8,1,-1 do
        click(500,500);
    end
    backNum = 3;
end

--完成打招呼或者点赞操作
function doCallBack()
    local sendRes = httpGet(doCallBackUrl.."?tiktok="..scrapUser.tiktok_id);
    mSleep(2000);
    back(backNum);
end

--获取打招呼或者点赞的粉丝列表
function getAskList()
    local sendRes = httpGet(getAskListUrl.."?tiktok_id="..scrapUser.tiktok_id);
    return jsonDecode(sendRes);
end

--获取回复信息
function getReply(start)
    local s = scrapUser.said;
    for i=start-1,1,-1 do
        p = string.find(s,'|');
        if p then
            s = string.sub(s,p+1,-1);
        end
        
    end
    p = string.find(s,'|');
    if p then
        s = string.sub(s,1,p-1);
    end
    return s;
    
end


    
--运行app
function runApp(first)
    if first then
        appKill("com.ss.iphone.ugc.Aweme");
    end
    keyDown('HOME');    -- HOME键按下
    mSleep(100);        --延时100毫秒
    keyUp('HOME');      -- HOME键抬起
    mSleep(500); 
    appRun('com.ss.iphone.ugc.Aweme');
    if first then
        doFirstRun() --第一次启动会慢点，执行等待与关闭其它弹框
    end
end

function doSleep()
    local r = split(scrapUser.time_range,',');
    local getTime = tonumber(os.date("%H"));
    --notifyMessage(r[#r]);

    local nextTime = 0;
    if getTime >= r[#r] then
        nextTime = 24 + tonumber(r[1]);
    else
        for i,v in pairs(r) do
            if v > getTime then
               nextTime = v;
               break;
            end
        end
    end
    notifyMessage('下次脚本执行时间'..tostring(nextTime%24),3000);
    mSleep((nextTime - getTime)*3600*1000);
end

function split( str,reps )
    local resultStrList = {}
    string.gsub(str,'[^'..reps..']+',function ( w )
        table.insert(resultStrList,tonumber(w))
    end)
    return resultStrList
end

function doFirstRun()
    local checkHome = checkAtHome();
    if checkHome == false then
        mSleep(5000);
        return doFirstRun();
    end
    return true;
end