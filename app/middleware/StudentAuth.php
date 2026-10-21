<?php
// +----------------------------------------------------------------------
// | 学生认证中间件
// +----------------------------------------------------------------------

namespace app\middleware;

use app\model\Student;
use Closure;
use think\Request;

class StudentAuth
{
    public function handle(Request $request, Closure $next)
    {
        $studentId = $_SESSION['student_id'] ?? null;
        if (!$studentId) {
            return $this->reject($request);
        }

        // 每次请求都从数据库刷新身份，保证侧边栏显示与登录账号一致
        $student = Student::find($studentId);
        if (!$student) {
            unset($_SESSION['student_id'], $_SESSION['student_user']);
            return $this->reject($request);
        }
        $_SESSION['student_user'] = [
            'id'         => $student->id,
            'student_no' => $student->student_no,
            'name'       => $student->name,
            'class_id'   => $student->class_id,
        ];

        return $next($request);
    }

    protected function reject(Request $request)
    {
        if ($request->isAjax() || $request->isJson()) {
            return json(['code' => 401, 'msg' => '未登录，请先登录']);
        }

        // 判题系统（/oj）区分入口来源：教师已登录时直接进教师端判题管理
        $path = strtolower(trim($request->pathinfo(), '/'));
        $isOj = $path === 'oj' || strpos($path, 'oj/') === 0;
        if ($isOj && !empty($_SESSION['admin_id'])) {
            return redirect('/admin/oj/problem');
        }

        return redirect($isOj ? '/student/login?from=oj' : '/student/login');
    }
}
