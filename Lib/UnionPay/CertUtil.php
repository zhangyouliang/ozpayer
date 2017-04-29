<?php
/**
 * Created by PhpStorm.
 * User: zyl
 * Date: 2017/4/9
 * Time: 10:15
 */

namespace OZPayer\Lib\UnionPay;


use OZPayer\Lib\LogUtil;

const COMPANY = "中国银联股份有限公司";

class CertUtil
{

    private static $signCerts = array();
    private static $encryptCerts = array();
    private static $verifyCerts = array();
    private static $verifyCerts510 = array();

    private static function initSignCert($certPath, $certPwd){
        $logger = LogUtil::getLogger();
        $logger->LogInfo("读取签名证书……");

        $pkcs12certdata = file_get_contents ( $certPath );
        if($pkcs12certdata === false ){
            $logger->LogInfo($certPath . "file_get_contents fail。");
            return;
        }

        if(openssl_pkcs12_read ( $pkcs12certdata, $certs, $certPwd ) == FALSE ){
            $logger->LogInfo($certPath . ", pwd[" . $certPwd . "] openssl_pkcs12_read fail。");
            return;
        }

        $cert = new Cert();
        $x509data = $certs ['cert'];

        if(!openssl_x509_read ( $x509data )){
            $logger->LogInfo($certPath . " openssl_x509_read fail。");
        }
        $certdata = openssl_x509_parse ( $x509data );
        $cert->certId = $certdata ['serialNumber'];

// 		$certId = CertSerialUtil::getSerial($x509data, $errMsg);
// 		if($certId === false){
//         	$logger->LogInfo("签名证书读取序列号失败：" . $errMsg);
//         	return;
// 		}
//         $cert->certId = $certId;

        $cert->key = $certs ['pkey'];
        $cert->cert = $x509data;

        $logger->LogInfo("签名证书读取成功，序列号：" . $cert->certId);
        CertUtil::$signCerts[$certPath] = $cert;
    }

    public static function getSignKeyFromPfx($certPath=null, $certPwd=null)
    {
        if( $certPath == null ) {
            $certPath = SDKConfig::getSDKConfig()->signCertPath;
            $certPwd = SDKConfig::getSDKConfig()->signCertPwd;
        }

        if (!array_key_exists($certPath, CertUtil::$signCerts)) {
            self::initSignCert($certPath, $certPwd);
        }
        return CertUtil::$signCerts[$certPath] -> key;
    }

    public static function getSignCertIdFromPfx($certPath=null, $certPwd=null)
    {

        if( $certPath == null ) {
            $certPath = SDKConfig::getSDKConfig()->signCertPath;
            $certPwd = SDKConfig::getSDKConfig()->signCertPwd;
        }

        if (!array_key_exists($certPath, CertUtil::$signCerts)) {
            self::initSignCert($certPath, $certPwd);
        }
        return CertUtil::$signCerts[$certPath] -> certId;
    }

    private static function initEncryptCert($cert_path)
    {
        $logger = LogUtil::getLogger();
        $logger->LogInfo("读取加密证书……");

        $x509data = file_get_contents ( $cert_path );
        if($x509data === false ){
            $logger->LogInfo($cert_path . " file_get_contents fail。");
            return;
        }

        if(!openssl_x509_read ( $x509data )){
            $logger->LogInfo($cert_path . " openssl_x509_read fail。");
            return;
        }

        $cert = new Cert();
        $certdata = openssl_x509_parse ( $x509data );
        $cert->certId = $certdata ['serialNumber'];

// 	    $certId = CertSerialUtil::getSerial($x509data, $errMsg);
// 	    if($certId === false){
// 	    	$logger->LogInfo("签名证书读取序列号失败：" . $errMsg);
// 	    	return;
// 	    }
// 	    $cert->certId = $certId;

        $cert->key = $x509data;
        CertUtil::$encryptCerts[$cert_path] = $cert;
        $logger->LogInfo("加密证书读取成功，序列号：" . $cert->certId);
    }

    public static function verifyAndGetVerifyCert($certBase64String){

        $logger = LogUtil::getLogger();

        if (array_key_exists($certBase64String, CertUtil::$verifyCerts510)){
            return CertUtil::$verifyCerts510[$certBase64String];
        }

        if (SDKConfig::getSDKConfig()->middleCertPath === null || SDKConfig::getSDKConfig()->rootCertPath === null){
            $logger->LogError("rootCertPath or middleCertPath is none, exit initRootCert");
            return null;
        }
        openssl_x509_read($certBase64String);
        $certInfo = openssl_x509_parse($certBase64String);

        $cn = CertUtil::getIdentitiesFromCertficate($certInfo);
        if(strtolower(SDKConfig::getSDKConfig()->ifValidateCNName) == "true"){
            if (COMPANY != $cn){
                $logger->LogInfo("cer owner is not CUP:" . $cn);
                return null;
            }
        } else if (COMPANY != $cn && "00040000:SIGN" != $cn){
            $logger->LogInfo("cer owner is not CUP:" . $cn);
            return null;
        }

        $from = date_create ( '@' . $certInfo ['validFrom_time_t'] );
        $to = date_create ( '@' . $certInfo ['validTo_time_t'] );
        $now = date_create ( date ( 'Ymd' ) );
        $interval1 = $from->diff ( $now );
        $interval2 = $now->diff ( $to );
        if ($interval1->invert || $interval2->invert) {
            $logger->LogInfo("signPubKeyCert has expired");
            return null;
        }

        $result = openssl_x509_checkpurpose($certBase64String, X509_PURPOSE_ANY, array(SDKConfig::getSDKConfig()->rootCertPath, SDKConfig::getSDKConfig()->middleCertPath));
        if($result === FALSE){
            $logger->LogInfo("validate signPubKeyCert by rootCert failed");
            return null;
        } else if($result === TRUE){
            CertUtil::$verifyCerts510[$certBase64String] = $certBase64String;
            return CertUtil::$verifyCerts510[$certBase64String];
        } else {
            $logger->LogInfo("validate signPubKeyCert by rootCert failed with error");
            return null;
        }
    }

    public static function getIdentitiesFromCertficate($certInfo){

        $cn = $certInfo['subject'];
        $cn = $cn['CN'];
        $company = explode('@',$cn);

        if(count($company) < 3) {
            return null;
        }
        return $company[2];
    }

    public static function getEncryptCertId($cert_path=null){
        if( $cert_path == null ) {
            $cert_path = SDKConfig::getSDKConfig()->encryptCertPath;
        }
        if(!array_key_exists($cert_path, CertUtil::$encryptCerts)){
            self::initEncryptCert($cert_path);
        }
        if(array_key_exists($cert_path, CertUtil::$encryptCerts)){
            return CertUtil::$encryptCerts[$cert_path] -> certId;
        }
        return false;
    }

    public static function getEncryptKey($cert_path=null){
        if( $cert_path == null ) {
            $cert_path = SDKConfig::getSDKConfig()->encryptCertPath;
        }
        if(!array_key_exists($cert_path, CertUtil::$encryptCerts)){
            self::initEncryptCert($cert_path);
        }
        if(array_key_exists($cert_path, CertUtil::$encryptCerts)){
            return CertUtil::$encryptCerts[$cert_path] -> key;
        }
        return false;
    }

    private static function initVerifyCerts($cert_dir=null) {

        if( $cert_dir == null ) {
            $cert_dir = SDKConfig::getSDKConfig()->validateCertDir;
        }

        $logger = LogUtil::getLogger();
        $logger->LogInfo ( '验证签名证书目录 :>' . $cert_dir );
        $handle = opendir ( $cert_dir );
        if (!$handle) {
            $logger->LogInfo ( '证书目录 ' . $cert_dir . '不正确' );
            return;
        }

        while ($file = readdir($handle)) {
            clearstatcache();
            $filePath = $cert_dir . '/' . $file;
            if (is_file($filePath)) {
                if (pathinfo($file, PATHINFO_EXTENSION) == 'cer') {

                    $x509data = file_get_contents($filePath);
                    if($x509data === false ){
                        $logger->LogInfo($filePath . " file_get_contents fail。");
                        continue;
                    }
                    if(!openssl_x509_read($x509data)){
                        $logger->LogInfo($certPath . " openssl_x509_read fail。");
                        continue;
                    }

                    $cert = new Cert();
                    $certdata = openssl_x509_parse($x509data);
                    $cert->certId = $certdata ['serialNumber'];

//                     $certId = CertSerialUtil::getSerial($x509data, $errMsg);
//                     if($certId === false){
//                     	$logger->LogInfo("签名证书读取序列号失败：" . $errMsg);
//                     	return;
//                     }
//                     $cert->certId = $certId;

                    $cert->key = $x509data;
                    CertUtil::$verifyCerts[$cert->certId] = $cert;
                    $logger->LogInfo($filePath . "读取成功，序列号：" . $cert->certId);
                }
            }
        }
        closedir ( $handle );
    }

    public static function getVerifyCertByCertId($certId){
        $logger = LogUtil::getLogger();
        if(count(CertUtil::$verifyCerts) == 0){
            self::initVerifyCerts();
        }
        if(count(CertUtil::$verifyCerts) == 0){
            $logger->LogInfo("未读取到任何证书……");
            return null;
        }
        if(array_key_exists($certId, CertUtil::$verifyCerts)){
            return CertUtil::$verifyCerts[$certId]->key;
        } else {
            $logger->LogInfo("未匹配到序列号为[" . certId . "]的证书");
            return null;
        }
    }

    public static function test() {

        $x509data = file_get_contents ( "d:/certs/acp_test_enc.cer" );
// 		$resource = openssl_x509_read ( $x509data );
        // $certdata = openssl_x509_parse ( $resource ); //<=这句尼玛内存泄漏啊根本释放不掉啊啊啊啊啊啊啊
        // echo $certdata ['serialNumber']; //<=就是需要这个数据啦
        // echo $x509data;
        // unset($certdata); //<=没有什么用
        // openssl_x509_free($resource); //<=没有什么用x2
        echo CertSerialUtil::getSerial ( $x509data, $errMsg ) . "\n";
    }
}