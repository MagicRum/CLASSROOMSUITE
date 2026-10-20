@echo off
chcp 65001 >nul
title 课堂管理系统 - 本地开发服务器
echo ============================================
echo   课堂管理系统 - 本地开发服务器启动中...
echo ============================================
echo.

cd /d "%~dp0"
set PORT=8080

if not exist "tools\php\php.exe" (
    echo [错误] 找不到 PHP！请确保 tools\php\php.exe 存在
    pause
    exit /b 1
)

echo [信息] 启动 PHP 内置服务器...
echo [信息] 端口: %PORT%
echo [信息] 首页: http://127.0.0.1:%PORT%/
echo [信息] 管理员: http://127.0.0.1:%PORT%/admin/login  (admin / admin123)
echo [信息] 学生端: http://127.0.0.1:%PORT%/student/login
echo.
echo [提示] 按 Ctrl+C 停止服务器
echo ============================================
echo.

tools\php\php.exe -S 127.0.0.1:%PORT% -t "public" "public\index.php"
pause
