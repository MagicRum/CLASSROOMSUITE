<?php
// +----------------------------------------------------------------------
// | 路由配置
// | 单应用模式下，路由目标格式: 子目录.控制器/方法
// | 例如: admin.Login/index  → app\controller\admin\Login::index()
// +----------------------------------------------------------------------

use think\facade\Route;

// 默认首页（系统门户）
Route::rule('/', 'Index/index');
// 抽题系统首页（登录 / 注册入口）
Route::rule('draw', 'Index/draw');

// ============== 管理员端 - 公开路由（无需登录） ==============
// 注意：更具体的路由必须放在前面，否则会被短路由贪婪匹配
Route::rule('admin/login/doLogin', 'admin.Login/doLogin');
Route::rule('admin/login', 'admin.Login/index');
Route::rule('admin/logout', 'admin.Login/logout');

// ============== 学生端 - 公开路由（无需登录） ==============
Route::rule('student/register/doRegister', 'student.Register/doRegister');
Route::rule('student/register', 'student.Register/index');
Route::rule('student/login/doLogin', 'student.Login/doLogin');
Route::rule('student/login', 'student.Login/index');
Route::rule('student/logout', 'student.Login/logout');

// ============== 学生端 - 受保护路由 ==============
Route::group('student', function () {
    Route::rule('draw/go', 'student.Exam/draw');
    Route::rule('draw', 'student.Exam/index');
    Route::rule('records/detail', 'student.Exam/detail');
    Route::rule('records', 'student.Exam/records');
})->middleware(\app\middleware\StudentAuth::class);

// ============== 在线判题 - 学生端 ==============
Route::group('oj', function () {
    Route::rule('status', 'student.Oj/status');
    Route::rule('submit', 'student.Oj/submit');
    Route::rule('run', 'student.Oj/run');
    Route::rule('problem', 'student.Oj/problem');
    Route::rule('records', 'student.Oj/records');
})->middleware(\app\middleware\StudentAuth::class);
// 索引路由放最后，避免贪婪匹配拦截具体动作
Route::rule('oj', 'student.Oj/index')->middleware(\app\middleware\StudentAuth::class);

// ============== 管理员端 - 受保护路由 ==============
// 注意：具体动作路由必须放在索引路由(xxx)前面，否则被贪婪匹配
Route::group('admin', function () {
    Route::rule('dashboard', 'admin.Dashboard/index');

    // 在线判题
    Route::rule('oj/upload', 'admin.OjProblem/upload');
    Route::rule('oj/pretest/run', 'admin.OjSubmit/pretestRun');
    Route::rule('oj/pretest', 'admin.OjSubmit/pretest');
    Route::rule('oj/submission/status', 'admin.OjSubmit/status');
    Route::rule('oj/submission/detail', 'admin.OjSubmit/detail');
    Route::rule('oj/submission', 'admin.OjSubmit/index');
    Route::rule('oj/export', 'admin.OjSubmit/export');
    Route::rule('oj/stats', 'admin.OjSubmit/stats');
    Route::rule('oj/problem/toggle', 'admin.OjProblem/toggle');
    Route::rule('oj/problem/copy', 'admin.OjProblem/copy');
    Route::rule('oj/problem/delete', 'admin.OjProblem/delete');
    Route::rule('oj/problem/save', 'admin.OjProblem/save');
    Route::rule('oj/problem/edit', 'admin.OjProblem/edit');
    Route::rule('oj/problem', 'admin.OjProblem/index');
    Route::rule('oj/case/save', 'admin.OjCase/save');
    Route::rule('oj/case/delete', 'admin.OjCase/delete');
    Route::rule('oj/case/toggle', 'admin.OjCase/toggle');
    Route::rule('oj/case/upload', 'admin.OjCase/upload');
    Route::rule('oj/category/save', 'admin.OjCategory/save');
    Route::rule('oj/category/delete', 'admin.OjCategory/delete');
    Route::rule('oj/category', 'admin.OjCategory/index');
    Route::rule('oj', 'admin.OjProblem/index');

    Route::rule('setting/save', 'admin.Setting/save');
    Route::rule('setting', 'admin.Setting/index');

    Route::rule('student/edit', 'admin.Student/edit');
    Route::rule('student/save', 'admin.Student/save');
    Route::rule('student/delete', 'admin.Student/delete');
    Route::rule('student/batchDelete', 'admin.Student/batchDelete');
    Route::rule('student/import', 'admin.Student/import');
    Route::rule('student/template', 'admin.Student/template');
    Route::rule('student', 'admin.Student/index');

    Route::rule('question/edit', 'admin.Question/edit');
    Route::rule('question/save', 'admin.Question/save');
    Route::rule('question/delete', 'admin.Question/delete');
    Route::rule('question/import', 'admin.Question/import');
    Route::rule('question/template', 'admin.Question/template');
    Route::rule('question', 'admin.Question/index');

    Route::rule('group/random', 'admin.Group/random');
    Route::rule('group/create', 'admin.Group/create');
    Route::rule('group/delete', 'admin.Group/delete');
    Route::rule('group/export', 'admin.Group/export');
    Route::rule('group', 'admin.Group/index');

    Route::rule('draw/go', 'admin.Draw/go');
    Route::rule('draw', 'admin.Draw/index');

    Route::rule('clazz/save', 'admin.Clazz/save');
    Route::rule('clazz/delete', 'admin.Clazz/delete');
    Route::rule('clazz', 'admin.Clazz/index');

    Route::rule('exam/detail', 'admin.Exam/detail');
    Route::rule('exam/delete', 'admin.Exam/delete');
    Route::rule('exam/batchDelete', 'admin.Exam/batchDelete');
    Route::rule('exam', 'admin.Exam/index');
})->middleware(\app\middleware\AdminAuth::class);
