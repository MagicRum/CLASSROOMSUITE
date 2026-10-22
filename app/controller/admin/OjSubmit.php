<?php
// +----------------------------------------------------------------------
// | 在线判题 - 提交记录、预评测、成绩与统计
// +----------------------------------------------------------------------

namespace app\controller\admin;

use app\service\JudgeService;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use think\facade\Db;

class OjSubmit extends Base
{
    public function index()
    {
        $classId = intval(input('class_id', 0));
        $problemId = intval(input('problem_id', 0));
        $language = trim(input('language', ''));
        $status = trim(input('status', ''));
        $keyword = trim(input('keyword', ''));
        $start = trim(input('start', ''));
        $end = trim(input('end', ''));

        $filters = compact('classId', 'problemId', 'language', 'status', 'keyword', 'start', 'end');

        // 按学生分组分页：每个学生一条汇总，展开后查看其全部提交
        $list = $this->filterQuery($filters)
            ->field('student_id, student_no, student_name, class_id, COUNT(*) AS total, SUM(CASE WHEN status=\'ac\' THEN 1 ELSE 0 END) AS ac_count, COUNT(DISTINCT CASE WHEN status=\'ac\' THEN problem_id END) AS solved, MAX(create_time) AS last_time')
            ->group('student_id, student_no, student_name, class_id')
            ->order('last_time', 'desc')
            ->paginate([
                'list_rows' => 20,
                'query'     => request()->param(),
            ]);

        $studentIds = [];
        foreach ($list as $stu) {
            $studentIds[] = intval($stu['student_id']);
        }

        // 当前页学生在本页展开时需要的明细
        $grouped = [];
        if ($studentIds) {
            $rows = $this->filterQuery($filters)
                ->whereIn('student_id', $studentIds)
                ->order('id', 'desc')
                ->select();
            foreach ($rows as $row) {
                $grouped[intval($row['student_id'])][] = $row;
            }
        }

        $problemMap = [];
        foreach (Db::name('oj_problems')->field('id,title')->select() as $p) {
            $problemMap[intval($p['id'])] = $p['title'];
        }
        $classMap = [];
        foreach (Db::name('classes')->field('id,class_name')->select() as $c) {
            $classMap[intval($c['id'])] = $c['class_name'];
        }

        return view('admin/oj/submission/index', [
            'list'        => $list,
            'problems'    => Db::name('oj_problems')->field('id,title')->order('id', 'desc')->select(),
            'classes'     => Db::name('classes')->order('id', 'asc')->select(),
            'problemMap'  => $problemMap,
            'classMap'    => $classMap,
            'languageMap' => JudgeService::$languages,
            'statusMap'   => JudgeService::$statusMap,
            'grouped'     => $grouped,
            'class_id'    => $classId,
            'problem_id'  => $problemId,
            'language'    => $language,
            'status'      => $status,
            'keyword'     => $keyword,
            'start'       => $start,
            'end'         => $end,
        ]);
    }

    /**
     * 按当前筛选条件构造提交查询
     */
    private function filterQuery(array $f)
    {
        $query = Db::name('oj_submissions')->where('is_test', 0);
        if ($f['classId']) {
            $query->where('class_id', $f['classId']);
        }
        if ($f['problemId']) {
            $query->where('problem_id', $f['problemId']);
        }
        if ($f['language'] !== '') {
            $query->where('language', $f['language']);
        }
        if ($f['status'] !== '') {
            $query->where('status', $f['status']);
        }
        if ($f['keyword'] !== '') {
            $keyword = $f['keyword'];
            $query->where(function ($q) use ($keyword) {
                $q->where('student_no', 'like', '%' . $keyword . '%')
                  ->whereOr('student_name', 'like', '%' . $keyword . '%');
            });
        }
        if ($f['start'] !== '') {
            $query->where('create_time', '>=', $f['start'] . ' 00:00:00');
        }
        if ($f['end'] !== '') {
            $query->where('create_time', '<=', $f['end'] . ' 23:59:59');
        }
        return $query;
    }

    public function detail()
    {
        $id = intval(input('id', 0));
        $sub = Db::name('oj_submissions')->where('id', $id)->find();
        if (!$sub) {
            return redirect('/admin/oj/submission');
        }
        $problem = Db::name('oj_problems')->where('id', $sub['problem_id'])->find();
        $cases = $sub['result_json'] ? json_decode($sub['result_json'], true) : [];

        return view('admin/oj/submission/detail', [
            'sub'         => $sub,
            'problem'     => $problem,
            'cases'       => is_array($cases) ? $cases : [],
            'languageMap' => JudgeService::$languages,
            'statusMap'   => JudgeService::$statusMap,
        ]);
    }

    /**
     * 状态轮询（预评测页使用）
     */
    public function status()
    {
        $id = intval(input('id', 0));
        $sub = Db::name('oj_submissions')->where('id', $id)->find();
        if (!$sub) {
            return json(['code' => 0, 'msg' => '提交不存在']);
        }
        if ($sub['status'] === 'pending' && strtotime($sub['create_time']) < time() - 3) {
            JudgeService::spawnWorker();
        }
        $cases = $sub['result_json'] ? json_decode($sub['result_json'], true) : [];
        return json([
            'code' => 1,
            'data' => [
                'id'          => intval($sub['id']),
                'status'      => $sub['status'],
                'status_text' => JudgeService::statusText($sub['status']),
                'score'       => floatval($sub['score']),
                'time_used'   => intval($sub['time_used']),
                'memory_used' => intval($sub['memory_used']),
                'compile_log' => (string) $sub['compile_log'],
                'cases'       => is_array($cases) ? $cases : [],
            ],
        ]);
    }

    public function pretest()
    {
        $problemId = intval(input('problem_id', 0));
        $problem = Db::name('oj_problems')->where('id', $problemId)->find();
        if (!$problem) {
            return redirect('/admin/oj/problem');
        }
        return view('admin/oj/pretest', [
            'problem'     => $problem,
            'languageMap' => JudgeService::$languages,
        ]);
    }

    public function pretestRun()
    {
        $problemId = intval(input('post.problem_id', 0));
        $problem = Db::name('oj_problems')->where('id', $problemId)->find();
        if (!$problem) {
            return $this->error('题目不存在');
        }
        $language = input('post.language', '');
        $allowed = explode(',', $problem['languages']);
        if (!in_array($language, $allowed, true) || !isset(JudgeService::$languages[$language])) {
            return $this->error('该题目不支持所选语言');
        }
        $code = isset($_POST['code']) ? (string) $_POST['code'] : '';
        if (trim($code) === '') {
            return $this->error('请输入测试代码');
        }

        $id = Db::name('oj_submissions')->insertGetId([
            'problem_id'   => $problemId,
            'student_id'   => 0,
            'student_no'   => '',
            'student_name' => '教师预评测',
            'class_id'     => 0,
            'language'     => $language,
            'code'         => $code,
            'status'       => 'pending',
            'is_test'      => 1,
            'ip'           => request()->ip(),
            'create_time'  => date('Y-m-d H:i:s'),
        ]);
        JudgeService::spawnWorker();

        return $this->success('已提交判题', ['id' => $id]);
    }

    /**
     * 班级成绩总表导出
     */
    public function export()
    {
        $classId = intval(input('class_id', 0));
        if (!$classId) {
            return $this->error('请选择班级');
        }
        $class = Db::name('classes')->where('id', $classId)->find();
        if (!$class) {
            return $this->error('班级不存在');
        }

        $students = Db::name('students')->where('class_id', $classId)->order('student_no', 'asc')->select();
        $problems = Db::name('oj_problems')->where('is_public', 1)->order('sort', 'asc')->order('id', 'asc')->select();

        $best = [];
        $counts = [];
        $rows = Db::name('oj_submissions')
            ->where('is_test', 0)
            ->where('class_id', $classId)
            ->field('student_id, problem_id, MAX(score) AS best, COUNT(*) AS cnt')
            ->group('student_id, problem_id')
            ->select();
        foreach ($rows as $row) {
            $sid = intval($row['student_id']);
            $pid = intval($row['problem_id']);
            $best[$sid][$pid] = floatval($row['best']);
            $counts[$sid] = ($counts[$sid] ?? 0) + intval($row['cnt']);
        }

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('成绩总表');

        $headers = ['学号', '姓名'];
        foreach ($problems as $p) {
            $headers[] = $p['title'] . '（' . floatval($p['total_score']) . '分）';
        }
        $headers[] = '总分';
        $headers[] = '提交次数';

        foreach ($headers as $i => $h) {
            $colLetter = Coordinate::stringFromColumnIndex($i + 1);
            $sheet->setCellValue($colLetter . '1', $h);
            $sheet->getStyle($colLetter . '1')->getFont()->setBold(true);
        }
        $sheet->freezePane('C2');

        $rowNo = 2;
        foreach ($students as $student) {
            $sheet->setCellValue('A' . $rowNo, $student['student_no']);
            $sheet->setCellValue('B' . $rowNo, $student['name']);
            $col = 3;
            $total = 0;
            foreach ($problems as $p) {
                $score = $best[intval($student['id'])][intval($p['id'])] ?? 0;
                $total += $score;
                $sheet->setCellValue(Coordinate::stringFromColumnIndex($col) . $rowNo, $score);
                $col++;
            }
            $sheet->setCellValue(Coordinate::stringFromColumnIndex($col) . $rowNo, round($total, 2));
            $sheet->setCellValue(Coordinate::stringFromColumnIndex($col + 1) . $rowNo, $counts[intval($student['id'])] ?? 0);
            $rowNo++;
        }

        $sheet->getColumnDimension('A')->setWidth(16);
        $sheet->getColumnDimension('B')->setWidth(12);
        for ($c = 3; $c <= count($headers); $c++) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($c))->setWidth(22);
        }

        $filename = $class['class_name'] . '-在线判题成绩-' . date('Ymd') . '.xlsx';
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . rawurlencode($filename) . '"');
        header('Cache-Control: max-age=0');

        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
    }

    /**
     * 题目统计与学生统计
     */
    public function stats()
    {
        $classId = intval(input('class_id', 0));
        $problemId = intval(input('problem_id', 0));

        $problemQuery = Db::name('oj_problems')->where('is_public', 1);
        if ($problemId) {
            $problemQuery->where('id', $problemId);
        }
        $problems = $problemQuery->order('sort', 'asc')->order('id', 'asc')->select();

        $subQuery = Db::name('oj_submissions')->where('is_test', 0);
        if ($classId) {
            $subQuery->where('class_id', $classId);
        }
        if ($problemId) {
            $subQuery->where('problem_id', $problemId);
        }

        $problemStats = [];
        $rows = $subQuery->field("problem_id, status, COUNT(*) AS cnt, COUNT(DISTINCT student_id) AS students")
            ->group('problem_id, status')
            ->select();
        foreach ($rows as $row) {
            $pid = intval($row['problem_id']);
            if (!isset($problemStats[$pid])) {
                $problemStats[$pid] = ['total' => 0, 'ac' => 0, 'students' => 0, 'errors' => []];
            }
            $cnt = intval($row['cnt']);
            $problemStats[$pid]['total'] += $cnt;
            if ($row['status'] === 'ac') {
                $problemStats[$pid]['ac'] += $cnt;
            } else {
                $problemStats[$pid]['errors'][$row['status']] = ($problemStats[$pid]['errors'][$row['status']] ?? 0) + $cnt;
            }
        }

        $studentQuery = Db::name('oj_submissions')->where('is_test', 0);
        if ($classId) {
            $studentQuery->where('class_id', $classId);
        }
        if ($problemId) {
            $studentQuery->where('problem_id', $problemId);
        }
        $studentStats = $studentQuery
            ->field("student_id, student_no, student_name, class_id, COUNT(*) AS total, SUM(CASE WHEN status='ac' THEN 1 ELSE 0 END) AS ac_count, COUNT(DISTINCT CASE WHEN status='ac' THEN problem_id END) AS solved")
            ->group('student_id, student_no, student_name, class_id')
            ->order('total', 'desc')
            ->select();

        $summary = ['total' => 0, 'ac' => 0, 'rate' => 0, 'students' => count($studentStats)];
        foreach ($problemStats as $item) {
            $summary['total'] += intval($item['total']);
            $summary['ac'] += intval($item['ac']);
        }
        if ($summary['total'] > 0) {
            $summary['rate'] = round($summary['ac'] / $summary['total'] * 100, 1);
        }

        return view('admin/oj/stats', [
            'problems'      => $problems,
            'problemStats'  => $problemStats,
            'studentStats'  => $studentStats,
            'summary'       => $summary,
            'classes'       => Db::name('classes')->order('id', 'asc')->select(),
            'statusMap'     => JudgeService::$statusMap,
            'class_id'      => $classId,
            'problem_id'    => $problemId,
        ]);
    }
}
