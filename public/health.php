<?php
// 健康检查 - 部署后先访问 /health.php 看是否返回 JSON
// 正常返回：{"status":"ok","php":"8.x.x","db":"ok"}
// 返回 db 错误说明 .env 或 config/database.php 里的数据库连不上

header('Content-Type: application/json; charset=utf-8');

$result = ['status' => 'ok', 'php' => PHP_VERSION, 'db' => ''];

try {
    require __DIR__ . '/../vendor/autoload.php';
    $app = new \think\App();
    $app->initialize();

    // 测试数据库连接
    \think\facade\Db::query('SELECT 1');
    $result['db'] = 'ok';
} catch (\Throwable $e) {
    $result['status'] = 'error';
    $result['db'] = 'fail: ' . $e->getMessage();
}

echo json_encode($result, JSON_UNESCAPED_UNICODE);
