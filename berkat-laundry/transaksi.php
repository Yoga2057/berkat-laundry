<?php
$page_title = 'Transaksi Laundry';
$page_subtitle = 'Kelola pesanan cucian, pembayaran, dan pengambilan laundry.';
require_once 'config/database.php';
require_once 'includes/header.php';

// Dynamic Base URL for sharing via Mobile / Whatsapp
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
$host = $_SERVER['HTTP_HOST'];
$base_dir = str_replace('\\', '/', dirname($_SERVER['PHP_SELF']));
if ($base_dir !== '/') {
    $base_dir = rtrim($base_dir, '/') . '/';
}
$base_url = $protocol . "://" . $host . $base_dir;

$status_proses_indo = [
    'received'  => 'Diterima',
    'washing'   => 'Dicuci',
    'drying'    => 'Dikeringkan',
    'ironing'   => 'Disetrika',
    'ready'     => 'Siap Diambil',
    'completed' => 'Selesai'
];

$status_bayar_indo = [
    'belum_bayar' => 'Belum Lunas ❌',
    'lunas'       => 'Lunas (Sudah Dibayar)  '
];

$message = '';
$error = '';

// Handle status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $id_transaksi = intval($_POST['id_transaksi']);
    
    try {
        // Fetch current statuses of the transaction for validation
        $val_stmt = $pdo->prepare("SELECT status_proses, status_pembayaran, status_pengambilan FROM transaksi WHERE id = ?");
        $val_stmt->execute([$id_transaksi]);
        $current_trx = $val_stmt->fetch();
        
        if ($current_trx) {
            $proposed_proses = isset($_POST['status_proses']) ? $_POST['status_proses'] : $current_trx['status_proses'];
            $proposed_pembayaran = isset($_POST['status_pembayaran']) ? $_POST['status_pembayaran'] : $current_trx['status_pembayaran'];
            $proposed_pengambilan = isset($_POST['status_pengambilan']) ? $_POST['status_pengambilan'] : $current_trx['status_pengambilan'];
            
            $isValid = true;
            if ($proposed_pembayaran === 'lunas' && $proposed_proses !== 'completed') {
                $isValid = false;
                if (isset($_POST['status_pembayaran'])) {
                    $error = "Status pembayaran hanya bisa diubah menjadi 'Lunas' apabila status pengerjaan sudah selesai.";
                } elseif (isset($_POST['status_proses'])) {
                    $error = "Status pengerjaan tidak bisa diubah dari 'Selesai' karena status pembayaran sudah lunas.";
                } else {
                    $error = "Status pengerjaan belum selesai, sehingga status pembayaran harus belum lunas.";
                }
            } elseif ($proposed_pengambilan === 'diambil') {
                if ($proposed_pembayaran !== 'lunas') {
                    $isValid = false;
                    if (isset($_POST['status_pengambilan'])) {
                        $error = "Status pengambilan tidak bisa diubah menjadi 'Sudah Diambil' karena status pembayaran belum lunas.";
                    } elseif (isset($_POST['status_pembayaran'])) {
                        $error = "Status pembayaran tidak bisa diubah menjadi 'Belum Bayar' karena cucian sudah diambil.";
                    } else {
                        $error = "Status pengambilan sudah diambil, sehingga status pembayaran harus lunas.";
                    }
                } elseif ($proposed_proses !== 'completed') {
                    $isValid = false;
                    if (isset($_POST['status_pengambilan'])) {
                        $error = "Status pengambilan hanya boleh diubah menjadi 'Sudah Diambil' apabila status pengerjaan sudah selesai.";
                    } elseif (isset($_POST['status_proses'])) {
                        $error = "Status pengerjaan tidak bisa diubah karena cucian sudah diambil.";
                    } else {
                        $error = "Status pengambilan sudah diambil, sehingga status pengerjaan harus selesai.";
                    }
                }
            }
            
            if ($isValid) {
                if (isset($_POST['status_proses'])) {
                    $status_proses = $_POST['status_proses'];
                    // If process is completed, set completed date
                    if ($status_proses === 'completed') {
                        $stmt = $pdo->prepare("UPDATE transaksi SET status_proses = ?, tgl_selesai = NOW() WHERE id = ?");
                    } else {
                        $stmt = $pdo->prepare("UPDATE transaksi SET status_proses = ? WHERE id = ?");
                    }
                    $stmt->execute([$status_proses, $id_transaksi]);
                    $message = 'Status proses transaksi berhasil diperbarui.';
                }
                
                if (isset($_POST['status_pembayaran'])) {
                    $status_pembayaran = $_POST['status_pembayaran'];
                    $stmt = $pdo->prepare("UPDATE transaksi SET status_pembayaran = ? WHERE id = ?");
                    $stmt->execute([$status_pembayaran, $id_transaksi]);
                    $message = 'Status pembayaran transaksi berhasil diperbarui.';
                }
                
                if (isset($_POST['status_pengambilan'])) {
                    $status_pengambilan = $_POST['status_pengambilan'];
                    $stmt = $pdo->prepare("UPDATE transaksi SET status_pengambilan = ? WHERE id = ?");
                    $stmt->execute([$status_pengambilan, $id_transaksi]);
                    $message = 'Status pengambilan transaksi berhasil diperbarui.';
                }
            }
        } else {
            $error = 'Transaksi tidak ditemukan.';
        }
    } catch (PDOException $e) {
        $error = 'Gagal memperbarui status: ' . $e->getMessage();
    }
}

// Handle Delete (Admin only)
if (isset($_GET['action']) && $_GET['action'] === 'hapus' && isset($_GET['id'])) {
    if ($user_role !== 'admin') {
        $error = 'Anda tidak memiliki hak untuk menghapus transaksi.';
    } else {
        $id_transaksi = intval($_GET['id']);
        try {
            // Cascade delete will handle detail_transaksi automatically due to FK constraints
            $stmt = $pdo->prepare("DELETE FROM transaksi WHERE id = ?");
            $stmt->execute([$id_transaksi]);
            $message = 'Transaksi berhasil dihapus.';
        } catch (PDOException $e) {
            $error = 'Gagal menghapus transaksi: ' . $e->getMessage();
        }
    }
}

// Filters and Search
$search = $_GET['search'] ?? '';
$filter_proses = $_GET['filter_proses'] ?? '';
$filter_bayar = $_GET['filter_bayar'] ?? '';

// Build query
$query = "SELECT t.*, p.nama AS nama_pelanggan, p.telepon AS telp_pelanggan, u.nama AS nama_petugas
          FROM transaksi t
          JOIN pelanggan p ON t.id_pelanggan = p.id
          JOIN users u ON t.id_user = u.id";

$params = [];
$where_clauses = [];

if (!empty($search)) {
    $where_clauses[] = "(t.kode_transaksi LIKE ? OR p.nama LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($filter_proses)) {
    $where_clauses[] = "t.status_proses = ?";
    $params[] = $filter_proses;
}

if (!empty($filter_bayar)) {
    $where_clauses[] = "t.status_pembayaran = ?";
    $params[] = $filter_bayar;
}

if (count($where_clauses) > 0) {
    $query .= " WHERE " . implode(" AND ", $where_clauses);
}

$query .= " ORDER BY t.tgl_masuk DESC";

try {
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $transactions = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = 'Gagal mengambil data transaksi: ' . $e->getMessage();
    $transactions = [];
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

<!-- Main Table Container -->
<div class="glass-panel table-card">
    <div class="table-header" style="flex-wrap: wrap; gap: 1.5rem;">
        <div style="display:flex; align-items:center; gap: 1rem;">
            <h3>Daftar Transaksi Laundry</h3>
            <?php if ($user_role === 'admin' || $user_role === 'karyawan'): ?>
                <a href="transaksi-tambah.php" class="btn btn-primary" style="padding: 0.5rem 1rem; font-size: 0.85rem;">
                    <i class="fa-solid fa-cart-plus"></i> Catat Transaksi Baru
                </a>
            <?php endif; ?>
        </div>
        
        <!-- Filters Form -->
        <form action="transaksi.php" method="GET" style="display: flex; gap: 0.75rem; flex-wrap: wrap;">
            <!-- Search -->
            <input type="text" name="search" class="form-control" style="width: 200px; padding: 0.5rem 0.75rem; font-size: 0.85rem;" 
                   placeholder="Kode / nama pelanggan..." value="<?php echo htmlspecialchars($search); ?>">
            
            <!-- Process Status -->
            <select name="filter_proses" class="form-control" style="width: 140px; padding: 0.5rem; font-size: 0.85rem;">
                <option value="">-- Status Proses --</option>
                <option value="received" <?php echo $filter_proses === 'received' ? 'selected' : ''; ?>>Diterima</option>
                <option value="washing" <?php echo $filter_proses === 'washing' ? 'selected' : ''; ?>>Dicuci</option>
                <option value="drying" <?php echo $filter_proses === 'drying' ? 'selected' : ''; ?>>Dikeringkan</option>
                <option value="ironing" <?php echo $filter_proses === 'ironing' ? 'selected' : ''; ?>>Disetrika</option>
                <option value="ready" <?php echo $filter_proses === 'ready' ? 'selected' : ''; ?>>Siap Diambil</option>
                <option value="completed" <?php echo $filter_proses === 'completed' ? 'selected' : ''; ?>>Selesai</option>
            </select>

            <!-- Payment Status -->
            <select name="filter_bayar" class="form-control" style="width: 140px; padding: 0.5rem; font-size: 0.85rem;">
                <option value="">-- Status Bayar --</option>
                <option value="belum_bayar" <?php echo $filter_bayar === 'belum_bayar' ? 'selected' : ''; ?>>Belum Bayar</option>
                <option value="lunas" <?php echo $filter_bayar === 'lunas' ? 'selected' : ''; ?>>Lunas</option>
            </select>

            <button type="submit" class="btn btn-secondary" style="padding: 0.5rem 1rem; font-size: 0.85rem;">
                <i class="fa-solid fa-filter"></i> Filter
            </button>
            
            <?php if (!empty($search) || !empty($filter_proses) || !empty($filter_bayar)): ?>
                <a href="transaksi.php" class="btn btn-secondary" style="padding: 0.5rem 1rem; font-size: 0.85rem; color: var(--accent);">Clear</a>
            <?php endif; ?>
        </form>
    </div>

    <div style="overflow-x: auto;">
        <table>
            <thead>
                <tr>
                    <th>Kode</th>
                    <th>Pelanggan</th>
                    <th>WhatsApp</th>
                    <th>Tgl Masuk</th>
                    <th>Total Bayar</th>
                    <th>Status Proses</th>
                    <th>Pembayaran</th>
                    <th>Pengambilan</th>
                    <th style="width: 120px; text-align: center;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($transactions) === 0): ?>
                    <tr>
                        <td colspan="9" style="text-align: center; color: var(--text-muted); padding: 2rem;">Tidak ada transaksi laundry ditemukan.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($transactions as $row): ?>
                        <tr>
                            <!-- Code -->
                            <td style="font-weight: 800; color: var(--primary);">
                                <?php echo htmlspecialchars($row['kode_transaksi']); ?>
                            </td>
                            
                            <!-- Customer -->
                            <td>
                                <div><strong><?php echo htmlspecialchars($row['nama_pelanggan']); ?></strong></div>
                                <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.1rem;">Petugas: <?php echo htmlspecialchars($row['nama_petugas']); ?></div>
                            </td>
                            
                            <!-- Phone -->
                            <td>
                                <?php
                                $cust_name = $row['nama_pelanggan'];
                                $trx_code = $row['kode_transaksi'];
                                $total_bayar = number_format($row['total_bayar'], 0, ',', '.');
                                $proc_status = $status_proses_indo[$row['status_proses']] ?? $row['status_proses'];
                                $pay_status = $status_bayar_indo[$row['status_pembayaran']] ?? $row['status_pembayaran'];

                                $wa_message = "Halo *{$cust_name}*,\n\nTerima kasih telah menggunakan *Berkat Laundry*.\nBerikut rincian nota transaksi Anda:\n"
                                            . "- *No. Nota:* {$trx_code}\n"
                                            . "- *Status Proses:* {$proc_status}\n"
                                            . "- *Pembayaran:* {$pay_status}\n"
                                            . "- *Total Biaya:* Rp {$total_bayar}\n\n"
                                            . "Anda dapat memantau status cucian dan melihat nota digital Anda secara online melalui tautan berikut:\n"
                                            . "{$base_url}nota-publik.php?code={$trx_code}\n\n"
                                            . "Cucian bersih, rapi, dan wangi adalah prioritas kami. ✨";
                                ?>
                                <a href="https://wa.me/<?php echo preg_replace('/[^0-9]/', '', $row['telp_pelanggan']); ?>?text=<?php echo rawurlencode($wa_message); ?>" target="_blank" 
                                   style="text-decoration:none; color: var(--success); font-weight:600; display:flex; align-items:center; gap:0.25rem; font-size:0.85rem;"
                                   title="Kirim Nota via WhatsApp">
                                    <i class="fa-brands fa-whatsapp"></i> <?php echo htmlspecialchars($row['telp_pelanggan']); ?>
                                </a>
                            </td>
                            
                            <!-- In Date -->
                            <td style="font-size: 0.85rem; color: var(--text-muted);">
                                <?php echo date('d M Y H:i', strtotime($row['tgl_masuk'])); ?>
                            </td>
                            
                            <!-- Total price -->
                            <td style="font-weight: 700;">
                                Rp <?php echo number_format($row['total_bayar'], 0, ',', '.'); ?>
                            </td>
                                                     <!-- Process Status -->
                            <td>
                                <form action="transaksi.php" method="POST" style="margin: 0;">
                                    <input type="hidden" name="update_status" value="1">
                                    <input type="hidden" name="id_transaksi" value="<?php echo $row['id']; ?>">
                                    <select name="status_proses" class="table-action-select" onchange="validateAndSubmitStatus(this)" <?php echo $row['status_pengambilan'] === 'diambil' ? 'disabled' : ''; ?>>
                                        <option value="received" <?php echo $row['status_proses'] === 'received' ? 'selected' : ''; ?>>Diterima</option>
                                        <option value="washing" <?php echo $row['status_proses'] === 'washing' ? 'selected' : ''; ?>>Dicuci</option>
                                        <option value="drying" <?php echo $row['status_proses'] === 'drying' ? 'selected' : ''; ?>>Dikeringkan</option>
                                        <option value="ironing" <?php echo $row['status_proses'] === 'ironing' ? 'selected' : ''; ?>>Disetrika</option>
                                        <option value="ready" <?php echo $row['status_proses'] === 'ready' ? 'selected' : ''; ?>>Siap Diambil</option>
                                        <option value="completed" <?php echo $row['status_proses'] === 'completed' ? 'selected' : ''; ?>>Selesai</option>
                                    </select>
                                </form>
                            </td>

                            <!-- Payment status -->
                            <td>
                                <form action="transaksi.php" method="POST" style="margin: 0;">
                                    <input type="hidden" name="update_status" value="1">
                                    <input type="hidden" name="id_transaksi" value="<?php echo $row['id']; ?>">
                                    <select name="status_pembayaran" class="table-action-select" onchange="validateAndSubmitStatus(this)" <?php echo ($row['status_pengambilan'] === 'diambil' || $row['status_proses'] !== 'completed') ? 'disabled' : ''; ?>
                                             style="font-weight: 700; color: <?php echo $row['status_pembayaran'] === 'lunas' ? 'var(--success)' : 'var(--accent)'; ?>;">
                                        <option value="belum_bayar" <?php echo $row['status_pembayaran'] === 'belum_bayar' ? 'selected' : ''; ?>>Belum Bayar</option>
                                        <option value="lunas" <?php echo $row['status_pembayaran'] === 'lunas' ? 'selected' : ''; ?>>Lunas</option>
                                    </select>
                                </form>
                            </td>

                            <!-- Collection status -->
                            <td>
                                <form action="transaksi.php" method="POST" style="margin: 0;">
                                    <input type="hidden" name="update_status" value="1">
                                    <input type="hidden" name="id_transaksi" value="<?php echo $row['id']; ?>">
                                    <select name="status_pengambilan" class="table-action-select" onchange="validateAndSubmitStatus(this)" <?php echo (($row['status_proses'] === 'completed' && $row['status_pembayaran'] === 'lunas') || $row['status_pengambilan'] === 'diambil') ? '' : 'disabled'; ?>
                                             style="font-weight: 700; color: <?php echo $row['status_pengambilan'] === 'diambil' ? 'var(--success)' : 'var(--warning)'; ?>;">
                                        <option value="belum_diambil" <?php echo $row['status_pengambilan'] === 'belum_diambil' ? 'selected' : ''; ?>>Belum Diambil</option>
                                        <option value="diambil" <?php echo $row['status_pengambilan'] === 'diambil' ? 'selected' : ''; ?>>Sudah Diambil</option>
                                    </select>
                                </form>
                             </td>
                            
                            <!-- Actions -->
                            <td style="text-align: center;">
                                <div style="display: flex; gap: 0.5rem; justify-content: center;">
                                    <a href="transaksi-nota.php?id=<?php echo $row['id']; ?>" class="btn btn-secondary" 
                                       style="padding: 0.4rem; font-size: 0.85rem; border-radius: var(--radius-sm);" title="Cetak Nota">
                                        <i class="fa-solid fa-receipt" style="color: var(--primary);"></i>
                                    </a>
                                    <?php if ($user_role === 'admin' || $user_role === 'karyawan'): ?>
                                        <a href="transaksi-edit.php?id=<?php echo $row['id']; ?>" class="btn btn-secondary" 
                                           style="padding: 0.4rem; font-size: 0.85rem; border-radius: var(--radius-sm);" title="Edit / Tambah Layanan">
                                            <i class="fa-solid fa-pen-to-square" style="color: var(--warning);"></i>
                                        </a>
                                    <?php endif; ?>
                                    <?php if ($user_role === 'admin'): ?>
                                        <a href="transaksi.php?action=hapus&id=<?php echo $row['id']; ?>" class="btn btn-secondary" 
                                           style="padding: 0.4rem; font-size: 0.85rem; border-radius: var(--radius-sm);" title="Hapus"
                                           onclick="return confirm('Apakah Anda yakin ingin menghapus transaksi ini? Data rincian juga akan dihapus.');">
                                            <i class="fa-solid fa-trash-can" style="color: var(--accent);"></i>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
function validateAndSubmitStatus(selectElement) {
    const row = selectElement.closest("tr");
    if (!row) return;

    const prosesSelect = row.querySelector("select[name='status_proses']");
    const pembayaranSelect = row.querySelector("select[name='status_pembayaran']");
    const pengambilanSelect = row.querySelector("select[name='status_pengambilan']");

    if (!prosesSelect || !pembayaranSelect || !pengambilanSelect) return;

    const proses = prosesSelect.value;
    const pembayaran = pembayaranSelect.value;
    const pengambilan = pengambilanSelect.value;

    let isValid = true;
    let errorMessage = "";

    if (pembayaran === "lunas" && proses !== "completed") {
        isValid = false;
        if (selectElement === pembayaranSelect) {
            errorMessage = "Status pembayaran hanya bisa diubah menjadi 'Lunas' apabila status pengerjaan sudah selesai.";
        } else if (selectElement === prosesSelect) {
            errorMessage = "Status pengerjaan tidak bisa diubah dari 'Selesai' karena status pembayaran sudah lunas.";
        } else {
            errorMessage = "Status pengerjaan belum selesai, sehingga status pembayaran harus belum lunas.";
        }
    } else if (pengambilan === "diambil") {
        if (pembayaran !== "lunas") {
            isValid = false;
            if (selectElement === pengambilanSelect) {
                errorMessage = "Status pengambilan tidak bisa diubah menjadi 'Sudah Diambil' karena status pembayaran belum lunas.";
            } else if (selectElement === pembayaranSelect) {
                errorMessage = "Status pembayaran tidak bisa diubah menjadi 'Belum Bayar' karena cucian sudah diambil.";
            } else {
                errorMessage = "Status pengambilan sudah diambil, sehingga status pembayaran harus lunas.";
            }
        } else if (proses !== "completed") {
            isValid = false;
            if (selectElement === pengambilanSelect) {
                errorMessage = "Status pengambilan hanya boleh diubah menjadi 'Sudah Diambil' apabila status pengerjaan sudah selesai.";
            } else if (selectElement === prosesSelect) {
                errorMessage = "Status pengerjaan tidak bisa diubah karena cucian sudah diambil.";
            } else {
                errorMessage = "Status pengambilan sudah diambil, sehingga status pengerjaan harus selesai.";
            }
        }
    }

    if (!isValid) {
        alert(errorMessage);
        location.reload(); // Bulletproof revert of the dropdown state
    } else {
        selectElement.form.submit();
    }
}
</script>

<?php
require_once 'includes/footer.php';
?>
