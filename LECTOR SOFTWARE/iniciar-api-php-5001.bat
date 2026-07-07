@echo off
cd /d "%~dp0"
set PHP_BIN=%~dp0.codex-temp\php-8.5.8-run\php.exe
echo Iniciando API PHP en http://127.0.0.1:5001
echo Deja esta ventana abierta mientras usas Live Server en http://127.0.0.1:5000
"%PHP_BIN%" -d upload_max_filesize=50M -d post_max_size=50M -S 127.0.0.1:5001 -t public public/index.php
pause
