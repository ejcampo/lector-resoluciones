@echo off
setlocal

set "PROJECT_DIR=%~dp0"
set "PHP_EXE=%PROJECT_DIR%.codex-temp\php-8.5.8-run\php.exe"
set "CODEX_PYTHON=C:\Users\julia\.cache\codex-runtimes\codex-primary-runtime\dependencies\python"

if not exist "%PHP_EXE%" (
    echo No se encontro PHP portable en:
    echo %PHP_EXE%
    echo.
    echo Abre Codex para prepararlo de nuevo o instala PHP y ejecuta:
    echo php -S 127.0.0.1:8000 -t public public/index.php
    pause
    exit /b 1
)

if exist "%CODEX_PYTHON%\python.exe" (
    set "PATH=%CODEX_PYTHON%;%PATH%"
)

start "" "http://127.0.0.1:8000"
echo Servidor iniciado en http://127.0.0.1:8000
echo.
echo Deja esta ventana abierta mientras uses el proyecto.
echo Para apagarlo, presiona Ctrl+C y luego S.
echo.

"%PHP_EXE%" -S 127.0.0.1:8000 -t "%PROJECT_DIR%public" "%PROJECT_DIR%public\index.php"

pause
