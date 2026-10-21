<?php
// +----------------------------------------------------------------------
// | 学生登录
// +----------------------------------------------------------------------

namespace app\controller\student;

use app\model\Student as StudentModel;

class Login
{
    public function index()
    {
        $from = input('get.from', '');
        $from = $from === 'oj' ? 'oj' : '';
        if (!empty($_SESSION['student_id'])) {
            return redirect($from === 'oj' ? '/oj' : '/student/draw');
        }
        return view('student/login/index', ['from' => $from]);
    }

    /**
     * 登录：学号 + 密码
     */
    public function doLogin()
    {
        $studentNo = trim((string) input('post.student_no', ''));
        $password  = (string) input('post.password', '');
        $from      = input('post.from', '') === 'oj' ? 'oj' : '';

        if (!$studentNo || $password === '') {
            return json(['code' => 0, 'msg' => '学号和密码不能为空']);
        }

        $student = StudentModel::where('student_no', $studentNo)->find();
        if (!$student) {
            return json(['code' => 0, 'msg' => '学号或密码错误']);
        }
        if (empty($student->password)) {
            return json(['code' => 0, 'msg' => '该账号尚未设置密码，请先到注册页用学号 + 姓名设置密码']);
        }
        if (!password_verify($password, $student->password)) {
            return json(['code' => 0, 'msg' => '学号或密码错误']);
        }

        $_SESSION['student_id'] = $student->id;
        $_SESSION['student_user'] = [
            'id'         => $student->id,
            'student_no' => $student->student_no,
            'name'       => $student->name,
            'class_id'   => $student->class_id,
        ];

        return json(['code' => 1, 'msg' => '登录成功', 'url' => $from === 'oj' ? '/oj' : '/student/draw']);
    }

    public function logout()
    {
        unset($_SESSION['student_id'], $_SESSION['student_user']);
        return redirect('/student/login');
    }
}
