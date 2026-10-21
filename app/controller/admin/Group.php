<?php
// +----------------------------------------------------------------------
// | 分组管理控制器（随机分组 + 导出）
// +----------------------------------------------------------------------

namespace app\controller\admin;

use app\model\Classes;
use app\model\Student;
use app\model\Group as GroupModel;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

class Group extends Base
{
    /**
     * 分组管理首页
     */
    public function index()
    {
        $classId = input('class_id', 0);
        $classes = Classes::order('id', 'asc')->select();

        $groups = [];
        if ($classId) {
            $groups = GroupModel::where('class_id', $classId)->order('id', 'asc')->select();
            foreach ($groups as &$g) {
                $g['student_count'] = Student::where('group_id', $g->id)->count();
                $g['students'] = Student::where('group_id', $g->id)->order('id', 'asc')->select();
            }
        }

        return view('admin/group/index', [
            'classes' => $classes,
            'class_id' => $classId,
            'groups' => $groups,
        ]);
    }

    /**
     * 随机分组
     * 参数：class_id, group_count（组数） 或 group_size（每组人数）
     */
    public function random()
    {
        $classId = intval(input('post.class_id', 0));
        $groupCount = intval(input('post.group_count', 0));
        $groupSize = intval(input('post.group_size', 0));

        if (!$classId) return $this->error('请选择班级');

        $students = Student::where('class_id', $classId)->select();
        $total = count($students);

        if ($total == 0) return $this->error('该班级暂无学生');

        // 确定组数
        if ($groupSize > 0) {
            $groupCount = (int) ceil($total / $groupSize);
        }
        if ($groupCount <= 0) return $this->error('请设置组数或每组人数');

        // 1. 打乱学生数组
        $studentIds = array_column($students->toArray(), 'id');
        shuffle($studentIds);

        // 2. 清空该班级原有分组
        GroupModel::where('class_id', $classId)->delete();
        Student::where('class_id', $classId)->update(['group_id' => 0]);

        // 3. 切片分配
        $groups = array_chunk($studentIds, (int) ceil($total / $groupCount));

        foreach ($groups as $idx => $ids) {
            $group = GroupModel::create([
                'class_id' => $classId,
                'group_name' => "第" . ($idx + 1) . "组",
            ]);
            Student::whereIn('id', $ids)->update(['group_id' => $group->id]);
        }

        return $this->success("分组完成，共分" . count($groups) . "组", null, '/admin/group?class_id=' . $classId);
    }

    /**
     * 手动创建分组
     */
    public function create()
    {
        $classId = intval(input('post.class_id', 0));
        $groupName = trim(input('post.group_name', ''));
        if (!$classId || !$groupName) return $this->error('参数不完整');

        GroupModel::create([
            'class_id' => $classId,
            'group_name' => $groupName,
        ]);

        return $this->success('创建成功');
    }

    /**
     * 删除分组
     */
    public function delete()
    {
        if ($resp = $this->requirePost()) return $resp;
        $id = input('post.id', 0);
        if (!$id) return $this->error('参数错误');
        GroupModel::where('id', $id)->delete();
        Student::where('group_id', $id)->update(['group_id' => 0]);
        return $this->success('删除成功');
    }

    /**
     * 导出分组结果为 Excel
     */
    public function export()
    {
        $classId = intval(input('class_id', 0));
        if (!$classId) return $this->error('请选择班级');

        $classInfo = Classes::find($classId);
        if (!$classInfo) return $this->error('班级不存在');

        $groups = GroupModel::where('class_id', $classId)->order('id', 'asc')->select();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle($classInfo->class_name);

        $sheet->setCellValue('A1', $classInfo->class_name . ' 分组名单');
        $sheet->mergeCells('A1:D1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

        $row = 3;
        foreach ($groups as $idx => $group) {
            $sheet->setCellValue("A{$row}", $group->group_name);
            $sheet->mergeCells("A{$row}:D{$row}");
            $sheet->getStyle("A{$row}")->getFont()->setBold(true)->setSize(12);
            $row++;

            $sheet->setCellValue("A{$row}", '学号');
            $sheet->setCellValue("B{$row}", '姓名');
            $sheet->setCellValue("C{$row}", '手机号');
            $sheet->mergeCells("A{$row}:D{$row}");
            $row++;

            $students = Student::where('group_id', $group->id)->order('id', 'asc')->select();
            foreach ($students as $s) {
                $sheet->setCellValue("A{$row}", $s->student_no);
                $sheet->setCellValue("B{$row}", $s->name);
                $sheet->setCellValue("C{$row}", $s->phone);
                $sheet->mergeCells("A{$row}:D{$row}");
                $row++;
            }
            $row++;
        }

        foreach (range('A', 'D') as $c) {
            $sheet->getColumnDimension($c)->setWidth(25);
        }

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $classInfo->class_name . '_分组结果.xlsx"');

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
    }
}
