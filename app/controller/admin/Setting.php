<?php
// +----------------------------------------------------------------------
// | 系统设置（门户名称等）
// +----------------------------------------------------------------------

namespace app\controller\admin;

class Setting extends Base
{
    public function index()
    {
        return view('admin/setting/index', [
            'systemName' => system_name(),
        ]);
    }

    public function save()
    {
        $name = trim((string) input('post.system_name', ''));
        if ($name === '') {
            return $this->error('系统名称不能为空');
        }
        if (mb_strlen($name, 'UTF-8') > 20) {
            return $this->error('系统名称不能超过 20 个字');
        }
        if (!system_name_save($name)) {
            return $this->error('保存失败，请检查 runtime 目录的写入权限');
        }
        return $this->success('保存成功');
    }
}
