<?php
namespace app\controller\admin;

class Base
{
    protected $admin;

    public function __construct()
    {
        $this->admin = $_SESSION['admin_user'] ?? null;
    }

    /**
     * 操作成功（统一返回 JSON，前端 ajax 使用）
     */
    protected function success($msg = '操作成功', $data = null, $url = null)
    {
        return json([
            'code' => 1,
            'msg'  => $msg,
            'data' => $data,
            'url'  => $url,
        ]);
    }

    /**
     * 操作失败
     */
    protected function error($msg = '操作失败', $data = null)
    {
        return json([
            'code' => 0,
            'msg'  => $msg,
            'data' => $data,
        ]);
    }
}
