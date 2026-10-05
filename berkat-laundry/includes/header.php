<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Redirect to login if user is not authenticated
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$current_page = basename($_SERVER['PHP_SELF']);
$user_role = $_SESSION['user_role'] ?? 'admin';
$user_name = $_SESSION['user_nama'] ?? 'User';

// Helper to determine active class
function is_active($page, $current_page) {
    return $page === $current_page ? 'active' : '';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?php echo $page_title ?? 'Berkat Laundry'; ?> - Sistem Administrasi & Pembukuan</title>
  <!-- FontAwesome Icons -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <!-- CSS Stylesheet -->
  <link rel="stylesheet" href="assets/css/style.css?v=1.0.2">
</head>
<body data-theme="light">

  <div class="layout-container">
    <!-- Sidebar Navigation -->
    <aside>
      <button class="sidebar-close-btn" id="sidebar-close-btn" title="Tutup Menu">
        <i class="fa-solid fa-xmark"></i>
      </button>
      <div class="sidebar-logo">
        <i class="fa-solid fa-soap"></i>
        <span>Berkat Laundry</span>
      </div>
      
      <nav class="sidebar-menu">
        <a href="dashboard.php" class="sidebar-menu-item <?php echo is_active('dashboard.php', $current_page); ?>">
          <i class="fa-solid fa-chart-pie"></i> Dashboard
        </a>
        
        <?php if ($user_role === 'admin' || $user_role === 'karyawan'): ?>
        <a href="pelanggan.php" class="sidebar-menu-item <?php echo is_active('pelanggan.php', $current_page); ?>">
          <i class="fa-solid fa-users"></i> Pelanggan
        </a>
        <?php endif; ?>
        
        <?php if ($user_role === 'admin'): ?>
        <a href="layanan.php" class="sidebar-menu-item <?php echo is_active('layanan.php', $current_page); ?>">
          <i class="fa-solid fa-sliders"></i> Layanan Laundry
        </a>
        <?php endif; ?>

        <a href="transaksi.php" class="sidebar-menu-item <?php echo is_active('transaksi.php', $current_page) || is_active('transaksi-tambah.php', $current_page) || is_active('transaksi-detail.php', $current_page) || is_active('transaksi-edit.php', $current_page) ? 'active' : ''; ?>">
          <i class="fa-solid fa-calculator"></i> Transaksi Laundry
        </a>

        <?php if ($user_role === 'admin'): ?>
        <a href="pengeluaran.php" class="sidebar-menu-item <?php echo is_active('pengeluaran.php', $current_page); ?>">
          <i class="fa-solid fa-wallet"></i> Biaya Operasional
        </a>
        <?php endif; ?>

        <?php if ($user_role === 'admin'): ?>
        <a href="pengguna.php" class="sidebar-menu-item <?php echo is_active('pengguna.php', $current_page); ?>">
          <i class="fa-solid fa-user-shield"></i> Kelola Pengguna
        </a>
        <a href="laporan-labarugi.php" class="sidebar-menu-item <?php echo is_active('laporan-labarugi.php', $current_page); ?>">
          <i class="fa-solid fa-file-invoice-dollar"></i> Laporan Kas Masuk & Keluar
        </a>
        <?php endif; ?>
      </nav>

      <div class="sidebar-footer">
        <div class="user-profile-info" style="margin-bottom: 0.25rem;">
          <div class="avatar">
            <?php echo strtoupper(substr($user_name, 0, 1)); ?>
          </div>
          <div class="user-profile-details">
            <span class="name"><?php echo htmlspecialchars($user_name); ?></span>
            <span class="role"><?php echo htmlspecialchars(ucfirst($user_role)); ?></span>
          </div>
        </div>
        <a href="logout.php" class="btn btn-logout">
          <i class="fa-solid fa-right-from-bracket"></i> Keluar (Logout)
        </a>
      </div>
    </aside>

    <!-- Main Content Area -->
    <main class="main-content">
      <!-- Top Bar / Page Header -->
      <header class="page-header">
        <div>
          <h1><?php echo $page_title ?? 'Dashboard'; ?></h1>
          <p><?php echo $page_subtitle ?? 'Selamat datang kembali di panel administrasi.'; ?></p>
        </div>
        <div class="header-actions">
          <button class="theme-toggle-btn" id="theme-toggle-btn" title="Ganti Tema">
            <i class="fa-solid fa-moon"></i>
          </button>
          <button class="menu-toggle-btn" id="menu-toggle-btn" title="Menu">
            <i class="fa-solid fa-bars"></i>
          </button>
        </div>
      </header>
