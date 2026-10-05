@echo off
title Berkat Laundry - Hubungkan Ngrok
color 0e

echo =========================================================
echo               BERKAT LAUNDRY - NGROK TUNNEL              
echo =========================================================
echo Alat ini membantu Anda membuat link internet publik agar 
echo dosen/penguji bisa membuka web laundry dari HP mereka.
echo.
echo PENTING:
echo Pastikan 'jalankan-laundry.bat' sudah dijalankan lebih dulu
echo dan statusnya masih aktif (running).
echo =========================================================
echo.

:: 1. Cek apakah ngrok.exe ada di folder ini atau di PATH
where ngrok >nul 2>nul
if %errorlevel% neq 0 (
    if not exist ngrok.exe (
        color 0c
        echo [X] ERROR: File 'ngrok.exe' tidak ditemukan!
        echo.
        echo Cara Mengatasi:
        echo 1. Download Ngrok gratis di: https://ngrok.com/download
        echo 2. Ekstrak file 'ngrok.exe' dari file zip yang diunduh.
        echo 3. Pindahkan/copy file 'ngrok.exe' ke folder ini:
        echo    %~dp0
        echo 4. Buka kembali file 'hubungkan-ngrok.bat' ini.
        echo.
        pause
        exit
    )
)

:: 2. Tanya apakah butuh setup authtoken
echo --- PENGATURAN AUTHTOKEN NGROK ---
echo Catatan: Ngrok versi terbaru memerlukan Authtoken (Gratis).
echo Anda hanya perlu memasukkannya SEKALI saja di PC ini.
echo.
set /p setup_token="Apakah Anda ingin memasukkan/memperbarui Authtoken Ngrok? (y/n): "
if /i "%setup_token%"=="y" (
    echo.
    echo Silakan salin Authtoken Anda dari dashboard ngrok (https://dashboard.ngrok.com)
    set /p token="Masukkan Authtoken Anda: "
    if not "%token%"=="" (
        echo.
        echo Mengonfigurasi authtoken...
        ngrok config add-authtoken %token%
        echo [OK] Authtoken berhasil disimpan!
        echo.
    ) else (
        echo [!] Token kosong, melewati konfigurasi...
    )
)

echo.
echo =========================================================
echo [OK] Menjalankan Terowongan Ngrok ke Port 8000...
echo.
echo CARA MEMBACA HASIL:
echo - Cari tulisan "Forwarding" pada jendela yang terbuka nanti.
echo - Tautan berawalan "https://..." adalah link publik Anda.
echo - Berikan link tersebut ke Dosen / Penguji Anda!
echo =========================================================
echo.
pause

ngrok http 8000
