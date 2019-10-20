#!/usr/bin/python
# -*- coding: UTF-8 -*-
import requests
import urllib3
import json
import time
import re

urllib3.disable_warnings()

class ScrapComment:
    hostUrl = 'http://dy_api.jgyljt.com/api/'
    getVideoUrl = 'fans/getKSVideo'
    addFansUrl = 'addFans'
    compUrl ='fans/compKSVideo'
    sign = 'server_01' #脚本标识，用于区分其它脚本同时运行

    def __init__(self):
        print "script running"

    # 获取待采集视频列表
    def getVideoList(self):
        postData = {'sign':self.sign}
        rep = requests.post(self.hostUrl+self.getVideoUrl,data=postData)
        return rep.json()

    def parseSubComment(self,vid,scid,sp='""'):
        subUrl = 'https://live.kuaishou.com/graphql?operationName=SubCommentFeeds&variables={"photoId":%20"'+vid+'",%20"rootCommentId":%20"'+scid+'",%20"pcursor":%20"'+sp+'","%20count":%20200}&query=query%20SubCommentFeeds($photoId:%20String,%20$rootCommentId:%20String,%20$pcursor:%20String,%20$count:%20Int)%20{%20getSubCommentList(photoId:%20$photoId,%20rootCommentId:%20$rootCommentId,%20pcursor:%20$pcursor,%20count:%20$count)%20{%20pcursor%20subCommentsList%20{%20...BaseComment%20}%20}}fragment%20BaseComment%20on%20BaseComment%20{%20commentId%20authorId%20authorName}'
        header = {'Cookie': 'clientid=3; did=web_65123b46a4843957d6c227cd35118d34; client_key=65890b29; didv=1561095644017; Hm_lvt_86a27b7db2c5c0ae37fee4a8a35033ee=1561095647,1561101793; Hm_lpvt_86a27b7db2c5c0ae37fee4a8a35033ee=1561101793; kuaishou.live.bfb1s=9b8f70844293bed778aade6e0a8f9942',
        'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/74.0.3729.169 Safari/537.36'}
        
        try:
            response = requests.get(subUrl,headers=header,verify=False)
        except:
            print 'get sub error'
        else:    
            parseData = response.json()
            for p in parseData['data']['getSubCommentList']['subCommentsList']:
                self.fans[p['authorId']] = {'id':p['authorId'],'nickname':re.sub(r'[:[}&]', '_', p['authorName']),'gender':'0','signature':''}
        
            if parseData['data']['getSubCommentList']['pcursor'] != 'no_more' and parseData['data']['getSubCommentList']['pcursor'] != '':
                print "get next sub page"
                time.sleep(1)
                return self.parseSubComment(vid,scid,parseData['data']['getSubCommentList']['pcursor'])
            return self.fans
    
    # 递归解析数据并上传
    def parseDetail(self,vid,pp='""'):
        url = 'https://live.kuaishou.com/graphql?operationName=CommentFeeds&variables={%22photoId%22:%22'+vid+'%22,%22page%22:1,%22pcursor%22:'+pp+',%22count%22:200000}&query=query%20CommentFeeds($photoId:%20String,%20$page:%20Int,%20$pcursor:%20String,%20$count:%20Int)%20{shortVideoCommentList(photoId:%20$photoId,%20page:%20$page,%20pcursor:%20$pcursor,%20count:%20$count)%20{pcursor%20commentList%20{...BaseComment%20subCommentsPcursor%20subComments%20{commentId%20authorName%20authorId}}}}fragment%20BaseComment%20on%20BaseComment%20{commentId%20authorId%20authorName}'
        header = {'Cookie': 'clientid=3; did=web_65123b46a4843957d6c227cd35118d34; client_key=65890b29; didv=1561095644017; kuaishou.live.bfb1s=9b8f70844293bed778aade6e0a8f9942; Hm_lvt_86a27b7db2c5c0ae37fee4a8a35033ee=1561095647,1561101793,1561186327; Hm_lpvt_86a27b7db2c5c0ae37fee4a8a35033ee=1561186327',
        'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/74.0.3729.169 Safari/537.36'}
        print url
        try:
            response = requests.get(url,headers=header,verify=False)
            parseData = response.json()
        except:
            print 'get comment error'
        else:
            for p in parseData['data']['shortVideoCommentList']['commentList']:
                self.fans[p['authorId']] = {'id':p['authorId'],'nickname':re.sub(r'[:[}&]', '_', p['authorName']),'gender':'0','signature':''}
                if len(p['subComments']) > 0:
                    for sp in p['subComments']:
                        print "find sub"
                        self.fans[sp['authorId']] = {'id':sp['authorId'],'nickname':re.sub(r'[:[}&]', '_', sp['authorName']),'gender':'0','signature':''}
                    if p['subCommentsPcursor'] != 'no_more' and p['subCommentsPcursor'] != '':
                        self.parseSubComment(vid,p['commentId'],p['subCommentsPcursor'])

            if parseData['data']['shortVideoCommentList']['pcursor'] != 'no_more':
                print "get next page"
                time.sleep(1)
                return self.parseDetail(vid,parseData['data']['shortVideoCommentList']['pcursor'])
            return self.fans

    # 完成一个视频采集修改采集状态
    def comScrap(self,id):
        print 'comp ' + str(id)
        try:
            requests.post(self.hostUrl+self.compUrl,data={'id':id})
        except:
            print "network error"
        
    # 解析评论列表
    def parseList(self,vList):
        print 'parse data'

        for jp in vList:
            self.fans = {}
            postData = {'userfans':{'from':jp['photo_id'],'fansType':'1','fans':self.parseDetail(jp['photo_id'])}}
            try:
                res = requests.post(self.hostUrl+self.addFansUrl,json=postData,headers={'Content-Type':'application/json'})
                print res.text
            except:
                print 'net error'
            else:
                self.comScrap(jp['id'])
            time.sleep(5)

    # 执行入库，运行脚本后会一直请求，直到没有数据
    def runScript(self):
        vList = self.getVideoList()
        if len(vList) != 0:
            self.parseList(vList)
            time.sleep(5)
            return self.runScript()

sc = ScrapComment()
sc.runScript()

