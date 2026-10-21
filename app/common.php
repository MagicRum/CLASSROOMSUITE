<?php
// +----------------------------------------------------------------------
// | 公共函数库
// +----------------------------------------------------------------------

if (!function_exists('session_clear')) {
    function session_clear()
    {
        \think\facade\Session::clear();
    }
}

if (!function_exists('system_setting_file')) {
    function system_setting_file()
    {
        return runtime_path() . 'system-setting.json';
    }
}

/**
 * 门户系统名称（管理后台可改），默认「软件工程教研室」
 */
if (!function_exists('system_name')) {
    function system_name()
    {
        static $name = null;
        if ($name !== null) {
            return $name;
        }
        $name = '软件工程教研室';
        $file = system_setting_file();
        if (is_file($file)) {
            $data = json_decode((string) file_get_contents($file), true);
            if (!empty($data['system_name'])) {
                $name = (string) $data['system_name'];
            }
        }
        return $name;
    }
}

if (!function_exists('system_name_save')) {
    function system_name_save($name)
    {
        return file_put_contents(
            system_setting_file(),
            json_encode(['system_name' => $name], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
        ) !== false;
    }
}

/**
 * 门户完整标题：系统名称 + 管理系统
 */
if (!function_exists('system_title')) {
    function system_title()
    {
        return system_name() . '管理系统';
    }
}
