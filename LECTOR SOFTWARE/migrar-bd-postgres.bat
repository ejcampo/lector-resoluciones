@echo off
cd /d "%~dp0"

set "DB_HOST=%DB_HOST%"
if "%DB_HOST%"=="" set "DB_HOST=127.0.0.1"

set "DB_PORT=%DB_PORT%"
if "%DB_PORT%"=="" set "DB_PORT=5432"

set "DB_DATABASE=%DB_DATABASE%"
if "%DB_DATABASE%"=="" set "DB_DATABASE=lector_resoluciones"

set "DB_USERNAME=%DB_USERNAME%"
if "%DB_USERNAME%"=="" set "DB_USERNAME=postgres"

:: Buscar psql.exe si no está en el PATH
set "PSQL_PATH=psql"
where psql >nul 2>nul
if errorlevel 1 (
    for /d %%i in ("%ProgramFiles%\PostgreSQL\*") do (
        if exist "%%i\bin\psql.exe" set "PSQL_PATH=%%i\bin\psql.exe"
    )
)

echo Creando tablas en PostgreSQL...
echo Base: %DB_DATABASE%  Host: %DB_HOST%:%DB_PORT%  Usuario: %DB_USERNAME%
"%PSQL_PATH%" -h "%DB_HOST%" -p "%DB_PORT%" -U "%DB_USERNAME%" -d "%DB_DATABASE%" -f "database\migrations\001_create_resoluciones_extraidas.sql"
"%PSQL_PATH%" -h "%DB_HOST%" -p "%DB_PORT%" -U "%DB_USERNAME%" -d "%DB_DATABASE%" -f "database\migrations\002_create_usuarios.sql"

echo.
echo Migraciones finalizadas.
pause
