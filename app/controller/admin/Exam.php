<?php
// +----------------------------------------------------------------------
// | 考试记录管理（查看学生抽题记录、得分）
// +----------------------------------------------------------------------

namespace app\controller\admin;

use app\model\ExamRecord;
use app\model\Question;
use app\model\Student;

class Exam extends Base
{
    /**
     * 考试记录列表
     */
    public function index()
    {
        $keyword = input('keyword', '');
        $status = input('status', -1);
        $page = input('page', 1);
        $limit = input('limit', 20);

        $query = ExamRecord::alias('r')->join('students s', 'r.student_id = s.id', 'left')
            ->field('r.*, s.name, s.student_no as s_student_no');

        if ($keyword) {
            $query->where(function ($q) use ($keyword) {
                $q->where('r.student_no', 'like', "%{$keyword}%")
                  ->whereOr('s.name', 'like', "%{$keyword}%");
            });
        }
        if ($status >= 0) {
            $query->where('r.status', $status);
        }

        $list = $query->order('r.id', 'desc')->paginate([
            'list_rows' => $limit,
            'page' => $page,
            'query' => request()->param(),
        ]);

        return view('admin/exam/index', [
            'list' => $list,
            'keyword' => $keyword,
            'status' => $status,
        ]);
    }

    /**
     * 查看某条考试记录详情（展示抽取的题目、学生答案、正确答案）
     */
    public function detail()
    {
        $id = input('id', 0);
        $record = ExamRecord::find($id);
        if (!$record) return '记录不存在';

        // 读唯一真源 question_ids，反查题目
        $questions = Question::whereIn('id', $record->question_ids)->select();

        // 按 question_ids 的顺序排序
        $sorted = [];
        foreach ($record->question_ids as $qid) {
            foreach ($questions as $q) {
                if ($q->id == $qid) {
                    $sorted[] = $q;
                    break;
                }
            }
        }

        $student = Student::find($record->student_id);

        $totalScore = 0;
        foreach ($sorted as $q) {
            $totalScore += floatval($q->score);
        }

        return view('admin/exam/detail', [
            'record' => $record,
            'questions' => $sorted,
            'student' => $student,
            'typeMap' => Question::$typeMap,
            'totalScore' => $totalScore,
        ]);
    }

    /**
     * 批量删除考试记录
     */
    public function batchDelete()
    {
        if ($resp = $this->requirePost()) return $resp;
        $ids = input('post.ids/a', []);
        if (empty($ids)) return $this->error('请选择要删除的记录');
        ExamRecord::whereIn('id', $ids)->delete();
        return $this->success('批量删除成功');
    }

    /**
     * 手动删除考试记录
     */
    public function delete()
    {
        if ($resp = $this->requirePost()) return $resp;
        $id = input('post.id', 0);
        if (!$id) return $this->error('参数错误');
        ExamRecord::where('id', $id)->delete();
        return $this->success('删除成功');
    }
}
