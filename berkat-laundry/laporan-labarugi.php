<?php
$page_title = 'Laporan Kas Masuk dan Kas Keluar';
$page_subtitle = 'Analisis kas masuk dari transaksi lunas dan kas keluar dari biaya operasional.';
require_once 'config/database.php';
require_once 'includes/header.php';

// Access Control: Only Admin can access financial reports
if ($user_role !== 'admin') {
    echo '<script>window.location.href="dashboard.php";</script>';
    exit();
}

$error = '';

// Default Date Range: Current Month (First day to today)
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-d');

try {
    // 1. Fetch total income in period (Only paid transactions)
    $stmt = $pdo->prepare("SELECT SUM(total_bayar) FROM transaksi 
                           WHERE status_pembayaran = 'lunas' 
                           AND DATE(tgl_masuk) BETWEEN ? AND ?");
    $stmt->execute([$start_date, $end_date]);
    $total_pemasukan = $stmt->fetchColumn() ?: 0;

    // Fetch individual income transactions
    $stmt = $pdo->prepare("SELECT t.*, p.nama AS nama_pelanggan 
                           FROM transaksi t 
                           JOIN pelanggan p ON t.id_pelanggan = p.id 
                           WHERE t.status_pembayaran = 'lunas' 
                           AND DATE(t.tgl_masuk) BETWEEN ? AND ? 
                           ORDER BY t.tgl_masuk ASC");
    $stmt->execute([$start_date, $end_date]);
    $income_list = $stmt->fetchAll();

    // 2. Fetch total expenses in period
    $stmt = $pdo->prepare("SELECT SUM(jumlah) FROM pengeluaran 
                           WHERE DATE(tgl_pengeluaran) BETWEEN ? AND ?");
    $stmt->execute([$start_date, $end_date]);
    $total_pengeluaran = $stmt->fetchColumn() ?: 0;

    // Fetch individual expenses
    $stmt = $pdo->prepare("SELECT * FROM pengeluaran 
                           WHERE DATE(tgl_pengeluaran) BETWEEN ? AND ? 
                           ORDER BY tgl_pengeluaran ASC");
    $stmt->execute([$start_date, $end_date]);
    $expense_list = $stmt->fetchAll();

} catch (PDOException $e) {
    $error = 'Gagal memuat data laporan kas masuk dan kas keluar: ' . $e->getMessage();
    $total_pemasukan = 0;
    $total_pengeluaran = 0;
    $income_list = [];
    $expense_list = [];
}
?>

<!-- Alert Box -->
<?php if (!empty($error)): ?>
    <div class="alert alert-error">
      <i class="fa-solid fa-triangle-exclamation"></i>
      <span><?php echo htmlspecialchars($error); ?></span>
    </div>
<?php endif; ?>

<!-- Filter Bar (Hidden in Print) -->
<div class="glass-panel no-print" style="padding: 1.5rem; margin-bottom: 2rem;">
    <form action="laporan-labarugi.php" method="GET" style="display: flex; gap: 1.5rem; align-items: flex-end; flex-wrap: wrap;">
        <div class="form-group" style="margin-bottom: 0; flex: 1; min-width: 150px;">
            <label for="start_date">Dari Tanggal</label>
            <input type="date" id="start_date" name="start_date" class="form-control" required value="<?php echo htmlspecialchars($start_date); ?>">
        </div>
        
        <div class="form-group" style="margin-bottom: 0; flex: 1; min-width: 150px;">
            <label for="end_date">Sampai Tanggal</label>
            <input type="date" id="end_date" name="end_date" class="form-control" required value="<?php echo htmlspecialchars($end_date); ?>">
        </div>

        <div style="display: flex; gap: 0.75rem;">
            <button type="submit" class="btn btn-primary">
                <i class="fa-solid fa-sync"></i> Tampilkan
            </button>
            <button type="button" onclick="window.print()" class="btn btn-secondary">
                <i class="fa-solid fa-print"></i> Cetak Laporan
            </button>
        </div>
    </form>
</div>

<!-- Report Header for Print Mode only -->
<div class="print-only" style="text-align: center; margin-bottom: 2rem;">
    <h2 style="font-weight: 800; font-size: 1.8rem; margin-bottom: 0.25rem;">LAPORAN KAS MASUK DAN KAS KELUAR BERKAT LAUNDRY</h2>
    <p style="color: #64748b; font-size: 0.95rem;">Periode: <?php echo date('d M Y', strtotime($start_date)); ?> s.d. <?php echo date('d M Y', strtotime($end_date)); ?></p>
    <div style="border-bottom: 2px solid #0f172a; margin-top: 1.5rem;"></div>
</div>

<!-- Financial Summary Cards -->
<div class="laba-rugi-grid">
    <!-- Pemasukan / Kas Masuk -->
    <div class="glass-panel stat-card" style="border-left: 5px solid var(--success);">
        <div class="stat-icon" style="background: rgba(16, 185, 129, 0.12); color: var(--success);">
            <i class="fa-solid fa-arrow-down-long"></i>
        </div>
        <div class="stat-details">
            <span class="stat-num" style="color: var(--success);">Rp <?php echo number_format($total_pemasukan, 0, ',', '.'); ?></span>
            <span class="stat-label">Total Kas Masuk</span>
        </div>
    </div>

    <!-- Pengeluaran / Kas Keluar -->
    <div class="glass-panel stat-card" style="border-left: 5px solid var(--accent);">
        <div class="stat-icon" style="background: rgba(244, 63, 94, 0.12); color: var(--accent);">
            <i class="fa-solid fa-arrow-up-long"></i>
        </div>
        <div class="stat-details">
            <span class="stat-num" style="color: var(--accent);">Rp <?php echo number_format($total_pengeluaran, 0, ',', '.'); ?></span>
            <span class="stat-label">Total Kas Keluar</span>
        </div>
    </div>
</div>

<!-- Detailed Tables Split Section -->
<div class="labarugi-split-grid">
    
    <!-- Left Column: Kas Masuk Details -->
    <div class="glass-panel table-card">
        <div class="table-header">
            <h3><i class="fa-solid fa-hand-holding-dollar" style="color: var(--success); margin-right: 0.5rem;"></i>Rincian Kas Masuk</h3>
        </div>
        <div style="overflow-x: auto;">
            <table>
                <thead>
                    <tr>
                        <th>Tanggal</th>
                        <th>Transaksi</th>
                        <th>Pelanggan</th>
                        <th style="text-align: right;">Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($income_list) === 0): ?>
                        <tr>
                            <td colspan="4" style="text-align: center; color: var(--text-muted); padding: 1.5rem;">Tidak ada kas masuk pada periode ini.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($income_list as $row): ?>
                            <tr>
                                <td style="font-size: 0.85rem; color: var(--text-muted);"><?php echo date('d/m/Y', strtotime($row['tgl_masuk'])); ?></td>
                                <td style="font-weight: 700; color: var(--primary);"><?php echo htmlspecialchars($row['kode_transaksi']); ?></td>
                                <td><strong><?php echo htmlspecialchars($row['nama_pelanggan']); ?></strong></td>
                                <td style="font-weight: 600; color: var(--success); text-align: right;">Rp <?php echo number_format($row['total_bayar'], 0, ',', '.'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <tr style="font-weight: 800; border-top: 2px solid var(--border-color);">
                            <td colspan="3" style="text-align: right;">Total Kas Masuk:</td>
                            <td style="color: var(--success); text-align: right;">Rp <?php echo number_format($total_pemasukan, 0, ',', '.'); ?></td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Right Column: Kas Keluar Details -->
    <div class="glass-panel table-card">
        <div class="table-header">
            <h3><i class="fa-solid fa-wallet" style="color: var(--accent); margin-right: 0.5rem;"></i>Rincian Kas Keluar</h3>
        </div>
        <div style="overflow-x: auto;">
            <table>
                <thead>
                    <tr>
                        <th>Tanggal</th>
                        <th>Keterangan</th>
                        <th style="text-align: right;">Nominal</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($expense_list) === 0): ?>
                        <tr>
                            <td colspan="3" style="text-align: center; color: var(--text-muted); padding: 1.5rem;">Tidak ada kas keluar pada periode ini.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($expense_list as $row): ?>
                            <tr>
                                <td style="font-size: 0.85rem; color: var(--text-muted);"><?php echo date('d/m/Y', strtotime($row['tgl_pengeluaran'])); ?></td>
                                <td><strong><?php echo htmlspecialchars($row['keterangan']); ?></strong></td>
                                <td style="font-weight: 600; color: var(--accent); text-align: right;">Rp <?php echo number_format($row['jumlah'], 0, ',', '.'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <tr style="font-weight: 800; border-top: 2px solid var(--border-color);">
                            <td colspan="2" style="text-align: right;">Total Kas Keluar:</td>
                            <td style="color: var(--accent); text-align: right;">Rp <?php echo number_format($total_pengeluaran, 0, ',', '.'); ?></td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<!-- Print Stylesheet specific override to hide layout elements -->
<style>
@media print {
  aside, .theme-toggle-btn, .btn, .page-header, .no-print, footer {
    display: none !important;
  }
  .main-content {
    margin-left: 0 !important;
    padding: 0 !important;
  }
  .laba-rugi-grid {
    grid-template-columns: repeat(2, 1fr) !important;
    margin-bottom: 2rem !important;
  }
  .table-card {
    border: 1px solid #cbd5e1 !important;
    box-shadow: none !important;
    background: transparent !important;
  }
  th {
    background: #f1f5f9 !important;
    color: #000000 !important;
    border-bottom: 1px solid #000000 !important;
  }
}
</style>

<?php
require_once 'includes/footer.php';
?>
