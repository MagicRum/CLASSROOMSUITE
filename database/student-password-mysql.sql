-- 学生表增量：新增登录密码字段（MySQL 环境）
-- 适用于早期版本已建库、缺少 password 字段的站点
-- 若字段已存在会报 Duplicate column name，可忽略

ALTER TABLE `cs_students`
    ADD COLUMN `password` varchar(255) NOT NULL DEFAULT '' COMMENT '登录密码（password_hash加密，空=未设置）' AFTER `name`;
