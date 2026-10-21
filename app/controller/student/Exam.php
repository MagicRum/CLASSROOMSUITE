<?php
// +----------------------------------------------------------------------
// | 学生端：随机抽题 + 抽题记录
// | 题目与教师端共用同一个题库，抽题结果静默写入 exam_records
// +----------------------------------------------------------------------

namespace app\controller\student;

use app\model\ExamRecord;
use app\model\Question;
use think\facade\Db;

class Exam
{
    /**
     * 抽题页
     */
    public function index()
    {
        $pool = Question::field('id,title')->order('id', 'asc')->select()->toArray();

        return view('student/draw/index', [
            'poolCount' => count($pool),
            'poolJson'  => json_encode($pool, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT),
        ]);
    }

    /**
     * 执行抽题：随机抽 2 道并落库
     */
    public function draw()
    {
        $driver = Db::getConfig()['default'] ?? 'sqlite';
        $randFn = stripos(json_encode($driver), 'mysql') !== false ? 'RAND()' : 'RANDOM()';

        $questions = Question::orderRaw($randFn)->limit(2)->select();
        if ($questions->count() < 2) {
            return json(['code' => 0, 'msg' => '题库不足2道题，请联系教师导入试题']);
        }

        $student = $_SESSION['student_user'] ?? [];
        $questionIds = $questions->column('id');

        ExamRecord::create([
            'student_id'      => intval($student['id'] ?? 0),
            'student_no'      => (string) ($student['student_no'] ?? ''),
            'question_ids'    => $questionIds,
            'student_answers' => [],
            'score'           => 0,
            'status'          => 0,
            'draw_time'       => date('Y-m-d H:i:s'),
        ]);

        $list = [];
        foreach ($questions as $q) {
            $list[] = [
                'id'            => $q->id,
                'title'         => $q->title,
                'question_type' => $q->question_type,
                'type_name'     => Question::$typeMap[$q->question_type] ?? '单选题',
                'option_a'      => $q->option_a,
                'option_b'      => $q->option_b,
                'option_c'      => $q->option_c,
                'option_d'      => $q->option_d,
            ];
        }

        return json(['code' => 1, 'data' => ['questions' => $list]]);
    }

    /**
     * 我的抽题记录
     */
    public function records()
    {
        $studentId = intval($_SESSION['student_id'] ?? 0);
        $list = ExamRecord::where('student_id', $studentId)
            ->order('id', 'desc')
            ->paginate([
                'list_rows' => 10,
                'query'     => request()->param(),
            ]);

        return view('student/records/index', [
            'list' => $list,
        ]);
    }

    /**
     * 记录详情（只看题干与选项，不显示答案）
     */
    public function detail()
    {
        $studentId = intval($_SESSION['student_id'] ?? 0);
        $id = intval(input('id', 0));

        $record = ExamRecord::where('id', $id)->where('student_id', $studentId)->find();
        if (!$record) {
            return '记录不存在';
        }

        $questions = Question::whereIn('id', $record->question_ids)->select();
        $sorted = [];
        foreach ($record->question_ids as $qid) {
            foreach ($questions as $q) {
                if ($q->id == $qid) {
                    $sorted[] = $q;
                    break;
                }
            }
        }

        return view('student/records/detail', [
            'record'    => $record,
            'questions' => $sorted,
            'typeMap'   => Question::$typeMap,
        ]);
    }
}
