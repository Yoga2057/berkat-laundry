<?php
// Public Receipt & Status Tracker - No Authentication Required
require_once 'config/database.php';

$code = trim($_GET['code'] ?? '');

$transaction = null;
$details = [];
$error_msg = '';

if (empty($code)) {
    $error_msg = 'Kode transaksi tidak valid atau tidak disertakan.';
} else {
    try {
        // 1. Fetch transaction and customer data
        $stmt = $pdo->prepare("SELECT t.*, p.nama AS nama_pelanggan, p.alamat AS alamat_pelanggan, 
                                      p.telepon AS telp_pelanggan, u.nama AS nama_petugas
                               FROM transaksi t
                               JOIN pelanggan p ON t.id_pelanggan = p.id
                               JOIN users u ON t.id_user = u.id
                               WHERE t.kode_transaksi = ?");
        $stmt->execute([$code]);
        $transaction = $stmt->fetch();

        if (!$transaction) {
            $error_msg = 'Nota transaksi dengan kode tersebut tidak ditemukan.';
        } else {
            // 2. Fetch transaction details (services)
            $stmt = $pdo->prepare("SELECT dt.*, l.nama_layanan, l.harga AS harga_satuan, l.tipe_hitung
                                   FROM detail_transaksi dt
                                   JOIN layanan l ON dt.id_layanan = l.id
                                   WHERE dt.id_transaksi = ?");
            $stmt->execute([$transaction['id']]);
            $details = $stmt->fetchAll();
        }
    } catch (PDOException $e) {
        $error_msg = 'Terjadi kesalahan sistem: ' . $e->getMessage();
    }
}

function get_status_bayar_label($status) {
    return $status === 'lunas' ? 'LUNAS' : 'BELUM BAYAR';
}

// Map process status to step index for visual tracker
$status_steps = [
    'received'  => 1,
    'washing'   => 2,
    'drying'    => 3,
    'ironing'   => 4,
    'ready'     => 5,
    'completed' => 6
];

$current_step = isset($transaction['status_proses']) ? ($status_steps[$transaction['status_proses']] ?? 1) : 0;
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Nota Laundry <?php echo htmlspecialchars($code); ?> - Berkat Laundry</title>
  <!-- FontAwesome Icons -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <!-- Google Fonts - Outfit -->
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap">
  <!-- CSS Stylesheet -->
  <link rel="stylesheet" href="assets/css/style.css?v=1.0.2">
  <style>
    /* Custom Styling for Public Tracking Page */
    .public-container {
      max-width: 680px;
      margin: 2rem auto;
      padding: 1.5rem;
    }
    
    .public-header {
      text-align: center;
      margin-bottom: 2rem;
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 0.5rem;
    }
    
    .public-header i {
      font-size: 3.5rem;
      background: linear-gradient(135deg, var(--primary), var(--secondary));
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
    }

    .public-header h1 {
      font-size: 2rem;
      font-weight: 800;
      letter-spacing: -0.5px;
    }

    .public-header p {
      color: var(--text-muted);
      font-size: 0.95rem;
    }

    /* Tracker Progress Stepper */
    .tracker-card {
      padding: 2rem;
      margin-bottom: 2rem;
      border-radius: var(--radius-md);
    }
    
    .tracker-title {
      font-weight: 700;
      font-size: 1.15rem;
      margin-bottom: 1.5rem;
      display: flex;
      align-items: center;
      gap: 0.5rem;
      color: var(--primary);
    }

    .stepper {
      display: flex;
      justify-content: space-between;
      position: relative;
      margin-bottom: 1rem;
      padding-top: 1rem;
    }

    .stepper::before {
      content: '';
      position: absolute;
      top: 26px;
      left: 10px;
      right: 10px;
      height: 4px;
      background: var(--border-color);
      z-index: 1;
      border-radius: var(--radius-full);
    }

    .stepper-progress {
      position: absolute;
      top: 26px;
      left: 10px;
      height: 4px;
      background: linear-gradient(90deg, var(--primary), var(--secondary));
      z-index: 2;
      border-radius: var(--radius-full);
      transition: width 0.5s ease-in-out;
      width: <?php echo (($current_step - 1) / 5) * 100; ?>%;
    }

    .step {
      position: relative;
      z-index: 3;
      display: flex;
      flex-direction: column;
      align-items: center;
      flex: 1;
      text-align: center;
    }

    .step-icon {
      width: 36px;
      height: 36px;
      border-radius: 50%;
      background: var(--bg-card);
      border: 3px solid var(--border-color);
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 0.9rem;
      color: var(--text-muted);
      transition: all 0.3s ease;
    }

    .step.active .step-icon {
      border-color: var(--primary);
      color: var(--primary);
      box-shadow: 0 0 12px rgba(14, 165, 233, 0.3);
      background: var(--primary-soft);
    }

    .step.completed .step-icon {
      background: linear-gradient(135deg, var(--primary), var(--secondary));
      border-color: var(--primary);
      color: #ffffff;
      box-shadow: 0 0 8px rgba(14, 165, 233, 0.2);
    }

    .step-label {
      font-size: 0.75rem;
      font-weight: 700;
      margin-top: 0.5rem;
      color: var(--text-muted);
      max-width: 80px;
      line-height: 1.2;
    }

    .step.active .step-label {
      color: var(--primary);
    }

    .step.completed .step-label {
      color: var(--text-main);
    }

    /* Receipt Detail Paper Look */
    .receipt-paper {
      background: #ffffff;
      color: #0f172a;
      border-radius: var(--radius-sm);
      border: 1px solid #e2e8f0;
      box-shadow: var(--shadow-md);
      padding: 2.5rem;
      position: relative;
      overflow: hidden;
    }

    [data-theme="dark"] .receipt-paper {
      /* Keep standard clean white receipt style even in dark theme for authentic receipt physical print look, but with slight softer shade or full-dark if desired. Let's make it styled beautiful and readable. */
      background: #ffffff; 
      color: #0f172a;
    }

    .receipt-paper::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      height: 4px;
      background: linear-gradient(90deg, var(--primary), var(--secondary));
    }

    .receipt-top {
      text-align: center;
      border-bottom: 2px dashed #cbd5e1;
      padding-bottom: 1.5rem;
      margin-bottom: 1.5rem;
    }

    .receipt-top h2 {
      font-weight: 800;
      font-size: 1.6rem;
      color: #0f172a;
      letter-spacing: -0.5px;
    }

    .receipt-top p {
      color: #64748b;
      font-size: 0.85rem;
      margin-top: 0.25rem;
    }

    .receipt-info-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 1rem;
      margin-bottom: 1.5rem;
      font-size: 0.85rem;
      color: #334155;
    }

    .receipt-info-grid div:nth-child(even) {
      text-align: right;
    }

    .table-receipt {
      width: 100%;
      border-collapse: collapse;
      margin-bottom: 1.5rem;
    }

    .table-receipt th, .table-receipt td {
      padding: 0.6rem 0;
      font-size: 0.85rem;
      color: #0f172a;
      border-bottom: 1px solid #e2e8f0;
    }

    .table-receipt th {
      font-weight: 700;
      color: #64748b;
      text-align: left;
      border-bottom: 2px solid #cbd5e1;
      background: none;
    }

    .table-receipt td.num, .table-receipt th.num {
      text-align: right;
    }

    .table-receipt td.center, .table-receipt th.center {
      text-align: center;
    }

    .receipt-bottom-summary {
      border-top: 2px dashed #cbd5e1;
      padding-top: 1rem;
      margin-bottom: 1.5rem;
    }

    .receipt-row {
      display: flex;
      justify-content: space-between;
      padding: 0.25rem 0;
      font-size: 0.9rem;
    }

    .receipt-row.total {
      font-weight: 800;
      font-size: 1.2rem;
      color: #0f172a;
      padding-top: 0.5rem;
      border-top: 1px solid #e2e8f0;
    }

    .badge-receipt {
      display: inline-flex;
      align-items: center;
      padding: 0.25rem 0.6rem;
      font-size: 0.75rem;
      font-weight: 700;
      border-radius: var(--radius-full);
    }
    
    .badge-receipt-unpaid {
      background: #ffe4e6;
      color: #e11d48;
    }

    .badge-receipt-paid {
      background: #d1fae5;
      color: #059669;
    }

    .public-footer {
      text-align: center;
      margin-top: 2rem;
      font-size: 0.85rem;
      color: var(--text-muted);
    }

    .theme-switcher-box {
      display: flex;
      justify-content: flex-end;
      margin-bottom: 1rem;
    }

    /* Responsive */
    @media (max-width: 576px) {
      .stepper {
        padding-top: 0.5rem;
      }
      .step-icon {
        width: 30px;
        height: 30px;
        font-size: 0.8rem;
        border-width: 2px;
      }
      .stepper::before, .stepper-progress {
        top: 23px;
      }
      .step-label {
        font-size: 0.65rem;
        max-width: 60px;
      }
      .receipt-paper {
        padding: 1.5rem;
      }
      .receipt-info-grid {
        grid-template-columns: 1fr;
        gap: 0.5rem;
      }
      .receipt-info-grid div:nth-child(even) {
        text-align: left;
      }
    }
  </style>
</head>
<body data-theme="light">
  <script>
    // Set user color scheme choice or system preference
    const savedTheme = localStorage.getItem("berkat_laundry_theme") || (window.matchMedia("(prefers-color-scheme: dark)").matches ? "dark" : "light");
    document.body.setAttribute("data-theme", savedTheme);
  </script>

  <div class="public-container">
    
    <!-- Top Actions / Theme Switcher -->
    <div class="theme-switcher-box">
      <button class="theme-toggle-btn" id="theme-toggle-btn" title="Ganti Tema">
        <i class="fa-solid fa-moon"></i>
      </button>
    </div>

    <!-- Header Logo -->
    <div class="public-header">
      <i class="fa-solid fa-soap"></i>
      <h1>Berkat Laundry</h1>
      <p>Status Pelacakan & Nota Digital Anda</p>
    </div>

    <?php if (!empty($error_msg)): ?>
      <!-- Error Panel -->
      <div class="glass-panel" style="padding: 2.5rem; text-align: center;">
        <i class="fa-solid fa-triangle-exclamation" style="font-size: 3rem; color: var(--accent); margin-bottom: 1rem;"></i>
        <h3 style="font-size: 1.25rem; margin-bottom: 0.5rem;">Nota Tidak Ditemukan</h3>
        <p style="color: var(--text-muted); margin-bottom: 1.5rem;"><?php echo htmlspecialchars($error_msg); ?></p>
        <div style="font-size: 0.85rem; color: var(--text-muted);">
          Silakan hubungi kami di <strong>0856-0675-3110</strong> jika terdapat kekeliruan.
        </div>
      </div>
    <?php else: ?>
      
      <!-- Progress Stepper Tracking Panel -->
      <div class="glass-panel tracker-card">
        <div class="tracker-title">
          <i class="fa-solid fa-truck-ramp-box"></i>
          <span>Status Pengerjaan Cucian</span>
        </div>
        
        <div class="stepper">
          <div class="stepper-progress"></div>
          
          <!-- Step 1: Received -->
          <div class="step <?php echo $current_step >= 1 ? ($current_step > 1 ? 'completed' : 'active') : ''; ?>">
            <div class="step-icon">
              <i class="fa-solid fa-soap"></i>
            </div>
            <div class="step-label">Diterima</div>
          </div>
          
          <!-- Step 2: Washing -->
          <div class="step <?php echo $current_step >= 2 ? ($current_step > 2 ? 'completed' : 'active') : ''; ?>">
            <div class="step-icon">
              <i class="fa-solid fa-rotate"></i>
            </div>
            <div class="step-label">Dicuci</div>
          </div>
          
          <!-- Step 3: Drying -->
          <div class="step <?php echo $current_step >= 3 ? ($current_step > 3 ? 'completed' : 'active') : ''; ?>">
            <div class="step-icon">
              <i class="fa-solid fa-wind"></i>
            </div>
            <div class="step-label">Dikeringkan</div>
          </div>
          
          <!-- Step 4: Ironing -->
          <div class="step <?php echo $current_step >= 4 ? ($current_step > 4 ? 'completed' : 'active') : ''; ?>">
            <div class="step-icon">
              <i class="fa-solid fa-square-rss"></i>
            </div>
            <div class="step-label">Disetrika</div>
          </div>
          
          <!-- Step 5: Ready -->
          <div class="step <?php echo $current_step >= 5 ? ($current_step > 5 ? 'completed' : 'active') : ''; ?>">
            <div class="step-icon">
              <i class="fa-solid fa-boxes-packing"></i>
            </div>
            <div class="step-label">Siap Diambil</div>
          </div>
          
          <!-- Step 6: Completed -->
          <div class="step <?php echo $current_step >= 6 ? 'completed' : ''; ?>">
            <div class="step-icon">
              <i class="fa-solid fa-circle-check"></i>
            </div>
            <div class="step-label">Selesai</div>
          </div>
        </div>
      </div>

      <!-- Receipt Paper View -->
      <div class="receipt-paper">
        <div class="receipt-top">
          <h2>BERKAT LAUNDRY</h2>
          <p>Srayu, Canden, Jetis, Bantul, Yogyakarta</p>
          <p>Telp: 0856-0675-3110 | WA: 0856-0675-3110</p>
        </div>

        <div class="receipt-info-grid">
          <div>
            <strong>No. Nota:</strong> <?php echo htmlspecialchars($transaction['kode_transaksi']); ?><br>
            <strong>Pelanggan:</strong> <?php echo htmlspecialchars($transaction['nama_pelanggan']); ?><br>
            <strong>Telepon:</strong> <?php echo htmlspecialchars($transaction['telp_pelanggan']); ?>
          </div>
          <div>
            <strong>Tgl Masuk:</strong> <?php echo date('d/m/Y H:i', strtotime($transaction['tgl_masuk'])); ?><br>
            <strong>Petugas:</strong> <?php echo htmlspecialchars($transaction['nama_petugas']); ?><br>
            <strong>Status Bayar:</strong> 
            <span class="badge-receipt <?php echo $transaction['status_pembayaran'] === 'lunas' ? 'badge-receipt-paid' : 'badge-receipt-unpaid'; ?>">
              <?php echo get_status_bayar_label($transaction['status_pembayaran']); ?>
            </span>
          </div>
        </div>

        <table class="table-receipt">
          <thead>
            <tr>
              <th>Layanan</th>
              <th class="center" style="width: 80px;">Jumlah</th>
              <th class="num" style="width: 120px;">Subtotal</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($details as $item): ?>
              <tr>
                <td>
                  <?php echo htmlspecialchars($item['nama_layanan']); ?><br>
                  <span style="font-size: 0.75rem; color: #64748b;">
                    @ Rp <?php echo number_format($item['harga_satuan'], 0, ',', '.'); ?> / <?php echo htmlspecialchars($item['tipe_hitung']); ?>
                  </span>
                </td>
                <td class="center">
                  <?php echo floatval($item['jumlah']); ?> <?php echo htmlspecialchars($item['tipe_hitung']); ?>
                </td>
                <td class="num">
                  Rp <?php echo number_format($item['subtotal'], 0, ',', '.'); ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>

        <div class="receipt-bottom-summary">
          <div class="receipt-row total">
            <span>TOTAL TAGIHAN</span>
            <span>Rp <?php echo number_format($transaction['total_bayar'], 0, ',', '.'); ?></span>
          </div>
        </div>

        <div class="receipt-footer">
          <p>Terima kasih atas kepercayaan Anda.</p>
          <p>Cucian bersih, rapi, dan wangi adalah prioritas kami.</p>
          <?php if ($transaction['status_pengambilan'] !== 'diambil'): ?>
            <p style="margin-top: 1rem; font-size: 0.75rem; font-style: italic; color: #e11d48;">
              *Harap tunjukkan nota digital ini saat mengambil pakaian.*
            </p>
          <?php else: ?>
            <p style="margin-top: 1rem; font-size: 0.75rem; font-style: italic; color: #059669; font-weight: 700;">
              *Pakaian sudah diambil pada tanggal <?php echo date('d/m/Y H:i', strtotime($transaction['tgl_selesai'] ?? $transaction['tgl_masuk'])); ?>.*
            </p>
          <?php endif; ?>
        </div>
      </div>

    <?php endif; ?>

    <div class="public-footer">
      <p>&copy; <?php echo date('Y'); ?> Berkat Laundry. All rights reserved.</p>
      <p style="font-size: 0.75rem; margin-top: 0.25rem;">Powered by Outfit-Design System.</p>
    </div>

  </div>

  <script>
    // Theme Toggle Handler
    const themeBtn = document.getElementById("theme-toggle-btn");
    if (themeBtn) {
      themeBtn.addEventListener("click", () => {
        const currentTheme = document.body.getAttribute("data-theme");
        const newTheme = currentTheme === "dark" ? "light" : "dark";
        
        document.body.setAttribute("data-theme", newTheme);
        localStorage.setItem("berkat_laundry_theme", newTheme);
        updateThemeIcon(themeBtn, newTheme);
      });
      
      // Init icon
      const currentTheme = document.body.getAttribute("data-theme");
      updateThemeIcon(themeBtn, currentTheme);
    }

    function updateThemeIcon(btn, theme) {
      if (theme === "dark") {
        btn.innerHTML = '<i class="fa-solid fa-sun"></i>';
      } else {
        btn.innerHTML = '<i class="fa-solid fa-moon"></i>';
      }
    }
  </script>
</body>
</html>
