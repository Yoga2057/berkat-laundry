<?php
$page_title = 'Kelola Pengguna';
$page_subtitle = 'Tambah, ubah data, dan atur peran pengguna sistem (Admin & Karyawan).';
require_once 'config/database.php';
require_once 'includes/header.php';

// Access Control: Only Admin can manage users
if ($user_role !== 'admin') {
    echo '<script>window.location.href="dashboard.php";</script>';
    exit();
}

$action = $_GET['action'] ?? 'view';
$id = isset($_GET['id']) ? intval($_GET['id']) : null;
$message = '';
$error = '';

// Handle POST Requests (Tambah & Edit)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'tambah') {
        $nama     = trim($_POST['nama'] ?? '');
        $username = strtolower(trim($_POST['username'] ?? ''));
        $password = trim($_POST['password'] ?? '');
        $role     = $_POST['role'] ?? 'karyawan';

        if (!empty($nama) && !empty($username) && !empty($password) && in_array($role, ['admin', 'karyawan'])) {
            try {
                // Check if username already exists
                $check = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
                $check->execute([$username]);
                if ($check->fetchColumn() > 0) {
                    $error = 'Username "' . htmlspecialchars($username) . '" sudah digunakan. Silakan gunakan username lain.';
                } else {
                    $hashed_password = password_hash($password, PASSWORD_BCRYPT);
                    $stmt = $pdo->prepare("INSERT INTO users (nama, username, password, role) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$nama, $username, $hashed_password, $role]);
                    $message = 'Pengguna baru berhasil ditambahkan.';
                    $action = 'view';
                }
            } catch (PDOException $e) {
                $error = 'Gagal menambahkan pengguna: ' . $e->getMessage();
            }
        } else {
            $error = 'Harap isi semua kolom wajib dengan benar.';
        }
    } elseif ($action === 'edit' && $id) {
        $nama     = trim($_POST['nama'] ?? '');
        $username = strtolower(trim($_POST['username'] ?? ''));
        $password = trim($_POST['password'] ?? '');
        $role     = $_POST['role'] ?? 'karyawan';

        if (!empty($nama) && !empty($username) && in_array($role, ['admin', 'karyawan'])) {
            try {
                // Check username uniqueness excluding current ID
                $check = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ? AND id != ?");
                $check->execute([$username, $id]);
                if ($check->fetchColumn() > 0) {
                    $error = 'Username "' . htmlspecialchars($username) . '" sudah digunakan oleh pengguna lain.';
                } else {
                    if (!empty($password)) {
                        // Update with new password
                        $hashed_password = password_hash($password, PASSWORD_BCRYPT);
                        $stmt = $pdo->prepare("UPDATE users SET nama = ?, username = ?, password = ?, role = ? WHERE id = ?");
                        $stmt->execute([$nama, $username, $hashed_password, $role, $id]);
                    } else {
                        // Update without changing password
                        $stmt = $pdo->prepare("UPDATE users SET nama = ?, username = ?, role = ? WHERE id = ?");
                        $stmt->execute([$nama, $username, $role, $id]);
                    }

                    // If user edited their own account session, update session
                    if ($id == $_SESSION['user_id']) {
                        $_SESSION['username']  = $username;
                        $_SESSION['user_nama'] = $nama;
                        $_SESSION['user_role'] = $role;
                    }

                    $message = 'Data pengguna berhasil diperbarui.';
                    $action = 'view';
                }
            } catch (PDOException $e) {
                $error = 'Gagal memperbarui pengguna: ' . $e->getMessage();
            }
        } else {
            $error = 'Harap isi semua kolom wajib dengan benar.';
        }
    }
}

// Handle GET Delete Action
if ($action === 'hapus' && $id) {
    if ($id == $_SESSION['user_id']) {
        $error = 'Anda tidak dapat menghapus akun Anda sendiri yang sedang aktif digunakan.';
    } else {
        try {
            // Check if user has recorded transactions or expenses
            $check_trx = $pdo->prepare("SELECT COUNT(*) FROM transaksi WHERE id_user = ?");
            $check_trx->execute([$id]);
            $trx_count = $check_trx->fetchColumn();

            $check_exp = $pdo->prepare("SELECT COUNT(*) FROM pengeluaran WHERE id_user = ?");
            $check_exp->execute([$id]);
            $exp_count = $check_exp->fetchColumn();

            if ($trx_count > 0 || $exp_count > 0) {
                $error = 'Pengguna ini tidak dapat dihapus karena memiliki riwayat transaksi/pengeluaran tercatat.';
            } else {
                $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                $stmt->execute([$id]);
                $message = 'Pengguna berhasil dihapus.';
            }
        } catch (PDOException $e) {
            $error = 'Gagal menghapus pengguna: ' . $e->getMessage();
        }
    }
    $action = 'view';
}

// Prepare User Data for Edit Form
$user_data = null;
if ($action === 'edit' && $id) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$id]);
    $user_data = $stmt->fetch();
    if (!$user_data) {
        $error = 'Data pengguna tidak ditemukan.';
        $action = 'view';
    }
}

// Fetch all users for List View
$search = $_GET['search'] ?? '';
if (!empty($search)) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE nama LIKE ? OR username LIKE ? OR role LIKE ? ORDER BY role ASC, nama ASC");
    $stmt->execute(["%$search%", "%$search%", "%$search%"]);
} else {
    $stmt = $pdo->query("SELECT * FROM users ORDER BY role ASC, nama ASC");
}
$users_list = $stmt->fetchAll();
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
    <!-- FORM TAMBAH / EDIT PENGGUNA -->
    <div class="glass-panel" style="max-width: 600px; padding: 2.5rem; margin: 0 auto;">
        <h2 style="font-size: 1.5rem; font-weight: 700; margin-bottom: 1.5rem;">
            <?php echo $action === 'tambah' ? '<i class="fa-solid fa-user-plus" style="color:var(--primary);"></i> Tambah Pengguna Baru' : '<i class="fa-solid fa-user-gear" style="color:var(--primary);"></i> Edit Data Pengguna'; ?>
        </h2>
        
        <form action="pengguna.php?action=<?php echo $action; ?><?php echo $id ? '&id='.$id : ''; ?>" method="POST">
            <div class="form-group">
                <label for="nama">Nama Lengkap</label>
                <input type="text" id="nama" name="nama" class="form-control" placeholder="Contoh: Yoga Christian..." required 
                       value="<?php echo htmlspecialchars($user_data['nama'] ?? ''); ?>">
            </div>

            <div class="layanan-form-grid">
                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" class="form-control" placeholder="Contoh: yoga_admin" required
                           value="<?php echo htmlspecialchars($user_data['username'] ?? ''); ?>">
                </div>

                <div class="form-group">
                    <label for="role">Peran Sistem (Role)</label>
                    <select id="role" name="role" class="form-control" required>
                        <option value="admin" <?php echo isset($user_data['role']) && $user_data['role'] === 'admin' ? 'selected' : ''; ?>>Admin (Akses Penuh)</option>
                        <option value="karyawan" <?php echo isset($user_data['role']) && $user_data['role'] === 'karyawan' ? 'selected' : ''; ?>>Karyawan (Akses Operasional)</option>
                    </select>
                </div>
            </div>

            <div class="form-group" style="margin-bottom: 2rem;">
                <label for="password">Password <?php echo $action === 'edit' ? '<span style="font-weight:400; font-size:0.85rem; color:var(--text-muted);">(Biarkan kosong jika tidak ingin mengubah password)</span>' : ''; ?></label>
                <div style="position: relative;">
                    <input type="password" id="password" name="password" class="form-control" placeholder="<?php echo $action === 'edit' ? 'Masukkan password baru (opsional)' : 'Masukkan password akun'; ?>" <?php echo $action === 'tambah' ? 'required' : ''; ?> style="padding-right: 2.75rem;">
                    <button type="button" id="toggle-password-btn" title="Lihat / Sembunyikan Password" style="position: absolute; right: 0.8rem; top: 50%; transform: translateY(-50%); background: none; border: none; color: var(--text-muted); cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 1.1rem; outline: none;">
                        <i class="fa-solid fa-eye" id="eye-icon"></i>
                    </button>
                </div>
            </div>

            <div style="display: flex; gap: 1rem;">
                <a href="pengguna.php" class="btn btn-secondary" style="flex: 1; justify-content: center;">Batal</a>
                <button type="submit" class="btn btn-primary" style="flex: 1; justify-content: center;">
                    <i class="fa-solid fa-floppy-disk"></i> Simpan Pengguna
                </button>
            </div>
        </form>
    </div>

    <script>
        const togglePasswordBtn = document.getElementById('toggle-password-btn');
        const passwordInput = document.getElementById('password');
        const eyeIcon = document.getElementById('eye-icon');

        if (togglePasswordBtn && passwordInput && eyeIcon) {
            togglePasswordBtn.addEventListener('click', () => {
                if (passwordInput.type === 'password') {
                    passwordInput.type = 'text';
                    eyeIcon.classList.remove('fa-eye');
                    eyeIcon.classList.add('fa-eye-slash');
                } else {
                    passwordInput.type = 'password';
                    eyeIcon.classList.remove('fa-eye-slash');
                    eyeIcon.classList.add('fa-eye');
                }
            });
        }
    </script>

<?php else: ?>
    <!-- TABLE VIEW PENGGUNA -->
    <div class="glass-panel table-card">
        <div class="table-header">
            <div style="display:flex; align-items:center; gap: 1rem;">
                <h3>Daftar Pengguna Sistem</h3>
                <a href="pengguna.php?action=tambah" class="btn btn-primary" style="padding: 0.5rem 1rem; font-size: 0.85rem;">
                    <i class="fa-solid fa-user-plus"></i> Tambah Pengguna Baru
                </a>
            </div>
            
            <!-- Search bar -->
            <form action="pengguna.php" method="GET" style="display: flex; gap: 0.5rem;">
                <input type="text" name="search" class="form-control" style="width: 240px; padding: 0.5rem 1rem; font-size: 0.85rem;" 
                       placeholder="Cari nama / username..." value="<?php echo htmlspecialchars($search); ?>">
                <button type="submit" class="btn btn-secondary" style="padding: 0.5rem 1rem; font-size: 0.85rem;">
                    <i class="fa-solid fa-search"></i>
                </button>
                <?php if (!empty($search)): ?>
                    <a href="pengguna.php" class="btn btn-secondary" style="padding: 0.5rem 1rem; font-size: 0.85rem; color: var(--accent);">Clear</a>
                <?php endif; ?>
            </form>
        </div>

        <div style="overflow-x: auto;">
            <table>
                <thead>
                    <tr>
                        <th style="width: 50px;">No</th>
                        <th>Nama Lengkap</th>
                        <th>Username</th>
                        <th>Peran (Role)</th>
                        <th style="width: 140px; text-align: center;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($users_list) === 0): ?>
                        <tr>
                            <td colspan="5" style="text-align: center; color: var(--text-muted); padding: 2rem;">Tidak ada data pengguna ditemukan.</td>
                        </tr>
                    <?php else: ?>
                        <?php $no = 1; foreach ($users_list as $row): ?>
                            <tr>
                                <td><?php echo $no++; ?></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($row['nama']); ?></strong>
                                    <?php if ($row['id'] == $_SESSION['user_id']): ?>
                                        <span class="badge badge-info" style="margin-left: 0.5rem; font-size: 0.7rem;">Anda</span>
                                    <?php endif; ?>
                                </td>
                                <td style="font-family: monospace; font-size: 0.9rem; color: var(--primary);">
                                    @<?php echo htmlspecialchars($row['username']); ?>
                                </td>
                                <td>
                                    <?php if ($row['role'] === 'admin'): ?>
                                        <span class="badge badge-success"><i class="fa-solid fa-user-shield" style="margin-right:0.3rem;"></i> Admin</span>
                                    <?php else: ?>
                                        <span class="badge badge-primary"><i class="fa-solid fa-user-tie" style="margin-right:0.3rem;"></i> Karyawan</span>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align: center;">
                                    <div style="display: flex; gap: 0.5rem; justify-content: center;">
                                        <a href="pengguna.php?action=edit&id=<?php echo $row['id']; ?>" class="btn btn-secondary" 
                                           style="padding: 0.4rem; font-size: 0.85rem; border-radius: var(--radius-sm);" title="Edit / Reset Password">
                                            <i class="fa-solid fa-user-pen" style="color: var(--primary);"></i>
                                        </a>
                                        <?php if ($row['id'] != $_SESSION['user_id']): ?>
                                            <a href="pengguna.php?action=hapus&id=<?php echo $row['id']; ?>" class="btn btn-secondary" 
                                               style="padding: 0.4rem; font-size: 0.85rem; border-radius: var(--radius-sm);" title="Hapus Pengguna"
                                               onclick="return confirm('Apakah Anda yakin ingin menghapus pengguna ini?');">
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
<?php endif; ?>

<?php
require_once 'includes/footer.php';
?>
