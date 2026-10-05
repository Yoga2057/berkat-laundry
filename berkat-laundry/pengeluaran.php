<?php
$page_title = 'Kelola Biaya Operasional';
$page_subtitle = 'Catat dan pantau pengeluaran operasional laundry (deterjen, listrik, dll).';
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
        $tgl_pengeluaran = $_POST['tgl_pengeluaran'];
        $keterangan = trim($_POST['keterangan']);
        $jumlah = intval($_POST['jumlah']);
        $id_user = $_SESSION['user_id'];

        if (!empty($tgl_pengeluaran) && !empty($keterangan) && $jumlah > 0) {
            try {
                $stmt = $pdo->prepare("INSERT INTO pengeluaran (id_user, tgl_pengeluaran, keterangan, jumlah) VALUES (?, ?, ?, ?)");
                $stmt->execute([$id_user, $tgl_pengeluaran, $keterangan, $jumlah]);
                $message = 'Catatan pengeluaran berhasil disimpan.';
                $action = 'view';
            } catch (PDOException $e) {
                $error = 'Gagal menyimpan pengeluaran: ' . $e->getMessage();
            }
        } else {
            $error = 'Harap isi semua kolom dengan benar. Jumlah pengeluaran harus lebih besar dari 0.';
        }
    } elseif ($action === 'edit' && $id) {
        $tgl_pengeluaran = $_POST['tgl_pengeluaran'];
        $keterangan = trim($_POST['keterangan']);
        $jumlah = intval($_POST['jumlah']);

        if (!empty($tgl_pengeluaran) && !empty($keterangan) && $jumlah > 0) {
            try {
                $stmt = $pdo->prepare("UPDATE pengeluaran SET tgl_pengeluaran = ?, keterangan = ?, jumlah = ? WHERE id = ?");
                $stmt->execute([$tgl_pengeluaran, $keterangan, $jumlah, $id]);
                $message = 'Catatan pengeluaran berhasil diperbarui.';
                $action = 'view';
            } catch (PDOException $e) {
                $error = 'Gagal memperbarui pengeluaran: ' . $e->getMessage();
            }
        } else {
            $error = 'Harap isi semua kolom dengan benar. Jumlah pengeluaran harus lebih besar dari 0.';
        }
    }
}

// Handle Actions (GET Delete)
if ($action === 'hapus' && $id) {
    try {
        $stmt = $pdo->prepare("DELETE FROM pengeluaran WHERE id = ?");
        $stmt->execute([$id]);
        $message = 'Catatan pengeluaran berhasil dihapus.';
    } catch (PDOException $e) {
        $error = 'Gagal menghapus catatan pengeluaran: ' . $e->getMessage();
    }
    $action = 'view';
}

// Prepare Data for Forms or List
$pengeluaran_data = null;
if ($action === 'edit' && $id) {
    $stmt = $pdo->prepare("SELECT * FROM pengeluaran WHERE id = ?");
    $stmt->execute([$id]);
    $pengeluaran_data = $stmt->fetch();
    if (!$pengeluaran_data) {
        $error = 'Data pengeluaran tidak ditemukan.';
        $action = 'view';
    }
}

// Fetch all expenses with user audit info
try {
    $stmt = $pdo->query("SELECT pg.*, u.nama AS nama_user 
                         FROM pengeluaran pg 
                         JOIN users u ON pg.id_user = u.id 
                         ORDER BY pg.tgl_pengeluaran DESC");
    $expenses = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = 'Gagal mengambil data pengeluaran: ' . $e->getMessage();
    $expenses = [];
}
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
            <?php echo $action === 'tambah' ? '<i class="fa-solid fa-file-invoice-dollar" style="color:var(--primary);"></i> Catat Pengeluaran Baru' : '<i class="fa-solid fa-pen-to-square" style="color:var(--primary);"></i> Edit Data Pengeluaran'; ?>
        </h2>
        
        <form action="pengeluaran.php?action=<?php echo $action; ?><?php echo $id ? '&id='.$id : ''; ?>" method="POST">
            <div class="form-group">
                <label for="tgl_pengeluaran">Tanggal Pengeluaran</label>
                <input type="date" id="tgl_pengeluaran" name="tgl_pengeluaran" class="form-control" required 
                       value="<?php echo htmlspecialchars($pengeluaran_data['tgl_pengeluaran'] ?? date('Y-m-d')); ?>">
            </div>

            <div class="form-group">
                <label for="keterangan">Keterangan / Deskripsi Biaya</label>
                <input type="text" id="keterangan" name="keterangan" class="form-control" placeholder="Contoh: Pembelian detergen liquid 5L, Biaya listrik..." required 
                       value="<?php echo htmlspecialchars($pengeluaran_data['keterangan'] ?? ''); ?>">
            </div>

            <div class="form-group" style="margin-bottom: 2rem;">
                <label for="jumlah">Jumlah Pengeluaran (Rupiah)</label>
                <input type="number" id="jumlah" name="jumlah" class="form-control" placeholder="Contoh: 75000" min="1" required
                       value="<?php echo htmlspecialchars($pengeluaran_data['jumlah'] ?? ''); ?>">
            </div>

            <div style="display: flex; gap: 1rem;">
                <a href="pengeluaran.php" class="btn btn-secondary" style="flex: 1; justify-content: center;">Batal</a>
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
            <h3>Daftar Pengeluaran Operasional</h3>
            <a href="pengeluaran.php?action=tambah" class="btn btn-primary" style="padding: 0.5rem 1rem; font-size: 0.85rem;">
                <i class="fa-solid fa-plus"></i> Catat Pengeluaran
            </a>
        </div>

        <div style="overflow-x: auto;">
            <table>
                <thead>
                    <tr>
                        <th style="width: 50px;">No</th>
                        <th>Tanggal</th>
                        <th>Keterangan Pengeluaran</th>
                        <th>Dicatat Oleh</th>
                        <th>Jumlah Biaya</th>
                        <th style="width: 120px; text-align: center;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($expenses) === 0): ?>
                        <tr>
                            <td colspan="6" style="text-align: center; color: var(--text-muted); padding: 2rem;">Belum ada catatan pengeluaran operasional.</td>
                        </tr>
                    <?php else: ?>
                        <?php $no = 1; foreach ($expenses as $row): ?>
                            <tr>
                                <td><?php echo $no++; ?></td>
                                <td><strong><?php echo date('d M Y', strtotime($row['tgl_pengeluaran'])); ?></strong></td>
                                <td><?php echo htmlspecialchars($row['keterangan']); ?></td>
                                <td style="font-size: 0.85rem; color: var(--text-muted);"><?php echo htmlspecialchars($row['nama_user']); ?></td>
                                <td style="font-weight: 700; color: var(--accent);">Rp <?php echo number_format($row['jumlah'], 0, ',', '.'); ?></td>
                                <td style="text-align: center;">
                                    <div style="display: flex; gap: 0.5rem; justify-content: center;">
                                        <a href="pengeluaran.php?action=edit&id=<?php echo $row['id']; ?>" class="btn btn-secondary" 
                                           style="padding: 0.4rem; font-size: 0.85rem; border-radius: var(--radius-sm);" title="Edit">
                                            <i class="fa-solid fa-pen-to-square" style="color: var(--primary);"></i>
                                        </a>
                                        <a href="pengeluaran.php?action=hapus&id=<?php echo $row['id']; ?>" class="btn btn-secondary" 
                                           style="padding: 0.4rem; font-size: 0.85rem; border-radius: var(--radius-sm);" title="Hapus"
                                           onclick="return confirm('Apakah Anda yakin ingin menghapus catatan pengeluaran ini?');">
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
