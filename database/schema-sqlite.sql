-- SQLite 建表脚本（本地开发默认使用）
-- 与 MySQL 版本的区别：用 INTEGER PRIMARY KEY AUTOINCREMENT、TEXT 代替 DATETIME/JSON/COMMENT

PRAGMA foreign_keys = ON;

-- 管理员表
DROP TABLE IF EXISTS cs_admins;
CREATE TABLE cs_admins (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username TEXT NOT NULL DEFAULT '',
    password TEXT NOT NULL DEFAULT '',
    real_name TEXT NOT NULL DEFAULT '',
    last_login_time TEXT DEFAULT NULL,
    last_login_ip TEXT DEFAULT '',
    create_time TEXT DEFAULT CURRENT_TIMESTAMP,
    update_time TEXT DEFAULT CURRENT_TIMESTAMP
);
INSERT INTO cs_admins (username, password, real_name) VALUES
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '系统管理员');

-- 班级表
DROP TABLE IF EXISTS cs_classes;
CREATE TABLE cs_classes (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    class_name TEXT NOT NULL DEFAULT '',
    remark TEXT DEFAULT '',
    create_time TEXT DEFAULT CURRENT_TIMESTAMP
);

-- 学生表
DROP TABLE IF EXISTS cs_students;
CREATE TABLE cs_students (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    student_no TEXT NOT NULL DEFAULT '',
    name TEXT NOT NULL DEFAULT '',
    class_id INTEGER NOT NULL DEFAULT 0,
    group_id INTEGER NOT NULL DEFAULT 0,
    phone TEXT DEFAULT '',
    create_time TEXT DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(student_no)
);

-- 分组表
DROP TABLE IF EXISTS cs_groups;
CREATE TABLE cs_groups (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    class_id INTEGER NOT NULL DEFAULT 0,
    group_name TEXT NOT NULL DEFAULT '',
    remark TEXT DEFAULT '',
    create_time TEXT DEFAULT CURRENT_TIMESTAMP
);

-- 试题表
DROP TABLE IF EXISTS cs_questions;
CREATE TABLE cs_questions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    title TEXT NOT NULL,
    question_type INTEGER NOT NULL DEFAULT 1,
    option_a TEXT DEFAULT '',
    option_b TEXT DEFAULT '',
    option_c TEXT DEFAULT '',
    option_d TEXT DEFAULT '',
    correct_answer TEXT NOT NULL DEFAULT '',
    score REAL NOT NULL DEFAULT 5.0,
    difficulty INTEGER NOT NULL DEFAULT 2,
    category TEXT DEFAULT '',
    create_time TEXT DEFAULT CURRENT_TIMESTAMP
);

-- 考试记录表
DROP TABLE IF EXISTS cs_exam_records;
CREATE TABLE cs_exam_records (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    student_id INTEGER NOT NULL DEFAULT 0,
    student_no TEXT NOT NULL DEFAULT '',
    question_ids TEXT NOT NULL,
    student_answers TEXT DEFAULT NULL,
    score REAL NOT NULL DEFAULT 0.0,
    status INTEGER NOT NULL DEFAULT 0,
    draw_time TEXT DEFAULT NULL,
    submit_time TEXT DEFAULT NULL
);

-- 测试数据：几个班级 + 学生 + 试题
INSERT INTO cs_classes (class_name, remark) VALUES ('计算机1班', '软件工程教研室'), ('计算机2班', '软件工程教研室');

INSERT INTO cs_students (student_no, name, class_id, phone) VALUES
('2024001', '张三', 1, '13800000001'),
('2024002', '李四', 1, '13800000002'),
('2024003', '王五', 1, '13800000003'),
('2024004', '赵六', 2, '13800000004'),
('2024005', '孙七', 2, '13800000005');

INSERT INTO cs_questions (title, question_type, option_a, option_b, option_c, option_d, correct_answer, score, difficulty, category) VALUES
('PHP中用于输出文本的函数是？', 1, 'echo', 'print', 'printf', '以上都可以', 'D', 5.0, 2, 'PHP基础'),
('HTTP是无状态协议', 3, '', '', '', '', 'A', 5.0, 1, '网络基础'),
('以下哪个不是前端框架？', 1, 'Vue', 'React', 'Laravel', 'Angular', 'C', 5.0, 1, '前端'),
('MySQL中用于查询的关键字是？', 1, 'UPDATE', 'SELECT', 'INSERT', 'DELETE', 'B', 5.0, 1, '数据库'),
('TCP是面向连接的协议', 3, '', '', '', '', 'A', 5.0, 2, '网络基础'),
('以下哪种数据结构是先进先出？', 1, '栈', '队列', '树', '图', 'B', 5.0, 2, '数据结构');
