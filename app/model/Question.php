<?php
// +----------------------------------------------------------------------
// | 试题模型
// +----------------------------------------------------------------------

namespace app\model;

use think\Model;

class Question extends Model
{
    protected $name = 'questions';
    protected $autoWriteTimestamp = 'datetime';
    protected $createTime = 'create_time';
    protected $updateTime = false;

    // 题型映射
    public static $typeMap = [
        1 => '单选题',
        2 => '多选题',
        3 => '判断题',
        4 => '填空题',
        5 => '简答题',
    ];

    // 难度映射
    public static $difficultyMap = [
        1 => '简单',
        2 => '中等',
        3 => '困难',
    ];

    /**
     * 随机抽题
     * @param int $count 抽题数量
     * @param array $filter 过滤条件 ['question_type'=>1, 'difficulty'=>2]
     * @return array 题目ID数组
     */
    public static function randomDraw($count = 2, $filter = [])
    {
        $query = self::where('id', '>', 0);
        foreach ($filter as $field => $value) {
            if ($value !== '' && $value !== null) {
                $query->where($field, $value);
            }
        }
        return $query->orderRaw('RAND()')->limit($count)->column('id');
    }
}
