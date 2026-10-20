<?php
// +----------------------------------------------------------------------
// | 管理员认证中间件
// +----------------------------------------------------------------------

namespace app\middleware;

use Closure;
use think\Request;

class AdminAuth
{
    public function handle(Request $request, Closure $next)
    {
        $adminId = $_SESSION['admin_id'] ?? null;
        if (!$adminId) {
            // AJAX 请求返回 JSON，否则跳转登录
            if ($request->isAjax() || $request->isJson()) {
                return json(['code' => 401, 'msg' => '未登录，请先登录']);
            }
            return redirect((string) url('admin/Login/index'));
        }
        return $next($request);
    }
}
