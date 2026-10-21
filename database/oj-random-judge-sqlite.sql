-- +----------------------------------------------------------------------
-- | 在线判题系统：随机对拍字段增量脚本（SQLite / 内网）
-- | 已存在的题目表逐列追加，仅需执行一次
-- | 用法：sqlite3 runtime/classroom.sqlite < database/oj-random-judge-sqlite.sql
-- +----------------------------------------------------------------------

ALTER TABLE cs_oj_problems ADD COLUMN ref_language TEXT DEFAULT '';
ALTER TABLE cs_oj_problems ADD COLUMN ref_code TEXT DEFAULT '';
ALTER TABLE cs_oj_problems ADD COLUMN gen_language TEXT DEFAULT '';
ALTER TABLE cs_oj_problems ADD COLUMN gen_code TEXT DEFAULT '';
ALTER TABLE cs_oj_problems ADD COLUMN random_cases INTEGER NOT NULL DEFAULT 0;
