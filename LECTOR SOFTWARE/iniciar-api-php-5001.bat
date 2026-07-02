@echo off
cd /d "%~dp0"
echo Iniciando API PHP en http://127.0.0.1:5001
echo Deja esta ventana abierta mientras usas Live Server en http://127.0.0.1:5000
php -S 127.0.0.1:5001 -t public public/index.php
pause
