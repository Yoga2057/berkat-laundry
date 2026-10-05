@echo off
title Berkat Laundry - Local Server
color 0b
echo ===================================================
echo             BERKAT LAUNDRY LOCAL SERVER            
echo ===================================================
echo.
echo [1] Mencari IP Address lokal PC Anda...
set LOCAL_IP=127.0.0.1
for /f "tokens=2 delims=:" %%a in ('ipconfig ^| findstr /i "IPv4"') do (
    set LOCAL_IP=%%a
    goto :ip_found
)
:ip_found
:: Remove leading spaces
set LOCAL_IP=%LOCAL_IP:~1%

echo IP Address PC Anda: %LOCAL_IP%
echo.
echo [2] Menjalankan server pada port 8000...
echo.
echo - Akses dari PC ini:      http://localhost:8000
echo - Akses dari HP (Mobile):   http://%LOCAL_IP%:8000
echo.
echo [3] Membuka browser ke http://localhost:8000 ...
start http://localhost:8000
echo.
echo Tekan Ctrl+C di jendela ini untuk mematikan server.
echo ---------------------------------------------------
php -S 0.0.0.0:8000
pause
