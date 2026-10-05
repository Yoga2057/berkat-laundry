<?php
$page_title = 'Catat Transaksi Baru';
$page_subtitle = 'Buat pesanan laundry baru untuk pelanggan.';
require_once 'config/database.php';
require_once 'includes/header.php';

// Access Control: Admin and Karyawan can record new transactions
if ($user_role !== 'admin' && $user_role !== 'karyawan') {
    echo '<script>window.location.href="transaksi.php";</script>';
    exit();
}

$error = '';
$message = '';

// Handle POST request to create transaction
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_pelanggan = intval($_POST['id_pelanggan']);
    $status_pembayaran = $_POST['status_pembayaran'] ?? 'belum_bayar';
    $selected_services = $_POST['services'] ?? []; // Array of active service IDs
    $qty_inputs = $_POST['qty'] ?? []; // Associative array of service_id => qty

    if ($id_pelanggan > 0 && !empty($selected_services)) {
        try {
            $pdo->beginTransaction();

            // 1. Generate unique Transaction Code (TRX + 4 random digits)
            $kode_transaksi = '';
            $is_unique = false;
            while (!$is_unique) {
                $rand = rand(1000, 9999);
                $kode_transaksi = 'TRX' . $rand;
                $check = $pdo->prepare("SELECT COUNT(*) FROM transaksi WHERE kode_transaksi = ?");
                $check->execute([$kode_transaksi]);
                if ($check->fetchColumn() == 0) {
                    $is_unique = true;
                }
            }

            // 2. Insert into Table `transaksi`
            $id_user = $_SESSION['user_id'];
            $stmt = $pdo->prepare("INSERT INTO transaksi (kode_transaksi, id_pelanggan, id_user, tgl_masuk, status_pembayaran) VALUES (?, ?, ?, NOW(), ?)");
            $stmt->execute([$kode_transaksi, $id_pelanggan, $id_user, $status_pembayaran]);
            $id_transaksi = $pdo->lastInsertId();

            // 3. Insert details and calculate total
            $total_bayar = 0;
            foreach ($selected_services as $service_id) {
                $service_id = intval($service_id);
                $qty = floatval($qty_inputs[$service_id] ?? 1);
                
                if ($qty <= 0) $qty = 1;

                // Fetch service price
                $s_stmt = $pdo->prepare("SELECT harga FROM layanan WHERE id = ?");
                $s_stmt->execute([$service_id]);
                $price = $s_stmt->fetchColumn() ?: 0;

                $subtotal = $price * $qty;
                $total_bayar += $subtotal;

                // Insert to `detail_transaksi`
                $d_stmt = $pdo->prepare("INSERT INTO detail_transaksi (id_transaksi, id_layanan, jumlah, subtotal) VALUES (?, ?, ?, ?)");
                $d_stmt->execute([$id_transaksi, $service_id, $qty, $subtotal]);
            }

            // 4. Update total payment in `transaksi`
            $u_stmt = $pdo->prepare("UPDATE transaksi SET total_bayar = ? WHERE id = ?");
            $u_stmt->execute([$total_bayar, $id_transaksi]);

            $pdo->commit();
            
            // Redirect to receipt
            echo '<script>window.location.href="transaksi-nota.php?id=' . $id_transaksi . '&success=1";</script>';
            exit();

        } catch (Exception $e) {
            $pdo->rollBack();
            $error = 'Gagal menyimpan transaksi: ' . $e->getMessage();
        }
    } else {
        $error = 'Harap pilih Pelanggan dan minimal satu Layanan laundry.';
    }
}

// Fetch all active customers for dropdown
try {
    $stmt = $pdo->query("SELECT * FROM pelanggan ORDER BY nama ASC");
    $customers = $stmt->fetchAll();

    // Fetch all active services
    $stmt = $pdo->query("SELECT * FROM layanan WHERE status = 'aktif' ORDER BY nama_layanan ASC");
    $services = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = 'Gagal memuat data formulir: ' . $e->getMessage();
    $customers = [];
    $services = [];
}
?>

<!-- Alert Box -->
<?php if (!empty($error)): ?>
    <div class="alert alert-error">
      <i class="fa-solid fa-triangle-exclamation"></i>
      <span><?php echo htmlspecialchars($error); ?></span>
    </div>
<?php endif; ?>

<?php if (count($customers) === 0): ?>
    <div class="glass-panel" style="padding: 2.5rem; text-align: center;">
        <i class="fa-solid fa-users-slash" style="font-size: 3rem; color: var(--text-muted); margin-bottom: 1rem;"></i>
        <h3>Belum Ada Data Pelanggan</h3>
        <p style="color: var(--text-muted); margin: 0.5rem 0 1.5rem 0;">Anda harus menambahkan pelanggan terlebih dahulu sebelum dapat mencatat transaksi laundry.</p>
        <a href="pelanggan.php?action=tambah" class="btn btn-primary">
            <i class="fa-solid fa-user-plus"></i> Tambah Pelanggan Pertama
        </a>
    </div>
<?php else: ?>

    <form action="transaksi-tambah.php" method="POST" id="transaction-form">
        <div class="transaction-grid">
            
            <!-- Left column: form inputs -->
            <div style="display: flex; flex-direction: column; gap: 2rem;">
                
                <!-- Customer & Basic info -->
                <div class="glass-panel" style="padding: 2rem;">
                    <h3 style="font-size: 1.25rem; font-weight: 700; margin-bottom: 1.5rem;"><i class="fa-solid fa-user-edit" style="color:var(--primary); margin-right: 0.5rem;"></i>Informasi Pelanggan</h3>
                    
                    <div class="form-group">
                        <label for="id_pelanggan">Pilih Pelanggan</label>
                        <select name="id_pelanggan" id="id_pelanggan" class="form-control" required>
                            <option value="">-- Pilih Pelanggan --</option>
                            <?php foreach ($customers as $c): ?>
                                <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['nama']); ?> (<?php echo htmlspecialchars($c['telepon']); ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>


                </div>

                <!-- Services selection -->
                <div class="glass-panel" style="padding: 2rem;">
                    <h3 style="font-size: 1.25rem; font-weight: 700; margin-bottom: 1.5rem;"><i class="fa-solid fa-sliders" style="color:var(--primary); margin-right: 0.5rem;"></i>Pilih Layanan & Detail</h3>
                    
                    <div class="service-selection-list">
                        <?php if (count($services) === 0): ?>
                            <p style="color: var(--text-muted); text-align: center; padding: 1rem;">Tidak ada layanan laundry aktif yang tersedia.</p>
                        <?php else: ?>
                            <?php foreach ($services as $s): ?>
                                <div class="service-item-row">
                                    <div style="display: flex; align-items: center; gap: 0.75rem;">
                                        <input type="checkbox" name="services[]" value="<?php echo $s['id']; ?>" 
                                               class="service-check-input" id="service-<?php echo $s['id']; ?>"
                                               data-price="<?php echo $s['harga']; ?>" 
                                               data-name="<?php echo htmlspecialchars($s['nama_layanan']); ?>"
                                               data-unit="<?php echo htmlspecialchars($s['tipe_hitung']); ?>"
                                               style="width: 18px; height: 18px; cursor: pointer;">
                                        <label for="service-<?php echo $s['id']; ?>" style="cursor: pointer; font-weight: 700; margin-bottom: 0;">
                                            <?php echo htmlspecialchars($s['nama_layanan']); ?>
                                        </label>
                                    </div>
                                    <div style="color: var(--text-muted); font-size: 0.9rem;">
                                        Rp <?php echo number_format($s['harga'], 0, ',', '.'); ?> / <?php echo htmlspecialchars($s['tipe_hitung']); ?>
                                    </div>
                                    <div style="color: var(--text-muted); font-size: 0.9rem; text-align: center;">
                                        <i class="fa-regular fa-clock"></i> <?php echo htmlspecialchars($s['estimasi']); ?>
                                    </div>
                                    <div style="display: flex; align-items: center; gap: 0.5rem; justify-content: flex-end;">
                                        <input type="number" name="qty[<?php echo $s['id']; ?>]" value="1" min="0.1" step="0.1" 
                                               class="form-control service-qty-input" style="width: 70px; padding: 0.35rem; font-size: 0.9rem; text-align: center;" 
                                               disabled>
                                        <span style="font-weight: 600; font-size: 0.85rem; color: var(--text-muted);"><?php echo htmlspecialchars($s['tipe_hitung']); ?></span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Right column: summary sticky box -->
            <div>
                <div class="glass-panel order-summary-card">
                    <h3 class="summary-title"><i class="fa-solid fa-receipt" style="color:var(--primary); margin-right:0.5rem;"></i>Ringkasan Pesanan</h3>
                    
                    <div class="order-summary-list" id="summary-items-list">
                        <!-- Filled dynamically by main.js -->
                        <div class="order-summary-item" style="color: var(--text-muted); font-style: italic;">
                            Pilih minimal satu layanan...
                        </div>
                    </div>
                    
                    <div class="order-summary-item total">
                        <span class="label">Total Estimasi</span>
                        <span class="val" id="summary-total-price">Rp 0</span>
                    </div>

                    <button type="submit" class="btn btn-primary" style="width: 100%; justify-content: center; padding: 1rem; margin-top: 1.5rem;">
                        <i class="fa-solid fa-paper-plane"></i> Simpan Transaksi & Cetak
                    </button>
                </div>
            </div>

        </div>
    </form>

<?php endif; ?>

<?php
require_once 'includes/footer.php';
?>
