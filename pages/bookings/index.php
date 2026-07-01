<?php
require_once __DIR__ . '/../../includes/auth.php';
requireLogin();

$title      = 'Booking Online';
$activePage = 'bookings';

$db = getDB();

$statusFlt = $_GET['status'] ?? 'pending';
$search    = trim($_GET['search'] ?? '');
$page      = max(1, (int)($_GET['page'] ?? 1));
$perPage   = 15;

$where  = ['1=1'];
$params = [];
if ($statusFlt) { $where[] = "b.status = ?"; $params[] = $statusFlt; }
if ($search)    { $where[] = "(b.customer_name LIKE ? OR b.customer_phone LIKE ? OR b.booking_number LIKE ?)"; $s="%{$search}%"; $params=array_merge($params,[$s,$s,$s]); }
$whereStr = implode(' AND ', $where);

$total = $db->prepare("SELECT COUNT(*) FROM bookings b WHERE {$whereStr}");
$total->execute($params);
$pg = paginate((int)$total->fetchColumn(), $perPage, $page);

$stmt = $db->prepare("SELECT * FROM bookings b WHERE {$whereStr} ORDER BY b.preferred_date ASC, b.preferred_time ASC LIMIT {$pg['per_page']} OFFSET {$pg['offset']}");
$stmt->execute($params);
$bookings = $stmt->fetchAll();

$statusCounts = $db->query("SELECT status, COUNT(*) cnt FROM bookings GROUP BY status")->fetchAll(PDO::FETCH_KEY_PAIR);

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
  <div class="page-header-left">
    <h1>Booking Online</h1>
    <p>Kelola permintaan booking dari pelanggan</p>
  </div>
</div>

<!-- STATUS TABS -->
<div style="display:flex;gap:6px;flex-wrap:wrap;margin-bottom:16px">
  <?php foreach ([''=>'Semua','pending'=>'Menunggu','confirmed'=>'Dikonfirmasi','cancelled'=>'Dibatalkan','converted'=>'Dijadikan WO'] as $val=>$lbl): ?>
  <a href="?status=<?= $val ?>&search=<?= urlencode($search) ?>"
     style="padding:7px 14px;border-radius:20px;font-size:13px;font-weight:500;text-decoration:none;border:1.5px solid <?= $statusFlt===$val?'var(--primary)':'var(--border)' ?>;background:<?= $statusFlt===$val?'var(--primary)':'#fff' ?>;color:<?= $statusFlt===$val?'#fff':'var(--text-secondary)' ?>">
    <?= $lbl ?> <?php $cnt = $val?($statusCounts[$val]??0):array_sum($statusCounts); if($cnt>0): ?><span style="opacity:.7;font-size:11px">(<?= $cnt ?>)</span><?php endif; ?>
  </a>
  <?php endforeach; ?>
</div>

<!-- SEARCH -->
<div class="card mb-16">
  <div class="card-body" style="padding:14px 20px">
    <form method="GET" class="d-flex gap-8 align-center">
      <input type="hidden" name="status" value="<?= htmlspecialchars($statusFlt) ?>">
      <div class="search-box" style="flex:1">
        <i class="fas fa-search search-icon"></i>
        <input type="text" name="search" class="form-control" placeholder="Cari nama, telepon, no. booking..." value="<?= htmlspecialchars($search) ?>">
      </div>
      <button type="submit" class="btn btn-primary">Cari</button>
    </form>
  </div>
</div>

<!-- TABLE -->
<div class="card">
  <div class="card-header">
    <div class="card-header-title"><i class="fas fa-calendar-alt" style="color:var(--primary);margin-right:8px"></i>Daftar Booking <span style="color:var(--text-muted);font-weight:400;font-size:13px">(<?= $pg['total'] ?> data)</span></div>
  </div>
  <div class="table-responsive">
    <table class="table">
      <thead>
        <tr><th>No. Booking</th><th>Pelanggan</th><th>Kendaraan</th><th>Jadwal</th><th>Layanan</th><th>Status</th><th>Aksi</th></tr>
      </thead>
      <tbody>
        <?php if (empty($bookings)): ?>
        <tr><td colspan="7"><div class="empty-state"><i class="fas fa-calendar"></i><h3>Tidak ada booking <?= $statusFlt ?></h3></div></td></tr>
        <?php else: foreach ($bookings as $b): ?>
        <tr>
          <td><span style="font-size:12px;font-weight:700;color:var(--primary)"><?= $b['booking_number'] ?></span><div style="font-size:11px;color:var(--text-muted)"><?= date('d M Y', strtotime($b['created_at'])) ?></div></td>
          <td>
            <div style="font-weight:600"><?= htmlspecialchars($b['customer_name']) ?></div>
            <div style="font-size:12px;color:var(--text-muted)"><?= $b['customer_phone'] ?></div>
          </td>
          <td>
            <div style="font-weight:500"><?= $b['vehicle_brand'] . ' ' . $b['vehicle_model'] ?></div>
            <div style="font-size:12px;color:var(--text-muted)">
              <i class="fas <?= $b['vehicle_type']==='mobil'?'fa-car':'fa-motorcycle' ?>"></i>
              <?= $b['plate_number'] ?: '—' ?> · <?= $b['vehicle_year'] ?>
            </div>
          </td>
          <td>
            <div style="font-weight:600"><?= date('d M Y', strtotime($b['preferred_date'])) ?></div>
            <div style="font-size:12px;color:var(--text-muted)"><i class="fas fa-clock"></i> <?= substr($b['preferred_time'],0,5) ?></div>
          </td>
          <td style="max-width:200px;font-size:13px"><?= htmlspecialchars($b['service_type']) ?></td>
          <td><span class="badge badge-<?= $b['status'] ?>"><?= ucfirst($b['status']) ?></span></td>
          <td>
            <div class="table-actions">
              <?php if ($b['status'] === 'pending'): ?>
              <button class="btn btn-success btn-sm" onclick="confirmBooking(<?= $b['id'] ?>)"><i class="fas fa-check"></i> Konfirmasi</button>
              <a href="<?= BASE_URL ?>/pages/work-orders/create.php?booking_id=<?= $b['id'] ?>" class="btn btn-primary btn-sm"><i class="fas fa-clipboard-list"></i> Buat WO</a>
              <button class="btn btn-danger btn-sm btn-icon" onclick="cancelBooking(<?= $b['id'] ?>)" title="Batalkan"><i class="fas fa-times"></i></button>
              <?php elseif ($b['status'] === 'confirmed'): ?>
              <a href="<?= BASE_URL ?>/pages/work-orders/create.php?booking_id=<?= $b['id'] ?>" class="btn btn-primary btn-sm"><i class="fas fa-clipboard-list"></i> Buat WO</a>
              <?php elseif ($b['status'] === 'converted' && $b['converted_to_wo']): ?>
              <a href="<?= BASE_URL ?>/pages/work-orders/view.php?id=<?= $b['converted_to_wo'] ?>" class="btn btn-info btn-sm"><i class="fas fa-eye"></i> Lihat WO</a>
              <?php endif; ?>
            </div>
          </td>
        </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
  <?php if ($pg['total_pages'] > 1): ?>
  <div class="card-footer">
    <div class="pagination" style="justify-content:center">
      <?php for ($i=max(1,$pg['current']-2);$i<=min($pg['total_pages'],$pg['current']+2);$i++): ?>
      <a href="?page=<?= $i ?>&status=<?= $statusFlt ?>&search=<?= urlencode($search) ?>" class="<?= $i===$pg['current']?'active':'' ?>"><?= $i ?></a>
      <?php endfor; ?>
    </div>
  </div>
  <?php endif; ?>
</div>

<script>
function confirmBooking(id) {
  const fd = new FormData();
  fd.append('action', 'confirm');
  fd.append('id', id);
  fd.append('csrf_token', '<?= csrf() ?>');
  App.postForm('<?= BASE_URL ?>/pages/bookings/action.php', fd).then(r => {
    if (r?.success) { App.toast('Booking dikonfirmasi'); location.reload(); }
    else App.toast(r?.message || 'Gagal', 'error');
  });
}
function cancelBooking(id) {
  App.confirm('Batalkan booking ini?', () => {
    const fd = new FormData();
    fd.append('action','cancel'); fd.append('id',id);
    fd.append('csrf_token','<?= csrf() ?>');
    App.postForm('<?= BASE_URL ?>/pages/bookings/action.php',fd).then(r=>{
      if (r?.success) { App.toast('Booking dibatalkan','warning'); location.reload(); }
    });
  });
}
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
