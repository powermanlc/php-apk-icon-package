<?php
/**
 * @file CheckApkInfo.php
 * @Synopsis  下载，解析apk
 * @date 2018-06-20
 */
class Tool_CheckApkInfo {
    static $strDir = "/home/www/ekwing/data/apk/";
    static $strIconUrl = "http://www.ekwing.com/data/apk/icon/";
    /**
     * @synopsis先下载再解析，此解析方法将label和icon转为标记值("@7F070066", "@7F0202B4")，需要重新做处理
     * Tool_ApkParser 此方法只能解析本地已下载的apk应用，故需要下载步骤
     * 下载与解析功能可单独分开，下载返回即可插入数据库，后台自动解析，省去前端心跳步骤
     * eg: <application android:theme="@android:0103012B" android:label="@7F070066" android:icon="@7F0202B4" ... >
     * @param $strUrl
     * @return array|bool
     */
    public static function checkApk($strUrl) {
        $apk = pathinfo($strUrl, PATHINFO_BASENAME);
        $apk = self::$strDir . $apk;
        if (!file_exists($apk)) {
			//说明：因apk大小不等，服务器下载时间可能会超时，故接口直接返回，下载过程在后台自动进行，本人前端程序采用心跳每5秒请求一次接口，下载完成即可解析，可优化。
            $command = "wget -b -P ".  self::$strDir . " ". $strUrl . " -o ".  self::$strDir . "apk.log";
            exec($command, $return);
        }

        if (!file_exists($apk)) {
            return false;
        }
        $arrApkInfo = array();
        $objApkTool = new Tool_ApkParser();
        $apkInfo = $objApkTool->open($apk);
        if (!$apkInfo) {
           return false;
        }
        $arrApkInfo['package'] = $objApkTool->getPackage();
        $arrApkInfo['version_name'] = $objApkTool->getVersionName();
        $arrApkInfo['version_code'] = $objApkTool->getVersionCode();
        $arrApkInfo['app_name'] = '';
        $arrApkInfo['min_sdk_version']= $objApkTool->getMinSdkVersion();
        $arrApkInfo['icon']= '';
        $arrApkInfo['md5'] = md5_file($apk);

        $arrAaptReturn = self::aaptCheckApk($apk);
        if (is_array($arrAaptReturn)) {
            extract($arrAaptReturn);
            if (isset($appName)) {
                $arrApkInfo['app_name'] = $appName;
            }
            if (isset($icon)) {
                $arrApkInfo['icon'] = $icon;
            }
        }
        return $arrApkInfo;
    }

    /**
     * @synopsis 使用aapt解析apk包的icon和label信息,preg_match匹配结果
     * eg: application: label='翼课学生' icon='res/drawable-hdpi-v4/ic_launcher.png'
     *     launchable-activity: name='com.ekwing.students.activity.WelAct'  label='翼课学生' icon=''
     *  application-label-zh-CN:'护眼宝防蓝光'
     * @param $apk
     * @return array
     */
    public static function aaptCheckApk($apk) {
        $arrApkInfo = array();
        $command = "/usr/local/apktool/aapt dump badging " . $apk;
        $output = shell_exec($command);
        preg_match('/application: label=\'([^\'\"]+)\'/', $output, $appName);
        if (is_array($appName) && isset($appName[1])) {
            $arrApkInfo['appName'] = $appName[1];
        }

        $output = shell_exec($command);
        preg_match('/application-label-zh-CN:\'([^\'\"]+)\'/', $output, $zhAppName);  //中文名称
        if (is_array($zhAppName) && isset($zhAppName[1])) {
            if ($zhAppName[1] != $arrApkInfo['appName']) {
                $arrApkInfo['appName'] = $zhAppName[1];
            }
        }

        $out = shell_exec($command);
        preg_match('/icon=\'([^\'\"]+)\'/', $out, $icon);
        if (is_array($icon) && isset($icon[1])) {
            $iconReturn = static::getFileFromApk($apk, $icon[1]);
            if ($iconReturn) {
                $arrApkInfo['icon'] = $iconReturn;
            }
        }

        return $arrApkInfo;
    }

    /**
     * @synopsis 从Apk包中提取指定文件 (apk icon图标地址)
     * @param $apk apk文件
     * @param $icon apk文件中相应文件相对路径
     * @return string | false
     */
    public static function getFileFromApk($apk, $icon){
        $strApkName = pathinfo($apk, PATHINFO_BASENAME);
        $fileApkName = substr($strApkName, 0, strlen($strApkName)-4);
        $iconDir = self::$strDir . "icon/" . $fileApkName . "/";
        if (!file_exists($iconDir . $icon)) {
            $command = "unzip " . $apk . " " . $icon . " -d " . $iconDir;
            exec($command);
        }
        $iconUrl = self::$strIconUrl . $fileApkName . "/";
        $iconFile = $iconDir . $icon;
        if (file_exists($iconFile)) {
			//上传图标至阿里云并删除apk
        /*    $upRes = static::upApkIcon($iconFile, $fileApkName);
            if ($upRes) {
                self::removeApkFile($apk);
                self::removeApkFile($iconDir);
                self::removeApkFile(rtrim($iconDir, "/"));
                return HTTP . $upRes;
            }
		*/
            return  $iconUrl . $icon;
        }
        return false;
    }

    public static function upApkIcon($localIconFile, $iconFileName) {
        $upTool=new Tool_UpOss();
        $upTool->connect();
        return $upTool->uploadOss($localIconFile, "data/apk/icon/", $iconFileName . ".png");
    }

    public static function removeApkFile($path) {
        if (is_dir($path)) {
            $arrFile = scandir($path);
            foreach($arrFile as $file){
                if($file=='.' || $file=='..'){
                    continue;
                }
                if (is_dir($path . $file . '/')) {
                    self::removeApkFile($path . $file . '/');
                    @rmdir($path . $file . '/');
                } else {
                    unlink($path . $file);
                }
            }
        } else {
            unlink($path);
        }
    }

    /**
     * @synopsis 使用aapt解析apk包的icon和label信息,并用正则表达式grep awk匹配查找
     * eg: application: label='翼课学生' icon='res/drawable-hdpi-v4/ic_launcher.png'
     *     launchable-activity: name='com.ekwing.students.activity.WelAct'  label='翼课学生' icon=''

     */
    public function checkApkParserByGrep($apk) {
        $fileApkPath = pathinfo($apk, PATHINFO_DIRNAME);
        $strApkName = pathinfo($apk, PATHINFO_BASENAME);
        $fileApkName = substr($strApkName, 0, strlen($strApkName)-4);
        $fileApkParserName = $fileApkPath . '/' . $fileApkName . '.parser';
        if (!file_exists($fileApkParserName)) {
            $command = "/usr/local/apktool/aapt dump badging " . $apk . ' > ' . $fileApkParserName;
            exec($command, $return,$status);
            if ($status != 0) {
                return false;
            }
        }
        if (!file_exists($fileApkParserName)) {
            return false;
        }

        $pregMatchLabel = "grep -E '^(application: label)' " . $fileApkParserName . "| awk '{print $2}' | sed 's/label=//' ";
        $pregMatchLabel2 = "grep -E '^(launchable-activity)' " . $fileApkParserName . "| awk '{print $3}' | sed 's/label=//' ";
        $pregMatchIcon = "grep -E '^(application: label)' " . $fileApkParserName . "| awk '{print $3}' | sed 's/icon=//' ";
        $arrApkInfo = array();
        exec($pregMatchLabel, $strLabel);
        if (empty($istrLabel) || (isset($strLabel[0]) && $strLabel[0] == "''")) {
            $strLabel = array();
            exec($pregMatchLabel2,$strLabel);
        }
        exec($pregMatchIcon, $strIcon);
        if (!empty($strLabel) && isset($strLabel[0])) {
            $arrApkInfo['appName'] = trim($strLabel[0], "''");
        }
        if (!empty($strIcon) && isset($strIcon[0])) {
            $arrApkInfo['icon'] = trim($strIcon[0], "''");
        }
        return $arrApkInfo;
    }
}
