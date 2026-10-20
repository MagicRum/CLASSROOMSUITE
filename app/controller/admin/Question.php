<?php
// +----------------------------------------------------------------------
// | 试题管理控制器（含 Excel 导入）
// +----------------------------------------------------------------------

namespace app\controller\admin;

use app\model\Question as QuestionModel;
use PhpOffice\PhpSpreadsheet\IOFactory;

class Question extends Base
{
    /**
     * 试题列表
     */
    public function index()
    {
        $type = input('question_type', 0);
        $difficulty = input('difficulty', 0);
        $keyword = input('keyword', '');
        $page = input('page', 1);
        $limit = input('limit', 20);

        $query = QuestionModel::where('id', '>', 0);
        if ($type) $query->where('question_type', $type);
        if ($difficulty) $query->where('difficulty', $difficulty);
        if ($keyword) $query->where('title', 'like', "%{$keyword}%");

        $list = $query->order('id', 'desc')->paginate([
            'list_rows' => $limit,
            'page' => $page,
            'query' => request()->param(),
        ]);

        return view('admin/question/index', [
            'list' => $list,
            'typeMap' => QuestionModel::$typeMap,
            'difficultyMap' => QuestionModel::$difficultyMap,
            'question_type' => $type,
            'difficulty' => $difficulty,
            'keyword' => $keyword,
        ]);
    }

    /**
     * 添加/编辑试题页
     */
    public function edit()
    {
        $id = input('id', 0);
        $question = $id ? QuestionModel::find($id) : null;
        return view('admin/question/edit', [
            'question' => $question,
            'typeMap' => QuestionModel::$typeMap,
            'difficultyMap' => QuestionModel::$difficultyMap,
        ]);
    }

    /**
     * 保存试题
     */
    public function save()
    {
        $id = input('post.id', 0);
        $qType = intval(input('post.question_type', 1));
        $rawAnswer = trim(input('post.correct_answer', ''));
        $data = [
            'title'           => trim(input('post.title', '')),
            'question_type'   => $qType,
            'option_a'        => trim(input('post.option_a', '')),
            'option_b'        => trim(input('post.option_b', '')),
            'option_c'        => trim(input('post.option_c', '')),
            'option_d'        => trim(input('post.option_d', '')),
            // 简答题答案为中文参考文本，不转大写
            'correct_answer'  => $qType == 5 ? $rawAnswer : strtoupper($rawAnswer),
            'score'           => floatval(input('post.score', 5)),
            'difficulty'      => intval(input('post.difficulty', 2)),
            'category'        => trim(input('post.category', '')),
        ];

        if (!$data['title']) {
            return $this->error('题目内容不能为空');
        }
        // 简答题用于面试抽题，参考答案可为空
        if ($qType != 5 && !$data['correct_answer']) {
            return $this->error('正确答案不能为空');
        }

        if ($id) {
            QuestionModel::where('id', $id)->update($data);
        } else {
            QuestionModel::create($data);
        }

        return $this->success('保存成功', null, (string) url('admin/Question/index'));
    }

    /**
     * 删除试题
     */
    public function delete()
    {
        $id = input('id', 0);
        if (!$id) return $this->error('参数错误');
        QuestionModel::where('id', $id)->delete();
        return $this->success('删除成功');
    }

    /**
     * Excel 导入试题库
     * 列：题目 | 题型(1单选/2多选/3判断) | 选项A | 选项B | 选项C | 选项D | 正确答案 | 分值 | 难度(1简单/2中等/3困难) | 分类
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

                for ($row = 2; $row <= $highestRow; $row++) {
                    $title = trim((string) $worksheet->getCell("A{$row}")->getValue());
                    $qType = intval($worksheet->getCell("B{$row}")->getValue() ?? 1);
                    $optA = trim((string) $worksheet->getCell("C{$row}")->getValue());
                    $optB = trim((string) $worksheet->getCell("D{$row}")->getValue());
                    $optC = trim((string) $worksheet->getCell("E{$row}")->getValue());
                    $optD = trim((string) $worksheet->getCell("F{$row}")->getValue());
                    $answer = trim(strtoupper((string) $worksheet->getCell("G{$row}")->getValue()));
                    $score = floatval($worksheet->getCell("H{$row}")->getValue() ?? 5);
                    $diff = intval($worksheet->getCell("I{$row}")->getValue() ?? 2);
                    $category = trim((string) $worksheet->getCell("J{$row}")->getValue());

                    // 题型容错（5=简答题）
                    if (!in_array($qType, [1, 2, 3, 4, 5])) $qType = 1;

                    // 简答题参考答案可为空
                    if (!$title || ($qType != 5 && !$answer)) {
                        $fail++;
                        $errors[] = "第{$row}行：题目或答案为空";
                        continue;
                    }

                    // 查重：同题目标题不重复
                    $exists = QuestionModel::where('title', $title)->find();
                    if ($exists) {
                        $exists->save([
                            'question_type'  => $qType,
                            'option_a'       => $optA,
                            'option_b'       => $optB,
                            'option_c'       => $optC,
                            'option_d'       => $optD,
                            'correct_answer' => $answer,
                            'score'          => $score,
                            'difficulty'     => $diff,
                            'category'       => $category,
                        ]);
                    } else {
                        QuestionModel::create([
                            'title'           => $title,
                            'question_type'   => $qType,
                            'option_a'        => $optA,
                            'option_b'        => $optB,
                            'option_c'        => $optC,
                            'option_d'        => $optD,
                            'correct_answer'  => $answer,
                            'score'           => $score,
                            'difficulty'      => $diff,
                            'category'        => $category,
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

        return view('admin/question/import');
    }

    /**
     * 下载试题导入模板
     */
    public function template()
    {
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="试题库导入模板.xlsx"');

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('试题库');

        $headers = ['题目', '题型(1单选/2多选/3判断/4填空/5简答)', '选项A', '选项B', '选项C', '选项D', '正确答案(简答填参考答案可空)', '分值', '难度(1简单/2中等/3困难)', '分类'];
        foreach ($headers as $i => $h) {
            $sheet->setCellValue(chr(65 + $i) . '1', $h);
        }

        // 示例数据
        $sheet->setCellValue('A2', 'PHP中用于输出文本的函数是？');
        $sheet->setCellValue('B2', '1');
        $sheet->setCellValue('C2', 'echo');
        $sheet->setCellValue('D2', 'print');
        $sheet->setCellValue('E2', 'printf');
        $sheet->setCellValue('F2', '以上都可以');
        $sheet->setCellValue('G2', 'D');
        $sheet->setCellValue('H2', '5');
        $sheet->setCellValue('I2', '2');
        $sheet->setCellValue('J2', 'PHP基础');

        $sheet->setCellValue('A3', 'HTTP是无状态协议');
        $sheet->setCellValue('B3', '3');
        $sheet->setCellValue('C3', '');
        $sheet->setCellValue('D3', '');
        $sheet->setCellValue('E3', '');
        $sheet->setCellValue('F3', '');
        $sheet->setCellValue('G3', 'A');
        $sheet->setCellValue('H3', '5');
        $sheet->setCellValue('I3', '1');
        $sheet->setCellValue('J3', '网络基础');

        for ($c = 'A'; $c <= 'J'; $c++) {
            $sheet->getColumnDimension($c)->setWidth(20);
        }

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
    }
}
