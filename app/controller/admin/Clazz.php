<?php
// +----------------------------------------------------------------------
// | 班级管理
// +----------------------------------------------------------------------

namespace app\controller\admin;

use app\model\Classes;
use app\model\Student as StudentModel;

class Clazz extends Base
{
    /**
     * 班级列表（含学生数）
     */
    public function index()
    {
        $list = Classes::order('id', 'asc')->select();
        foreach ($list as $c) {
            $c->student_count = StudentModel::where('class_id', $c->id)->count();
        }
        return view('admin/clazz/index', [
            'active' => 'clazz',
            'list'   => $list,
        ]);
    }

    /**
     * 添加/编辑班级
     */
    public function save()
    {
        $id   = intval(input('post.id', 0));
        $name = trim(input('post.class_name', ''));
        if (!$name) {
            return $this->error('班级名称不能为空');
        }

        $query = Classes::where('class_name', $name);
        if ($id) {
            $query->where('id', '<>', $id);
        }
        if ($query->find()) {
            return $this->error('班级名称已存在');
        }

        if ($id) {
            Classes::update(['id' => $id, 'class_name' => $name]);
            return $this->success('修改成功');
        }
        Classes::create(['class_name' => $name]);
        return $this->success('添加成功');
    }

    /**
     * 删除班级（有学生时禁止删除）
     */
    public function delete()
    {
        $id = intval(input('post.id', 0));
        $cnt = StudentModel::where('class_id', $id)->count();
        if ($cnt > 0) {
            return $this->error("该班级下还有 {$cnt} 名学生，请先删除或转移学生");
        }
        Classes::destroy($id);
        return $this->success('删除成功');
    }
}
