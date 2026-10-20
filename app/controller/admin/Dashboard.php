<?php
// +----------------------------------------------------------------------
// | 管理员首页（仪表盘）
// +----------------------------------------------------------------------

namespace app\controller\admin;

use app\model\Classes;
use app\model\Student;
use app\model\Question;
use app\model\ExamRecord;

class Dashboard extends Base
{
    public function index()
    {
        $stats = [
            'class_count'       => Classes::count(),
            'student_count'     => Student::count(),
            'question_count'    => Question::count(),
            'exam_record_count' => ExamRecord::count(),
            'submitted_count'   => ExamRecord::where('status', 1)->count(),
            'avg_score'         => ExamRecord::where('status', 1)->avg('score'),
        ];
        return view('admin/dashboard/index', ['stats' => $stats]);
    }
}
