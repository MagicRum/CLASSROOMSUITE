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

SET FOREIGN_KEY_CHECKS = 1;
