@echo off
chcp 65001 >nul
title 停止服务器
echo 正在停止 PHP 服务器...
taskkill /f /im php.exe >nul 2>&1
echo 已停止。
pause
