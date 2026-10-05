<?php
$page_title = 'Kelola Layanan Laundry';
$page_subtitle = 'Tambah, ubah, dan nonaktifkan layanan laundry Berkat Laundry.';
require_once 'config/database.php';
require_once 'includes/header.php';

// Access Control: Only Admin can access
if ($user_role !== 'admin') {
    echo '<script>window.location.href="dashboard.php";</script>';
    exit();
}

$action = $_GET['action'] ?? 'view';
$id = $_GET['id'] ?? null;
$message = '';
$error = '';

// Handle Actions (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'tambah') {
        $nama_layanan = trim($_POST['nama_layanan']);
        $harga = intval($_POST['harga']);
        $tipe_hitung = trim($_POST['tipe_hitung']);
        $estimasi = trim($_POST['estimasi']);
        $status = $_POST['status'] ?? 'aktif';

        if (!empty($nama_layanan) && $harga > 0 && !empty($tipe_hitung) && !empty($estimasi)) {
            try {
                $stmt = $pdo->prepare("INSERT INTO layanan (nama_layanan, harga, tipe_hitung, estimasi, status) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$nama_layanan, $harga, $tipe_hitung, $estimasi, $status]);
                $message = 'Layanan baru berhasil ditambahkan.';
                $action = 'view';
            } catch (PDOException $e) {
                $error = 'Gagal menambahkan layanan: ' . $e->getMessage();
            }
        } else {
            $error = 'Harap isi semua kolom dengan benar. Harga harus lebih besar dari 0.';
        }
    } elseif ($action === 'edit' && $id) {
        $nama_layanan = trim($_POST['nama_layanan']);
        $harga = intval($_POST['harga']);
        $tipe_hitung = trim($_POST['tipe_hitung']);
        $estimasi = trim($_POST['estimasi']);
        $status = $_POST['status'] ?? 'aktif';

        if (!empty($nama_layanan) && $harga > 0 && !empty($tipe_hitung) && !empty($estimasi)) {
            try {
                $stmt = $pdo->prepare("UPDATE layanan SET nama_layanan = ?, harga = ?, tipe_hitung = ?, estimasi = ?, status = ? WHERE id = ?");
                $stmt->execute([$nama_layanan, $harga, $tipe_hitung, $estimasi, $status, $id]);
                $message = 'Layanan berhasil diperbarui.';
                $action = 'view';
            } catch (PDOException $e) {
                $error = 'Gagal memperbarui layanan: ' . $e->getMessage();
            }
        } else {
            $error = 'Harap isi semua kolom dengan benar. Harga harus lebih besar dari 0.';
        }
    }
}

// Handle Actions (GET Delete)
if ($action === 'hapus' && $id) {
    try {
        // Check if service is used in transaction details
        $check = $pdo->prepare("SELECT COUNT(*) FROM detail_transaksi WHERE id_layanan = ?");
        $check->execute([$id]);
        if ($check->fetchColumn() > 0) {
            // Soft delete: turn to 'nonaktif'
            $stmt = $pdo->prepare("UPDATE layanan SET status = 'nonaktif' WHERE id = ?");
            $stmt->execute([$id]);
            $message = 'Layanan ini sudah memiliki riwayat transaksi, status diubah menjadi "nonaktif".';
        } else {
            // Hard delete
            $stmt = $pdo->prepare("DELETE FROM layanan WHERE id = ?");
            $stmt->execute([$id]);
            $message = 'Layanan berhasil dihapus.';
        }
    } catch (PDOException $e) {
        $error = 'Gagal memproses layanan: ' . $e->getMessage();
    }
    $action = 'view';
}

// Prepare Data for Forms or List
$layanan_data = null;
if ($action === 'edit' && $id) {
    $stmt = $pdo->prepare("SELECT * FROM layanan WHERE id = ?");
    $stmt->execute([$id]);
    $layanan_data = $stmt->fetch();
    if (!$layanan_data) {
        $error = 'Layanan tidak ditemukan.';
        $action = 'view';
    }
}

// Fetch all services
$stmt = $pdo->query("SELECT * FROM layanan ORDER BY status ASC, nama_layanan ASC");
$services = $stmt->fetchAll();
?>

<!-- Alert Box -->
<?php if (!empty($message)): ?>
    <div class="alert alert-success">
      <i class="fa-solid fa-circle-check"></i>
      <span><?php echo htmlspecialchars($message); ?></span>
    </div>
<?php endif; ?>
<?php if (!empty($error)): ?>
    <div class="alert alert-error">
      <i class="fa-solid fa-triangle-exclamation"></i>
      <span><?php echo htmlspecialchars($error); ?></span>
    </div>
<?php endif; ?>

<!-- Sub Page Content -->
<?php if ($action === 'tambah' || $action === 'edit'): ?>
    <!-- FORM TAMBAH / EDIT -->
    <div class="glass-panel" style="max-width: 600px; padding: 2.5rem; margin: 0 auto;">
        <h2 style="font-size: 1.5rem; font-weight: 700; margin-bottom: 1.5rem;">
            <?php echo $action === 'tambah' ? '<i class="fa-solid fa-cart-plus" style="color:var(--primary);"></i> Tambah Layanan Baru' : '<i class="fa-solid fa-pen-to-square" style="color:var(--primary);"></i> Edit Data Layanan'; ?>
        </h2>
        
        <form action="layanan.php?action=<?php echo $action; ?><?php echo $id ? '&id='.$id : ''; ?>" method="POST">
            <div class="form-group">
                <label for="nama_layanan">Nama Layanan</label>
                <input type="text" id="nama_layanan" name="nama_layanan" class="form-control" placeholder="Contoh: Cuci Setrika, Dry Clean Bedcover..." required 
                       value="<?php echo htmlspecialchars($layanan_data['nama_layanan'] ?? ''); ?>">
            </div>

            <div class="layanan-split-grid">
                <div class="form-group">
                    <label for="harga">Harga (Rupiah)</label>
                    <input type="number" id="harga" name="harga" class="form-control" placeholder="Contoh: 7000" min="1" required
                           value="<?php echo htmlspecialchars($layanan_data['harga'] ?? ''); ?>">
                </div>

                <div class="form-group">
                    <label for="tipe_hitung">Tipe Hitungan</label>
                    <select id="tipe_hitung" name="tipe_hitung" class="form-control" required>
                        <option value="kg" <?php echo isset($layanan_data['tipe_hitung']) && $layanan_data['tipe_hitung'] === 'kg' ? 'selected' : ''; ?>>Per kg</option>
                        <option value="pcs" <?php echo isset($layanan_data['tipe_hitung']) && $layanan_data['tipe_hitung'] === 'pcs' ? 'selected' : ''; ?>>Per pcs (Satuan)</option>
                        <option value="meter" <?php echo isset($layanan_data['tipe_hitung']) && $layanan_data['tipe_hitung'] === 'meter' ? 'selected' : ''; ?>>Per meter</option>
                    </select>
                </div>
            </div>

            <div class="layanan-form-grid">
                <div class="form-group">
                    <label for="estimasi">Estimasi Waktu Selesai</label>
                    <input type="text" id="estimasi" name="estimasi" class="form-control" placeholder="Contoh: 2 Hari, 12 Jam..." required
                           value="<?php echo htmlspecialchars($layanan_data['estimasi'] ?? ''); ?>">
                </div>

                <div class="form-group">
                    <label for="status">Status Layanan</label>
                    <select id="status" name="status" class="form-control" required>
                        <option value="aktif" <?php echo isset($layanan_data['status']) && $layanan_data['status'] === 'aktif' ? 'selected' : ''; ?>>Aktif</option>
                        <option value="nonaktif" <?php echo isset($layanan_data['status']) && $layanan_data['status'] === 'nonaktif' ? 'selected' : ''; ?>>Nonaktif</option>
                    </select>
                </div>
            </div>

            <div style="display: flex; gap: 1rem; margin-top: 1.5rem;">
                <a href="layanan.php" class="btn btn-secondary" style="flex: 1; justify-content: center;">Batal</a>
                <button type="submit" class="btn btn-primary" style="flex: 1; justify-content: center;">
                    <i class="fa-solid fa-floppy-disk"></i> Simpan
                </button>
            </div>
        </form>
    </div>

<?php else: ?>
    <!-- TABLE VIEW -->
    <div class="glass-panel table-card">
        <div class="table-header">
            <h3>Daftar Layanan Laundry</h3>
            <a href="layanan.php?action=tambah" class="btn btn-primary" style="padding: 0.5rem 1rem; font-size: 0.85rem;">
                <i class="fa-solid fa-plus"></i> Tambah Layanan
            </a>
        </div>

        <div style="overflow-x: auto;">
            <table>
                <thead>
                    <tr>
                        <th style="width: 50px;">No</th>
                        <th>Nama Layanan</th>
                        <th>Harga</th>
                        <th>Tipe Hitung</th>
                        <th>Estimasi Selesai</th>
                        <th>Status</th>
                        <th style="width: 120px; text-align: center;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($services) === 0): ?>
                        <tr>
                            <td colspan="7" style="text-align: center; color: var(--text-muted); padding: 2rem;">Belum ada layanan laundry.</td>
                        </tr>
                    <?php else: ?>
                        <?php $no = 1; foreach ($services as $row): ?>
                            <tr>
                                <td><?php echo $no++; ?></td>
                                <td><strong><?php echo htmlspecialchars($row['nama_layanan']); ?></strong></td>
                                <td style="font-weight: 600; color: var(--primary);">Rp <?php echo number_format($row['harga'], 0, ',', '.'); ?></td>
                                <td><span class="badge badge-secondary"><?php echo htmlspecialchars($row['tipe_hitung']); ?></span></td>
                                <td><?php echo htmlspecialchars($row['estimasi']); ?></td>
                                <td>
                                    <?php if ($row['status'] === 'aktif'): ?>
                                        <span class="badge badge-success">Aktif</span>
                                    <?php else: ?>
                                        <span class="badge badge-danger">Nonaktif</span>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align: center;">
                                    <div style="display: flex; gap: 0.5rem; justify-content: center;">
                                        <a href="layanan.php?action=edit&id=<?php echo $row['id']; ?>" class="btn btn-secondary" 
                                           style="padding: 0.4rem; font-size: 0.85rem; border-radius: var(--radius-sm);" title="Edit">
                                            <i class="fa-solid fa-pen-to-square" style="color: var(--primary);"></i>
                                        </a>
                                        <a href="layanan.php?action=hapus&id=<?php echo $row['id']; ?>" class="btn btn-secondary" 
                                           style="padding: 0.4rem; font-size: 0.85rem; border-radius: var(--radius-sm);" title="Hapus / Nonaktifkan"
                                           onclick="return confirm('Apakah Anda yakin ingin menghapus/menonaktifkan layanan ini? Jika layanan memiliki riwayat transaksi, ia akan dinonaktifkan secara aman.');">
                                            <i class="fa-solid fa-trash-can" style="color: var(--accent);"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<?php
require_once 'includes/footer.php';
?>
