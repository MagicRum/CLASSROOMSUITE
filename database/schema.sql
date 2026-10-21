-- +----------------------------------------------------------------------
-- | 数据库迁移 SQL
-- +----------------------------------------------------------------------
-- | 使用方法：宝塔面板 → phpMyAdmin → 选中数据库 → 导入本文件
-- | 或命令行：mysql -u root -p classroom_sys < schema.sql
-- +----------------------------------------------------------------------

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------
-- 管理员表（老师登录）
-- ----------------------------
DROP TABLE IF EXISTS `cs_admins`;
CREATE TABLE `cs_admins` (
    `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
    `username` varchar(50) NOT NULL DEFAULT '' COMMENT '用户名',
    `password` varchar(255) NOT NULL DEFAULT '' COMMENT '密码（password_hash加密）',
    `real_name` varchar(50) NOT NULL DEFAULT '' COMMENT '真实姓名',
    `last_login_time` datetime DEFAULT NULL COMMENT '最后登录时间',
    `last_login_ip` varchar(45) DEFAULT '' COMMENT '最后登录IP',
    `create_time` datetime DEFAULT CURRENT_TIMESTAMP,
    `update_time` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='管理员表';

-- 默认管理员：admin / 密码 admin123（已用password_hash加密）
INSERT INTO `cs_admins` (`username`, `password`, `real_name`) VALUES
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '系统管理员');

-- ----------------------------
-- 班级表
-- ----------------------------
DROP TABLE IF EXISTS `cs_classes`;
CREATE TABLE `cs_classes` (
    `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
    `class_name` varchar(100) NOT NULL DEFAULT '' COMMENT '班级名称',
    `remark` varchar(255) DEFAULT '' COMMENT '备注',
    `create_time` datetime DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='班级表';

-- ----------------------------
-- 学生表
-- ----------------------------
DROP TABLE IF EXISTS `cs_students`;
CREATE TABLE `cs_students` (
    `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
    `student_no` varchar(50) NOT NULL DEFAULT '' COMMENT '学号',
    `name` varchar(50) NOT NULL DEFAULT '' COMMENT '姓名',
    `password` varchar(255) NOT NULL DEFAULT '' COMMENT '登录密码（password_hash加密，空=未设置）',
    `class_id` int(11) unsigned NOT NULL DEFAULT 0 COMMENT '班级ID',
    `group_id` int(11) unsigned NOT NULL DEFAULT 0 COMMENT '当前分组ID（0=未分组）',
    `phone` varchar(20) DEFAULT '' COMMENT '手机号（可选）',
    `create_time` datetime DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_student_no` (`student_no`),
    KEY `idx_class_id` (`class_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='学生表';

-- ----------------------------
-- 分组表
-- ----------------------------
DROP TABLE IF EXISTS `cs_groups`;
CREATE TABLE `cs_groups` (
    `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
    `class_id` int(11) unsigned NOT NULL DEFAULT 0 COMMENT '班级ID',
    `group_name` varchar(50) NOT NULL DEFAULT '' COMMENT '分组名称',
    `remark` varchar(255) DEFAULT '' COMMENT '备注',
    `create_time` datetime DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_class_id` (`class_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='分组表';

-- ----------------------------
-- 试题表
-- ----------------------------
DROP TABLE IF EXISTS `cs_questions`;
CREATE TABLE `cs_questions` (
    `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
    `title` text NOT NULL COMMENT '题目内容',
    `question_type` tinyint(1) unsigned NOT NULL DEFAULT 1 COMMENT '题型：1单选 2多选 3判断 4填空',
    `option_a` varchar(500) DEFAULT '' COMMENT '选项A',
    `option_b` varchar(500) DEFAULT '' COMMENT '选项B',
    `option_c` varchar(500) DEFAULT '' COMMENT '选项C',
    `option_d` varchar(500) DEFAULT '' COMMENT '选项D',
    `correct_answer` varchar(50) NOT NULL DEFAULT '' COMMENT '正确答案（如A/B/C或ABCD）',
    `score` decimal(5,2) NOT NULL DEFAULT 5.00 COMMENT '分值',
    `difficulty` tinyint(1) unsigned NOT NULL DEFAULT 2 COMMENT '难度：1简单 2中等 3困难',
    `category` varchar(100) DEFAULT '' COMMENT '题目分类',
    `create_time` datetime DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_type` (`question_type`),
    KEY `idx_difficulty` (`difficulty`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='试题表';

-- ----------------------------
-- 考试记录表
-- ----------------------------
DROP TABLE IF EXISTS `cs_exam_records`;
CREATE TABLE `cs_exam_records` (
    `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
    `student_id` int(11) unsigned NOT NULL DEFAULT 0 COMMENT '学生ID',
    `student_no` varchar(50) NOT NULL DEFAULT '' COMMENT '学号（冗余便于查询）',
    `question_ids` json NOT NULL COMMENT '抽中的题目ID数组，如[1,5]（唯一真源，展示和评分都读这个）',
    `student_answers` json DEFAULT NULL COMMENT '学生提交的答案，如{"1":"A","5":"B"}',
    `score` decimal(5,2) NOT NULL DEFAULT 0.00 COMMENT '本次得分',
    `status` tinyint(1) unsigned NOT NULL DEFAULT 0 COMMENT '状态：0进行中 1已提交',
    `draw_time` datetime DEFAULT NULL COMMENT '抽题时间',
    `submit_time` datetime DEFAULT NULL COMMENT '提交时间',
    PRIMARY KEY (`id`),
    KEY `idx_student_id` (`student_id`),
    KEY `idx_draw_time` (`draw_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='考试记录表';

-- ----------------------------
-- 在线判题：题库分类
-- ----------------------------
DROP TABLE IF EXISTS `cs_oj_categories`;
CREATE TABLE `cs_oj_categories` (
    `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
    `name` varchar(100) NOT NULL DEFAULT '' COMMENT '分类名称',
    `parent_id` int(11) unsigned NOT NULL DEFAULT 0 COMMENT '上级分类ID（0=顶级）',
    `sort` int(11) NOT NULL DEFAULT 0 COMMENT '排序',
    `create_time` datetime DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='OJ题库分类';

-- ----------------------------
-- 在线判题：题目
-- ----------------------------
DROP TABLE IF EXISTS `cs_oj_problems`;
CREATE TABLE `cs_oj_problems` (
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

-- ----------------------------
-- 在线判题：测试用例
-- ----------------------------
DROP TABLE IF EXISTS `cs_oj_test_cases`;
CREATE TABLE `cs_oj_test_cases` (
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

-- ----------------------------
-- 在线判题：提交记录
-- ----------------------------
DROP TABLE IF EXISTS `cs_oj_submissions`;
CREATE TABLE `cs_oj_submissions` (
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

SET FOREIGN_KEY_CHECKS = 1;
