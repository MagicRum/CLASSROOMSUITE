<?php
// +----------------------------------------------------------------------
// | 班级模型
// +----------------------------------------------------------------------

namespace app\model;

use think\Model;

class Classes extends Model
{
    protected $name = 'classes';
    protected $autoWriteTimestamp = 'datetime';

    public function students()
    {
        return $this->hasMany(Student::class, 'class_id');
    }
}
