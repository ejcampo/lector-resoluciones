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

echo Creando base de datos PostgreSQL si no existe...
echo Base: %DB_DATABASE%  Host: %DB_HOST%:%DB_PORT%  Usuario: %DB_USERNAME%

"%PSQL_PATH%" -h "%DB_HOST%" -p "%DB_PORT%" -U "%DB_USERNAME%" -d postgres -tc "SELECT 1 FROM pg_database WHERE datname = '%DB_DATABASE%'" | findstr 1 >nul
if errorlevel 1 (
    "%PSQL_PATH%" -h "%DB_HOST%" -p "%DB_PORT%" -U "%DB_USERNAME%" -d postgres -c "CREATE DATABASE %DB_DATABASE%"
) else (
    echo La base %DB_DATABASE% ya existe.
)

echo.
echo Ahora ejecuta migrar-bd-postgres.bat para crear las tablas.
pause
