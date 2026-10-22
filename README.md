# QTS 抽题系统 技术文档
---

## 1. 项目概览

**QTS（Question & Test System）** 是一个面向高校课堂的抽题考试管理系统，核心能力：

- **题库管理**：5 种题型（单选、多选、判断、填空、简答），Excel 批量导入（三步向导：上传 → 预览编辑 → 确认）
- **随机抽题**：按难度/分类/题型多维度筛选，一键随机组卷
- **在线考试**：学生端答题、提交、自动评分（客观题即时，简答题教师批改）
- **在线判题（OJ）**：编程题在线评测，C/C++/Python/Java/GCC 沙箱编译运行
- **随机分组**：按班级一键生成实践课分组名单
- **多门户**：管理员 / 教师 / 学生三端分离

---

## 2. 技术栈

| 层 | 技术 | 备注 |
|---|---|---|
| 后端框架 | ThinkPHP 6.1 | PHP 7.4~8.2 |
| ORM | ThinkORM 2.x | |
| 数据库 | SQLite 3 | 文件型 DB，零安装；腾讯云 `runtime/classroom.sqlite` |
| Excel | PhpSpreadsheet 2.x | 批量导入/导出 |
| 判题沙箱 | C++ 编译器 + Python subprocess | php-fpm 下需 `putenv` 设置 PATH |
| 前端 | Bootstrap 5.3 + 原生 JS | JetBrains 暗色主题 + 亮色切换 |
| 模板引擎 | ThinkPHP Blade 风格 | `{$var}`、`{volist}`、`{if}` |
| Web 服务器 | Nginx + PHP-FPM | 宝塔面板管理 |
| 版本控制 | Git + 裸仓库 post-receive 钩子 | |

---

## 3. 目录结构

```
classroom-system/
├── app/
│   ├── controller/
│   │   ├── admin/          # 管理员后台控制器（Question/Draw/Exam/Oj* 等）
│   │   ├── student/        # 学生端控制器
│   │   └── common.php      # 公共函数
│   ├── middleware/         # 中间件（AdminAuth 鉴权等）
│   ├── model/              # ThinkORM 模型
│   ├── view/
│   │   ├── admin/
│   │   │   ├── layout/    # 后台布局（侧边栏 + 主题切换按钮）
│   │   │   └── question/   # 题库管理页（index / edit / import）
│   │   └── student/
│   └── service/            # 业务服务（可选）
├── config/                 # 框架配置（app.php / database.php / route.php）
├── route/route.php         # 路由定义
├── public/
│   ├── static/css/theme.css   # 主题变量 + 亮色覆盖（约 620 行）
│   ├── static/js/theme.js      # 主题切换逻辑
│   └── index.php               # 入口文件
├── runtime/
│   ├── classroom.sqlite   # SQLite 数据库文件（敏感！含用户密码哈希）
│   ├── temp/              # 模板编译缓存（改模板后需清空）
│   └── log/               # 运行日志（按日）
├── vendor/                 # Composer 依赖
└── composer.json
```

---

## 4. 数据库结构

数据库文件：`runtime/classroom.sqlite`  
连接在 `config/database.php` 中配置，使用 `sqlite` 驱动。

### 核心表

| 表名 | 字段 | 说明 |
|---|---|---|
| `cs_admins` | id / username / password(phpass) / real_name / last_login_* | 管理员 |
| `cs_questions` | id / title / question_type(1-5) / option_a-d / correct_answer / score / difficulty(1-3) / category | 题库 |
| `cs_exam_records` | id / student_id / question_ids(逗号) / student_answers(JSON) / score / status / draw_time / submit_time | 考试记录 |
| `cs_clazz` | id / name | 班级 |
| `cs_students` | id / student_no / name / password / clazz_id | 学生 |
| `cs_groups` / `cs_group_members` | 分组 + 成员 | 实践课分组 |
| `cs_oj_problems` / `cs_oj_submissions` / `cs_oj_test_cases` / `cs_oj_categories` | 判题系统四表 | |


---

## 6. 核心模块实现

### 6.1 题库管理

**文件**：`app/controller/admin/Question.php` + `app/view/admin/question/index.html` / `import.html`

**路由**（`route/route.php`）：
```
GET  /admin/Question/index          列表
GET  /admin/Question/edit?id=N      新增/编辑页
POST /admin/Question/save           保存
POST /admin/Question/delete         删除
GET  /admin/Question/template       下载 Excel 模板
GET  /admin/Question/import         三步向导入口
POST /admin/Question/importPreview  解析 Excel 返回 JSON
POST /admin/Question/confirmImport  确认入库
```

**Excel 导入三步向导**：

1. **Step 1 上传**：拖拽 + 点击双模式，PhpSpreadsheet 解析后 POST 到 `importPreview`
2. **Step 2 预览编辑**：返回逐行 JSON（含 `errors[]`、`exists` 查重、`skip` 标记），前端可内联编辑每题
3. **Step 3 确认**：POST 整页 `rows[]` 到 `confirmImport`，服务端做防御性答案校验（单选必须 A-D、多选 A-D+、判断 A/B）后 upsert 入库

**列表页 UI**：
- CSS Grid 6 列（序号 / 题目 / 题型 / 分类 / 难度 / 操作）—— 答案默认收起、ID 列删除
- 序号 JS 计算 `seqBase + rowIndex + 1`，分页内从 1 连续
- 题型/难度彩色徽标
- 点击行或预览按钮弹预览弹窗

**预览弹窗按题型分样式**（`openDetail()` JS）：
- **单选/多选**：选项卡片 + 正确答案绿色高亮 `.is-answer`
- **判断**：A/B 正确/错误双卡片
- **填空**：下划线占位 `.qs-fill-block`
- **简答**：粉色答题区 + 教师参考答案 `.qs-essay-block`

### 6.2 主题系统

- **暗色（默认）**：JetBrains 深灰 `#101013` + 紫罗兰强调 `#A855F7`
- **亮色**：`html[data-theme="light"]` 触发，白底 `#F5F5F7`，深紫 `#7C3AED` 强调
- **主题变量**：`theme.css` 先在 `:root` 定义基础色，再用 `html[data-theme="light"]` 整体覆盖
- **切换按钮**：右下角浮动圆按钮，`localStorage.setItem('cs-theme', 'light')` 持久化
- **缓存注意**：nginx 对 `.css` 有 `expires 12h`，改 `theme.css` 后需要在 `admin/layout/index.html` 的 `<link>` 加版本号 `?v=N` 绕过


*文档完。系统状态：GitHub 已同步 HEAD `9b93370`，腾讯云服务器运行中，内网 rsync 定时同步。*
