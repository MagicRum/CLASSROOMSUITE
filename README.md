# class="doc"
# 学生课堂管理系统 - 宝塔部署手册

## 一、环境要求

| 组件 | 版本要求 |
|------|----------|
| PHP | >= 8.0 |
| MySQL | >= 5.7（推荐 8.0）|
| Nginx | 宝塔默认版本即可 |

## 二、宝塔面板准备

1. **安全组开放端口**（腾讯云控制台 → 云服务器 → 安全组）：
   - 入站规则：TCP 80（HTTP）、443（HTTPS），源 IP 0.0.0.0/0
   - **不要**开放 3306（MySQL）到公网，只留本地访问

2. **宝塔一键安装 LNMP**：
   - 软件商店 → 搜「LNMP」→ 选 PHP 8.0 + MySQL 8.0 + Nginx → 一键安装

## 三、创建站点

1. 宝塔面板 → **网站** → **添加站点**
   - 域名：填你的域名或服务器公网 IP
   - 根目录：默认（一般是 `/www/wwwroot/你的域名/`）
   - PHP 版本：选 **PHP 8.0**
   - 数据库：勾选 MySQL，自动创建，记下数据库名、用户名、密码

2. 开启必要 PHP 扩展：
   - 网站 → 设置 → PHP设置 → 安装扩展 → 勾选 `fileinfo`、`openssl`、`pdo_mysql`
   - PHP设置 → 禁用函数 → **删除** `putenv`（ThinkPHP 部分版本需要）

## 四、上传项目代码

**方式 A：Git clone（推荐）**
```bash
cd /www/wwwroot/你的域名/
git clone https://你的仓库地址.git .
```

**方式 B：宝塔文件管理器**
- 本地将 `classroom-system` 目录打包为 zip
- 宝塔文件管理器上传到站点根目录
- 右键解压

## 五、Composer 安装依赖

宝塔面板 → **软件商店** → 搜「Composer」→ 安装

然后打开宝塔的**终端**，执行：
```bash
cd /www/wwwroot/你的域名/
composer install --no-dev --optimize-autoloader
```

如果下载慢，可以用国内镜像：
```bash
composer config -g repo.packagist composer https://mirrors.aliyun.com/composer/
composer install --no-dev --optimize-autoloader
```

## 六、配置数据库

### 方式 1：用 .env 文件（推荐）
```bash
cp .env.example .env
vi .env
```
把里面的 `database.password` 改成宝塔创建数据库时生成的密码。

### 方式 2：手动导入 SQL（不管 .env 也行，默认读 config/database.php）
- 宝塔 → 数据库 → 点「管理」进 phpMyAdmin
- 选中你的数据库 → 导入 → 上传 `database/schema.sql` → 执行

## 七、配置伪静态

宝塔 → 网站 → 设置 → **伪静态** → 粘贴 deploy/nginx.conf 的内容 → 保存

## 八、目录权限

宝塔 → 文件管理器 → 选中 `runtime` 目录 → 权限 → **755**（或 777）

```bash
chmod -R 755 runtime/
chown -R www:www runtime/
```

## 九、默认账号

| 角色 | 登录地址 | 账号 | 密码 |
|------|----------|------|------|
| 管理员（老师） | `http://你的域名/admin/login` | admin | admin123 |
| 学生 | `http://你的域名/student/login` | 学号+姓名（无需密码） | - |

> **⚠️ 登录后立即修改管理员密码！** 在 phpMyAdmin 中更新 `cs_admins` 表的 `password` 字段，新密码用 PHP `password_hash('新密码', PASSWORD_DEFAULT)` 生成。

## 十、快速验证清单

- [ ] 首页能访问 → 选「我是老师」→ 管理员登录成功
- [ ] 后台「仪表盘」能看到统计数字
- [ ] 学生管理 → 下载模板 → 填几个测试数据 → 导入成功
- [ ] 试题管理 → 下载模板 → 填几道题 → 导入成功
- [ ] 分组管理 → 选班级 → 随机分组成功
- [ ] 学生端 → 用测试学生的学号+姓名登录 → 点击抽题 → 看到2道随机题 → 作答提交 → 看到得分
- [ ] 后台考试记录 → 能看到刚才学生的抽题记录和答案

## 十一、HTTPS（推荐）

宝塔 → 网站 → 设置 → **SSL** → Let's Encrypt → 勾选域名 → 申请
申请成功后开启「强制 HTTPS」

## 十二、安全加固

1. **修改宝塔端口**：安全 → 面板设置 → 修改默认端口
2. **MySQL 远程访问关闭**：数据库 → 权限 → 只允许 127.0.0.1
3. **定期备份**：计划任务 → 添加「备份网站」和「备份数据库」，每天凌晨执行
4. **上传文件白名单**：代码里只允许 `.xlsx/.xls/.csv`
5. **隐藏 index.php**：伪静态已配置，访问 `/admin/login` 不带 index.php

## 十三、常见问题

| 问题 | 解决方案 |
|------|----------|
| 页面白屏 | 宝塔 → PHP设置 → 打开「调试模式」，看错误日志（runtime/log/）|
| 数据库连接失败 | 检查 .env 文件里的密码，MySQL 是否启动 |
| Composer 下载慢 | 换阿里云镜像（见第五节）|
| Excel 导入失败 | 确认 vendor 里有 phpoffice/phpspreadsheet（重新跑 composer install）|
| 伪静态不生效 | 确认 deploy/nginx.conf 里的规则粘贴进去了；宝塔 PHP 设置里「URL重写」已开启 |
| runtime 写入权限 | chmod -R 777 runtime/ 试试（生产用 755 即可）|

## 十四、目录结构

```
classroom-system/
├── app/                     # 业务代码
│   ├── controller/
│   │   ├── admin/           # 管理员端控制器
│   │   └── student/         # 学生端控制器
│   ├── model/               # 数据模型
│   ├── middleware/          # 认证中间件
│   └── view/                # 前端模板
├── config/                  # ThinkPHP 配置
├── database/
│   └── schema.sql           # 建表 SQL
├── deploy/
│   └── nginx.conf           # Nginx 伪静态示例
├── public/                  # Web 入口（宝塔站点根目录指向这里）
│   └── index.php
├── route/
│   └── app.php              # 路由定义
├── runtime/                 # 运行时缓存（需可写）
├── .env.example             # 环境变量示例
├── composer.json
└── README.md
```

## 十五、更新维护

```bash
cd /www/wwwroot/你的域名/
git pull
composer install --no-dev --optimize-autoloader
```
