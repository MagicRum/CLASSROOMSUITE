<?php
// +----------------------------------------------------------------------
// | 教师端随机抽题（课堂投屏用）
// | 每次抽题自动写入 exam_records 作为记录，学生端不参与
// +----------------------------------------------------------------------

namespace app\controller\admin;

use app\model\ExamRecord;
use app\model\Question;

class Draw extends Base
{
    /**
     * 抽题页（注入题库池供滚动预览）
     */
    public function index()
    {
        $pool = Question::field('id,title')->order('id', 'asc')->select()->toArray();

        return view('admin/draw/index', [
            'active'    => 'draw',
            'poolJson'  => json_encode($pool, JSON_UNESCAPED_UNICODE),
            'poolCount' => count($pool),
        ]);
    }

    /**
     * 执行抽题：随机抽 2 道并落库记录
     */
    public function go()
    {
        // SQLite/MySQL 随机函数兼容
        $driver = \think\facade\Db::getConfig()['default'] ?? 'sqlite';
        $randFn = stripos(json_encode($driver), 'mysql') !== false ? 'RAND()' : 'RANDOM()';
        $questions = Question::orderRaw($randFn)->limit(2)->select();
        if ($questions->count() < 2) {
            return json(['code' => 0, 'msg' => '题库不足2道题，请先导入试题']);
        }

        $questionIds = $questions->column('id');

        // 抽题记录落库（教师抽题，无学生）
        $record = ExamRecord::create([
            'student_id'      => 0,
            'student_no'      => '教师抽题',
            'question_ids'    => $questionIds,
            'student_answers' => [],
            'score'           => 0,
            'status'          => 0,
            'draw_time'       => date('Y-m-d H:i:s'),
        ]);

        $list = [];
        foreach ($questions as $q) {
            $list[] = [
                'id'             => $q->id,
                'title'          => $q->title,
                'question_type'  => $q->question_type,
                'type_name'      => Question::$typeMap[$q->question_type] ?? '单选题',
                'option_a'       => $q->option_a,
                'option_b'       => $q->option_b,
                'option_c'       => $q->option_c,
                'option_d'       => $q->option_d,
            ];
        }

        return json([
            'code' => 1,
            'msg'  => '抽题成功，已保存记录 #' . $record->id,
            'data' => [
                'record_id' => $record->id,
                'questions' => $list,
            ],
        ]);
    }
}
