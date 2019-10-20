auto();
var Mobile = function(){
    var scripHost = 'http://dy_api.jgyljt.com/';
    var getConfigUrl = 'api/getScrapUser?tiktok_id=';
    var getFansUrl = 'api/user/getUser';
    var saveFansUrl = 'api/user/saveUser';
    var scrapUser = '';
    var obj = new Object();
    var width = device.width;
    var height = device.height;
    var acountId = '';
    var errroNum = 5; // 允许出错重启次数
    var pApp = {x:0.15,y:0.89}; // app坐标位置
    var checkNews = true;
    var firstAsk = true;
    var asking = true;
    var saidArray = [];

    obj.checkWindow = function (){
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
    
        if(className("android.widget.TextView").textContains("进入青少年模式").exists()){
            //要支持的动作
            click("我知道了")
        }
    }

    getAcount = function(){
        scrapUser = id('tab_name').findOne().text();
        var res = http.get(scripHost+getConfigUrl+scrapUser,{
            headers: {
                'Accept': 'application/jgwl.douyin.v1+json'
            }
        });
        res = obj.scrapUser = res.body.json();
        if(!res||!res.said){
            console.log(scripHost+getConfigUrl+scrapUser);
            alert("当前账户未添加到后台，或未设置话述，请联系管理员");
            exit();
        }
        saidArray = res.said.split('|');

        return res;
    }

    getAskList = function(){
        try {
            var res = http.post(scripHost+getFansUrl, {
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

    obj.init = function(){
        home();
        device.keepScreenOn();
        console.log('初始化完成');
        startApp()
    }

    restart = function(){
        if(errroNum == 0) {
            alert('自动重启次数过多，请检查网络');
            exit();
        }
        console.log('错误重启一次');
        back();
        back();
        back();
        back();
        sleep(1000);
        home();
        sleep(15000);
        errroNum --;
        return obj.runAsk();
    }

    toSearchPage = function(getAccount){
        sleep(1000);
        id('left_btn').click();
        sleep(2500)
        if(getAccount) getAcount();
        click('查找');
    }

    searchFans = function(first,number){
        sleep(1500);
        if(first != 0){
            click('取消');
        }
        sleep(2000);
        var pInput = classNameEndsWith("android.widget.TextView").textStartsWith("大家都在看").findOne(12000).bounds();
        click(pInput.centerX(),pInput.centerY());
        sleep(1500);
        setText(number);
        
        sleep(2500);
        click('搜索');
        sleep(5000);
        if(id('description').exists()){
            return false;
        }
        sleep(10000);

        if(classNameEndsWith("android.widget.TextView").textStartsWith("关注").exists()){
            var pSendPhoto = classNameEndsWith("android.widget.TextView").textStartsWith("关注").findOne().bounds();
        }else if(id('right_arrow').exists()){
            var pSendPhoto = id('right_arrow').findOne().bounds();
        }

        click(width/2,pSendPhoto.centerY());
        sleep(3500);
        classNameEndsWith("android.widget.ImageButton").findOnce(1).click();
        sleep(8500);
        return true;
    }
    sortNumber = function (a,b)
    {
        return a - b
    }

    randomNum = function (minNum,maxNum){ 
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

    // 定时睡眠
    doRest = function (returnTime){
        var clock = obj.scrapUser.time_range;
        if(clock == ''){
            alert("请检查当前账号打招呼设置");
            exit();
        }
        var clockArray = clock.split(',');
        console.log('打招呼时间',clockArray);

        clockArray = clockArray.sort(sortNumber);
        var d = new Date();
        var h = d.getHours();
        var m = d.getMinutes();

        var nextHour = 24;

        for(var i in clockArray){
            if(clockArray[i] > h){
                nextHour = clockArray[i];
                askTime = nextHour;
                break;
            }
        }

        if(h>=clockArray[clockArray.length - 1]){
            askTime = clockArray[0];
            nextHour += parseInt(clockArray[0]);
        }
        if(!returnTime){
            firstAsk = confirm("脚本将在"+askTime+":00开始打招呼，点击确定立即开始打招呼");
        }
        return (nextHour - h - 1)*3600 + (60 - m)*60;
    }

    doAskAction = function(i){
        setText(saidArray[i]);
        sleep(500);
        click('发送')
    }

    callBack = function(){
        back();
        sleep(1500);
        back();
        sleep(2000);
    }

    // 保存打招呼结果
    doSave = function(){
        try{
            var res = http.post(scripHost+saveFansUrl, {
                "tiktok": scrapUser
            },{
                headers: {
                    'Accept': 'application/jgwl.douyin.v1+json'
                }
            });
            console.log('保存打招呼结果')
            return res.statusCode;
        }catch(err){
            console.log(err)
        }
        return 500;

    }

    startApp = function(){
        home();
        sleep(2500);
        click(width*pApp.x,height*pApp.y)
        return launch("com.smile.gifmaker");
    }

    doAsk = function(){
        console.log('我在打招呼');
        toSearchPage(true);
        doRest(false);
        
        while(askList = getAskList()){
            console.log('获取到数据了')
            if(askList.length == 0){
                alert('暂无可用数据，点击确定停止脚本');
                exit();
            }
            checkNews = true;
            
            // 一直休息到最近的下个时间节点开始打招呼
            if(!firstAsk){
                restTime = doRest(true) + randomNum(60,300);
                console.log("睡眠 "+restTime+"s 后继续打招呼")
            
                sleep(restTime*1000);
                console.log('睡眠结束，开始打招呼');
            }
            firstAsk = false;
            console.log('开始遍历打招呼')
            for(var i in askList){
                $res = searchFans(i,askList[i]);
                if(!$res) continue;
                console.log(askList[i]);
                doAskAction(0);
                sleep(1000)
                callBack();
                doSave();
                sleep(8000);
            }
            if(saidArray.length > 1) readyReply();
        }
        
    }

    readyReply = function (){
        back();
        back();
        back();
        back();
        home();
        sleep(1000);
        if(startApp()){
            toNewsPage()
        }
        sleep(2000);
        tab = id('tabs').findOne(2000).find(className("android.view.View"))[2].bounds()
        autoReply(tab.centerX(),tab.centerY())
    
    }
    toNewsPage = function(){
        id('left_btn').click();
        sleep(2500)
        click('私信');
    }
    
    getReply = function (){
        var count = id('recycler_view').findOne().childCount();
        var num = id("avatar").desc('私信').find().length;
        if(num == 0) return num;
        return count;
    }
    
    autoReply = function (x,y){
        while(asking){
            click(x,y);
            sleep(100);
            click(x,y);
            sleep(5000)
    
            var p = id('sliding_layout').findOne(2000).bounds();
            click(p.centerX(),p.centerY())
            sleep(3000);
            if(getReply() > 3|| getReply() == 0){
                asking = false;
                break;
            }
            doAskAction(1);
            sleep(2000)
            back();
            sleep(3000);
        }
        sleep(1000);
        back();
        toSearchPage(false)
    
    }

    obj.runAsk = function(){
        try {
            startApp();
            doAsk();
        } catch (error) {
            console.log(error)
            return restart();
        }
    }

    return obj;
}

var m = new Mobile();
m.init();
threads.start(function(){
    //在新线程执行的代码
    while(true){
        // cons.log("检测更新");
        m.checkWindow();
        sleep(1000);
    }
});
m.runAsk();