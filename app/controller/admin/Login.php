<?php
namespace app\controller\admin;

use app\model\Admin;

class Login
{
    public function index()
    {
        if (!empty($_SESSION['admin_id'])) {
            return redirect((string) url('admin.Dashboard/index'));
        }
        return view('admin/login/index');
    }

    public function doLogin()
    {
        $username = trim(input('post.username', ''));
        $password = input('post.password', '');

        if (!$username || !$password) {
            return json(['code' => 0, 'msg' => '用户名和密码不能为空']);
        }

        $admin = Admin::where('username', $username)->find();
        if (!$admin || !$admin->checkPassword($password)) {
            return json(['code' => 0, 'msg' => '用户名或密码错误']);
        }

        $_SESSION['admin_id'] = $admin->id;
        $_SESSION['admin_user'] = [
            'id' => $admin->id,
            'username' => $admin->username,
            'real_name' => $admin->real_name,
        ];

        $admin->save([
            'last_login_time' => date('Y-m-d H:i:s'),
            'last_login_ip' => $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
        ]);

        return json(['code' => 1, 'msg' => '登录成功', 'url' => '/admin/dashboard']);
    }

    public function logout()
    {
        $_SESSION = [];
        session_destroy();
        return redirect('/admin/login');
    }
}
