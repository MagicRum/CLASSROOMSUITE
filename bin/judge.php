<?php
// +----------------------------------------------------------------------
// | 在线判题 worker
// | 用法：
// |   php bin/judge.php            处理一批待判题任务后退出
// |   php bin/judge.php --loop     常驻模式，持续消费队列
// +----------------------------------------------------------------------

require __DIR__ . '/../vendor/autoload.php';

$app = new \think\App(dirname(__DIR__));
$app->initialize();

$loop = in_array('--loop', $argv, true);
$limit = 5;
foreach ($argv as $arg) {
    if (ctype_digit((string) $arg)) {
        $limit = max(1, intval($arg));
    }
}

do {
    $done = \app\service\JudgeService::runPending($limit);
    if ($loop) {
        if ($done === 0) {
            sleep(1);
        }
    }
} while ($loop);
