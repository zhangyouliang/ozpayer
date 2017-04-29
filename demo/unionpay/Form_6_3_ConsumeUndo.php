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
 *  @date     2017年4月9日
 */
use OZPayer\Lib\UnionPay\AcpService;
use OZPayer\Lib\UnionPay\SDKConfig;

require_once __DIR__ . '/../../vendor/autoload.php';

//消费撤销
$_POST = [
    'orderId'=>'20170409110122',
    'merId'=>'777290058110048',
    'txnTime'=>'20170409110122',
    'txnAmt'=>'1000',
    'origQryId'=>'11212121'
];

$params = array(

    //以下信息非特殊情况不需要改动
    'version' => SDKConfig::getSDKConfig()->version,		      //版本号
    'encoding' => 'utf-8',		      //编码方式
    'signMethod' => SDKConfig::getSDKConfig()->signMethod,		      //签名方法
    'txnType' => '31',		          //交易类型	
    'txnSubType' => '00',		      //交易子类
    'bizType' => '000201',		      //业务类型
    'accessType' => '0',		      //接入类型
    'channelType' => '07',		      //渠道类型
    'backUrl' => SDKConfig::getSDKConfig()->backUrl, //后台通知地址	

    //TODO 以下信息需要填写
    'orderId' => $_POST["orderId"],	    //商户订单号，8-32位数字字母，不能含“-”或“_”，可以自行定制规则，重新产生，不同于原消费，此处默认取demo演示页面传递的参数
    'merId' => $_POST["merId"],			//商户代码，请改成自己的测试商户号，此处默认取demo演示页面传递的参数
    'origQryId' => $_POST["origQryId"], //原消费的queryId，可以从查询接口或者通知接口中获取，此处默认取demo演示页面传递的参数
    'txnTime' => $_POST["txnTime"],	    //订单发送时间，格式为YYYYMMDDhhmmss，重新产生，不同于原消费，此处默认取demo演示页面传递的参数
    'txnAmt' => $_POST["txnAmt"],       //交易金额，消费撤销时需和原消费一致，此处默认取demo演示页面传递的参数

    // 请求方保留域，
    // 透传字段，查询、通知、对账文件中均会原样出现，如有需要请启用并修改自己希望透传的数据。
    // 出现部分特殊字符时可能影响解析，请按下面建议的方式填写：
    // 1. 如果能确定内容不会出现&={}[]"'等符号时，可以直接填写数据，建议的方法如下。
    //    'reqReserved' =>'透传信息1|透传信息2|透传信息3',
    // 2. 内容可能出现&={}[]"'符号时：
    // 1) 如果需要对账文件里能显示，可将字符替换成全角＆＝｛｝【】“‘字符（自己写代码，此处不演示）；
    // 2) 如果对账文件没有显示要求，可做一下base64（如下）。
    //    注意控制数据长度，实际传输的数据长度不能超过1024位。
    //    查询、通知等接口解析时使用base64_decode解base64后再对数据做后续解析。
    //    'reqReserved' => base64_encode('任意格式的信息都可以'),
);

AcpService::sign ( $params ); // 签名
$url = SDKConfig::getSDKConfig()->backTransUrl;

$result_arr = AcpService::post ( $params, $url);
if(count($result_arr)<=0) { //没收到200应答的情况
    printResult ( $url, $params, "" );
    echo "POST请求失败：" . $errMsg;
    return;
}

printResult ($url, $params, $result_arr ); //页面打印请求应答数据

if (!AcpService::validate ($result_arr) ){
    echo "应答报文验签失败<br>\n";
    return;
}

echo "应答报文验签成功<br>\n";
if ($result_arr["respCode"] == "00"){
    //交易已受理，等待接收后台通知更新订单状态，如果通知长时间未收到也可发起交易状态查询
    //TODO
    echo "受理成功。<br>\n";
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
