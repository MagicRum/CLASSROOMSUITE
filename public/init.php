<?php
// 本地数据库初始化脚本
// 访问 /init.php 即可自动创建 SQLite 数据库、建表、插入测试数据
// 仅本地开发使用，部署宝塔后可删除此文件

try {
    // 引入 ThinkPHP
    require __DIR__ . '/../vendor/autoload.php';

    // 定位 SQLite 文件
    $dbFile = __DIR__ . '/../runtime/classroom.sqlite';
    $schema = __DIR__ . '/../database/schema-sqlite.sql';

    // 如果 SQLite 文件已存在，允许重建（加 ?force=1 就重建）
    if (file_exists($dbFile)) {
        $force = isset($_GET['force']) && $_GET['force'] == 1;
        if (!$force) {
            echo json_encode(['code' => 1, 'msg' => '数据库已存在，加 ?force=1 可重建', 'file' => $dbFile], JSON_UNESCAPED_UNICODE);
            exit;
        }
        unlink($dbFile);
    }

    // 创建目录
    @mkdir(dirname($dbFile), 0777, true);

    // 用 PDO 直接建表（绕开 ThinkPHP 的 .env 依赖）
    $pdo = new PDO('sqlite:' . $dbFile);
    $pdo->exec('PRAGMA foreign_keys = ON');

    $sql = file_get_contents($schema);
    // SQLite 不支持 DROP TABLE IF EXISTS 的多行语句一次性执行，按 ; 分割逐个执行
    $statements = array_filter(array_map('trim', explode(";\n", str_replace("\r\n", "\n", $sql))));
    $count = 0;
    foreach ($statements as $stmt) {
        if (empty($stmt) || strpos($stmt, '--') === 0) continue;
        $pdo->exec($stmt);
        $count++;
    }

    echo json_encode([
        'code' => 1,
        'msg' => '初始化成功！执行了 ' . $count . ' 条语句',
        'file' => $dbFile,
        '默认管理员' => 'admin / admin123',
        '测试学生' => '2024001 / 张三',
        '测试试题数' => 6,
    ], JSON_UNESCAPED_UNICODE);

} catch (\Throwable $e) {
    echo json_encode(['code' => 0, 'msg' => '初始化失败：' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
