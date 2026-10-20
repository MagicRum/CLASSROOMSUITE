<?php
// +----------------------------------------------------------------------
// | 学生管理控制器（含 Excel 导入）
// +----------------------------------------------------------------------

namespace app\controller\admin;

use app\model\Classes;
use app\model\Student as StudentModel;
use PhpOffice\PhpSpreadsheet\IOFactory;

class Student extends Base
{
    /**
     * 学生列表
     */
    public function index()
    {
        $classId = input('class_id', 0);
        $keyword = input('keyword', '');
        $page = input('page', 1);
        $limit = input('limit', 20);

        $query = StudentModel::alias('s')->join('classes c', 's.class_id = c.id', 'left')
            ->field('s.*, c.class_name');

        if ($classId) {
            $query->where('s.class_id', $classId);
        }
        if ($keyword) {
            $query->where(function ($q) use ($keyword) {
                $q->where('s.student_no', 'like', "%{$keyword}%")
                  ->whereOr('s.name', 'like', "%{$keyword}%");
            });
        }

        $list = $query->order('s.id', 'desc')->paginate([
            'list_rows' => $limit,
            'page' => $page,
            'query' => request()->param(),
        ]);

        $classes = Classes::order('id', 'asc')->select();

        return view('admin/student/index', [
            'list' => $list,
            'classes' => $classes,
            'class_id' => $classId,
            'keyword' => $keyword,
        ]);
    }

    /**
     * 添加/编辑学生页
     */
    public function edit()
    {
        $id = input('id', 0);
        $student = $id ? StudentModel::find($id) : null;
        $classes = Classes::order('id', 'asc')->select();
        return view('admin/student/edit', [
            'student' => $student,
            'classes' => $classes,
        ]);
    }

    /**
     * 保存学生
     */
    public function save()
    {
        $id = input('post.id', 0);
        $data = [
            'student_no' => trim(input('post.student_no', '')),
            'name'       => trim(input('post.name', '')),
            'class_id'   => intval(input('post.class_id', 0)),
            'phone'      => trim(input('post.phone', '')),
        ];

        if (!$data['student_no'] || !$data['name'] || !$data['class_id']) {
            return $this->error('学号、姓名、班级不能为空');
        }

        // 学号唯一性检查
        $exists = StudentModel::where('student_no', $data['student_no']);
        if ($id) {
            $exists->where('id', '<>', $id);
        }
        if ($exists->find()) {
            return $this->error('学号已存在');
        }

        if ($id) {
            StudentModel::where('id', $id)->update($data);
        } else {
            StudentModel::create($data);
        }

        return $this->success('保存成功', null, (string) url('admin/Student/index'));
    }

    /**
     * 删除学生
     */
    public function delete()
    {
        $id = input('id', 0);
        if (!$id) return $this->error('参数错误');
        StudentModel::where('id', $id)->delete();
        return $this->success('删除成功');
    }

    /**
     * 批量删除
     */
    public function batchDelete()
    {
        $ids = input('ids/a', []);
        if (empty($ids)) return $this->error('请选择要删除的记录');
        StudentModel::whereIn('id', $ids)->delete();
        return $this->success('批量删除成功');
    }

    /**
     * Excel 导入学生名单
     * Excel 列顺序：学号 | 姓名 | 班级名称 | 手机号（可选）
     */
    public function import()
    {
        if (request()->isPost()) {
            $file = request()->file('file');
            if (!$file) return $this->error('请选择上传文件');

            try {
                $spreadsheet = IOFactory::load($file->getPathname());
                $worksheet = $spreadsheet->getActiveSheet();
                $highestRow = $worksheet->getHighestRow();

                $success = 0;
                $fail = 0;
                $errors = [];
                $cachedClasses = []; // 班级名 => id 缓存

                for ($row = 2; $row <= $highestRow; $row++) {
                    $studentNo = trim((string) $worksheet->getCell("A{$row}")->getValue());
                    $name = trim((string) $worksheet->getCell("B{$row}")->getValue());
                    $className = trim((string) $worksheet->getCell("C{$row}")->getValue());
                    $phone = trim((string) $worksheet->getCell("D{$row}")->getValue());

                    if (!$studentNo || !$name) {
                        $fail++;
                        $errors[] = "第{$row}行：学号或姓名为空";
                        continue;
                    }

                    // 查找/创建班级
                    $classId = 0;
                    if ($className) {
                        if (isset($cachedClasses[$className])) {
                            $classId = $cachedClasses[$className];
                        } else {
                            $cls = Classes::where('class_name', $className)->find();
                            if (!$cls) {
                                $cls = Classes::create(['class_name' => $className]);
                            }
                            $classId = $cls->id;
                            $cachedClasses[$className] = $classId;
                        }
                    }

                    // 查重：已存在则跳过
                    $exists = StudentModel::where('student_no', $studentNo)->find();
                    if ($exists) {
                        $exists->save([
                            'name'     => $name,
                            'class_id' => $classId,
                            'phone'    => $phone,
                        ]);
                    } else {
                        StudentModel::create([
                            'student_no' => $studentNo,
                            'name'       => $name,
                            'class_id'   => $classId,
                            'phone'      => $phone,
                        ]);
                    }
                    $success++;
                }

                return $this->success("导入完成：成功{$success}条，失败{$fail}条", [
                    'errors' => array_slice($errors, 0, 10),
                ]);
            } catch (\Exception $e) {
                return $this->error('文件解析失败：' . $e->getMessage());
            }
        }

        // 显示导入页（含模板下载按钮）
        return view('admin/student/import');
    }

    /**
     * 下载 Excel 导入模板
     */
    public function template()
    {
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="学生名单导入模板.xlsx"');

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('学生名单');

        $sheet->setCellValue('A1', '学号');
        $sheet->setCellValue('B1', '姓名');
        $sheet->setCellValue('C1', '班级名称');
        $sheet->setCellValue('D1', '手机号（可选）');

        $sheet->setCellValue('A2', '2024001');
        $sheet->setCellValue('B2', '张三');
        $sheet->setCellValue('C2', '计算机1班');
        $sheet->setCellValue('D2', '13800000000');

        foreach (range('A', 'D') as $col) {
            $sheet->getColumnDimension($col)->setWidth(18);
        }

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
    }
}
