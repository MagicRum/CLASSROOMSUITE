<?php
// +----------------------------------------------------------------------
// | 学生注册
// +----------------------------------------------------------------------

namespace app\controller\student;

use app\model\Classes;
use app\model\Student as StudentModel;

class Register
{
    /**
     * 注册页
     */
    public function index()
    {
        $classes = Classes::order('id', 'asc')->column('class_name');
        return view('student/register/index', [
            'classes' => $classes,
        ]);
    }

    /**
     * 提交注册（姓名、学号、班级）
     */
    public function doRegister()
    {
        $name      = trim((string) input('post.name', ''));
        $studentNo = trim((string) input('post.student_no', ''));
        $className = trim((string) input('post.class_name', ''));
        $password  = (string) input('post.password', '');
        $confirm   = (string) input('post.confirm_password', '');

        if (!$name || !$studentNo || !$className || $password === '') {
            return json(['code' => 0, 'msg' => '姓名、学号、班级、密码不能为空']);
        }
        if (mb_strlen($name) > 32 || mb_strlen($studentNo) > 32 || mb_strlen($className) > 50) {
            return json(['code' => 0, 'msg' => '输入内容过长，请核对后重试']);
        }
        if (mb_strlen($password) < 6 || mb_strlen($password) > 32) {
            return json(['code' => 0, 'msg' => '密码长度需为 6-32 位']);
        }
        if ($password !== $confirm) {
            return json(['code' => 0, 'msg' => '两次输入的密码不一致']);
        }

        $hash = password_hash($password, PASSWORD_DEFAULT);

        $existing = StudentModel::where('student_no', $studentNo)->find();
        if ($existing) {
            // 教师导入的学生没有密码，姓名匹配即可设置密码
            if (!empty($existing->password)) {
                return json(['code' => 0, 'msg' => '该学号已注册，请直接登录']);
            }
            if ($existing->name !== $name) {
                return json(['code' => 0, 'msg' => '学号与姓名不匹配，请联系任课教师']);
            }
            $existing->save(['password' => $hash]);
            return json(['code' => 1, 'msg' => '密码设置成功，请使用学号 + 密码登录']);
        }

        // 班级已存在则直接使用，不存在则自动创建
        $class = Classes::where('class_name', $className)->find();
        if (!$class) {
            $class = Classes::create(['class_name' => $className]);
        }

        StudentModel::create([
            'student_no' => $studentNo,
            'name'       => $name,
            'password'   => $hash,
            'class_id'   => $class->id,
        ]);

        return json(['code' => 1, 'msg' => '注册成功，请使用学号 + 密码登录']);
    }
}
