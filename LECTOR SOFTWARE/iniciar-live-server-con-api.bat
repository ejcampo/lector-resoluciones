@echo off
cd /d "%~dp0"
set PHP_BIN=%~dp0.codex-temp\php-8.5.8-run\php.exe
set CODEX_PYTHON=%USERPROFILE%\.cache\codex-runtimes\codex-primary-runtime\dependencies\python
if exist "%CODEX_PYTHON%\python.exe" (
    set PYTHON_EXE=%CODEX_PYTHON%\python.exe
    set PATH=%CODEX_PYTHON%;%PATH%
)

netstat -ano | findstr ":5001 " | findstr "LISTENING" >nul
if errorlevel 1 (
    echo Iniciando API PHP en http://127.0.0.1:5001
    start "API PHP 5001" cmd /k "cd /d ""%~dp0"" && ""%~dp0.codex-temp\php-8.5.8-run\php.exe"" -d upload_max_filesize=50M -d post_max_size=50M -S 127.0.0.1:5001 -t public public/index.php"
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
