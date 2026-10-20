<?php
// +----------------------------------------------------------------------
// | 考试记录模型
// | 核心：question_ids 是唯一真源，所有展示和评分都只读这个字段
// +----------------------------------------------------------------------

namespace app\model;

use think\Model;

class ExamRecord extends Model
{
    protected $name = 'exam_records';
    protected $autoWriteTimestamp = 'datetime';
    protected $createTime = 'draw_time';
    protected $updateTime = false;

    // question_ids 和 student_answers 自动 JSON 编解码
    protected $json = ['question_ids', 'student_answers'];
    protected $jsonAssoc = true;

    public function student()
    {
        return $this->belongsTo(Student::class, 'student_id');
    }

    /**
     * 提交答卷并自动评分（只判客观题）
     */
    public function submitAndScore($answers)
    {
        $this->student_answers = $answers;
        $this->status = 1;
        $this->submit_time = date('Y-m-d H:i:s');

        $score = 0;
        $totalScore = 0;

        // 从 question_ids 读取唯一真源，反查题目
        $questions = Question::whereIn('id', $this->question_ids)->select();

        foreach ($questions as $q) {
            $totalScore += floatval($q->score);
            $studentAns = $answers[$q->id] ?? '';

            // 客观题自动判分
            if (in_array($q->question_type, [1, 2, 3])) {
                $stdAns = trim(strtoupper($q->correct_answer));
                $myAns = trim(strtoupper($studentAns));
                // 判断题：正确答案"对/正确/TRUE"或"错/错误/FALSE"
                if ($q->question_type == 3) {
                    $stdAns = in_array($stdAns, ['A', '对', '正确', 'TRUE', '1']) ? 'A' : 'B';
                    $myAns = in_array($myAns, ['A', '对', '正确', 'TRUE', '1']) ? 'A' : ($myAns ? 'B' : '');
                }
                if ($stdAns === $myAns) {
                    $score += floatval($q->score);
                }
            }
        }

        $this->score = $score;
        return $this->save();
    }
}
