-- +----------------------------------------------------------------------
-- | 在线判题系统：随机对拍字段增量脚本（MySQL / 线上）
-- | 已存在的题目表逐列追加，仅需执行一次
-- | 用法：mysql -u root -p classroom_sys < database/oj-random-judge-mysql.sql
-- +----------------------------------------------------------------------

ALTER TABLE `cs_oj_problems` ADD COLUMN `ref_language` varchar(20) NOT NULL DEFAULT '' COMMENT '参考程序语言';
ALTER TABLE `cs_oj_problems` ADD COLUMN `ref_code` longtext COMMENT '参考程序代码';
ALTER TABLE `cs_oj_problems` ADD COLUMN `gen_language` varchar(20) NOT NULL DEFAULT '' COMMENT '随机数据生成器语言';
ALTER TABLE `cs_oj_problems` ADD COLUMN `gen_code` longtext COMMENT '随机数据生成器代码';
ALTER TABLE `cs_oj_problems` ADD COLUMN `random_cases` int(11) unsigned NOT NULL DEFAULT 0 COMMENT '随机对拍组数，0关闭';
