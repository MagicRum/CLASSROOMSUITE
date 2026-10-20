<?php
// +----------------------------------------------------------------------
// | 数据库配置
// | 默认使用 SQLite（本地零配置，一个文件就是一个数据库）
// | 宝塔服务器部署时，把 .env 文件里的 driver 改成 mysql 即可切到 MySQL
// +----------------------------------------------------------------------

return [
    'default'         => env('database.driver', 'sqlite'),

    'time_query_rule' => [],

    'connections'     => [
        // SQLite —— 本地开发默认
        'sqlite' => [
            'type'            => 'sqlite',
            'database'        => env('database.database', runtime_path() . 'classroom.sqlite'),
            'prefix'          => env('database.prefix', 'cs_'),
        ],

        // MySQL —— 宝塔服务器部署用
        'mysql' => [
            'type'            => 'mysql',
            'hostname'        => env('database.hostname', '127.0.0.1'),
            'database'        => env('database.database', 'classroom_sys'),
            'username'        => env('database.username', 'root'),
            'password'        => env('database.password', ''),
            'hostport'        => env('database.hostport', '3306'),
            'charset'         => env('database.charset', 'utf8mb4'),
            'prefix'          => env('database.prefix', 'cs_'),
            'deploy'          => 0,
            'rw_separate'     => false,
            'master_num'      => 1,
            'slave_no'        => '',
            'fields_strict'   => true,
            'break_reconnect' => false,
            'trigger_sql'     => env('app_debug', true),
            'fields_cache'    => false,
        ],
    ],
];
