<?php
// 快速修复脚本：把 ThinkPHP 6.1 里 htmlentities($xxx) 改成能处理 null 的版本
// 因为 PHP 8.2 里 htmlentities(null) 会触发 deprecation fatal

$targets = [
    __DIR__ . '/../vendor/topthink/framework/src/tpl/think_exception.tpl',
    __DIR__ . '/../vendor/topthink/framework/src/tpl/dispatch_jump.tpl',
];

foreach ($targets as $file) {
    if (!file_exists($file)) continue;
    $c = file_get_contents($file);
    // 替换 htmlentities($xxx) 调用（简单变量）
    $c = preg_replace_callback(
        '/htmlentities\(\s*(\$[a-zA-Z_][a-zA-Z0-9_\'"\\\[\]\-]*)\s*\)/',
        function ($m) {
            return 'htmlentities((string)(' . $m[1] . ' ?? ""))';
        },
        $c
    );
    file_put_contents($file, $c);
    echo "Patched: $file\n";
}
echo "Done!\n";
