<?php
// ============================================================
// BENGKELIN - HTML Head & Sidebar Layout
// Params: $title (string), $activePage (string)
// ============================================================
$user = currentUser();
$flash = flashGet();

$navItems = [
    'dashboard'    => ['icon' => 'fa-tachometer-alt', 'label' => 'Dashboard',      'href' => BASE_URL . '/dashboard.php',                  'roles' => ['owner','admin','senior_teknisi','junior_teknisi']],
    'work-orders'  => ['icon' => 'fa-clipboard-list', 'label' => 'Work Order',     'href' => BASE_URL . '/pages/work-orders/index.php',    'roles' => ['owner','admin','senior_teknisi','junior_teknisi']],
    'bookings'     => ['icon' => 'fa-calendar-alt',   'label' => 'Booking',        'href' => BASE_URL . '/pages/bookings/index.php',       'roles' => ['owner','admin','senior_teknisi']],
    'customers'    => ['icon' => 'fa-users',          'label' => 'Pelanggan',      'href' => BASE_URL . '/pages/customers/index.php',      'roles' => ['owner','admin','senior_teknisi']],
    'vehicles'     => ['icon' => 'fa-car',            'label' => 'Kendaraan',      'href' => BASE_URL . '/pages/vehicles/index.php',       'roles' => ['owner','admin','senior_teknisi']],
    'inventory'    => ['icon' => 'fa-boxes',          'label' => 'Inventori',      'href' => BASE_URL . '/pages/inventory/index.php',      'roles' => ['owner','admin','senior_teknisi']],
    'services'     => ['icon' => 'fa-tools',          'label' => 'Layanan',        'href' => BASE_URL . '/pages/services/index.php',       'roles' => ['owner','admin','senior_teknisi']],
    'employees'    => ['icon' => 'fa-user-tie',        'label' => 'Karyawan',       'href' => BASE_URL . '/pages/employees/index.php',      'roles' => ['owner','admin']],
    'reports'      => ['icon' => 'fa-chart-bar',       'label' => 'Laporan',        'href' => BASE_URL . '/pages/reports/index.php',        'roles' => ['owner','admin']],
    'payroll'      => ['icon' => 'fa-money-check-alt',  'label' => 'Penggajian',     'href' => BASE_URL . '/pages/payroll/index.php',         'roles' => ['owner']],
    'settings'     => ['icon' => 'fa-cog',             'label' => 'Pengaturan',     'href' => BASE_URL . '/pages/settings/index.php',       'roles' => ['owner']],
];

// Count pending items for badge
$db = getDB();
$pendingWo      = $db->query("SELECT COUNT(*) FROM work_orders WHERE status IN ('waiting','inspection')")->fetchColumn();
$pendingBooking = $db->query("SELECT COUNT(*) FROM bookings WHERE status = 'pending'")->fetchColumn();
$lowStockCount  = $db->query("SELECT COUNT(*) FROM parts WHERE stock <= min_stock")->fetchColumn();
$notifTotal     = $pendingWo + $pendingBooking + $lowStockCount;

function navBadge(string $page, int $pendingWo, int $pendingBooking): string {
    if ($page === 'work-orders' && $pendingWo > 0) return "<span class='nav-badge'>{$pendingWo}</span>";
    if ($page === 'bookings'   && $pendingBooking > 0) return "<span class='nav-badge'>{$pendingBooking}</span>";
    return '';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($title ?? 'Dashboard') ?> — Bengkelin</title>
  <meta name="description" content="Bengkelin — Sistem Manajemen Bengkel Otomotif SMKS Pembda Nias, Program Teaching Factory (Tefa)">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css?v=<?= time() ?>">
  <?php if (!empty($extraCss)): foreach ($extraCss as $css): ?>
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/<?= $css ?>">
  <?php endforeach; endif; ?>
  <!-- Core Scripts -->
  <script src="<?= BASE_URL ?>/assets/js/app.js?v=<?= time() ?>"></script>
  
  <?php if (!empty($extraJs)): foreach ($extraJs as $js): ?>
  <script src="<?= BASE_URL ?>/assets/js/<?= $js ?>?v=<?= time() ?>"></script>
  <?php endforeach; endif; ?>
  <link rel="manifest" href="<?= BASE_URL ?>/manifest.json">
  <meta name="theme-color" content="#FF6B2B">
  <script>
    if ('serviceWorker' in navigator) {
      window.addEventListener('load', () => {
        navigator.serviceWorker.register('<?= BASE_URL ?>/service-worker.js')
          .catch(err => console.log('SW registration failed: ', err));
      });
    }
  </script>
</head>
<body>
<script>
  // Mencegah FOUC (Flash) saat memuat halaman dengan menerapkan class secara langsung sebelum DOM dirender
  if (localStorage.getItem('bengkelin_dark_mode') === 'true') document.body.classList.add('dark-mode');
  if (localStorage.getItem('bengkelin_sidebar_collapsed') === 'true' && window.innerWidth > 1024) document.body.classList.add('sidebar-collapsed');
</script>
<div id="loading-overlay" class="loading-overlay">
  <div class="spinner"></div>
</div>

<div class="app-wrapper">

  <!-- ===== SIDEBAR ===== -->
  <aside class="sidebar" id="app-sidebar">

    <a href="<?= BASE_URL ?>/dashboard.php" class="sidebar-brand">
      <div class="sidebar-brand-icon">
        <!-- Logo akan ditarik dari assets/icons/logo.png -->
        <img src="<?= BASE_URL ?>/assets/icons/logo.png" alt="Logo Bengkelin" onerror="this.outerHTML='<i class=\'fas fa-wrench\'></i>'">
      </div>
      <div class="sidebar-brand-text">
        <strong>Bengkelin</strong>
        <span>SMKS Pembda Nias — Tefa</span>
      </div>
    </a>

    <nav class="sidebar-nav">
      <div class="nav-section-title">Menu Utama</div>
      <?php foreach ($navItems as $key => $item):
          if (!in_array($user['role'], $item['roles'])) continue;
          $isActive = ($activePage ?? '') === $key ? 'active' : '';
      ?>
      <div class="nav-item">
        <a href="<?= $item['href'] ?>" class="nav-link <?= $isActive ?>">
          <i class="fas <?= $item['icon'] ?> nav-icon"></i>
          <?= $item['label'] ?>
          <?= navBadge($key, (int)$pendingWo, (int)$pendingBooking) ?>
        </a>
      </div>
      <?php endforeach; ?>
    </nav>

    <div class="sidebar-footer">
      <div class="sidebar-user">
        <div class="sidebar-avatar"><?= strtoupper(substr($user['name'], 0, 1)) ?></div>
        <div class="sidebar-user-info">
          <strong><?= htmlspecialchars($user['name']) ?></strong>
          <span><?= ucfirst($user['role']) ?></span>
        </div>
        <a href="<?= BASE_URL ?>/logout.php" style="margin-left:auto;color:rgba(255,255,255,.4);text-decoration:none;" title="Logout">
          <i class="fas fa-sign-out-alt"></i>
        </a>
      </div>
    </div>

  </aside>

  <!-- ===== MAIN WRAPPER ===== -->
  <div class="main-wrapper">

    <!-- TOPBAR -->
    <header class="topbar">
      <button class="sidebar-toggle" id="sidebar-toggle" aria-label="Toggle Sidebar">
        <i class="fas fa-bars"></i>
      </button>
      <div class="topbar-title">
        <?= htmlspecialchars($pageTitle ?? $title ?? '') ?>
        <div style="font-size: 11px; color: var(--text-muted); font-weight: 500; margin-top: 2px;">
          Bengkelin <i class="fas fa-chevron-right" style="font-size: 8px; margin: 0 4px;"></i> <?= htmlspecialchars($title ?? '') ?>
        </div>
      </div>
      <div class="topbar-actions">
        <button class="topbar-btn" id="dark-mode-toggle" title="Toggle Dark Mode">
          <i class="fas fa-moon"></i>
        </button>
        <a href="<?= BASE_URL ?>/pages/work-orders/create.php" class="btn btn-primary btn-sm" title="Buat Work Order">
          <i class="fas fa-plus"></i> WO Baru
        </a>
        <div class="dropdown">
          <div class="topbar-btn" onclick="App.toggleDropdown('notif-dropdown')" title="Notifikasi">
            <i class="fas fa-bell"></i>
            <?php if ($notifTotal > 0): ?><span class="notif-dot"></span><?php endif; ?>
          </div>
          <div class="dropdown-menu" id="notif-dropdown" style="min-width:300px;right:0">
            <div style="padding:14px 16px;border-bottom:1px solid #F3F4F6;font-weight:700;font-size:14px;display:flex;align-items:center;justify-content:space-between">
              <span><i class="fas fa-bell" style="color:var(--primary);margin-right:6px"></i>Notifikasi</span>
              <?php if ($notifTotal > 0): ?><span style="background:var(--danger);color:#fff;font-size:11px;padding:2px 8px;border-radius:10px;font-weight:600"><?= $notifTotal ?></span><?php endif; ?>
            </div>
            <?php if ($pendingWo > 0): ?>
            <a href="<?= BASE_URL ?>/pages/work-orders/index.php?status=waiting" class="dropdown-item" style="padding:12px 16px">
              <div style="width:32px;height:32px;border-radius:8px;background:var(--warning-bg);color:var(--warning);display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:13px"><i class="fas fa-clipboard-list"></i></div>
              <div style="flex:1;margin-left:10px"><div style="font-weight:600;font-size:13px"><?= $pendingWo ?> WO Menunggu</div><div style="font-size:11px;color:var(--text-muted)">Perlu inspeksi atau tindakan</div></div>
            </a>
            <?php endif; ?>
            <?php if ($pendingBooking > 0): ?>
            <a href="<?= BASE_URL ?>/pages/bookings/index.php?status=pending" class="dropdown-item" style="padding:12px 16px">
              <div style="width:32px;height:32px;border-radius:8px;background:var(--info-bg);color:var(--info);display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:13px"><i class="fas fa-calendar-alt"></i></div>
              <div style="flex:1;margin-left:10px"><div style="font-weight:600;font-size:13px"><?= $pendingBooking ?> Booking Baru</div><div style="font-size:11px;color:var(--text-muted)">Belum dikonfirmasi</div></div>
            </a>
            <?php endif; ?>
            <?php if ($lowStockCount > 0): ?>
            <a href="<?= BASE_URL ?>/pages/inventory/index.php?stock=low" class="dropdown-item" style="padding:12px 16px">
              <div style="width:32px;height:32px;border-radius:8px;background:var(--danger-bg);color:var(--danger);display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:13px"><i class="fas fa-boxes"></i></div>
              <div style="flex:1;margin-left:10px"><div style="font-weight:600;font-size:13px"><?= $lowStockCount ?> Stok Menipis</div><div style="font-size:11px;color:var(--text-muted)">Perlu restock segera</div></div>
            </a>
            <?php endif; ?>
            <?php if ($notifTotal === 0): ?>
            <div style="padding:30px 16px;text-align:center;color:var(--text-muted);font-size:13px">
              <i class="fas fa-check-circle" style="font-size:24px;opacity:.2;display:block;margin-bottom:8px"></i>
              Tidak ada notifikasi baru
            </div>
            <?php endif; ?>
          </div>
        </div>
        <div class="dropdown">
          <div class="topbar-btn" onclick="App.toggleDropdown('topbar-dropdown')" title="Akun">
            <i class="fas fa-user-circle"></i>
          </div>
          <div class="dropdown-menu" id="topbar-dropdown">
            <div style="padding:12px 14px;border-bottom:1px solid #F3F4F6;">
              <div style="font-weight:600;font-size:13px"><?= htmlspecialchars($user['name']) ?></div>
              <div style="font-size:12px;color:#9CA3AF"><?= $user['email'] ?></div>
            </div>
            <a href="<?= BASE_URL ?>/pages/settings/index.php" class="dropdown-item"><i class="fas fa-cog" style="width:16px"></i> Pengaturan</a>
            <hr class="dropdown-divider">
            <a href="<?= BASE_URL ?>/logout.php" class="dropdown-item danger"><i class="fas fa-sign-out-alt" style="width:16px"></i> Keluar</a>
          </div>
        </div>
      </div>
    </header>

    <!-- FLASH MESSAGE -->
    <?php if ($flash): ?>
    <div style="padding:0 24px;padding-top:16px">
      <div class="alert alert-<?= $flash['type'] ?>">
        <i class="fas <?= $flash['type']==='success'?'fa-check-circle':($flash['type']==='danger'?'fa-times-circle':'fa-info-circle') ?>"></i>
        <?= htmlspecialchars($flash['message']) ?>
      </div>
    </div>
    <?php endif; ?>

    <!-- PAGE CONTENT START -->
    <main class="page-content">
