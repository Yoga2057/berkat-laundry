<?php
$page_title = 'Cetak Nota Laundry';
$page_subtitle = 'Nota fisik sebagai bukti pembayaran dan pengambilan cucian.';
require_once 'config/database.php';
require_once 'includes/header.php';

$id = intval($_GET['id'] ?? 0);
$success_msg = isset($_GET['success']) ? 'Transaksi berhasil dicatat!' : '';

try {
    // 1. Fetch transaction and customer data
    $stmt = $pdo->prepare("SELECT t.*, p.nama AS nama_pelanggan, p.alamat AS alamat_pelanggan, 
                                  p.telepon AS telp_pelanggan, u.nama AS nama_petugas
                           FROM transaksi t
                           JOIN pelanggan p ON t.id_pelanggan = p.id
                           JOIN users u ON t.id_user = u.id
                           WHERE t.id = ?");
    $stmt->execute([$id]);
    $transaction = $stmt->fetch();

    if (!$transaction) {
        echo '<div class="alert alert-error">Nota transaksi tidak ditemukan.</div>';
        require_once 'includes/footer.php';
        exit();
    }

    // 2. Fetch transaction details (services)
    $stmt = $pdo->prepare("SELECT dt.*, l.nama_layanan, l.harga AS harga_satuan, l.tipe_hitung
                           FROM detail_transaksi dt
                           JOIN layanan l ON dt.id_layanan = l.id
                           WHERE dt.id_transaksi = ?");
    $stmt->execute([$id]);
    $details = $stmt->fetchAll();

} catch (PDOException $e) {
    echo '<div class="alert alert-error">Terjadi kesalahan: ' . htmlspecialchars($e->getMessage()) . '</div>';
    require_once 'includes/footer.php';
    exit();
}

function get_status_bayar_label($status) {
    return $status === 'lunas' ? 'LUNAS' : 'BELUM BAYAR';
}

function get_status_proses_label($status) {
    $map = [
        'received' => 'Diterima',
        'washing'  => 'Dicuci',
        'drying'   => 'Dikeringkan',
        'ironing'  => 'Disetrika',
        'ready'    => 'Siap Diambil',
        'completed'=> 'Selesai',
    ];
    return $map[$status] ?? $status;
}
?>

<!-- Action Bar (Hidden in Print) -->
<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;" class="no-print">
    <div>
        <?php if ($success_msg): ?>
            <div class="alert alert-success" style="margin-bottom: 0;">
                <i class="fa-solid fa-circle-check"></i>
                <span><?php echo $success_msg; ?></span>
            </div>
        <?php endif; ?>
    </div>
    
    <div style="display: flex; gap: 0.75rem;">
        <a href="transaksi.php" class="btn btn-secondary">
            <i class="fa-solid fa-arrow-left"></i> Kembali ke Transaksi
        </a>
        <button onclick="window.print()" class="btn btn-primary">
            <i class="fa-solid fa-print"></i> Cetak Nota (Print)
        </button>
    </div>
</div>

<!-- Receipt container -->
<div class="receipt-container">
    <div class="receipt-header">
        <h2>BERKAT LAUNDRY</h2>
        <p>Srayu, Canden, Jetis, Bantul, Yogyakarta</p>
        <p>Telp: 0856-0675-3110 | WhatsApp: 0856-0675-3110</p>
    </div>

    <div class="receipt-meta">
        <div>
            <div><strong>No. Nota:</strong> <?php echo htmlspecialchars($transaction['kode_transaksi']); ?></div>
            <div><strong>Pelanggan:</strong> <?php echo htmlspecialchars($transaction['nama_pelanggan']); ?></div>
            <div><strong>Telepon:</strong> <?php echo htmlspecialchars($transaction['telp_pelanggan']); ?></div>
        </div>
        <div style="text-align: right;">
            <div><strong>Tanggal:</strong> <?php echo date('d/m/Y H:i', strtotime($transaction['tgl_masuk'])); ?></div>
            <div><strong>Petugas:</strong> <?php echo htmlspecialchars($transaction['nama_petugas']); ?></div>
            <div><strong>Status:</strong> <?php echo get_status_proses_label($transaction['status_proses']); ?></div>
        </div>
    </div>

    <div class="receipt-items">
        <table>
            <thead>
                <tr>
                    <th style="text-align: left;">Layanan</th>
                    <th style="text-align: center;">Jumlah</th>
                    <th style="text-align: right;">Subtotal</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($details as $item): ?>
                    <tr>
                        <td style="text-align: left;">
                            <?php echo htmlspecialchars($item['nama_layanan']); ?> 
                            <span style="font-size: 0.75rem; color: #64748b; display: block;">
                                @ Rp <?php echo number_format($item['harga_satuan'], 0, ',', '.'); ?> / <?php echo htmlspecialchars($item['tipe_hitung']); ?>
                            </span>
                        </td>
                        <td style="text-align: center;">
                            <?php echo floatval($item['jumlah']); ?> <?php echo htmlspecialchars($item['tipe_hitung']); ?>
                        </td>
                        <td style="text-align: right;">
                            Rp <?php echo number_format($item['subtotal'], 0, ',', '.'); ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="receipt-total">
        <span>TOTAL BIAYA</span>
        <span>Rp <?php echo number_format($transaction['total_bayar'], 0, ',', '.'); ?></span>
    </div>
    
    <div style="display: flex; justify-content: space-between; font-weight: 700; font-size: 0.9rem; margin-bottom: 2rem; border-bottom: 1px solid #cbd5e1; padding-bottom: 1rem;">
        <span>PEMBAYARAN:</span>
        <span style="color: <?php echo $transaction['status_pembayaran'] === 'lunas' ? 'var(--success)' : 'var(--accent)'; ?>;">
            <?php echo get_status_bayar_label($transaction['status_pembayaran']); ?>
        </span>
    </div>

    <div class="receipt-footer">
        <p>Terima kasih atas kepercayaan Anda.</p>
        <p>Cucian bersih, rapi, dan wangi adalah prioritas kami.</p>
        <p style="margin-top: 1rem; font-size: 0.75rem; font-style: italic;">*Harap bawa nota ini saat pengambilan pakaian.*</p>
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
  .receipt-container {
    border: none !important;
    box-shadow: none !important;
    margin: 0 auto !important;
    padding: 0 !important;
    max-width: 100% !important;
  }
}
</style>

<?php
require_once 'includes/footer.php';
?>
