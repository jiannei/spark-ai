<h1 align="center"> spark-ai </h1>

> 讯飞星火大模型非官方 SDK.

[![StyleCI](https://github.styleci.io/repos/723286499/shield?branch=main&style=flat)](https://github.styleci.io/repos/723286499?branch=main&style=flat)
[![Latest Stable Version](http://poser.pugx.org/jiannei/spark-ai/v)](https://packagist.org/packages/jiannei/spark-ai)
[![Total Downloads](http://poser.pugx.org/jiannei/spark-ai/downloads)](https://packagist.org/packages/jiannei/spark-ai) 
[![Latest Unstable Version](http://poser.pugx.org/jiannei/spark-ai/v/unstable)](https://packagist.org/packages/jiannei/spark-ai)
[![License](http://poser.pugx.org/jiannei/spark-ai/license)](https://packagist.org/packages/jiannei/spark-ai)

## 介绍

- [星火大模型 API 免费套餐](https://xinghuo.xfyun.cn/sparkapi?scr=price)
- [官方文档](https://www.xfyun.cn/doc/spark/Web.html)

## 安装

```shell
$ composer require jiannei/spark-ai -vvv
```

## 使用

- 在[控制台](https://console.xfyun.cn/services/bm3)获取服务接口认证信息：APPID、APISecret、APIKey


- 流式输出

```php
use Jiannei\SparkAi\SparkAi;


$answer = SparkAi::getInstance()->withConfig([
    'url' => 'wss://spark-api.xf-yun.com/v3.1/chat',
    'app_id' => '',// 填入控制台中获取的 APPID
    'api_key' => '',// 填入控制台中获取的 APISecret
    'api_secret' => '',// 填入控制台中获取的 APIKey
])->chat()->create('你是谁？');

foreach ($answer as $item) {
    print_r($item);
}
```

![answer](https://raw.githubusercontent.com/jiannei/snc-pro/master/images/202401101917408.gif)

- 完整输出

```php
use Jiannei\SparkAi\SparkAi;


$answer = SparkAi::getInstance()->withConfig([
    'url' => 'wss://spark-api.xf-yun.com/v3.1/chat',
    'app_id' => '',// 填入控制台中获取的 APPID
    'api_key' => '',// 填入控制台中获取的 APISecret
    'api_secret' => '',// 填入控制台中获取的 APIKey
])->chat()->answer('你是谁？');

print_r($answer);
```


## License

MIT