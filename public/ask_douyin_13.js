var shareLink = 'https://www.iesdouyin.com/share/user/';
var scrapUser = '';
var scripHost = 'http://dy_api.jgyljt.com/';
// var scripHost = 'http://192.168.191.1/';

var getFocusFansUrl = 'api/getMyFocusList';
var getZanFansUrl = 'api/getMyZanList';
var saveFocusFansUrl = 'api/compMyFocusFans';
var saveZanFansUrl = 'api/compMyZanFans';
var logItUrl = 'api/user/logIt';

var getConfigUrl = 'api/getScrapUser?tiktok_id=';

var noticeUrl = 'api/notice';

var userHome = '';
var selecMode = 0;

var version = 1.8;
var appId = 10;
var sleepWait = 2; // 延迟倍率

var width = device.width;
var height = device.height;

// 抖音软件坐标
var x = 0.15;
var y = 0.89;

var x1 = 0.9;
var y1 = 0.14;
var x2 = 0.92;
var y2 = 0.19;
var x3 = 0.93;
var y3 = 0.91;
var x4 = 0.7;
var y4 = 0.9;
var x5 = 0.23;
var y5 = 0.74;

var x6 = 0.86;
var y6 = 0.15;

var checkNews = true;
var firstAsk = false;

var said = '';
var clock = '';
var scrapArea = '';
var askTime;
var timeOut = 0;
var asking = false;

var logIt = false;

var cons = new Object;

cons.log = function(v){
    var now = new Date();
    var nowTime = now.getFullYear()+'-'+now.getMonth()+'-'+now.getDate();
    var fileName = '/storage/emulated/0/douyin_ask_log-'+nowTime+".log";
    if(!files.exists(fileName)){
        var res = files.createWithDirs(fileName)
        console.log('添加文件',res)
    }
    console.log(v);
    if(logIt){
        files.append(fileName,v+"\r\n");
    }
}

function startApp(){
    home();
    sleep(500);
    click(width*x,height*y)
    launch("com.ss.android.ugc.aweme");
    return true;
}

// 特别设备适配
function setDeviceInfo(){
    if(device.product == 'JAT-AL00' && device.brand=='HONOR'){
        y2 = 0.17;
        y3 = 0.95;
    }
}

function logDevice(){
    var res = http.post(scripHost+logItUrl,{
        'width':width,
        'height':height,
        'product':device.product,
        'brand':device.brand,
        'release':device.release,
        'android_Id':device.getAndroidId(),
        'tiktok_id':scrapUser,
    },{
        headers: {
            'Accept': 'application/jgwl.douyin.v1+json'
        }
    });
}

// 根据抖音号获取账户信息
function getUserInfo(scrapUser){
    cons.log('获取用户信息中');
    console.log(scripHost+getConfigUrl+scrapUser+'&type='+selecMode);
    var res = http.get(scripHost+getConfigUrl+scrapUser+'&type='+selecMode,{
        headers: {
            'Accept': 'application/jgwl.douyin.v1+json'
        }
    });
    res = res.body.json();
    console.log(scripHost+getConfigUrl+scrapUser+'&type='+selecMode);
    if(!res||!res.said){
        alert("当前账户未添加到后台，或未设置话述，请联系管理员");
        exit();
    }
    clock = res.time_range;
    said = res.said;
    scrapArea = res.scrap_area;
    console.log(res)
    return res;
}

function sortNumber(a,b)
{
    return a - b
}

// 获取当前脚本用户抖音号
function getScriptUser(){
    if(startApp()){
        sleep(4000);
        while(!className("android.widget.RelativeLayout").find()){
            sleep(6000);
        }
        // 阻塞一下，等待首页加载完毕
        while(!className("android.widget.TextView").textStartsWith("首页").findOne(1000)){
            console.log('点击我')
            back();
            sleep(500);
        }
        click("首页");
        sleep(1000);
        while(!className("android.widget.TextView").text("编辑资料").exists()){
            click("我");
            sleep(5000);
        }
        console.log('个人主页')
        scrapUser = className("android.widget.TextView").textStartsWith("抖音号").findOne().text();
        scrapUser = scrapUser.substring(4).replace(/^\s+|\s+$/g,"");
        console.log("当前抖音号",scrapUser);
        return scrapUser;
    }
}

function randomNum(minNum,maxNum){ 
    switch(arguments.length){ 
        case 1: 
            return parseInt(Math.random()*minNum+1,10); 
        break; 
        case 2: 
            return parseInt(Math.random()*(maxNum-minNum+1)+minNum,10); 
        break; 
            default: 
                return 0; 
            break; 
    } 
}

function say(said){
    sleep(4500);
    cons.log('打开看看');
    // 粘贴板没有内容，重试三次，不行跳过
    var tt = 3;
    while(!className("android.widget.TextView").textStartsWith("打开看看").findOne(6000)){
        setClip('');
        tt--;
        if(tt == 0) return false;
        back();
        back();
        back();
        home();
        sleep(3000);
        setClip(userHome+'&t='+parseInt(Math.random()*10000));
        sleep(3000);
        if(startApp()){
            cons.log("重启")
        }
    }
    sleep(1500*sleepWait);
    className("android.widget.TextView").textStartsWith("打开看看").findOne().click();
    cons.log('进入个人主页');
    setClip('');
    // 阻塞一下，确保个人页面加载完成了
    while(!className("android.widget.TextView").textStartsWith("抖音号").findOne(30000)){
        return true;
    }
  
    if(text("已重置").exists()){
      //要支持的动作
      cons.log("跳过重置账号")
      return true;
    }

    if(selecMode == 1) return focus();


    cons.log('开始打招呼操作');
    click(width*x1,width*y1)
  
    sleep(2000*sleepWait);
    var a = className("android.widget.TextView").textStartsWith("抖音号").findOne(2000).bounds();
    click(width*x2,a.top);
    sleep(2000*sleepWait);

    reply(0);
    return true;
  }


function checkDouYinV(){
    var textView = className("android.widget.TextView").find()
    var afterHandle = 0;
    var handleNow = 0;
    textView.forEach(texts=>{
        if(texts.text()=="以后再说"){
            afterHandle = 1;
        }
        if(texts.text()=="立即升级"){
            handleNow = 1;
        }
    })
    if(afterHandle==1 && handleNow==1){
        text("以后再说").click()
    }

    if(text("用户隐私政策概要").exists()){
        //要支持的动作
        click("同意")
    }

    if(className("android.widget.TextView").textContains("不错过你的每一条私信").exists()){
        //要支持的动作
        click("取消")
    }

    if(className("android.widget.TextView").textContains("进入青少年模式").exists()){
        //要支持的动作
        click("我知道了")
    }

    // console.log('忽略弹框中');
    if(asking) timeOut++;
    //cons.log("超时时间:"+timeOut);
    if(selecMode != 6 && timeOut > 180){
        notice('超时，重启脚本','',1)
        cons.log('超时，重启脚本');
        alert('操作超时，请重启脚本');
        exit();
    }
}

function getScrapList(url,type){
    try {
        var res = http.post(scripHost+url, {
            "tiktok_id": scrapUser,
        },{
            headers: {
                'Accept': 'application/jgwl.douyin.v1+json'
            }
        });
        return res.body.json();
    } catch (error) {
        console.log(error)
    }
    return false;
}

// 点赞
function sayHello(uid){
    var uidArray = [uid];
    doSave(saveFansUrl,uidArray)
    if(say(said)){
        return sayCallBack(uid);
    }else{
        cons.log("点赞失败");
    }
    return true;

}

function focus(){
    var zan = false;
    sleep(2000);
    click("作品");
    sleep(4000);
    if(!className("android.widget.TextView").textContains("还没有发布过作品").exists()){
        click(width*x5,height*y5);
        sleep(15000);

        for(var j=0;j<5;j++){
            click(width*0.5,height*0.5);
            sleep(100);
        }
        while(!className("android.widget.TextView").textStartsWith("抖音号").findOne(6000)){
            zan = true;
            back();
        }
        
    }
    return zan;
}

// 点赞完成回调
function sayCallBack(uid){
    sleep(4000);
    var sendIt = true;
    if(className("android.widget.TextView").textContains("休息一下").exists()){
        console.log("消息发不出去了")
        sendIt = asking = false;
        notice('','',3);
    }
    back();
    sleep(1500);
    back();
    sleep(1500);
    back();
    
    cons.log("给"+uid+"点赞完成");
    return sendIt;
}

// 点赞
function doAsk(list){
    cons.log("开始点赞",list);
    for(var i in list){
        home();
        sleep(2500);
        
        userHome = shareLink+list[i]+'?r='+parseInt(Math.random()*10000);
        cons.log(userHome);
        setClip(userHome);
        
        if(startApp() && sayHello(list[i])){
            cons.log("给"+list[i]+"点赞成功");
            timeOut = 0;
            sleep(4000);
        }else{
            break;
        }

    }
    return true;
}

function notice(msg,rep,type){
    try {
        http.post(scripHost+noticeUrl, {
            "uid": scrapUser,
            "msg": msg,
            "rep": rep,
            "type": type
        },{
            headers: {
                'Accept': 'application/jgwl.douyin.v1+json'
            }
        });
    } catch (error) {
        cons.log(error)
    }

    return true;
}

function reply(num){
    // 如果是自动消息回复
    if(num == 1){
        click(width*x5,height*y5);
        var cc = getReplyNum();
        if(cc<2) return true;
        var nickName = className('android.widget.TextView').findOne().text();
        if(nickName == '消息助手') return;
        if(cc > 3) {
            console.log('获取回复内容');
            var content = classNameEndsWith("widget.RecyclerView").findOne().child(cc-1).find(className("android.widget.TextView"));
            var rep = '';
            content.forEach(texts=>{
                rep += texts.text()+' ';
            })
            return notice(nickName,rep,2);
        }
    }

    if(!textStartsWith("发送消息").exists()){
        return;
    }

    var saidArray = said.split('|');
    var ss = saidArray[num].split('&')
    var sendPosY = textStartsWith("发送消息").findOne(2000).bounds().centerY();

    setText(ss[Math.round(Math.random()*(ss.length-1))]);
    sleep(3500*sleepWait);
    click(width*x3,sendPosY)
    click(width*x3,height*y3)

}

// 各个手机版本检测消息回复数量 默认返回0
function getReplyNum(){
    if(device.product == 'cactus' && device.brand == 'xiaomi'){
        return classNameEndsWith("widget.RecyclerView").findOne().childCount();
    }
    return 0;
}

function sayReady(){
    // 防止已退出没检测到的死循环
    var returnLimit = 5;    
    while(!className("android.widget.TextView").textStartsWith("消息").findOne(4500) && returnLimit>0){
        console.log('退出');
        returnLimit -= 1;
        back();
    }
    
    back();
    back();
    back();
    sleep(4000);

    while(!className("android.widget.TextView").textStartsWith("消息").findOne(5000)){
        if(startApp()){
            console.log("重启")
        }
    }
    sleep(8000);
    while(!className("android.widget.TextView").textStartsWith("首页").findOne(3500)){
        console.log('点击我')
        back();
    }
    click('首页')
    click('消息');
}

function checkAction(){
    sleep(1000);
    click(width*x4,height*y4);
    click(width*x4,height*y4);
    sleep(2000)
    var countNews = classNameEndsWith("widget.RecyclerView").className('android.widget.LinearLayout').findOne().child(2).child(1).childCount();

    if(countNews == 1){
        classNameEndsWith("widget.RecyclerView").className('android.widget.LinearLayout').findOne().click()
        return true;
    }
    checkNews = false;
    return false;
}

// 保存点赞结果
function doSave(url,list){
    try{
        var res = http.post(scripHost+url, {
            "tiktok_id": scrapUser
        },{
            headers: {
                'Accept': 'application/jgwl.douyin.v1+json'
            }
        });
        return res.statusCode;
    }catch(err){
        console.log(err)
    }
    return 500;

}

function autoReply(){
    var saidArray = said.split('|');

    if(saidArray.length > 1 && checkNews) {
        sayReady()
        while(checkNews && checkAction()){
            console.log("检查消息完成");
            sleep(2000)
            reply(1);
            back();
        }
    }
    console.log('自动回复结束',checkNews);
}

function init(){
    device.keepScreenOn();
    for(var i=1;i<3;i++){
        var yesterday = new Date();
        yesterday.setTime(yesterday.getTime()-24*60*60*1000*1);
        var yesterdayTime = yesterday.getFullYear()+'-'+yesterday.getMonth()+'-'+yesterday.getDate();
        var oldFile = '/storage/emulated/0/douyin_ask_log-'+yesterdayTime+".log";
        if(files.exists(oldFile)){
            files.remove(oldFile);
        }
    }
    // cons.show()
    console.setPosition(100, 200)
    
    var w = floaty.window(
        <horizontal gravity="center">
            <button bg="#9BCD9B" textColor="white" w="*" text="脚本运行中"/>
        </horizontal>
    );
}

// 获取打招呼列表
// 打招呼
auto();
init();

var options = ["1.给自己的粉丝打招呼", "2.给自己的点赞评论粉丝点赞"]
var selecMode = dialogs.select("请选择一个操作", options);

if(selecMode == 0){
    getFansUrl = getFocusFansUrl;
    saveFansUrl = saveFocusFansUrl;
}else if(selecMode == 1){
    getFansUrl = getZanFansUrl;
    saveFansUrl = saveZanFansUrl;
}else if(selecMode == -1){
    toast("您取消了选择,退出脚本");
    exit();
}

threads.start(function(){
    //在新线程执行的代码
    while(true){
        // cons.log("检测更新");
        checkDouYinV();
        sleep(1000);
    }
});

// 获取当前脚本抖音号
if(scrapUser = getScriptUser()){
    logDevice();
    uInfo = getUserInfo(scrapUser);
    click("消息");
    home();
}

// 屏幕适配
setDeviceInfo();
var list;

while(list = getScrapList(getFansUrl)){
    checkNews = true;
    
    if(list.length == 0){
        alert("暂无可用数据,请确定数据已采集");
        sleep(30000)
    }else{
        // 其它类型粉丝及用户点赞
        asking = true;
        if(doAsk(list)){
            cons.log("完成一轮点赞");
        }
    }
    asking = false;
    getUserInfo(scrapUser);
    sleep(2500);
    // if(uInfo.user_id != 10) autoReply();
}
alert('脚本执行结束');
exit();