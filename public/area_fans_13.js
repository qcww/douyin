// console.show();
var shareLink = 'https://www.iesdouyin.com/share/user/';//分享链接
var userId = '2156244013';//当前用户的抖音号
var hostUrl = "http://douyin.jgyljt.com/public"
var serverName = "抖音区域粉";
var x=120;
var y=1286;
auto();
var appConfigUrl = hostUrl + '/api/scrap_user/getAppConfig';//获取版本信息所需的链接
var appId = 4;//数据库脚本ID
var version = 3.1;//版本号
checkUpdate();//版本检测
var thread = threads.start(function () {
    while (true) {
        checkDouYinV();
    }
});
autoStart();


/**
 * 开始运行：打开应用，点击消息，进入粉丝页
 */
function autoStart() {
    home();
    sleep(1000)
    click(x,y);
    // var name = getPackageName("抖音短视频"); //应用名称
    // console.log(name);
    // app.launchApp("抖音短视频")
    // console.log(app.launch('com.ss.android.ugc.aweme')); //打开应用
    sleep(2000)
    // checkDouYinV()
    var area = getArea();
    getFansAndCity(area[1], area[0])
    //停止线程执行
    thread.interrupt();
    exit();
}

/**
 * 开始搜索关键词
 */
function searchKeyworld(text) {
    sleep(2000)
    className("android.widget.FrameLayout").className("android.widget.RelativeLayout").className("android.widget.LinearLayout").className("android.widget.FrameLayout").className("android.widget.ImageView").findOne().click();//点击首页右上角放大镜，搜索
    // click(927, 103, 1047, 223)
    sleep(2000)
    className("android.widget.LinearLayout").className("android.widget.FrameLayout").className("android.widget.EditText").findOne().click();//点击输入框以便输入文字
    sleep(1000)
    setText(text)
    sleep(1000)
    click(device.width - 50, device.height - 120);//点击搜索
    sleep(2000)
}
/**
 * 点击粉丝，开始执行
 */
function searchStart(douyinNum, area) {
    searchKeyworld(douyinNum)//开始搜索
    sleep(1500)
    click(douyinNum)
    sleep(2000)
    getNumAndCity(douyinNum, area, '')
}
//获取地区和抖音号
function getArea() {
    className("android.widget.LinearLayout").text("我").findOne().parent().parent().parent().click();//安卓7.0以下写法
    var userId = className("android.widget.LinearLayout").textStartsWith("抖音号:").findOne().text();
    userId = getCaption(userId);//当前登陆的抖音号
    sleep(1000)
    var url = hostUrl + "/api/scrap_user/getScrapUser";
    var res = http.get(url + "?uid=" + userId);

    var html = res.body.json();//获取接口参数
    var scrap_area = html.data.scrap_area;//地区
    var userId = html.data.uid;
    // var scrap_area = "合肥";
    return [scrap_area, userId];
}
/**
 * 获取该粉丝的抖音号和地区
 * @param {*} city ：预设城市，有值的话需要进行匹配
 */
function getNumAndCity(douyinNum, citys, autoStart) {
    if (className("android.widget.TextView").textContains("已重置").find()[0]) {
        back();
        sleep(1000)
        home();
        return;
    }
    if (text("这是私密帐号").find()[0]) {
        back();
        sleep(1000)
        home();
        return;
    }
    if (className("android.widget.TextView").textContains(citys).find()[0]) {
        var fansNum = className("android.widget.TextView").textStartsWith("粉丝").findOne().parent().children()[0].text();//通过“粉丝”获取父元素再查找首个子元素，获取其文本
        if (fansNum < 1 || (fansNum && fansNum.indexOf("w") > -1) || fansNum > 400) {
            back();
            sleep(1000)
            home();
            return;
        } else {
            className("android.widget.TextView").textStartsWith("粉丝").findOne().parent().click();//通过“粉丝”获取父元素并点击
            sleep(2000)
            if (className("android.widget.TextView").textContains('TA还没有粉丝').find()[0]) {
                back();
                sleep(1000)
                home();
                return;
            } else {
                sleep(2000)
                swipeBottomToTop()
                sleep(1000)
            }
        }
        toHome(2)
        home();
        sleep(2000)
        if (autoStart) {
            return true;
        } else {
            getFansAndCity(citys)
        }
    } else {
        back();
        sleep(1000)
        home();
    }
}
/**
 * 从接口获取粉丝抖音号和城市
 */
function getFansAndCity(userId, area) {
    sleep(2000)
    home();
    sleep(2000);
    var list = getScrapList(userId, area);
    if (list) {
        doAsk(userId, list, area);
    } else {
        alert("暂无运行数据")
        //停止线程执行
        thread.interrupt();
        exit()
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
 * 获取抖音号
 * 函数功能：获取字符串指定符号后面的字符
 * @param {*} obj 字符串
 */
function getCaption(obj) {
    var index = obj.lastIndexOf("\:");
    obj = obj.substring(index + 1, obj.length);
    return obj;
}
/**
 * 滑屏:从下往上滑
 * num:滑动次数
 * swipeCate：类别，1初次滑屏，2二次滑屏，.....
 */
function swipeBottomToTop() {
    swipe(device.width - 50, device.height - 150, device.width - 50, 0, 1000)
    sleep(1500)
    var noneText = className("android.widget.TextView").text("暂时没有更多了").find()
    if (!noneText[0]) {
        noneText = className("android.widget.TextView").text("没有更多了~").find()
        if (!noneText[0]){
            swipeBottomToTop()
        }
    } else {
        return
    }
}
/**
 * 滑屏：从右往左滑
 * num:从下往上滑动的次数
 */
function swipeRightToLeft(num) {
    swipe(device.width - 10, device.height / 2, 0, device.height / 2, 200);
}


/**
 * 以下copy
 */
function startApp() {
    click(x,y);
    // var name = getPackageName("抖音短视频"); //应用名称
    // app.launch(name); //打开应用
    device.keepScreenOn()
    sleep(3000);
    return true;
}
//获取要抓取的粉丝列表
function getScrapList(uid, area) {
    var r = http.get(hostUrl + "/api/user/noGetActionFans?area=" + area + "&uid=" + uid);
    var res = r.body.json();
    var userList = [];
    if (res.code == 0) {
        alert(res.msg);
        exit();
    }
    if (res.data) {
        res.data.forEach(val => {
            userList.push(val.tiktok_uid)
        })
    }
    return userList;
    return ['77329674637', '105322364902', '4454840477947355', '98607903080'];
}

// 唤起抓取
function autoGetList(uid, area) {
    sleep(1000)
    var cnt = 3;
    while (cnt > 0) {
        var txtView = className("android.widget.FrameLayout").className("android.widget.FrameLayout").className("android.widget.FrameLayout").className("android.widget.RelativeLayout").className("android.widget.LinearLayout").className("android.widget.LinearLayout").className("android.widget.TextView").find()[4];
        if (txtView) {
            txtView.click();
            sleep(2000);
            getNumAndCity("", area, 'autoStart')
            sleep(2000);
            getCallBack(uid);
            cnt = 0;
            return true;
        } else {
            sleep(2000);
            cnt--;
        }
    }

}

// 采集完成回调
function getCallBack(uid) {
    // console.log("采集" + uid + "粉丝完成")
    var res = http.post(hostUrl + "/api/user/updateNoGetActionFans", {
        'uid': uid
    })
    return res;
}
/**
 * 
 * @param {当前登陆者的抖音号} userId 
 * @param {要采的粉丝uid数组} list 
 * @param {目标区域} area 
 */
function doAsk(userId, list, area) {
    for (var i in list) {
        setClip(shareLink + list[i]);
        startApp();
        sleep(2000)
        autoGetList(list[i], area)
        sleep(2000)
        home();
    }
    var lists = getScrapList(userId, area)
    // console.log(lists)
    if (lists) {
        doAsk(userId, lists, area);
    } else {
        alert("后台全部运行完毕！")
        //停止线程执行
        thread.interrupt();
        exit()
    }
}
/**
 * 如果弹出升级提示，则点击“以后再说”进行忽略操作
 */
function checkDouYinV() {
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
/**
 * 执行以后发送钉钉提醒
 * cate:完成类型=》1：全部运行完毕，2：运行完毕,3：其它情况
 * userId:用户抖音号，用来获取对应配置的钉钉号
 * server_name:脚本名称
 */
function endRun(cate, userId) {
    http.get(hostUrl + "/api/index/endRun?cate=" + cate + "&userid=" + userId + "&server_name=" + serverName);
    return true;
}
/**
 * 版本检测
 */
function checkUpdate() {
    var res = http.get(appConfigUrl);
    res = res.body.json();
    var returnData = res.data;
    for (var i in returnData) {
        if (returnData[i].id == appId && returnData[i].version != version) {
            alert("当前版本不是最新版本，请重新安装")
            app.openUrl(returnData[i].download_link)
            //停止线程执行
            thread.interrupt();
            exit();
        }
    }
    return true;
}
