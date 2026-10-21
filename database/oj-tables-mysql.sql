-- +----------------------------------------------------------------------
-- | 在线判题系统（OJS）增量建表脚本（MySQL / 线上）
-- | 仅创建 cs_oj_* 四张表，不删除、不修改任何已有业务表，可重复执行
-- | 用法：mysql -u root -p classroom_sys < database/oj-tables-mysql.sql
-- +----------------------------------------------------------------------

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `cs_oj_categories` (
    `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
    `name` varchar(100) NOT NULL DEFAULT '' COMMENT '分类名称',
    `parent_id` int(11) unsigned NOT NULL DEFAULT 0 COMMENT '上级分类ID（0=顶级）',
    `sort` int(11) NOT NULL DEFAULT 0 COMMENT '排序',
    `create_time` datetime DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='OJ题库分类';

CREATE TABLE IF NOT EXISTS `cs_oj_problems` (
    `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
    `category_id` int(11) unsigned NOT NULL DEFAULT 0 COMMENT '分类ID',
    `title` varchar(200) NOT NULL DEFAULT '' COMMENT '标题',
    `description` longtext COMMENT '题干（富文本HTML）',
    `input_desc` text COMMENT '输入说明',
    `output_desc` text COMMENT '输出说明',
    `sample_input` text COMMENT '样例输入',
    `sample_output` text COMMENT '样例输出',
    `hint` text COMMENT '提示',
    `difficulty` tinyint(1) unsigned NOT NULL DEFAULT 2 COMMENT '难度：1简单 2中等 3困难',
    `tags` varchar(255) DEFAULT '' COMMENT '知识点标签，逗号分隔',
    `languages` varchar(50) NOT NULL DEFAULT 'c,cpp,python,js' COMMENT '允许语言，逗号分隔',
    `time_limit` int(11) unsigned NOT NULL DEFAULT 1000 COMMENT '时间限制(ms)',
    `memory_limit` int(11) unsigned NOT NULL DEFAULT 262144 COMMENT '内存限制(KB)',
    `total_score` decimal(8,2) NOT NULL DEFAULT 100.00 COMMENT '总分',
    `score_mode` varchar(20) NOT NULL DEFAULT 'case' COMMENT '计分：all全部通过 case按用例',
    `ignore_trailing` tinyint(1) unsigned NOT NULL DEFAULT 1 COMMENT '忽略行末空格和末尾换行',
    `show_detail` tinyint(1) unsigned NOT NULL DEFAULT 1 COMMENT '允许学生查看错误用例详情',
    `is_public` tinyint(1) unsigned NOT NULL DEFAULT 1 COMMENT '1上架 0下架',
    `sort` int(11) NOT NULL DEFAULT 0,
    `create_time` datetime DEFAULT CURRENT_TIMESTAMP,
    `update_time` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_category` (`category_id`),
    KEY `idx_public` (`is_public`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='OJ题目';

CREATE TABLE IF NOT EXISTS `cs_oj_test_cases` (
    `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
    `problem_id` int(11) unsigned NOT NULL DEFAULT 0 COMMENT '题目ID',
    `name` varchar(100) NOT NULL DEFAULT '' COMMENT '用例名称',
    `input` longtext COMMENT '输入数据',
    `output` longtext COMMENT '标准输出',
    `score` decimal(8,2) NOT NULL DEFAULT 0.00 COMMENT '该用例分值（0=均分）',
    `type` tinyint(1) unsigned NOT NULL DEFAULT 2 COMMENT '1样例 2隐藏',
    `enabled` tinyint(1) unsigned NOT NULL DEFAULT 1 COMMENT '1启用 0禁用',
    `sort` int(11) NOT NULL DEFAULT 0,
    `create_time` datetime DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_problem` (`problem_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='OJ测试用例';

CREATE TABLE IF NOT EXISTS `cs_oj_submissions` (
    `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
    `problem_id` int(11) unsigned NOT NULL DEFAULT 0 COMMENT '题目ID',
    `student_id` int(11) unsigned NOT NULL DEFAULT 0 COMMENT '学生ID（0=教师预评测）',
    `student_no` varchar(50) DEFAULT '' COMMENT '学号',
    `student_name` varchar(50) DEFAULT '' COMMENT '姓名',
    `class_id` int(11) unsigned NOT NULL DEFAULT 0 COMMENT '班级ID',
    `language` varchar(20) NOT NULL DEFAULT '' COMMENT '语言',
    `code` longtext COMMENT '源代码',
    `status` varchar(20) NOT NULL DEFAULT 'pending' COMMENT 'pending/judging/ac/ce/wa/tle/mle/re/se',
    `score` decimal(8,2) NOT NULL DEFAULT 0.00 COMMENT '得分',
    `time_used` int(11) unsigned NOT NULL DEFAULT 0 COMMENT '最大耗时(ms)',
    `memory_used` int(11) unsigned NOT NULL DEFAULT 0 COMMENT '最大内存(KB)',
    `compile_log` text COMMENT '编译日志',
    `result_json` longtext COMMENT '逐用例结果JSON',
    `is_test` tinyint(1) unsigned NOT NULL DEFAULT 0 COMMENT '1教师预评测',
    `ip` varchar(45) DEFAULT '' COMMENT '提交IP',
    `create_time` datetime DEFAULT CURRENT_TIMESTAMP,
    `judge_time` datetime DEFAULT NULL COMMENT '判题完成时间',
    PRIMARY KEY (`id`),
    KEY `idx_problem` (`problem_id`),
    KEY `idx_student` (`student_id`),
    KEY `idx_status` (`status`),
    KEY `idx_create_time` (`create_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='OJ提交记录';
