# 🧺 Berkat Laundry - Sistem Administrasi & Pembukuan Kas Laundry

![PHP](https://img.shields.io/badge/PHP-7.4%2B-777BB4?style=for-the-badge&logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-Database-4479A1?style=for-the-badge&logo=mysql&logoColor=white)
![CSS3](https://img.shields.io/badge/CSS3-Vanilla-1572B6?style=for-the-badge&logo=css3&logoColor=white)
![JavaScript](https://img.shields.io/badge/JavaScript-ES6+-F7DF1E?style=for-the-badge&logo=javascript&logoColor=black)
![License](https://img.shields.io/badge/License-MIT-green?style=for-the-badge)

**Berkat Laundry** adalah sistem informasi manajemen dan pembukuan kas berbasis web yang dirancang khusus untuk mempermudah operasional usaha laundry harian. Aplikasi ini menyediakan solusi lengkap dari pencatatan transaksi, pengolahan data pelanggan dan layanan, pelacakan status cuci, pencetakan nota (termasuk nota publik online), hingga analisis laporan keuangan laba/rugi.

---

## 📸 Fitur Utama

### 👑 Hak Akses (Multi-Role)
- **Admin**: Akses penuh ke seluruh sistem (Dashboard, Pelanggan, Layanan, Transaksi, Pengeluaran, Laporan Keuangan, & Manajemen Pengguna).
- **Karyawan**: Akses terbatas untuk pencatatan pelanggan dan pembuatan/pembaruan transaksi harian.

### 📊 Dashboard & Statistik Real-Time
- Ringkasan statistik pendapatan hari ini dan bulan ini.
- Jumlah transaksi aktif, status proses, dan peringatan tagihan belum dibayar.
- Tampilan tabel transaksi terbaru dan status pengerjaan.

### 🏷️ Manajemen Layanan & Pelanggan (CRM)
- **Pelanggan**: Pencatatan data pelanggan (nama, alamat, nomor telepon/WhatsApp).
- **Layanan**: Pengaturan jenis layanan (misal: Cuci Lipat, Setrika, Dry Clean, Express), penetapan harga per satuan (`kg`, `pcs`, `meter`), estimasi pengerjaan, dan status aktif/nonaktif.

### 🧾 Transaksi & Nota Digital
- **Multi-Item Transaction**: Pencatatan transaksi dengan kombinasi beberapa jenis layanan sekaligus.
- **Workflow Status Pengerjaan**: Pelacakan status bertahap:
  `Diterima (Received)` ➡️ `Dicuci (Washing)` ➡️ `Pengeringan (Drying)` ➡️ `Disetrika (Ironing)` ➡️ `Selesai (Ready)` ➡️ `Diambil (Completed)`.
- **Cetak Nota Thermal / Digital**: Dukungan cetak nota siap print untuk printer thermal atau simpan sebagai dokumen.
- **Nota Publik & QR / Link Ngrok**: Fitur halaman nota publik (`nota-publik.php`) yang memungkinkan pelanggan melihat status cuci secara online via link / HP.

### 💰 Pembukuan Pengeluaran & Laporan Laba Rugi
- **Biaya Operasional**: Pencatatan pengeluaran operasional usaha (pembelian deterjen, bayar listrik, plastik kemasan, maintenance mesin, dsb).
- **Laporan Kas Masuk & Keluar**: Rekapitulasi otomatis pemasukan bersih dari transaksi vs pengeluaran operasional untuk mengetahui Laba Bersih usaha.

### 🌙 Dark Mode & Responsive Interface
- Desain antarmuka modern, bersih, dan intuitif.
- Fitur switcher **Dark Mode** / **Light Mode** yang responsif untuk kenyamanan penggunaan di PC maupun Smartphone.

---

## 📁 Struktur Direktori

```text
berkat-laundry/
├── assets/                 # Asset statis web
│   ├── css/                # Stylesheet Vanilla CSS (variabel tema & responsive layout)
│   ├── js/                 # Script interaksi UI & Theme Switcher
│   └── images/             # Gambar & ikon pendukung
├── config/
│   └── database.php        # Konfigurasi koneksi database PDO MySQL
├── database/
│   └── schema.sql          # File SQL database & data sampel awal
├── includes/
│   ├── header.php          # Component header & sidebar navigasi
│   └── footer.php          # Component footer & modal scripting
├── dashboard.php           # Halaman utama Ringkasan Statistik
├── index.php               # Halaman Form Login System
├── pelanggan.php           # Kelola Data Pelanggan
├── layanan.php             # Kelola Jenis Layanan & Tarif Laundry
├── transaksi.php           # Daftar & Filter Transaksi Laundry
├── transaksi-tambah.php    # Form Input Transaksi Baru
├── transaksi-edit.php      # Update Status Transaksi & Pembayaran
├── transaksi-nota.php      # Halaman Cetak Nota Internal (Thermal Print)
├── nota-publik.php         # Halaman Tracking Nota Publik Pelanggan
├── pengeluaran.php         # Pencatatan Biaya Operasional Toko
├── pengguna.php            # Manajemen Akun User (Admin & Karyawan)
├── laporan-labarugi.php    # Laporan Keuangan Pemasukan & Pengeluaran
├── logout.php              # Process Terminate Session
├── jalankan-laundry.bat    # Script Windows batch untuk menjalankan server lokal (Port 8000)
└── hubungkan-ngrok.bat     # Script Windows batch untuk membuat terowongan online Ngrok
```

---

## 💻 Persyaratan Sistem

- **PHP**: v7.4 atau versi yang lebih baru (disarankan PHP 8.x dengan ekstensi `pdo_mysql` aktif).
- **Database**: MySQL / MariaDB (via XAMPP, Laragon, atau MySQL Standalone).
- **Web Browser**: Google Chrome, Mozilla Firefox, Microsoft Edge, atau Safari.
- **Ngrok** *(Opsional)*: Untuk menguji tampilan nota publik via internet/smartphone secara cepat.

---

## 🛠️ Langkah-Langkah Instalasi

### 1. Persiapan Database
1. Buka **phpMyAdmin** (`http://localhost/phpmyadmin`) atau MySQL GUI client pilihan Anda.
2. Buat database baru dengan nama **`berkat_laundry`** atau biarkan file schema membuatnya otomatis.
3. Import file `database/schema.sql` ke dalam database `berkat_laundry`.

### 2. Konfigurasi Koneksi Database
Buka file `config/database.php` dan sesuaikan kredensial MySQL Anda jika berbeda dari default XAMPP:

```php
$host = '127.0.0.1';
$db   = 'berkat_laundry';
$user = 'root';      // Sesuaikan username MySQL Anda
$pass = '';          // Sesuaikan password MySQL Anda
```

---

## 🚀 Cara Menjalankan Aplikasi

Anda memiliki **2 Cara** untuk menjalankan aplikasi:

### Cara 1: Menggunakan Script One-Click `jalankan-laundry.bat` (Rekomendasi / Praktis)
1. Klik dua kali pada file `jalankan-laundry.bat` di dalam folder ini.
2. Script akan mendeteksi IP Address PC Anda dan memulai PHP Built-in Server pada port `8000`.
3. Browser akan otomatis membuka tautan: `http://localhost:8000`.
4. Jika ingin mengakses dari HP dalam satu jaringan Wi-Fi, gunakan URL IP lokal yang tertera di jendela command prompt (contoh: `http://192.168.1.5:8000`).

### Cara 2: Menggunakan Apache XAMPP
1. Pastikan folder `berkat-laundry` berada di dalam direktori `C:\xampp\htdocs\`.
2. Jalankan modul **Apache** dan **MySQL** melalui XAMPP Control Panel.
3. Buka browser dan ketik tautan: `http://localhost/berkat-laundry`.

---

## 🌐 Menghubungkan ke Internet dengan Ngrok (Opsional)

Jika Anda ingin mempublikasikan aplikasi ini secara sementara agar dapat diakses oleh dosen, penguji, atau klien dari jaringan luar (internet HP):

1. Pastikan server lokal sudah berjalan via `jalankan-laundry.bat`.
2. Pastikan file `ngrok.exe` berada di folder yang sama atau sudah terinstal di sistem Anda.
3. Klik dua kali file `hubungkan-ngrok.bat`.
4. Ikuti petunjuk di layar (masukkan Authtoken Ngrok jika diminta).
5. Salin link publik yang dihasilkan (misal: `https://xxxx.ngrok-free.app`) dan bagikan link tersebut.

---

## 🔑 Akun Login Default

| Role | Username | Password | Hak Akses |
| :--- | :--- | :--- | :--- |
| **Admin** | `admin` | `admin123` | Akses Penuh (Manajemen User, Tarif, Biaya, Laporan, Transaksi) |
| **Karyawan** | `karyawan` | `karyawan123` | Akses Operasional (Input Pelanggan, Transaksi, Update Status) |

---

## 📜 Lisensi & Pengembang

Dikembangkan untuk **Berkat Laundry** - Sistem Administrasi & Pembukuan Kas Laundry Modern.

*Hak Cipta &copy; 2026 Berkat Laundry. All rights reserved.*
