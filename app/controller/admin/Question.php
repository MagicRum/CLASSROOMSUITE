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

        return $this->success('保存成功', null, '/admin/question');
    }

    /**
     * 删除试题
     */
    public function delete()
    {
        if ($resp = $this->requirePost()) return $resp;
        $id = input('post.id', 0);
        if (!$id) return $this->error('参数错误');
        QuestionModel::where('id', $id)->delete();
        return $this->success('删除成功');
    }

    /**
     * Excel 导入试题库首页（GET 渲染向导首页）
     */
    public function import()
    {
        return view('admin/question/import', [
            'typeMap'     => QuestionModel::$typeMap,
            'difficultyMap' => QuestionModel::$difficultyMap,
        ]);
    }

    /**
     * 预览：解析 Excel，返回每行校验结果，不入库
     */
    public function importPreview()
    {
        if (!request()->isPost()) {
            return json(['code' => 0, 'msg' => '请求方式错误']);
        }
        $file = request()->file('file');
        if (!$file) return json(['code' => 0, 'msg' => '请选择上传文件']);

        try {
            $spreadsheet = IOFactory::load($file->getPathname());
            $worksheet = $spreadsheet->getActiveSheet();
            $highestRow = $worksheet->getHighestRow();

            $rows = [];
            for ($row = 2; $row <= $highestRow; $row++) {
                $title    = trim((string) $worksheet->getCell("A{$row}")->getValue());
                $qType    = intval($worksheet->getCell("B{$row}")->getValue() ?? 1);
                $optA     = trim((string) $worksheet->getCell("C{$row}")->getValue());
                $optB     = trim((string) $worksheet->getCell("D{$row}")->getValue());
                $optC     = trim((string) $worksheet->getCell("E{$row}")->getValue());
                $optD     = trim((string) $worksheet->getCell("F{$row}")->getValue());
                $answer   = trim((string) $worksheet->getCell("G{$row}")->getValue());
                $score    = $worksheet->getCell("H{$row}")->getValue();
                $diff     = intval($worksheet->getCell("I{$row}")->getValue() ?? 2);
                $category = trim((string) $worksheet->getCell("J{$row}")->getValue());

                // 空行跳过（整行全空视为结束）
                if ($title === '' && $optA === '' && $optB === '' && $answer === '') {
                    continue;
                }

                // 题型容错
                if (!in_array($qType, [1, 2, 3, 4, 5], true)) {
                    $qType = 1;
                }
                // 分值容错
                if ($score === null || $score === '' || !is_numeric($score)) {
                    $score = 5;
                }
                $score = floatval($score);
                if ($score <= 0) $score = 5;
                // 难度容错
                if (!in_array($diff, [1, 2, 3], true)) $diff = 2;

                // 校验
                $errors = [];
                if ($title === '') {
                    $errors[] = '题目内容不能为空';
                }
                $answerNorm = $qType == 5 ? $answer : strtoupper($answer);
                // 简答题参考答案可为空
                if ($qType != 5 && $answerNorm === '') {
                    $errors[] = '正确答案不能为空';
                }
                // 判断题答案应为 A 或 B（A=正确，B=错误）
                if ($qType == 3 && $answerNorm !== '' && !in_array($answerNorm, ['A', 'B'], true)) {
                    $errors[] = '判断题答案应为 A 或 B（A=正确，B=错误）';
                }
                // 单选答案必须在 A-D 之间
                if ($qType == 1 && $answerNorm !== '' && !preg_match('/^[A-D]$/', $answerNorm)) {
                    $errors[] = '单选题答案应为 A/B/C/D 中的一项';
                }
                // 多选答案必须由 A-D 中的字母组成
                if ($qType == 2 && $answerNorm !== '' && !preg_match('/^[A-D]+$/', $answerNorm)) {
                    $errors[] = '多选题答案应由 A-D 中字母组合而成';
                }

                $rows[] = [
                    'row_num'        => $row,
                    'title'          => $title,
                    'question_type'  => $qType,
                    'option_a'       => $optA,
                    'option_b'       => $optB,
                    'option_c'       => $optC,
                    'option_d'       => $optD,
                    'correct_answer' => $answerNorm,
                    'score'          => $score,
                    'difficulty'     => $diff,
                    'category'       => $category,
                    'valid'          => empty($errors),
                    'errors'         => $errors,
                ];
            }

            $total = count($rows);
            $valid = 0;
            foreach ($rows as $r) {
                if ($r['valid']) $valid++;
            }
            $existsTitles = [];
            if ($valid > 0) {
                $titles = array_values(array_unique(array_column($rows, 'title')));
                if ($titles) {
                    $hits = QuestionModel::whereIn('title', $titles)->column('id', 'title');
                    $existsTitles = $hits;
                }
            }
            foreach ($rows as $idx => $r) {
                if (isset($existsTitles[$r['title']])) {
                    $rows[$idx]['exists_id'] = intval($existsTitles[$r['title']]);
                    $rows[$idx]['exists'] = true;
                } else {
                    $rows[$idx]['exists'] = false;
                }
            }

            return json([
                'code' => 1,
                'msg'  => '解析完成',
                'data' => [
                    'rows'  => $rows,
                    'total' => $total,
                    'valid' => $valid,
                ],
            ]);
        } catch (\Exception $e) {
            return json(['code' => 0, 'msg' => '文件解析失败：' . $e->getMessage()]);
        }
    }

    /**
     * 确认导入：接收前端编辑后的 JSON 数组，执行 upsert
     */
    public function confirmImport()
    {
        if (!request()->isPost()) {
            return json(['code' => 0, 'msg' => '请求方式错误']);
        }
        $raw = file_get_contents('php://input');
        $data = json_decode($raw, true);
        if (!is_array($data) || empty($data['rows'])) {
            return json(['code' => 0, 'msg' => '未收到待导入数据']);
        }
        $rows = $data['rows'];
        $success = 0;
        $updated = 0;
        $fail = 0;

        foreach ($rows as $r) {
            // 前端可传 skip=1 让某行被跳过
            if (!empty($r['skip'])) { $fail++; continue; }

            $title = isset($r['title']) ? trim((string) $r['title']) : '';
            if ($title === '') { $fail++; continue; }
            $qType = isset($r['question_type']) ? intval($r['question_type']) : 1;
            if (!in_array($qType, [1, 2, 3, 4, 5], true)) { $qType = 1; }

            $answerRaw = isset($r['correct_answer']) ? trim((string) $r['correct_answer']) : '';
            $answer = $qType == 5 ? $answerRaw : strtoupper($answerRaw);
            $score = isset($r['score']) && is_numeric($r['score']) ? floatval($r['score']) : 5;
            if ($score <= 0) $score = 5;
            $diff = isset($r['difficulty']) ? intval($r['difficulty']) : 2;
            if (!in_array($diff, [1, 2, 3], true)) $diff = 2;

            // 服务端防御性校验（前端可能被绕过）
            $invalid = false;
            if ($qType != 5 && $answer === '') { $invalid = true; }
            if ($qType == 1 && $answer !== '' && !preg_match('/^[A-D]$/', $answer)) { $invalid = true; }
            if ($qType == 2 && $answer !== '' && !preg_match('/^[A-D]+$/', $answer)) { $invalid = true; }
            if ($qType == 3 && $answer !== '' && !in_array($answer, ['A', 'B'], true)) { $invalid = true; }
            if ($invalid) { $fail++; continue; }

            $payload = [
                'title'           => $title,
                'question_type'   => $qType,
                'option_a'        => trim((string) ($r['option_a'] ?? '')),
                'option_b'        => trim((string) ($r['option_b'] ?? '')),
                'option_c'        => trim((string) ($r['option_c'] ?? '')),
                'option_d'        => trim((string) ($r['option_d'] ?? '')),
                'correct_answer'  => $answer,
                'score'           => $score,
                'difficulty'      => $diff,
                'category'        => trim((string) ($r['category'] ?? '')),
            ];

            try {
                $existsId = isset($r['exists_id']) && intval($r['exists_id']) > 0
                    ? intval($r['exists_id'])
                    : QuestionModel::where('title', $title)->value('id');
                if ($existsId) {
                    QuestionModel::where('id', $existsId)->update($payload);
                    $updated++;
                } else {
                    QuestionModel::create($payload);
                    $success++;
                }
            } catch (\Exception $e) {
                $fail++;
            }
        }

        return json([
            'code' => 1,
            'msg'  => "导入完成：新增{$success}条，更新{$updated}条，跳过{$fail}条",
            'data' => [
                'success' => $success,
                'updated' => $updated,
                'fail'    => $fail,
            ],
        ]);
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
