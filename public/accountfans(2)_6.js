auto();
var shareLink = 'https://www.iesdouyin.com/share/user/';//分享链接
var getJpUrl = "http://dyapi.bjbctx.com";//获取竞品粉的链接(线上)
var serverName = "采集账号粉丝采集";

var width = device.width;
var height = device.height;
var x = 0.15;
var y = 0.9;
// checkUpdate();//版本检测
var thread = threads.start(function () {
    while (true) {
        closeWindow();
    }
});
home();
sleep(1500)
var scrapUser = getScriptUser();
home();
sleep(2000);
if(!scrapUser){
    alert('获取个人信息失败！');
    exit();
}
/**
 * 开始运行：打开应用，点击消息，进入粉丝页
 */
function getScriptUser() {
    click(width*x,height*y);
    console.log('启动应用')
    sleep(5000)

    sleep(1500)
    console.log('点击我')
    while(!className("android.widget.TextView").text("编辑资料").exists()){
        click("我");
        sleep(5000);
    }
    scrapUser = className("android.widget.TextView").textStartsWith("抖音号:").findOne().text();
    scrapUser = scrapUser.substring(4).replace(/^\s+|\s+$/g,"");
    console.log("当前抖音号",scrapUser);
    setClip(''); // 第一次打开软件清空下粘贴板
    return scrapUser;
}
console.log('请求数据中')
var res = http.post(getJpUrl+'/api/getscraplist',{'tiktok_id':scrapUser},{
    headers: {
        'Accept': 'application/jgwl.douyin.v1+json'
    }
});
var ret = res.body.json();
// if(ret.data.length<=0){
//     alert('没有数据！');
//     exit();
// }
console.log(ret);
doAsk(ret);

/**
 * 
 * @param {当前登陆者的抖音号} userId 
 * @param {要采的粉丝uid数组} list 
 * @param {目标区域} area 
 */
function doAsk(list) {
    for (var i in list) {
        setClip('');
        sleep(1000)
        console.log('采集账号：',list[i]);
        setClip(shareLink + list[i]);
        sleep(2000);
        startApp();
        sleep(2000)

        collectFans();
        sleep(2000)
        home();
    }
    alert("采集完毕！");
    exit()
}
/**
 * 打开粉丝界面采集
 */
function collectFans(){
    sleep(1000)
    var tt = 3;
    while(!className("android.widget.TextView").textStartsWith("打开看看").findOne(6000)){
        setClip('');
        tt--;
        if(tt == 0) return false;
        back();
        back();
        sleep(100);
        back();
        back();
        home();
        sleep(7000);
        setClip(userHome);
        sleep(7000);
        if(startApp()){
            cons.log("重启")
        }
    }
    sleep(1500);
    className("android.widget.TextView").textStartsWith("打开看看").findOne().click();
    sleep(1500)
    var fansNum = className("android.widget.TextView").text("粉丝").findOne().parent().children()[0].text();//通过“粉丝”获取父元素再查找首个子元素，获取其文本
    if (fansNum < 1) {
        toHome(3)
        home();
        return;
    }
    className("android.widget.TextView").text("粉丝").findOne().parent().click();//通过“粉丝”获取父元素并点击
    sleep(3000)
    if (className("android.widget.TextView").textContains('TA还没有粉丝').find()[0]) {
        toHome(3);
        home();
    } else {
        sleep(2000)
        swipeBottomToTop()
    }
}
/**
 * 滑屏:从下往上滑
 * num:滑动次数
 * swipeCate：类别，1初次滑屏，2二次滑屏，.....
 */
function swipeBottomToTop() {
    sleep(2000)
    swipe(device.width - 50, device.height - 150, device.width - 50, 0, 1000)
    sleep(1500)
    var noneText = className("android.widget.TextView").text("暂时没有更多了").find();
    var noneText2 = className("android.widget.TextView").text("没有更多了~").find();
    var netError = className("android.widget.TextView").text("加载失败，点击重试").find();
    if (!noneText[0] && !netError[0] && !noneText2[0]) {
        swipeBottomToTop()
    } else {
        toHome(3);
        home();
    }
}
/**
 * 回首页
 * backNum 需要点击返回的次数
 */
function toHome(backNum) {
    for (var i = 0; i < backNum; i++) {
        back()
        sleep(750)
    }
}

/**
 * 以下copy
 */
function startApp() {
    home();
    sleep(500);
    click(width*x,height*y);
    device.keepScreenOn()
    sleep(3000);
    return true;
}

/**
 * 如果弹出升级提示，则点击“以后再说”进行忽略操作
 */
function closeWindow() {
    var textView = className("android.widget.TextView").find()
    var afterHandle = 0;
    var handleNow = 0;
    if (textView[0]) {
        textView.forEach(texts => {
            if (texts.text() == "以后再说") {
                afterHandle = 1;
            }
            if (texts.text() == "立即升级") {
                handleNow = 1;
            }
        })
        if (afterHandle == 1 && handleNow == 1) {
            text("以后再说").click()
        }
    }

}