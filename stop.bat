@echo off
REM Nostek-NMSOLT - hentikan (Windows). Data tetap tersimpan di volume.
setlocal
cd /d "%~dp0"
echo Menghentikan Nostek-NMSOLT...
docker compose down
echo.
echo Dihentikan. Data (DB/upload/log) tetap aman.
echo Jalankan start.bat untuk menyalakan kembali.
pause
