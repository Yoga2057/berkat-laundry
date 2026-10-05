<?php
$page_title = 'Kelola Pelanggan';
$page_subtitle = 'Tambah, ubah, dan hapus data pelanggan Berkat Laundry.';
require_once 'config/database.php';
require_once 'includes/header.php';

// Access Control: Admin and Karyawan can access
if ($user_role !== 'admin' && $user_role !== 'karyawan') {
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
        $nama = trim($_POST['nama']);
        $alamat = trim($_POST['alamat']);
        $telepon = trim($_POST['telepon']);

        if (!empty($nama) && !empty($alamat) && !empty($telepon)) {
            try {
                $stmt = $pdo->prepare("INSERT INTO pelanggan (nama, alamat, telepon) VALUES (?, ?, ?)");
                $stmt->execute([$nama, $alamat, $telepon]);
                $message = 'Pelanggan berhasil ditambahkan.';
                $action = 'view'; // Go back to list
            } catch (PDOException $e) {
                $error = 'Gagal menambahkan pelanggan: ' . $e->getMessage();
            }
        } else {
            $error = 'Semua kolom wajib diisi.';
        }
    } elseif ($action === 'edit' && $id) {
        $nama = trim($_POST['nama']);
        $alamat = trim($_POST['alamat']);
        $telepon = trim($_POST['telepon']);

        if (!empty($nama) && !empty($alamat) && !empty($telepon)) {
            try {
                $stmt = $pdo->prepare("UPDATE pelanggan SET nama = ?, alamat = ?, telepon = ? WHERE id = ?");
                $stmt->execute([$nama, $alamat, $telepon, $id]);
                $message = 'Data pelanggan berhasil diperbarui.';
                $action = 'view';
            } catch (PDOException $e) {
                $error = 'Gagal memperbarui data pelanggan: ' . $e->getMessage();
            }
        } else {
            $error = 'Semua kolom wajib diisi.';
        }
    }
}

// Handle Actions (GET Delete)
if ($action === 'hapus' && $id) {
    try {
        // Check if customer is used in transactions
        $check = $pdo->prepare("SELECT COUNT(*) FROM transaksi WHERE id_pelanggan = ?");
        $check->execute([$id]);
        if ($check->fetchColumn() > 0) {
            $error = 'Pelanggan ini tidak dapat dihapus karena memiliki riwayat transaksi.';
        } else {
            $stmt = $pdo->prepare("DELETE FROM pelanggan WHERE id = ?");
            $stmt->execute([$id]);
            $message = 'Pelanggan berhasil dihapus.';
        }
    } catch (PDOException $e) {
        $error = 'Gagal menghapus pelanggan: ' . $e->getMessage();
    }
    $action = 'view';
}

// Prepare Data for Forms or List
$customer_data = null;
if ($action === 'edit' && $id) {
    $stmt = $pdo->prepare("SELECT * FROM pelanggan WHERE id = ?");
    $stmt->execute([$id]);
    $customer_data = $stmt->fetch();
    if (!$customer_data) {
        $error = 'Data pelanggan tidak ditemukan.';
        $action = 'view';
    }
}

// Fetch all customers for table
$search = $_GET['search'] ?? '';
if (!empty($search)) {
    $stmt = $pdo->prepare("SELECT * FROM pelanggan WHERE nama LIKE ? OR alamat LIKE ? OR telepon LIKE ? ORDER BY nama ASC");
    $stmt->execute(["%$search%", "%$search%", "%$search%"]);
} else {
    $stmt = $pdo->query("SELECT * FROM pelanggan ORDER BY nama ASC");
}
$customers = $stmt->fetchAll();
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

<!-- Sub Page content -->
<?php if ($action === 'tambah' || $action === 'edit'): ?>
    <!-- FORM TAMBAH / EDIT -->
    <div class="glass-panel" style="max-width: 600px; padding: 2.5rem; margin: 0 auto;">
        <h2 style="font-size: 1.5rem; font-weight: 700; margin-bottom: 1.5rem;">
            <?php echo $action === 'tambah' ? '<i class="fa-solid fa-user-plus" style="color:var(--primary);"></i> Tambah Pelanggan Baru' : '<i class="fa-solid fa-user-pen" style="color:var(--primary);"></i> Edit Data Pelanggan'; ?>
        </h2>
        
        <form action="pelanggan.php?action=<?php echo $action; ?><?php echo $id ? '&id='.$id : ''; ?>" method="POST">
            <div class="form-group">
                <label for="nama">Nama Lengkap</label>
                <input type="text" id="nama" name="nama" class="form-control" placeholder="Nama pelanggan..." required 
                       value="<?php echo htmlspecialchars($customer_data['nama'] ?? ''); ?>">
            </div>

            <div class="form-group">
                <label for="telepon">Nomor WhatsApp / Telepon</label>
                <input type="tel" id="telepon" name="telepon" class="form-control" placeholder="Contoh: 081234567890" required
                       value="<?php echo htmlspecialchars($customer_data['telepon'] ?? ''); ?>">
            </div>

            <div class="form-group" style="margin-bottom: 2rem;">
                <label for="alamat">Alamat Lengkap</label>
                <textarea id="alamat" name="alamat" class="form-control" style="height: 100px; resize: vertical;" placeholder="Alamat lengkap..." required><?php echo htmlspecialchars($customer_data['alamat'] ?? ''); ?></textarea>
            </div>

            <div style="display: flex; gap: 1rem;">
                <a href="pelanggan.php" class="btn btn-secondary" style="flex: 1; justify-content: center;">Batal</a>
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
            <div style="display:flex; align-items:center; gap: 1rem;">
                <h3>Daftar Pelanggan</h3>
                <a href="pelanggan.php?action=tambah" class="btn btn-primary" style="padding: 0.5rem 1rem; font-size: 0.85rem;">
                    <i class="fa-solid fa-plus"></i> Tambah Pelanggan
                </a>
            </div>
            
            <!-- Search bar -->
            <form action="pelanggan.php" method="GET" style="display: flex; gap: 0.5rem;">
                <input type="text" name="search" class="form-control" style="width: 240px; padding: 0.5rem 1rem; font-size: 0.85rem;" 
                       placeholder="Cari pelanggan..." value="<?php echo htmlspecialchars($search); ?>">
                <button type="submit" class="btn btn-secondary" style="padding: 0.5rem 1rem; font-size: 0.85rem;">
                    <i class="fa-solid fa-search"></i>
                </button>
                <?php if (!empty($search)): ?>
                    <a href="pelanggan.php" class="btn btn-secondary" style="padding: 0.5rem 1rem; font-size: 0.85rem; color: var(--accent);">Clear</a>
                <?php endif; ?>
            </form>
        </div>

        <div style="overflow-x: auto;">
            <table>
                <thead>
                    <tr>
                        <th style="width: 50px;">No</th>
                        <th>Nama Lengkap</th>
                        <th>WhatsApp</th>
                        <th>Alamat</th>
                        <th style="width: 120px; text-align: center;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($customers) === 0): ?>
                        <tr>
                            <td colspan="5" style="text-align: center; color: var(--text-muted); padding: 2rem;">Tidak ada data pelanggan.</td>
                        </tr>
                    <?php else: ?>
                        <?php $no = 1; foreach ($customers as $row): ?>
                            <tr>
                                <td><?php echo $no++; ?></td>
                                <td><strong><?php echo htmlspecialchars($row['nama']); ?></strong></td>
                                <td>
                                    <a href="https://wa.me/<?php echo preg_replace('/[^0-9]/', '', $row['telepon']); ?>" target="_blank" 
                                       style="text-decoration:none; color: var(--success); font-weight:600; display:flex; align-items:center; gap:0.25rem;">
                                        <i class="fa-brands fa-whatsapp"></i> <?php echo htmlspecialchars($row['telepon']); ?>
                                    </a>
                                </td>
                                <td style="color: var(--text-muted);"><?php echo htmlspecialchars($row['alamat']); ?></td>
                                <td style="text-align: center;">
                                    <div style="display: flex; gap: 0.5rem; justify-content: center;">
                                        <a href="pelanggan.php?action=edit&id=<?php echo $row['id']; ?>" class="btn btn-secondary" 
                                           style="padding: 0.4rem; font-size: 0.85rem; border-radius: var(--radius-sm);" title="Edit">
                                            <i class="fa-solid fa-user-pen" style="color: var(--primary);"></i>
                                        </a>
                                        <a href="pelanggan.php?action=hapus&id=<?php echo $row['id']; ?>" class="btn btn-secondary" 
                                           style="padding: 0.4rem; font-size: 0.85rem; border-radius: var(--radius-sm);" title="Hapus"
                                           onclick="return confirm('Apakah Anda yakin ingin menghapus pelanggan ini?');">
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
