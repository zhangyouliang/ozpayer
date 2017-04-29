<?php
/**
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the MIT-LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @author    zyl<1577121881@qq.com>
 * @copyright zyl<1577121881@qq.com>
 * @link      http://www.whatdy.com/
 * @date     2017年4月9日
 */
use OZPayer\Lib\UnionPay\AcpService;
use OZPayer\Lib\UnionPay\SDKConfig;

//require_once __DIR__ . '/Autoloader.php';
require_once __DIR__ . '/../../vendor/autoload.php';

//交易状态查询
$_POST = [
    'orderId'=>'20170409001831',
    'merId'=>'777290058110048',
    'txnTime'=>'20170409002229'
];
$params = array(
    //以下信息非特殊情况不需要改动
    'version' => SDKConfig::getSDKConfig()->version,		  //版本号
    'encoding' => 'utf-8',		  //编码方式
    'signMethod' => SDKConfig::getSDKConfig()->signMethod,		  //签名方法
    'txnType' => '00',		      //交易类型
    'txnSubType' => '00',		  //交易子类
    'bizType' => '000000',		  //业务类型
    'accessType' => '0',		  //接入类型
    'channelType' => '07',		  //渠道类型

    //TODO 以下信息需要填写
    'orderId' => $_POST["orderId"],	//请修改被查询的交易的订单号，8-32位数字字母，不能含“-”或“_”，此处默认取demo演示页面传递的参数
    'merId' => $_POST["merId"],	    //商户代码，请改自己的测试商户号，此处默认取demo演示页面传递的参数
    'txnTime' => $_POST["txnTime"],	//请修改被查询的交易的订单发送时间，格式为YYYYMMDDhhmmss，此处默认取demo演示页面传递的参数
);

AcpService::sign ( $params ); // 签名
$url = SDKConfig::getSDKConfig()->singleQueryUrl;

$result_arr = AcpService::post ( $params, $url);
if(count($result_arr)<=0) { //没收到200应答的情况
    printResult ( $url, $params, "" );
    return;
}

printResult ($url, $params, $result_arr ); //页面打印请求应答数据

if (!AcpService::validate ($result_arr) ){
    echo "应答报文验签失败<br>\n";
    return;
}

echo "应答报文验签成功<br>\n";
if ($result_arr["respCode"] == "00"){
    if ($result_arr["origRespCode"] == "00"){
        //交易成功
        //TODO
        echo "交易成功。<br>\n";
    } else if ($result_arr["origRespCode"] == "03"
        || $result_arr["origRespCode"] == "04"
        || $result_arr["origRespCode"] == "05"){
        //后续需发起交易状态查询交易确定交易状态
        //TODO
        echo "交易处理中，请稍微查询。<br>\n";
    } else {
        //其他应答码做以失败处理
        //TODO
        echo "交易失败：" . $result_arr["origRespMsg"] . "。<br>\n";
    }
} else if ($result_arr["respCode"] == "03"
    || $result_arr["respCode"] == "04"
    || $result_arr["respCode"] == "05" ){
    //后续需发起交易状态查询交易确定交易状态
    //TODO
    echo "处理超时，请稍微查询。<br>\n";
} else {
    //其他应答码做以失败处理
    //TODO
    echo "失败：" . $result_arr["respMsg"] . "。<br>\n";
}

/**
 * 打印请求应答
 *
 * @param
 *        	$url
 * @param
 *        	$req
 * @param
 *        	$resp
 */
function printResult($url, $req, $resp) {
    echo "=============<br>\n";
    echo "地址：" . $url . "<br>\n";
    echo "请求：" . str_replace ( "\n", "\n<br>", htmlentities ( createLinkString ( $req, false, true ) ) ) . "<br>\n";
    echo "应答：" . str_replace ( "\n", "\n<br>", htmlentities ( createLinkString ( $resp , false, false )) ) . "<br>\n";
    echo "=============<br>\n";
}


