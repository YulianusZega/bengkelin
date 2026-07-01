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
  <title>Login — Bengkelin | Sistem Manajemen Bengkel</title>
  <meta name="description" content="Login ke Bengkelin — Sistem Manajemen Bengkel Otomotif SMKS Pembda Nias, Program Teaching Factory (Tefa).">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/login.css?v=<?= time() ?>">
</head>
<body>

  <div class="login-container">
    
    <!-- LEFT PANEL: Branding & Visuals -->
    <div class="login-left">
      <div class="brand-wrapper">
        <div class="brand-logo">
          <img src="<?= BASE_URL ?>/assets/icons/logo.png" alt="Bengkelin Logo">
        </div>
        <div class="brand-text">
          <strong>Bengkelin</strong>
          <span>SMKS Pembda Nias</span>
        </div>
      </div>

      <div class="glass-card">
        <h1>Teaching Factory<br><span>Otomotif</span></h1>
        <p>Sistem manajemen bengkel cerdas terintegrasi. Pantau Work Order, kelola inventori sparepart, hingga analisis keuangan dalam satu platform premium.</p>
        
        <div class="features-grid">
          <div class="feature-item">
            <div class="feature-icon"><i class="fas fa-tools"></i></div>
            Work Order Cerdas
          </div>
          <div class="feature-item">
            <div class="feature-icon"><i class="fas fa-boxes"></i></div>
            Kontrol Inventori
          </div>
          <div class="feature-item">
            <div class="feature-icon"><i class="fas fa-chart-pie"></i></div>
            Analitik Bisnis
          </div>
          <div class="feature-item">
            <div class="feature-icon"><i class="fas fa-graduation-cap"></i></div>
            Standar Industri
          </div>
        </div>
      </div>
    </div>

    <!-- RIGHT PANEL: Login Form -->
    <div class="login-right">
      <div class="login-form-container">
        
        <div class="login-header">
          <h2>Selamat Datang Kembali 👋</h2>
          <p>Silakan masuk ke akun Anda untuk melanjutkan</p>
        </div>

        <?php if ($error): ?>
        <div class="login-error">
          <i class="fas fa-exclamation-circle"></i>
          <?= htmlspecialchars($error) ?>
        </div>
        <?php endif; ?>

        <form method="POST" autocomplete="off">
          <div class="form-group">
            <label class="form-label">Alamat Email</label>
            <div class="input-wrapper">
              <input type="email" name="email" class="form-control" placeholder="admin@bengkelin.com" required autofocus>
              <i class="fas fa-envelope input-icon"></i>
            </div>
          </div>

          <div class="form-group">
            <label class="form-label">Password</label>
            <div class="input-wrapper">
              <input type="password" name="password" id="login-password" class="form-control" placeholder="••••••••" required>
              <i class="fas fa-lock input-icon"></i>
              <button type="button" id="toggle-pwd" style="position:absolute;right:16px;top:50%;transform:translateY(-50%);background:none;border:none;color:var(--text-muted);cursor:pointer;font-size:16px;">
                <i class="fas fa-eye"></i>
              </button>
            </div>
          </div>

          <button type="submit" class="btn-login">
            Masuk ke Sistem <i class="fas fa-arrow-right" style="font-size:14px;margin-left:4px"></i>
          </button>
        </form>

      </div>
    </div>

  </div>

  <script>
    document.getElementById('toggle-pwd').addEventListener('click', function() {
      const input = document.getElementById('login-password');
      const icon = this.querySelector('i');
      if (input.type === 'password') {
        input.type = 'text';
        icon.classList.replace('fa-eye', 'fa-eye-slash');
        icon.style.color = 'var(--primary)';
      } else {
        input.type = 'password';
        icon.classList.replace('fa-eye-slash', 'fa-eye');
        icon.style.color = 'var(--text-muted)';
      }
    });
  </script>
</body>
</html>
