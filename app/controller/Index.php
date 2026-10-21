<?php
namespace app\controller;

class Index
{
    /**
     * 门户首页：系统名称 + 各子系统入口
     */
    public function index()
    {
        return view('index/index', [
            'systemTitle' => system_title(),
            'isAdmin'     => !empty($_SESSION['admin_id']),
            'isStudent'   => !empty($_SESSION['student_id']),
        ]);
    }

    /**
     * 抽题系统首页：教师/学生登录、学生注册入口
     */
    public function draw()
    {
        return view('index/draw');
    }
}
