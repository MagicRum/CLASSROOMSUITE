<?php
// 调试脚本：直接 CLI 跑首页路由，看具体报错
// 用法：php debug.php

require __DIR__ . '/../vendor/autoload.php';

// 开启 debug
error_reporting(E_ALL);
ini_set('display_errors', 1);

$app = new \think\App();
$app->debug(true);

// 用 request 对象模拟 GET /
$request = \think\Request::instance()->create('/', 'GET');
$response = $app->http->run($request);

echo "Status: " . $response->getCode() . "\n";
echo $response->getContent();
