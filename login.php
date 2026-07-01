<?php
require_once __DIR__ . '/includes/auth.php';
if (isLoggedIn()) {
    header('Location: ' . BASE_URL . '/dashboard.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    if ($email && $password) {
        $db   = getDB();
        $stmt = $db->prepare("SELECT * FROM users WHERE email = ? AND status = 'active' LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        if ($user && password_verify($password, $user['password'])) {
            // Update last login
            $db->prepare("UPDATE users SET last_login = NOW() WHERE id = ?")->execute([$user['id']]);
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user']    = $user;
            header('Location: ' . BASE_URL . '/dashboard.php');
            exit;
        } else {
            $error = 'Email atau password salah. Silakan coba lagi.';
        }
    } else {
        $error = 'Email dan password wajib diisi.';
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login — Bengkelin | Bengkel Otomotif SMKS Pembda Nias</title>
  <meta name="description" content="Login ke Bengkelin — Sistem Manajemen Bengkel Otomotif SMKS Pembda Nias, Program Teaching Factory (Tefa).">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/login.css">
</head>
<body>

  <div class="bg-animation"></div>

  <!-- LEFT PANEL -->
  <div class="login-left">
    <div class="particle" style="width:10px; height:10px; top:20%; left:10%; animation:float 4s infinite;"></div>
    <div class="particle" style="width:15px; height:15px; top:70%; left:80%; animation:float 6s infinite reverse;"></div>
    <div class="particle" style="width:8px; height:8px; top:40%; left:60%; animation:float 5s infinite;"></div>

    <div class="brand">
      <div class="brand-icon"><i class="fas fa-wrench"></i></div>
      <div class="brand-text">
        <strong>Bengkelin</strong>
        <span>SMKS Pembda Nias — Tefa</span>
      </div>
    </div>

    <div class="hero-text">
      <h1>Teaching<br>Factory <span>Bengkel</span><br>Otomotif</h1>
      <p>Sistem manajemen bengkel otomotif SMKS Pembda Nias. Dari work order, inventori, hingga laporan keuangan — semua dalam satu sistem Teaching Factory.</p>
    </div>

    <div class="features-list">
      <div class="feature-item">
        <div class="feature-icon"><i class="fas fa-clipboard-list"></i></div>
        Manajemen Work Order Real-time
      </div>
      <div class="feature-item">
        <div class="feature-icon"><i class="fas fa-boxes"></i></div>
        Kontrol Inventori & Sparepart
      </div>
      <div class="feature-item">
        <div class="feature-icon"><i class="fas fa-chart-line"></i></div>
        Laporan & Analitik Bisnis
      </div>
      <div class="feature-item">
        <div class="feature-icon"><i class="fas fa-graduation-cap"></i></div>
        Program Teaching Factory (Tefa)
      </div>
    </div>
  </div>

  <!-- RIGHT PANEL -->
  <div class="login-right">
    <div class="login-card">
      <div class="login-form-header">
        <h2>Selamat Datang 👋</h2>
        <p>Masuk ke akun Anda untuk melanjutkan</p>
      </div>

      <?php if ($error): ?>
      <div class="login-error">
        <i class="fas fa-exclamation-circle"></i>
        <?= htmlspecialchars($error) ?>
      </div>
      <?php endif; ?>

      <form method="POST" id="login-form" autocomplete="off">
        <div class="form-group">
          <label class="form-label" for="email">Email</label>
          <input
            type="email"
            id="email"
            name="email"
            class="form-control"
            placeholder="nama@bengkelin.com"
            value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
            required
            autofocus
          >
        </div>

        <div class="form-group">
          <label class="form-label" for="password">Password</label>
          <div class="password-wrapper">
            <input
              type="password"
              id="password"
              name="password"
              class="form-control"
              placeholder="Masukkan password"
              required
            >
            <button type="button" class="password-toggle" id="toggle-password" aria-label="Toggle Password">
              <i class="fas fa-eye" id="toggle-icon"></i>
            </button>
          </div>
        </div>

        <button type="submit" class="btn-login" id="btn-login">
          <i class="fas fa-sign-in-alt"></i>
          Masuk ke Sistem
        </button>
      </form>

      <div class="login-footer">
        <p>© <?= date('Y') ?> Bengkelin — SMKS Pembda Nias</p>
        <p style="font-size:11px;opacity:.5;margin-top:4px">Teaching Factory — Bengkel Otomotif</p>
      </div>
    </div>
  </div>

  <script>
    // Toggle password visibility
    document.getElementById('toggle-password').addEventListener('click', function () {
      const pw = document.getElementById('password');
      const ic = document.getElementById('toggle-icon');
      if (pw.type === 'password') {
        pw.type = 'text';
        ic.classList.replace('fa-eye', 'fa-eye-slash');
      } else {
        pw.type = 'password';
        ic.classList.replace('fa-eye-slash', 'fa-eye');
      }
    });

    // Button loading state
    document.getElementById('login-form').addEventListener('submit', function () {
      const btn = document.getElementById('btn-login');
      btn.disabled = true;
      btn.innerHTML = '<i class="fas fa-circle-notch fa-spin"></i> Memproses...';
    });
  </script>
</body>
</html>
