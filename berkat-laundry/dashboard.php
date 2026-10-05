<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
require_once 'config/database.php';

$page_title = 'Dashboard';
$page_subtitle = 'Ringkasan informasi administrasi dan keuangan Berkat Laundry.';
require_once 'includes/header.php';

// Fetch Statistics
try {
    // 1. Total Pelanggan
    $stmt = $pdo->query("SELECT COUNT(*) FROM pelanggan");
    $total_pelanggan = $stmt->fetchColumn();

    // 2. Total Transaksi
    $stmt = $pdo->query("SELECT COUNT(*) FROM transaksi");
    $total_transaksi = $stmt->fetchColumn();

    // 3. Transaksi Aktif (belum selesai)
    $stmt = $pdo->query("SELECT COUNT(*) FROM transaksi WHERE status_proses != 'completed'");
    $transaksi_aktif = $stmt->fetchColumn();

    // 4. Total Pendapatan Kotor (hanya yang sudah Lunas)
    $stmt = $pdo->query("SELECT SUM(total_bayar) FROM transaksi WHERE status_pembayaran = 'lunas'");
    $pendapatan_kotor = $stmt->fetchColumn() ?: 0;

    // 5. Total Pengeluaran
    $stmt = $pdo->query("SELECT SUM(jumlah) FROM pengeluaran");
    $total_pengeluaran = $stmt->fetchColumn() ?: 0;

    // 6. Laba Bersih
    $laba_bersih = $pendapatan_kotor - $total_pengeluaran;

    // 7. Recent Transactions (latest 5)
    $stmt = $pdo->query("SELECT t.*, p.nama AS nama_pelanggan 
                         FROM transaksi t 
                         JOIN pelanggan p ON t.id_pelanggan = p.id 
                         ORDER BY t.tgl_masuk DESC LIMIT 5");
    $recent_transactions = $stmt->fetchAll();

    // 8. Recent Expenses (latest 5)
    $stmt = $pdo->query("SELECT * FROM pengeluaran ORDER BY tgl_pengeluaran DESC LIMIT 5");
    $recent_expenses = $stmt->fetchAll();

} catch (PDOException $e) {
    echo '<div class="alert alert-error">Gagal memuat data statistik: ' . htmlspecialchars($e->getMessage()) . '</div>';
}

// Helpers for badges
function get_status_proses_badge($status) {
    $map = [
        'received' => '<span class="badge badge-secondary"><i class="fa-solid fa-soap"></i> Diterima</span>',
        'washing'  => '<span class="badge badge-info"><i class="fa-solid fa-rotate"></i> Dicuci</span>',
        'drying'   => '<span class="badge badge-warning"><i class="fa-solid fa-wind"></i> Dikeringkan</span>',
        'ironing'  => '<span class="badge badge-primary"><i class="fa-solid fa-square-rss"></i> Disetrika</span>',
        'ready'    => '<span class="badge badge-success"><i class="fa-solid fa-boxes-packing"></i> Siap Diambil</span>',
        'completed'=> '<span class="badge badge-success" style="background:#047857;"><i class="fa-solid fa-circle-check"></i> Selesai</span>',
    ];
    return $map[$status] ?? $status;
}

function get_status_bayar_badge($status) {
    if ($status === 'lunas') {
        return '<span class="badge badge-success">Lunas</span>';
    }
    return '<span class="badge badge-danger">Belum Bayar</span>';
}
?>

<!-- Statistics Cards -->
<div class="stats-grid">
  <!-- Total Orders -->
  <div class="glass-panel stat-card">
    <div class="stat-icon" style="background: rgba(14, 165, 233, 0.12); color: var(--primary);">
      <i class="fa-solid fa-cart-shopping"></i>
    </div>
    <div class="stat-details">
      <span class="stat-num"><?php echo $total_transaksi; ?></span>
      <span class="stat-label">Total Pesanan</span>
    </div>
  </div>

  <!-- Active Orders -->
  <div class="glass-panel stat-card">
    <div class="stat-icon" style="background: rgba(245, 158, 11, 0.12); color: var(--warning);">
      <i class="fa-solid fa-spinner"></i>
    </div>
    <div class="stat-details">
      <span class="stat-num"><?php echo $transaksi_aktif; ?></span>
      <span class="stat-label">Sedang Diproses</span>
    </div>
  </div>

  <!-- Gross Revenue (Only visible to Owner) -->
  <div class="glass-panel stat-card">
    <div class="stat-icon" style="background: rgba(16, 185, 129, 0.12); color: var(--success);">
      <i class="fa-solid fa-hand-holding-dollar"></i>
    </div>
    <div class="stat-details">
      <span class="stat-num">Rp <?php echo number_format($pendapatan_kotor, 0, ',', '.'); ?></span>
      <span class="stat-label">Pendapatan Kotor</span>
    </div>
  </div>

  <?php if ($user_role === 'admin'): ?>
  <!-- Total Expenses -->
  <div class="glass-panel stat-card">
    <div class="stat-icon" style="background: rgba(244, 63, 94, 0.12); color: var(--accent);">
      <i class="fa-solid fa-wallet"></i>
    </div>
    <div class="stat-details">
      <span class="stat-num">Rp <?php echo number_format($total_pengeluaran, 0, ',', '.'); ?></span>
      <span class="stat-label">Biaya Pengeluaran</span>
    </div>
  </div>
  <?php endif; ?>
</div>

<!-- Main Dashboard Split Content -->
<div class="dashboard-split-grid">
  
  <!-- Left Column: Recent Transactions -->
  <div class="glass-panel table-card">
    <div class="table-header">
      <h3><i class="fa-solid fa-list-check" style="color: var(--primary); margin-right: 0.5rem;"></i>Transaksi Terbaru</h3>
      <a href="transaksi.php" class="btn btn-secondary" style="padding: 0.4rem 0.8rem; font-size: 0.85rem;">Lihat Semua</a>
    </div>

    <div style="overflow-x: auto;">
      <table>
        <thead>
          <tr>
            <th>Kode</th>
            <th>Pelanggan</th>
            <th>Tanggal Masuk</th>
            <th>Status Bayar</th>
            <th>Status Proses</th>
            <th>Total</th>
          </tr>
        </thead>
        <tbody>
          <?php if (count($recent_transactions) === 0): ?>
            <tr>
              <td colspan="6" style="text-align: center; color: var(--text-muted); padding: 1.5rem;">Tidak ada transaksi baru.</td>
            </tr>
          <?php else: ?>
            <?php foreach ($recent_transactions as $row): ?>
              <tr>
                <td style="font-weight: 700; color: var(--primary);">
                  <a href="transaksi-nota.php?id=<?php echo $row['id']; ?>" style="text-decoration:none; color:inherit;">
                    <?php echo htmlspecialchars($row['kode_transaksi']); ?>
                  </a>
                </td>
                <td><strong><?php echo htmlspecialchars($row['nama_pelanggan']); ?></strong></td>
                <td style="font-size: 0.85rem; color: var(--text-muted);">
                  <?php echo date('d M Y H:i', strtotime($row['tgl_masuk'])); ?>
                </td>
                <td><?php echo get_status_bayar_badge($row['status_pembayaran']); ?></td>
                <td><?php echo get_status_proses_badge($row['status_proses']); ?></td>
                <td style="font-weight: 600;">Rp <?php echo number_format($row['total_bayar'], 0, ',', '.'); ?></td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Right Column: Recent Expenses & Quick Actions -->
  <div style="display: flex; flex-direction: column; gap: 2rem;">
    <?php if ($user_role === 'admin'): ?>
    <!-- Recent Expenses -->
    <div class="glass-panel table-card">
      <div class="table-header">
        <h3><i class="fa-solid fa-receipt" style="color: var(--primary); margin-right: 0.5rem;"></i>Pengeluaran Terbaru</h3>
        <a href="pengeluaran.php" class="btn btn-secondary" style="padding: 0.4rem 0.8rem; font-size: 0.85rem;">Lihat Semua</a>
      </div>

      <div style="overflow-x: auto;">
        <table>
          <thead>
            <tr>
              <th>Tanggal</th>
              <th>Keterangan</th>
              <th>Jumlah</th>
            </tr>
          </thead>
          <tbody>
            <?php if (count($recent_expenses) === 0): ?>
              <tr>
                <td colspan="3" style="text-align: center; color: var(--text-muted); padding: 1.5rem;">Tidak ada pengeluaran terbaru.</td>
              </tr>
            <?php else: ?>
              <?php foreach ($recent_expenses as $row): ?>
                <tr>
                  <td style="font-size: 0.85rem; color: var(--text-muted);"><?php echo date('d M Y', strtotime($row['tgl_pengeluaran'])); ?></td>
                  <td style="font-weight: 500;"><?php echo htmlspecialchars($row['keterangan']); ?></td>
                  <td style="font-weight: 600; color: var(--accent);">Rp <?php echo number_format($row['jumlah'], 0, ',', '.'); ?></td>
                </tr>
                  <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php endif; ?>

    <!-- Quick Actions -->
    <div class="glass-panel" style="padding: 1.75rem;">
      <h3 style="font-size: 1.25rem; font-weight: 700; margin-bottom: 1.25rem;"><i class="fa-solid fa-bolt" style="color: var(--primary); margin-right: 0.5rem;"></i>Aktivitas Cepat</h3>
      <div style="display: flex; flex-direction: column; gap: 0.75rem;">
        <?php if ($user_role === 'admin'): ?>
          <a href="transaksi-tambah.php" class="btn btn-primary" style="justify-content: center; width: 100%;">
            <i class="fa-solid fa-cart-plus"></i> Catat Transaksi Baru
          </a>
          <a href="pelanggan.php?action=tambah" class="btn btn-secondary" style="justify-content: center; width: 100%;">
            <i class="fa-solid fa-user-plus"></i> Tambah Data Pelanggan
          </a>
          <a href="laporan-labarugi.php" class="btn btn-secondary" style="justify-content: center; width: 100%;">
            <i class="fa-solid fa-file-invoice-dollar"></i> Laporan Kas Masuk & Keluar
          </a>
          <a href="layanan.php?action=tambah" class="btn btn-secondary" style="justify-content: center; width: 100%;">
            <i class="fa-solid fa-sliders"></i> Kelola Layanan Laundry
          </a>
          <a href="pengguna.php" class="btn btn-secondary" style="justify-content: center; width: 100%;">
            <i class="fa-solid fa-user-shield"></i> Kelola Pengguna System
          </a>
        <?php else: ?>
          <a href="transaksi-tambah.php" class="btn btn-primary" style="justify-content: center; width: 100%;">
            <i class="fa-solid fa-cart-plus"></i> Catat Transaksi Baru
          </a>
          <a href="pelanggan.php?action=tambah" class="btn btn-secondary" style="justify-content: center; width: 100%;">
            <i class="fa-solid fa-user-plus"></i> Tambah Data Pelanggan
          </a>
        <?php endif; ?>
      </div>
    </div>
  </div>

</div>

<?php
require_once 'includes/footer.php';
?>
