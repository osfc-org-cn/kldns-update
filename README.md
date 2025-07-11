# 快乐二级域名分发系统 OSFC Update版

## 此系统有哪些特点
* 目前支持的域名解析平台有
    *  dnspod
    *  cloudxns
    *  aliyun
    *  dnscom
    *  dnsla
    *  cloudxns
    *  DnsDun
* 多用户、多域名、多平台同时存在
* 界面简单、舒适，操作简单

## 安装说明
* 1、程序的框架是Laravel 5.8，因此需要环境满足以下要求：
    * PHP >= 7.1.3
    * PHP OpenSSL 扩展
    * PHP PDO 扩展
    * PHP Mbstring 扩展
    * PHP Tokenizer 扩展
    * PHP XML 扩展
    * PHP Ctype 扩展
    * PHP JSON 扩展
    * PHP BCMath 扩展
* 2、环境必须支持伪静态

* Apache 伪静态配置
    * 确保 Apache 启用了 mod_rewrite 模块以支持 .htaccess 解析。
* Nginx 伪静态配置

        location / {
            try_files $uri $uri/ /index.php?$query_string;
        }
