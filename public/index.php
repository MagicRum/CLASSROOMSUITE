<?php
namespace think;
require __DIR__ . '/../vendor/autoload.php';

// PHP 内置服务器：存在的静态文件直接返回（assets 等）
if (php_sapi_name() === 'cli-server') {
    $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    if ($path !== '/' && is_file(__DIR__ . $path)) {
        return false;
    }
}

// PHP 原生 Session (必须在所有输出之前启动)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$app = new App();
// 本地开发开启 debug，宝塔部署前注释掉下行
$app->debug(true);

$http = $app->http;
$response = $http->run();
$response->send();
$http->end($response);
