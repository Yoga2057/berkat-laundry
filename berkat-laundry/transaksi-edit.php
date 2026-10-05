<?php
$page_title = 'Edit Transaksi Laundry';
$page_subtitle = 'Perbarui pesanan, tambah layanan, atau sesuaikan jumlah cucian pelanggan.';
require_once 'config/database.php';
require_once 'includes/header.php';

// Access Control: Admin and Karyawan can edit transactions
if ($user_role !== 'admin' && $user_role !== 'karyawan') {
    echo '<script>window.location.href="transaksi.php";</script>';
    exit();
}

$id = intval($_GET['id'] ?? 0);
if ($id <= 0) {
    echo '<script>window.location.href="transaksi.php";</script>';
    exit();
}

$error = '';
$message = '';

try {
    // Fetch transaction data
    $stmt = $pdo->prepare("SELECT * FROM transaksi WHERE id = ?");
    $stmt->execute([$id]);
    $transaction = $stmt->fetch();

    if (!$transaction) {
        echo '<div class="alert alert-error">Transaksi tidak ditemukan.</div>';
        require_once 'includes/footer.php';
        exit();
    }

    // Fetch existing details for this transaction
    $stmt = $pdo->prepare("SELECT * FROM detail_transaksi WHERE id_transaksi = ?");
    $stmt->execute([$id]);
    $details = $stmt->fetchAll();
    
    // Map details by service ID for easier form building
    $details_map = [];
    foreach ($details as $d) {
        $details_map[$d['id_layanan']] = $d;
    }

} catch (PDOException $e) {
    $error = 'Terjadi kesalahan database: ' . $e->getMessage();
}

// Handle POST request to update transaction
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$error) {
    $id_pelanggan = intval($_POST['id_pelanggan']);
    $selected_services = $_POST['services'] ?? []; // Array of selected service IDs
    $qty_inputs = $_POST['qty'] ?? []; // Associative array of service_id => qty

    if ($id_pelanggan > 0 && !empty($selected_services)) {
        try {
            $pdo->beginTransaction();

            // 1. Update customer in `transaksi` table (keeping original dates and code)
            $stmt = $pdo->prepare("UPDATE transaksi SET id_pelanggan = ? WHERE id = ?");
            $stmt->execute([$id_pelanggan, $id]);

            // 2. Delete existing details first to perform clean insert (many-to-many update)
            $del_stmt = $pdo->prepare("DELETE FROM detail_transaksi WHERE id_transaksi = ?");
            $del_stmt->execute([$id]);

            // 3. Insert new details and calculate total price
            $total_bayar = 0;
            foreach ($selected_services as $service_id) {
                $service_id = intval($service_id);
                $qty = floatval($qty_inputs[$service_id] ?? 1);
                
                if ($qty <= 0) $qty = 1;

                // Fetch service price (even if nonactive, we fetch it because it was part of this transaction)
                $s_stmt = $pdo->prepare("SELECT harga FROM layanan WHERE id = ?");
                $s_stmt->execute([$service_id]);
                $price = $s_stmt->fetchColumn() ?: 0;

                $subtotal = $price * $qty;
                $total_bayar += $subtotal;

                // Insert to `detail_transaksi`
                $d_stmt = $pdo->prepare("INSERT INTO detail_transaksi (id_transaksi, id_layanan, jumlah, subtotal) VALUES (?, ?, ?, ?)");
                $d_stmt->execute([$id, $service_id, $qty, $subtotal]);
            }

            // 4. Update total payment in `transaksi` table
            $u_stmt = $pdo->prepare("UPDATE transaksi SET total_bayar = ? WHERE id = ?");
            $u_stmt->execute([$total_bayar, $id]);

            $pdo->commit();
            
            // Redirect to receipt page with success message
            echo '<script>window.location.href="transaksi-nota.php?id=' . $id . '&success=1";</script>';
            exit();

        } catch (Exception $e) {
            $pdo->rollBack();
            $error = 'Gagal memperbarui transaksi: ' . $e->getMessage();
        }
    } else {
        $error = 'Harap pilih Pelanggan dan minimal satu Layanan laundry.';
    }
}

// Fetch lists for form representation
try {
    $stmt = $pdo->query("SELECT * FROM pelanggan ORDER BY nama ASC");
    $customers = $stmt->fetchAll();

    // Fetch active services OR services that are already selected in this transaction (even if they are non-active now)
    $s_stmt = $pdo->prepare("
        SELECT * FROM layanan 
        WHERE status = 'aktif' 
           OR id IN (SELECT id_layanan FROM detail_transaksi WHERE id_transaksi = ?)
        ORDER BY status ASC, nama_layanan ASC
    ");
    $s_stmt->execute([$id]);
    $services = $s_stmt->fetchAll();
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

<!-- Main Form -->
<form action="transaksi-edit.php?id=<?php echo $id; ?>" method="POST" id="transaction-form">
    <div class="transaction-grid">
        
        <!-- Left column: form inputs -->
        <div style="display: flex; flex-direction: column; gap: 2rem;">
            
            <!-- Customer & Basic info -->
            <div class="glass-panel" style="padding: 2rem;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                    <h3 style="font-size: 1.25rem; font-weight: 700; margin: 0;">
                        <i class="fa-solid fa-user-edit" style="color:var(--primary); margin-right: 0.5rem;"></i>Informasi Pelanggan
                    </h3>
                    <span style="font-weight: 800; color: var(--primary); font-size: 1.1rem; background: var(--bg-hover); padding: 0.25rem 0.75rem; border-radius: var(--radius-sm);">
                        <?php echo htmlspecialchars($transaction['kode_transaksi']); ?>
                    </span>
                </div>
                
                <div class="form-group">
                    <label for="id_pelanggan">Pilih Pelanggan</label>
                    <select name="id_pelanggan" id="id_pelanggan" class="form-control" required>
                        <option value="">-- Pilih Pelanggan --</option>
                        <?php foreach ($customers as $c): ?>
                            <option value="<?php echo $c['id']; ?>" <?php echo $c['id'] == $transaction['id_pelanggan'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($c['nama']); ?> (<?php echo htmlspecialchars($c['telepon']); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- Services selection -->
            <div class="glass-panel" style="padding: 2rem;">
                <h3 style="font-size: 1.25rem; font-weight: 700; margin-bottom: 1.5rem;">
                    <i class="fa-solid fa-sliders" style="color:var(--primary); margin-right: 0.5rem;"></i>Pilih Layanan & Detail (Tambah / Sesuaikan)
                </h3>
                
                <div class="service-selection-list">
                    <?php if (count($services) === 0): ?>
                        <p style="color: var(--text-muted); text-align: center; padding: 1rem;">Tidak ada layanan laundry aktif yang tersedia.</p>
                    <?php else: ?>
                        <?php foreach ($services as $s): ?>
                            <?php 
                            $is_checked = isset($details_map[$s['id']]);
                            $qty_value = $is_checked ? floatval($details_map[$s['id']]['jumlah']) : 1;
                            $disabled_attr = $is_checked ? '' : 'disabled';
                            ?>
                            <div class="service-item-row">
                                <div style="display: flex; align-items: center; gap: 0.75rem;">
                                    <input type="checkbox" name="services[]" value="<?php echo $s['id']; ?>" 
                                           class="service-check-input" id="service-<?php echo $s['id']; ?>"
                                           data-price="<?php echo $s['harga']; ?>" 
                                           data-name="<?php echo htmlspecialchars($s['nama_layanan']); ?>"
                                           data-unit="<?php echo htmlspecialchars($s['tipe_hitung']); ?>"
                                           style="width: 18px; height: 18px; cursor: pointer;"
                                           <?php echo $is_checked ? 'checked' : ''; ?>>
                                    <label for="service-<?php echo $s['id']; ?>" style="cursor: pointer; font-weight: 700; margin-bottom: 0;">
                                        <?php echo htmlspecialchars($s['nama_layanan']); ?>
                                        <?php if ($s['status'] === 'nonaktif'): ?>
                                            <span class="badge badge-secondary" style="font-size: 0.7rem; padding: 0.1rem 0.35rem; margin-left: 0.5rem; background: var(--text-muted); color: #fff;">Nonaktif</span>
                                        <?php endif; ?>
                                    </label>
                                </div>
                                <div style="color: var(--text-muted); font-size: 0.9rem;">
                                    Rp <?php echo number_format($s['harga'], 0, ',', '.'); ?> / <?php echo htmlspecialchars($s['tipe_hitung']); ?>
                                </div>
                                <div style="color: var(--text-muted); font-size: 0.9rem; text-align: center;">
                                    <i class="fa-regular fa-clock"></i> <?php echo htmlspecialchars($s['estimasi']); ?>
                                </div>
                                <div style="display: flex; align-items: center; gap: 0.5rem; justify-content: flex-end;">
                                    <input type="number" name="qty[<?php echo $s['id']; ?>]" value="<?php echo $qty_value; ?>" min="0.1" step="0.1" 
                                           class="form-control service-qty-input" style="width: 70px; padding: 0.35rem; font-size: 0.9rem; text-align: center;" 
                                           <?php echo $disabled_attr; ?>>
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
                <h3 class="summary-title"><i class="fa-solid fa-receipt" style="color:var(--primary); margin-right:0.5rem;"></i>Ringkasan Pembaruan</h3>
                
                <div class="order-summary-list" id="summary-items-list">
                    <!-- Filled dynamically by main.js -->
                    <div class="order-summary-item" style="color: var(--text-muted); font-style: italic;">
                        Pilih minimal satu layanan...
                    </div>
                </div>
                
                <div class="order-summary-item total" style="margin-top: 1.5rem;">
                    <span class="label">Total Estimasi Baru</span>
                    <span class="val" id="summary-total-price">Rp 0</span>
                </div>

                <div style="display: flex; flex-direction: column; gap: 0.75rem; margin-top: 1.5rem;">
                    <button type="submit" class="btn btn-primary" style="width: 100%; justify-content: center; padding: 0.85rem;">
                        <i class="fa-solid fa-floppy-disk"></i> Simpan Perubahan
                    </button>
                    <a href="transaksi.php" class="btn btn-secondary" style="width: 100%; justify-content: center; padding: 0.85rem;">
                        Batal
                    </a>
                </div>
            </div>
        </div>

    </div>
</form>

<?php
require_once 'includes/footer.php';
?>
