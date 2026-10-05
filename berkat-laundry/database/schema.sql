-- Database creation script for Berkat Laundry

CREATE DATABASE IF NOT EXISTS `berkat_laundry` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `berkat_laundry`;

-- 1. Table `users`
CREATE TABLE IF NOT EXISTS `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(50) UNIQUE NOT NULL,
    `password` VARCHAR(255) NOT NULL,
    `nama` VARCHAR(100) NOT NULL,
    `role` ENUM('admin', 'karyawan') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. Table `pelanggan`
CREATE TABLE IF NOT EXISTS `pelanggan` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `nama` VARCHAR(100) NOT NULL,
    `alamat` TEXT NOT NULL,
    `telepon` VARCHAR(15) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Table `layanan`
CREATE TABLE IF NOT EXISTS `layanan` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `nama_layanan` VARCHAR(100) NOT NULL,
    `harga` INT NOT NULL,
    `tipe_hitung` VARCHAR(20) NOT NULL, -- e.g. "kg", "pcs", "meter"
    `estimasi` VARCHAR(50) NOT NULL, -- e.g. "2 Hari", "12 Jam"
    `status` ENUM('aktif', 'nonaktif') DEFAULT 'aktif'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. Table `transaksi`
CREATE TABLE IF NOT EXISTS `transaksi` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `kode_transaksi` VARCHAR(20) UNIQUE NOT NULL,
    `id_pelanggan` INT NOT NULL,
    `id_user` INT NOT NULL,
    `tgl_masuk` DATETIME NOT NULL,
    `tgl_selesai` DATETIME DEFAULT NULL,
    `total_bayar` INT DEFAULT 0,
    `status_pembayaran` ENUM('belum_bayar', 'lunas') DEFAULT 'belum_bayar',
    `status_pengambilan` ENUM('belum_diambil', 'diambil') DEFAULT 'belum_diambil',
    `status_proses` ENUM('received', 'washing', 'drying', 'ironing', 'ready', 'completed') DEFAULT 'received',
    CONSTRAINT `fk_transaksi_pelanggan` FOREIGN KEY (`id_pelanggan`) REFERENCES `pelanggan` (`id`) ON UPDATE CASCADE,
    CONSTRAINT `fk_transaksi_users` FOREIGN KEY (`id_user`) REFERENCES `users` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5. Table `detail_transaksi`
CREATE TABLE IF NOT EXISTS `detail_transaksi` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `id_transaksi` INT NOT NULL,
    `id_layanan` INT NOT NULL,
    `jumlah` DECIMAL(10,2) NOT NULL, -- e.g. weight in kg or item count
    `subtotal` INT NOT NULL,
    CONSTRAINT `fk_detail_transaksi_transaksi` FOREIGN KEY (`id_transaksi`) REFERENCES `transaksi` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_detail_transaksi_layanan` FOREIGN KEY (`id_layanan`) REFERENCES `layanan` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 6. Table `pengeluaran`
CREATE TABLE IF NOT EXISTS `pengeluaran` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `id_user` INT NOT NULL,
    `tgl_pengeluaran` DATE NOT NULL,
    `keterangan` VARCHAR(255) NOT NULL,
    `jumlah` INT NOT NULL,
    CONSTRAINT `fk_pengeluaran_users` FOREIGN KEY (`id_user`) REFERENCES `users` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ==========================================
-- Insert Default Data
-- ==========================================

-- Insert Users (Passwords are 'admin123' and 'karyawan123' respectively)
INSERT INTO `users` (`id`, `username`, `password`, `nama`, `role`) VALUES
(1, 'admin', '$2y$10$fYPo0ykoJJDJLeTH8XtQP.ucYbQmJg2Dn9OjWQnmdSB17ybX3XVf2', 'Yoga Christian', 'admin'),
(2, 'karyawan', '$2y$10$ScupfF6k0xQ0EjXp2VRmPeOBrPYjofC5UAX3uDrsVLzd1purQFoGe', 'Maria Dwiningsih', 'karyawan');

-- Insert Customers
INSERT INTO `pelanggan` (`id`, `nama`, `alamat`, `telepon`) VALUES
(1, 'Budi Santoso', 'Jl. Kaliurang KM 5, Sleman', '081234567890'),
(2, 'Siti Aminah', 'Jl. Gejayan No. 12, Depok', '089876543210'),
(3, 'Rian Hidayat', 'Jl. Monjali No. 45, Sleman', '085612345678');

-- Insert Services
INSERT INTO `layanan` (`id`, `nama_layanan`, `harga`, `tipe_hitung`, `estimasi`, `status`) VALUES
(1, 'Cuci Lipat', 7000, 'kg', '2-3 Hari', 'aktif'),
(2, 'Setrika Saja', 5000, 'kg', '1-2 Hari', 'aktif'),
(3, 'Cuci Setrika', 9000, 'kg', '2-3 Hari', 'aktif'),
(4, 'Dry Clean', 15000, 'pcs', '3 Hari', 'aktif'),
(5, 'Cuci Kilat', 12000, 'kg', '12 Jam', 'aktif');

-- Insert Transactions
-- Note: Date set around June 2026 to fit the narrative time
INSERT INTO `transaksi` (`id`, `kode_transaksi`, `id_pelanggan`, `id_user`, `tgl_masuk`, `tgl_selesai`, `total_bayar`, `status_pembayaran`, `status_pengambilan`, `status_proses`) VALUES
(1, 'TRX001', 1, 1, '2026-06-08 09:00:00', '2026-06-11 10:00:00', 45000, 'lunas', 'diambil', 'completed'),
(2, 'TRX002', 2, 1, '2026-06-10 14:15:00', NULL, 40000, 'lunas', 'belum_diambil', 'ready'),
(3, 'TRX003', 3, 1, '2026-06-11 10:30:00', NULL, 21000, 'belum_bayar', 'belum_diambil', 'ironing');

-- Insert Transaction Details
INSERT INTO `detail_transaksi` (`id`, `id_transaksi`, `id_layanan`, `jumlah`, `subtotal`) VALUES
(1, 1, 3, 5.00, 45000), -- 5 kg Cuci Setrika @ 9000
(2, 2, 4, 2.00, 30000), -- 2 pcs Dry Clean @ 15000
(3, 2, 2, 2.00, 10000), -- 2 kg Setrika Saja @ 5000
(4, 3, 1, 3.00, 21000); -- 3 kg Cuci Lipat @ 7000

-- Insert Expenses
INSERT INTO `pengeluaran` (`id`, `id_user`, `tgl_pengeluaran`, `keterangan`, `jumlah`) VALUES
(1, 1, '2026-06-08', 'Beli deterjen liquid 5L', 75000),
(2, 1, '2026-06-09', 'Beli pewangi softener 5L', 65000),
(3, 2, '2026-06-10', 'Bayar listrik bulanan toko', 250000),
(4, 1, '2026-06-11', 'Beli plastik kemasan laundry', 45000);
