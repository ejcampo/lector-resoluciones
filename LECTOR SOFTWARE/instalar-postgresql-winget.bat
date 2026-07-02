@echo off
echo Este instalador usa winget para descargar PostgreSQL.
echo Si Windows pide permisos, acepta la instalacion.
echo.
winget install -e --id PostgreSQL.PostgreSQL
echo.
echo Cuando termine, reinicia la terminal y revisa con: psql --version
pause
