-- 学生表增量：新增登录密码字段（SQLite 环境）
-- 适用于早期版本已建库、缺少 password 字段的站点
-- 执行方式：sqlite3 runtime/classroom.sqlite < database/student-password-sqlite.sql
-- 若字段已存在会报错，可忽略

ALTER TABLE cs_students ADD COLUMN password TEXT NOT NULL DEFAULT '';
