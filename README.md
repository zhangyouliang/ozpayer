#### OZPayer
> 目前只支持银联支付


使用composer 运行`composer require ozpayer/payer:dev-master`

####目录结构

````
├─certs  证书目录
├─config  配置文件目录
├─demo  案例
├─Lib  sdk
│  └─UnionPay 银联
├─logs log日志
├─Support 
└─vendor
    └─composer

````

可以通过直接访问 `demo`文件夹下面的相关文件,进行测试.



注:(银联)请自行修改`config/acp_sdk.ini`文件中的`acpsdk.signCert.path`,
`acpsdk.encryptCert.path`,`acpsdk.middleCert.path`,`acpsdk.rootCert.path`
四个证书的目录,以及日志的路径`acpsdk.log.file.path`



