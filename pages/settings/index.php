<?php
require_once __DIR__ . '/../../includes/auth.php';
requireLogin();
requireRole('owner');

$title      = 'Pengaturan';
$activePage = 'settings';
$db         = getDB();

// Save settings
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrf($_POST['csrf_token'] ?? '')) {
    $fields = ['bengkel_name','bengkel_tagline','bengkel_address','bengkel_phone','bengkel_email','bengkel_hours','wa_token','wa_sender','max_booking_per_day','tax_rate'];
    $stmt = $db->prepare("INSERT INTO settings (setting_key,setting_value) VALUES (?,?) ON DUPLICATE KEY UPDATE setting_value=?");
    foreach ($fields as $key) {
        $val = trim($_POST[$key] ?? '');
        $stmt->execute([$key, $val, $val]);
    }
    flashSet('success','Pengaturan berhasil disimpan.');
    header('Location: '.BASE_URL.'/pages/settings/index.php'); exit;
}

// Load settings
$settings = $db->query("SELECT setting_key,setting_value FROM settings")->fetchAll(PDO::FETCH_KEY_PAIR);
function cfg(array $s, string $k, string $def=''): string { return htmlspecialchars($s[$k] ?? $def); }

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
  <div class="page-header-left"><h1>Pengaturan Sistem</h1><p>Konfigurasi bengkel dan integrasi</p></div>
</div>

<form method="POST">
<input type="hidden" name="csrf_token" value="<?= csrf() ?>">

<div class="grid-2">
  <!-- Bengkel Info -->
  <div class="card">
    <div class="card-header"><div class="card-header-title"><i class="fas fa-store" style="color:var(--primary);margin-right:8px"></i>Informasi Bengkel</div></div>
    <div class="card-body">
      <div class="form-group"><label class="form-label">Nama Bengkel</label><input type="text" name="bengkel_name" class="form-control" value="<?= cfg($settings,'bengkel_name','Bengkelin') ?>"></div>
      <div class="form-group"><label class="form-label">Tagline</label><input type="text" name="bengkel_tagline" class="form-control" value="<?= cfg($settings,'bengkel_tagline') ?>"></div>
      <div class="form-group"><label class="form-label">Alamat</label><textarea name="bengkel_address" class="form-control" rows="2"><?= cfg($settings,'bengkel_address') ?></textarea></div>
      <div class="form-row">
        <div class="form-group"><label class="form-label">Telepon</label><input type="text" name="bengkel_phone" class="form-control" value="<?= cfg($settings,'bengkel_phone') ?>"></div>
        <div class="form-group"><label class="form-label">Email</label><input type="email" name="bengkel_email" class="form-control" value="<?= cfg($settings,'bengkel_email') ?>"></div>
      </div>
      <div class="form-group"><label class="form-label">Jam Operasional</label><input type="text" name="bengkel_hours" class="form-control" value="<?= cfg($settings,'bengkel_hours') ?>" placeholder="Senin-Sabtu: 08.00-17.00"></div>
    </div>
  </div>

  <!-- System Settings -->
  <div style="display:flex;flex-direction:column;gap:20px">
    <div class="card">
      <div class="card-header"><div class="card-header-title"><i class="fas fa-cog" style="color:var(--primary);margin-right:8px"></i>Pengaturan Sistem</div></div>
      <div class="card-body">
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Max Booking/Hari</label>
            <input type="number" name="max_booking_per_day" class="form-control" value="<?= cfg($settings,'max_booking_per_day','10') ?>" min="1">
          </div>
          <div class="form-group">
            <label class="form-label">Pajak (%)</label>
            <input type="number" name="tax_rate" class="form-control" value="<?= cfg($settings,'tax_rate','0') ?>" min="0" max="100" step="0.5">
          </div>
        </div>
      </div>
    </div>

    <div class="card">
      <div class="card-header">
        <div class="card-header-title"><i class="fab fa-whatsapp" style="color:#25D366;margin-right:8px"></i>Integrasi WhatsApp (Fonnte)</div>
        <a href="<?= BASE_URL ?>/pages/settings/wa_logs.php" style="font-size:12px;color:var(--primary);text-decoration:none;font-weight:600"><i class="fas fa-history"></i> Lihat Log WA</a>
      </div>
      <div class="card-body">
        <div class="alert alert-info" style="margin-bottom:16px"><i class="fas fa-info-circle"></i> Gunakan <strong>Fonnte.com</strong> untuk mengirim notifikasi WhatsApp otomatis ke pelanggan.</div>
        <div class="form-group">
          <label class="form-label">API Token Fonnte</label>
          <input type="text" name="wa_token" class="form-control" value="<?= cfg($settings,'wa_token') ?>" placeholder="Token dari dashboard Fonnte">
        </div>
        <div class="form-group">
          <label class="form-label">Nomor Pengirim (WA)</label>
          <input type="text" name="wa_sender" class="form-control" value="<?= cfg($settings,'wa_sender') ?>" placeholder="628xxxxxxxxxx">
        </div>
        <div style="margin-top:12px;padding-top:12px;border-top:1px solid var(--border-light);display:flex;gap:10px;flex-wrap:wrap">
          <a href="<?= BASE_URL ?>/booking.php" target="_blank" class="btn btn-outline btn-sm"><i class="fas fa-external-link-alt"></i> Halaman Booking Publik</a>
          <a href="<?= BASE_URL ?>/tracking.php" target="_blank" class="btn btn-outline btn-sm"><i class="fas fa-search"></i> Halaman Tracking Publik</a>
        </div>
      </div>
    </div>

    <div style="display:flex;justify-content:flex-end">
      <button type="submit" class="btn btn-primary btn-lg"><i class="fas fa-save"></i> Simpan Semua Pengaturan</button>
    </div>
  </div>
</div>
</form>

<div style="margin-top:20px">
  <!-- Change Password -->
  <div class="card">
    <div class="card-header"><div class="card-header-title"><i class="fas fa-lock" style="color:var(--primary);margin-right:8px"></i>Ubah Password Akun</div></div>
    <div class="card-body">
      <form method="POST" action="<?= BASE_URL ?>/pages/settings/change_password.php" style="max-width:500px">
        <input type="hidden" name="csrf_token" value="<?= csrf() ?>">
        <div class="form-group"><label class="form-label">Password Lama</label><input type="password" name="old_password" class="form-control" required placeholder="Password saat ini"></div>
        <div class="form-row">
          <div class="form-group"><label class="form-label">Password Baru</label><input type="password" name="new_password" class="form-control" required placeholder="Min. 8 karakter"></div>
          <div class="form-group"><label class="form-label">Konfirmasi Password</label><input type="password" name="confirm_password" class="form-control" required placeholder="Ulangi password baru"></div>
        </div>
        <button type="submit" class="btn btn-warning"><i class="fas fa-key"></i> Ubah Password</button>
      </form>
    </div>
  </div>
</div>


<?php include __DIR__ . '/../../includes/footer.php'; ?>
