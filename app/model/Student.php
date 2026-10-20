<?php
// +----------------------------------------------------------------------
// | 学生模型
// +----------------------------------------------------------------------

namespace app\model;

use think\Model;

class Student extends Model
{
    protected $name = 'students';
    protected $autoWriteTimestamp = 'datetime';

    public function classInfo()
    {
        return $this->belongsTo(Classes::class, 'class_id');
    }

    public function group()
    {
        return $this->belongsTo(Group::class, 'group_id');
    }

    public function examRecords()
    {
        return $this->hasMany(ExamRecord::class, 'student_id');
    }
}
