<?php
namespace app\controller;

class Index
{
    public function index()
    {
        if (!empty($_SESSION['admin_id'])) {
            return redirect('/admin/dashboard');
        }
        if (!empty($_SESSION['student_id'])) {
            return redirect('/student/exam');
        }
        return view('index/index');
    }
}
