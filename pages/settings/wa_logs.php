<?php
// ============================================================
// BENGKELIN - WhatsApp Logs
// ============================================================
require_once __DIR__ . '/../../includes/auth.php';
requireLogin();
requireRole('owner','admin');

$title      = 'Log WhatsApp';
$activePage = 'settings';
$db         = getDB();

$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 25;
$status  = $_GET['status'] ?? '';
$search  = trim($_GET['search'] ?? '');

$where  = ['1=1'];
$params = [];
if ($status) { $where[] = 'status = ?'; $params[] = $status; }
if ($search) { $where[] = '(recipient_phone LIKE ? OR message LIKE ?)'; $s = "%{$search}%"; $params[] = $s; $params[] = $s; }
$whereStr = implode(' AND ', $where);

$cnt = $db->prepare("SELECT COUNT(*) FROM wa_logs WHERE {$whereStr}");
$cnt->execute($params);
$pg = paginate((int)$cnt->fetchColumn(), $perPage, $page);

$logs = $db->prepare("
    SELECT * FROM wa_logs 
    WHERE {$whereStr} 
    ORDER BY sent_at DESC 
    LIMIT {$pg['per_page']} OFFSET {$pg['offset']}
");
$logs->execute($params);
$logs = $logs->fetchAll();

// Stats
$totalSent   = $db->query("SELECT COUNT(*) FROM wa_logs WHERE status='sent'")->fetchColumn();
$totalFailed = $db->query("SELECT COUNT(*) FROM wa_logs WHERE status='failed'")->fetchColumn();
$totalAll    = $db->query("SELECT COUNT(*) FROM wa_logs")->fetchColumn();
$lastSent    = $db->query("SELECT MAX(sent_at) FROM wa_logs WHERE status='sent'")->fetchColumn();

// Token check
$settings = $db->query("SELECT setting_key, setting_value FROM settings WHERE setting_key='wa_token'")->fetchAll(PDO::FETCH_KEY_PAIR);
$hasToken = !empty($settings['wa_token']);

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
  <div class="page-header-left">
    <h1>Log WhatsApp</h1>
    <p>Riwayat pengiriman pesan WhatsApp via Fonnte</p>
  </div>
  <div class="page-header-right">
    <a href="<?= BASE_URL ?>/pages/settings/index.php" class="btn btn-outline"><i class="fas fa-arrow-left"></i> Kembali ke Pengaturan</a>
  </div>
</div>

<!-- STATUS BANNER -->
<?php if (!$hasToken): ?>
<div style="background:var(--danger);color:#fff;padding:14px 20px;border-radius:12px;margin-bottom:20px;display:flex;align-items:center;gap:12px;font-size:14px">
  <i class="fas fa-exclamation-triangle" style="font-size:18px"></i>
  <div>
    <strong>WhatsApp belum dikonfigurasi!</strong>
    <div style="font-size:12px;opacity:.85">Masukkan API Token Fonnte di halaman Pengaturan untuk mengaktifkan notifikasi WhatsApp.</div>
  </div>
  <a href="<?= BASE_URL ?>/pages/settings/index.php" class="btn" style="background:#fff;color:var(--danger);font-size:13px;margin-left:auto;white-space:nowrap">Konfigurasi</a>
</div>
<?php endif; ?>

<!-- STATS -->
<div class="stats-grid mb-24" style="grid-template-columns:repeat(4,1fr)">
  <div class="stat-card">
    <div class="stat-icon blue"><i class="fab fa-whatsapp"></i></div>
    <div class="stat-info"><div class="stat-label">Total Pesan</div><div class="stat-value"><?= $totalAll ?></div></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon green"><i class="fas fa-check-circle"></i></div>
    <div class="stat-info"><div class="stat-label">Terkirim</div><div class="stat-value"><?= $totalSent ?></div></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon red"><i class="fas fa-times-circle"></i></div>
    <div class="stat-info"><div class="stat-label">Gagal</div><div class="stat-value"><?= $totalFailed ?></div></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon orange"><i class="fas fa-clock"></i></div>
    <div class="stat-info"><div class="stat-label">Terakhir Kirim</div><div class="stat-value" style="font-size:14px"><?= $lastSent ? date('d M Y H:i', strtotime($lastSent)) : '—' ?></div></div>
  </div>
</div>

<!-- FILTER -->
<div class="card mb-16">
  <div class="card-body" style="padding:14px 20px">
    <form method="GET" class="d-flex gap-8 align-center flex-wrap">
      <div class="search-box" style="flex:1;min-width:200px">
        <i class="fas fa-search search-icon"></i>
        <input type="text" name="search" class="form-control" placeholder="Cari no. telepon atau pesan..." value="<?= htmlspecialchars($search) ?>">
      </div>
      <select name="status" class="form-control" style="width:150px">
        <option value="">Semua Status</option>
        <option value="sent" <?= $status==='sent'?'selected':'' ?>>Terkirim</option>
        <option value="failed" <?= $status==='failed'?'selected':'' ?>>Gagal</option>
      </select>
      <button type="submit" class="btn btn-primary"><i class="fas fa-filter"></i> Filter</button>
      <?php if ($search || $status): ?><a href="?" class="btn btn-outline">Reset</a><?php endif; ?>
    </form>
  </div>
</div>

<!-- TABLE -->
<div class="card">
  <div class="card-header">
    <div class="card-header-title"><i class="fab fa-whatsapp" style="color:#25D366;margin-right:8px"></i>Riwayat Pesan <span style="color:var(--text-muted);font-weight:400;font-size:13px">(<?= $pg['total'] ?> pesan)</span></div>
  </div>
  <div class="table-responsive">
    <table class="table">
      <thead>
        <tr>
          <th>Waktu</th>
          <th>Penerima</th>
          <th>Tipe</th>
          <th>Pesan</th>
          <th>Status</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($logs)): ?>
        <tr><td colspan="5"><div class="empty-state"><i class="fab fa-whatsapp"></i><h3>Belum ada log pesan</h3><p>Pesan akan tercatat otomatis saat fitur WA digunakan</p></div></td></tr>
        <?php else: foreach ($logs as $log):
          $typeLabels = ['booking_confirm'=>'Booking','wo_created'=>'WO Baru','wo_done'=>'WO Selesai','general'=>'Umum'];
          $typeColors = ['booking_confirm'=>'info','wo_created'=>'warning','wo_done'=>'success','general'=>'done'];
        ?>
        <tr>
          <td style="white-space:nowrap;font-size:12px">
            <?= date('d M Y', strtotime($log['sent_at'])) ?><br>
            <span style="color:var(--text-muted)"><?= date('H:i:s', strtotime($log['sent_at'])) ?></span>
          </td>
          <td>
            <div style="font-weight:600;font-size:13px"><?= $log['recipient_phone'] ?></div>
          </td>
          <td>
            <span class="badge badge-<?= $typeColors[$log['type']] ?? 'info' ?>" style="font-size:11px">
              <?= $typeLabels[$log['type']] ?? $log['type'] ?>
            </span>
          </td>
          <td style="max-width:350px">
            <div style="font-size:12px;line-height:1.5;color:var(--text-secondary);max-height:60px;overflow:hidden;text-overflow:ellipsis;cursor:pointer" 
                 onclick="this.style.maxHeight = this.style.maxHeight === 'none' ? '60px' : 'none'" 
                 title="Klik untuk expand">
              <?= nl2br(htmlspecialchars(substr($log['message'], 0, 200))) ?><?= strlen($log['message']) > 200 ? '...' : '' ?>
            </div>
          </td>
          <td>
            <?php if ($log['status'] === 'sent'): ?>
            <span class="badge badge-success"><i class="fas fa-check" style="font-size:10px"></i> Terkirim</span>
            <?php else: ?>
            <span class="badge badge-cancelled"><i class="fas fa-times" style="font-size:10px"></i> Gagal</span>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
  <?php if ($pg['total_pages'] > 1): ?>
  <div class="card-footer">
    <div class="d-flex justify-between align-center">
      <span style="font-size:13px;color:var(--text-muted)">Hal <?= $pg['current'] ?> dari <?= $pg['total_pages'] ?></span>
      <div class="pagination">
        <?php for ($i=max(1,$pg['current']-2);$i<=min($pg['total_pages'],$pg['current']+2);$i++): ?>
        <a href="?page=<?= $i ?>&status=<?= $status ?>&search=<?= urlencode($search) ?>" class="<?= $i===$pg['current']?'active':'' ?>"><?= $i ?></a>
        <?php endfor; ?>
      </div>
    </div>
  </div>
  <?php endif; ?>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
