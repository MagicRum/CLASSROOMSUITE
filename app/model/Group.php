<?php
// +----------------------------------------------------------------------
// | 分组模型
// +----------------------------------------------------------------------

namespace app\model;

use think\Model;

class Group extends Model
{
    protected $name = 'groups';
    protected $autoWriteTimestamp = 'datetime';

    public function classInfo()
    {
        return $this->belongsTo(Classes::class, 'class_id');
    }

    public function students()
    {
        return $this->hasMany(Student::class, 'group_id');
    }
}
