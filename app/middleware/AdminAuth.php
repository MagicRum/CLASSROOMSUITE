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
            // 从判题管理进入时带上来源，登录后回到判题系统而不是抽题系统
            $path = strtolower(trim($request->pathinfo(), '/'));
            $isOj = strpos($path, 'admin/oj') === 0;
            return redirect($isOj ? '/admin/login?from=oj' : '/admin/login');
        }
        return $next($request);
    }
}
