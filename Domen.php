<?php
//parser apk
$arrApkInfo = Tool_CheckApkInfo::checkApk($strUrl);

/*
说明：参数$strUrl是下载apk的链接地址，必须要下载到本地才能解析，不可远程解析
	开源方法Tool_ApkParser()解析出的apk包信息将label和icon转为标记值（"@7F070066", "@7F0202B4"),所以需要linux下的aapt解析apk工具去接续label和icon值

注：此接口两种方法都用到，因第一思路用开源方法解析apk，最后发现label和icon值不对，故在Tool_ApkParser()方法的基础上直接增加aapt解析命令，如果怕麻烦可直接用aapt解析，把其他如package等信息也用政治表达式取出来

*/

