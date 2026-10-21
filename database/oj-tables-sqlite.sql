-- +----------------------------------------------------------------------
-- | 在线判题系统（OJS）增量建表脚本（SQLite / 内网）
-- | 仅创建 cs_oj_* 四张表与索引，可重复执行
-- | 用法：sqlite3 runtime/database.sqlite < database/oj-tables-sqlite.sql
-- +----------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS cs_oj_categories (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL DEFAULT '',
    parent_id INTEGER NOT NULL DEFAULT 0,
    sort INTEGER NOT NULL DEFAULT 0,
    create_time TEXT DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS cs_oj_problems (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    category_id INTEGER NOT NULL DEFAULT 0,
    title TEXT NOT NULL DEFAULT '',
    description TEXT DEFAULT '',
    input_desc TEXT DEFAULT '',
    output_desc TEXT DEFAULT '',
    sample_input TEXT DEFAULT '',
    sample_output TEXT DEFAULT '',
    hint TEXT DEFAULT '',
    difficulty INTEGER NOT NULL DEFAULT 2,
    tags TEXT DEFAULT '',
    languages TEXT NOT NULL DEFAULT 'c,cpp,python,js',
    time_limit INTEGER NOT NULL DEFAULT 1000,
    memory_limit INTEGER NOT NULL DEFAULT 262144,
    total_score REAL NOT NULL DEFAULT 100,
    score_mode TEXT NOT NULL DEFAULT 'case',
    ignore_trailing INTEGER NOT NULL DEFAULT 1,
    show_detail INTEGER NOT NULL DEFAULT 1,
    is_public INTEGER NOT NULL DEFAULT 1,
    sort INTEGER NOT NULL DEFAULT 0,
    create_time TEXT DEFAULT CURRENT_TIMESTAMP,
    update_time TEXT DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_oj_problem_category ON cs_oj_problems(category_id);
CREATE INDEX IF NOT EXISTS idx_oj_problem_public ON cs_oj_problems(is_public);

CREATE TABLE IF NOT EXISTS cs_oj_test_cases (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    problem_id INTEGER NOT NULL DEFAULT 0,
    name TEXT NOT NULL DEFAULT '',
    input TEXT DEFAULT '',
    output TEXT DEFAULT '',
    score REAL NOT NULL DEFAULT 0,
    type INTEGER NOT NULL DEFAULT 2,
    enabled INTEGER NOT NULL DEFAULT 1,
    sort INTEGER NOT NULL DEFAULT 0,
    create_time TEXT DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_oj_case_problem ON cs_oj_test_cases(problem_id);

CREATE TABLE IF NOT EXISTS cs_oj_submissions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    problem_id INTEGER NOT NULL DEFAULT 0,
    student_id INTEGER NOT NULL DEFAULT 0,
    student_no TEXT DEFAULT '',
    student_name TEXT DEFAULT '',
    class_id INTEGER NOT NULL DEFAULT 0,
    language TEXT NOT NULL DEFAULT '',
    code TEXT DEFAULT '',
    status TEXT NOT NULL DEFAULT 'pending',
    score REAL NOT NULL DEFAULT 0,
    time_used INTEGER NOT NULL DEFAULT 0,
    memory_used INTEGER NOT NULL DEFAULT 0,
    compile_log TEXT DEFAULT '',
    result_json TEXT DEFAULT '',
    is_test INTEGER NOT NULL DEFAULT 0,
    ip TEXT DEFAULT '',
    create_time TEXT DEFAULT CURRENT_TIMESTAMP,
    judge_time TEXT DEFAULT NULL
);

CREATE INDEX IF NOT EXISTS idx_oj_sub_problem ON cs_oj_submissions(problem_id);
CREATE INDEX IF NOT EXISTS idx_oj_sub_student ON cs_oj_submissions(student_id);
CREATE INDEX IF NOT EXISTS idx_oj_sub_status ON cs_oj_submissions(status);
CREATE INDEX IF NOT EXISTS idx_oj_sub_time ON cs_oj_submissions(create_time);
