<?php
// +----------------------------------------------------------------------
// | 管理员模型
// +----------------------------------------------------------------------

namespace app\model;

use think\Model;

class Admin extends Model
{
    protected $name = 'admins';
    protected $autoWriteTimestamp = 'datetime';
    protected $createTime = 'create_time';
    protected $updateTime = 'update_time';

    /**
     * 密码加密
     */
    public function setPasswordAttr($value)
    {
        return password_hash($value, PASSWORD_DEFAULT);
    }

    /**
     * 验证密码
     */
    public function checkPassword($password)
    {
        return password_verify($password, $this->password);
    }
}
