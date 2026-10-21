<?php
// +----------------------------------------------------------------------
// | 在线判题 - 题库分类
// +----------------------------------------------------------------------

namespace app\controller\admin;

use think\facade\Db;

class OjCategory extends Base
{
    public function index()
    {
        $list = Db::name('oj_categories')->order('sort', 'asc')->order('id', 'asc')->select();
        $list = is_array($list) ? $list : $list->toArray();

        $counts = [];
        $rows = Db::name('oj_problems')->field('category_id, COUNT(*) AS cnt')->group('category_id')->select();
        foreach ($rows as $row) {
            $counts[intval($row['category_id'])] = intval($row['cnt']);
        }
        foreach ($list as $k => $item) {
            $list[$k]['problem_count'] = $counts[intval($item['id'])] ?? 0;
        }

        return view('admin/oj/category/index', [
            'list' => $list,
        ]);
    }

    public function save()
    {
        $id = intval(input('post.id', 0));
        $name = trim(input('post.name', ''));
        $parentId = intval(input('post.parent_id', 0));
        $sort = intval(input('post.sort', 0));

        if ($name === '') {
            return $this->error('分类名称不能为空');
        }
        if ($id && $parentId === $id) {
            return $this->error('上级分类不能是自己');
        }

        $query = Db::name('oj_categories')->where('name', $name);
        if ($id) {
            $query->where('id', '<>', $id);
        }
        if ($query->find()) {
            return $this->error('分类名称已存在');
        }

        $data = [
            'name'      => $name,
            'parent_id' => $parentId,
            'sort'      => $sort,
        ];

        if ($id) {
            Db::name('oj_categories')->where('id', $id)->update($data);
            return $this->success('修改成功');
        }
        Db::name('oj_categories')->insert($data);
        return $this->success('添加成功');
    }

    public function delete()
    {
        $id = intval(input('post.id', 0));
        if (!$id) {
            return $this->error('参数错误');
        }
        if (Db::name('oj_categories')->where('parent_id', $id)->count() > 0) {
            return $this->error('请先删除该分类下的子分类');
        }
        $cnt = Db::name('oj_problems')->where('category_id', $id)->count();
        if ($cnt > 0) {
            return $this->error("该分类下还有 {$cnt} 道题目，请先移动或删除题目");
        }
        Db::name('oj_categories')->where('id', $id)->delete();
        return $this->success('删除成功');
    }
}
