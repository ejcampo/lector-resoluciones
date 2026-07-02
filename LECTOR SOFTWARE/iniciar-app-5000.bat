@echo off
cd /d "%~dp0"
echo Iniciando Lector de Resoluciones en http://127.0.0.1:5000
echo Este modo ejecuta HTML, JS y PHP en el mismo puerto.
php -S 127.0.0.1:5000 -t public public/index.php
pause
