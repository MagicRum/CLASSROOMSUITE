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
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Lax');
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        ini_set('session.cookie_secure', '1');
    }
    session_start();
}

$app = new App();
// 仅 PHP 内置服务器（本地开发）默认开启 debug，线上读取 .env 的 app_debug，缺省关闭
$app->debug((bool) \think\facade\Env::get('app_debug', php_sapi_name() === 'cli-server'));

$http = $app->http;
$response = $http->run();
$response->send();
$http->end($response);
