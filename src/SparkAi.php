<?php

/*
 * This file is part of the jiannei/spark-ai.
 *
 * (c) jiannei <jiannei@sinan.fun>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Jiannei\SparkAi;

use WebSocket\BadOpcodeException;
use WebSocket\Client;

class SparkAi
{
    private static $instance;

    private $config = [];

    private $header = [];

    private $parameter = [];

    private $answer = '';

    private $resp = [];

    private $context = [];

    public static function getInstance(): SparkAi
    {
        if (self::$instance === null) {
            self::$instance = new SparkAi();
        }

        return self::$instance;
    }

    public function chat(array $options, array $header = [], array $parameter = []): SparkAi
    {
        $this->withConfig($options)
            ->withHeader($header)
            ->withParameter($parameter);

        return $this;
    }

    /**
     * @throws BadOpcodeException
     */
    public function answer(string $question): string
    {
        $this->context[] = ['role' => 'user', 'content' => $question]; // TODO memory leak

        $client = $this->buildClient();

        while (true) {
            $resp = json_decode($client->receive(), true);

            // 异常
            if ($resp['header']['code'] != 0) {
                throw new \RuntimeException($resp['header']['message'], $resp['header']['code']);
            }

            // 正常
            $this->setLatestResponse($resp);

            $this->assembleAnswer();

            if ($resp['header']['status'] == 2) {// 响应结束
                break;
            }
        }

        $this->context[] = ['role' => 'assistant', 'content' => $this->answer];

        return $this->answer;
    }

    /**
     * @throws BadOpcodeException
     */
    protected function buildClient(): Client
    {
        if (! $this->config || ! $this->header || ! $this->parameter) {
            throw new \InvalidArgumentException('配置错误，或者缺少 header 或 parameter 参数');
        }

        // 创建ws连接对象，连接到 WebSocket 服务器
        $client = new Client($this->assembleAuthUrl());

        // 发送数据到 WebSocket 服务器
        $client->send(json_encode([
            'header' => $this->header,
            'parameter' => $this->parameter,
            'payload' => [
                'message' => [
                    'text' => $this->context,
                ],
            ],
        ]));

        return $client;
    }

    /**
     * @throws BadOpcodeException
     */
    public function create(string $question): \Generator
    {
        $this->context[] = ['role' => 'user', 'content' => $question]; // TODO memory leak

        $client = $this->buildClient();

        while (true) {
            $resp = json_decode($client->receive(), true);

            // 异常
            if ($resp['header']['code'] != 0) {
                throw new \RuntimeException($resp['header']['message'], $resp['header']['code']);
            }

            // 正常
            $this->setLatestResponse($resp);

            $this->assembleAnswer();

            yield $this->getLatestAnswer();

            if ($resp['header']['status'] == 2) {// 响应结束
                break;
            }
        }

        $this->context[] = ['role' => 'assistant', 'content' => $this->answer];
    }

    public function getLatestTokenUsage(): int
    {
        return $this->getLatestResponse()['payload']['usage']['text']['total_tokens'];
    }

    private function setLatestResponse(array $resp)
    {
        $this->resp = $resp;
    }

    public function getLatestResponse(): array
    {
        return $this->resp;
    }

    private function assembleAnswer()
    {
        $this->answer .= $this->getLatestAnswer();
    }

    protected function getLatestAnswer()
    {
        return $this->getLatestResponse()['payload']['choices']['text'][0]['content'];
    }

    public function withParameter(array $parameter = []): SparkAi
    {
        $this->parameter = array_merge_recursive([
            'chat' => [
                'domain' => 'generalv3',
                'temperature' => 0.5,
                'max_tokens' => 1024,
            ],
        ], $parameter);

        return $this;
    }

    public function withHeader(array $header = []): SparkAi
    {
        $this->header = array_merge([
            'app_id' => $this->config['app_id'],
            'uid' => (string) time(),
        ], $header);

        return $this;
    }

    public function withContext(array $context): SparkAi
    {
        $this->context = $context;

        return $this;
    }

    public function history(): array
    {
        return $this->context;
    }

    public function withConfig(array $options): SparkAi
    {
        $addr = $options['url'] ?? '';
        $appId = $options['app_id'] ?? '';
        $apiKey = $options['api_key'] ?? '';
        $apiSecret = $options['api_secret'] ?? '';

        // TODO league config
        if (! $addr || ! $appId || ! $apiKey || ! $apiSecret) {// 参数错误，不鉴权
            throw new \InvalidArgumentException('缺少 url、appId、apiKey 或 apiSecret');
        }

        $ul = parse_url($addr); // 解析地址

        if ($ul === false) { // 地址不对，也不鉴权
            throw new \InvalidArgumentException('url 解析异常');
        }

        $this->config = array_merge($this->config, $options);

        return $this;
    }

    public function getConfig(): array
    {
        return $this->config;
    }

    protected function assembleAuthUrl(): string
    {
        $ul = parse_url($this->config['url']); // 解析地址

        // // $date = date(DATE_RFC1123); // 获取当前时间并格式化为RFC1123格式的字符串
        $timestamp = time();
        $rfc1123_format = gmdate("D, d M Y H:i:s \G\M\T", $timestamp);
        // $rfc1123_format = "Mon, 31 Jul 2023 08:24:03 GMT";

        // 参与签名的字段 host, date, request-line
        $signString = ['host: '.$ul['host'], 'date: '.$rfc1123_format, $this->config['method'] ?? 'GET'.' '.$ul['path'].' HTTP/1.1'];

        // 对签名字符串进行排序，确保顺序一致
        // ksort($signString);

        // 将签名字符串拼接成一个字符串
        $sgin = implode("\n", $signString);

        // 对签名字符串进行HMAC-SHA256加密，得到签名结果
        $sha = hash_hmac('sha256', $sgin, $this->config['api_secret'], true);
        $signature_sha_base64 = base64_encode($sha);

        // 将API密钥、算法、头部信息和签名结果拼接成一个授权URL
        $authUrl = "api_key=\"{$this->config['api_key']}\", algorithm=\"hmac-sha256\", headers=\"host date request-line\", signature=\"{$signature_sha_base64}\"";

        // 对授权URL进行Base64编码，并添加到原始地址后面作为查询参数
        return $this->config['url'].'?'.http_build_query([
            'host' => $ul['host'],
            'date' => $rfc1123_format,
            'authorization' => base64_encode($authUrl),
        ]);
    }
}
