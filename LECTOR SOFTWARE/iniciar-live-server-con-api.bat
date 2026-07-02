@echo off
cd /d "%~dp0"

netstat -ano | findstr ":5001 " | findstr "LISTENING" >nul
if errorlevel 1 (
    echo Iniciando API PHP en http://127.0.0.1:5001
    start "API PHP 5001" cmd /k "cd /d ""%~dp0"" && php -S 127.0.0.1:5001 -t public public/index.php"
    timeout /t 2 /nobreak >nul
) else (
    echo La API PHP ya esta iniciada en el puerto 5001.
)

echo Abriendo Live Server en http://127.0.0.1:5000
start http://127.0.0.1:5000

echo.
echo Si la pagina no carga, abre VS Code y presiona Go Live.
echo Deja abierta la ventana "API PHP 5001" mientras procesas PDFs.
pause
