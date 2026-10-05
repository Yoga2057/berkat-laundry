<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
require_once 'config/database.php';

// If already logged in, redirect to dashboard
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit();
}

$error_msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($username !== '' && $password !== '') {
        try {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? LIMIT 1");
            $stmt->execute([$username]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                $_SESSION['user_id']   = $user['id'];
                $_SESSION['username']  = $user['username'];
                $_SESSION['user_nama']  = $user['nama'];
                $_SESSION['user_role']  = $user['role'];

                header("Location: dashboard.php");
                exit();
            } else {
                $error_msg = 'Username atau password salah.';
            }
        } catch (PDOException $e) {
            $error_msg = 'Koneksi database gagal: ' . $e->getMessage();
        }
    } else {
        $error_msg = 'Silakan masukkan username dan password.';
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login - Berkat Laundry</title>
  <!-- FontAwesome Icons -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <!-- Google Fonts - Outfit -->
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap">
  <!-- CSS Stylesheet -->
  <link rel="stylesheet" href="assets/css/style.css?v=1.0.2">
</head>
<body data-theme="light">
  <script>
    // Initialize theme before body renders to prevent visual flash
    const savedTheme = localStorage.getItem("berkat_laundry_theme") || "light";
    document.body.setAttribute("data-theme", savedTheme);
  </script>

  <div class="login-container">
    <div class="glass-panel login-card">
      <div class="login-header">
        <i class="fa-solid fa-soap"></i>
        <h2>Berkat Laundry</h2>
        <p>Silakan masuk ke akun Anda</p>
      </div>

      <?php if (!empty($error_msg)): ?>
        <div class="alert alert-error">
          <i class="fa-solid fa-circle-exclamation"></i>
          <span><?php echo htmlspecialchars($error_msg); ?></span>
        </div>
      <?php endif; ?>

      <form action="index.php" method="POST">
        <div class="form-group">
          <label for="username"><i class="fa-solid fa-user" style="margin-right: 0.5rem; color: var(--primary);"></i>Username</label>
          <input type="text" id="username" name="username" class="form-control" placeholder="Masukkan username" required autocomplete="username">
        </div>

        <div class="form-group">
          <label for="password"><i class="fa-solid fa-lock" style="margin-right: 0.5rem; color: var(--primary);"></i>Password</label>
          <div style="position: relative;">
            <input type="password" id="password" name="password" class="form-control" placeholder="Masukkan password" required autocomplete="current-password" style="padding-right: 2.75rem;">
            <button type="button" id="toggle-password-btn" style="position: absolute; right: 0.8rem; top: 50%; transform: translateY(-50%); background: none; border: none; color: var(--text-muted); cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 1.1rem; outline: none;">
              <i class="fa-solid fa-eye" id="eye-icon"></i>
            </button>
          </div>
        </div>

        <button type="submit" class="btn btn-primary" style="width: 100%; justify-content: center; margin-top: 1.5rem; padding: 0.85rem;">
          <i class="fa-solid fa-right-to-bracket"></i> Masuk
        </button>
      </form>
    </div>
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
</body>
</html>
