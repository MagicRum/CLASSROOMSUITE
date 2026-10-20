<?php
// +----------------------------------------------------------------------
// | 路由配置
// | 单应用模式下，路由目标格式: 子目录.控制器/方法
// | 例如: admin.Login/index  → app\controller\admin\Login::index()
// +----------------------------------------------------------------------

use think\facade\Route;

// 默认首页
Route::rule('/', 'Index/index');

// ============== 管理员端 - 公开路由（无需登录） ==============
// 注意：更具体的路由必须放在前面，否则会被短路由贪婪匹配
Route::rule('admin/login/doLogin', 'admin.Login/doLogin');
Route::rule('admin/login', 'admin.Login/index');
Route::rule('admin/logout', 'admin.Login/logout');

// ============== 管理员端 - 受保护路由 ==============
// 注意：具体动作路由必须放在索引路由(xxx)前面，否则被贪婪匹配
Route::group('admin', function () {
    Route::rule('dashboard', 'admin.Dashboard/index');

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
    Route::rule('exam', 'admin.Exam/index');
})->middleware(\app\middleware\AdminAuth::class);
