<?php
require_once __DIR__ . '/../../includes/auth.php';
requireLogin();

$title      = 'Work Order';
$activePage = 'work-orders';

$db = getDB();

$search  = trim($_GET['search'] ?? '');
$status  = $_GET['status'] ?? '';
$date    = $_GET['date'] ?? '';
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 15;

$where  = ['1=1'];
$params = [];

if ($search) {
    $where[]  = "(wo.wo_number LIKE ? OR c.name LIKE ? OR v.plate_number LIKE ?)";
    $s = "%{$search}%";
    $params = array_merge($params, [$s, $s, $s]);
}
if ($status) { $where[] = "wo.status = ?"; $params[] = $status; }
if ($date)   { $where[] = "DATE(wo.created_at) = ?"; $params[] = $date; }

// Role filter: mekanik hanya lihat WO-nya sendiri
$user = currentUser();
if ($user['role'] === 'mekanik') {
    $empStmt = $db->prepare("SELECT id FROM employees WHERE user_id=?");
    $empStmt->execute([$user['id']]);
    $emp = $empStmt->fetch();
    if ($emp) { $where[] = "wo.mechanic_id = ?"; $params[] = $emp['id']; }
}

$whereStr = implode(' AND ', $where);

$total = $db->prepare("SELECT COUNT(*) FROM work_orders wo LEFT JOIN customers c ON wo.customer_id=c.id LEFT JOIN vehicles v ON wo.vehicle_id=v.id WHERE {$whereStr}");
$total->execute($params);
$pg = paginate((int)$total->fetchColumn(), $perPage, $page);

$offset = (int)$pg['offset'];
$limit = (int)$pg['per_page'];

$stmt = $db->prepare("
    SELECT wo.*, wo.id AS wo_id, 
           c.name as customer_name, c.phone as customer_phone, 
           v.plate_number, v.brand, v.model, v.type as vehicle_type,
           e.name as mechanic_name
    FROM work_orders wo
    LEFT JOIN customers c ON wo.customer_id = c.id
    LEFT JOIN vehicles v ON wo.vehicle_id = v.id
    LEFT JOIN employees e ON wo.mechanic_id = e.id
    WHERE {$whereStr}
    ORDER BY wo.created_at DESC
    LIMIT $limit OFFSET $offset
");

$stmt->execute($params);
$workOrders = $stmt->fetchAll();

// Status counts for filter tabs
$statusCounts = $db->query("SELECT status, COUNT(*) as cnt FROM work_orders GROUP BY status")->fetchAll(PDO::FETCH_KEY_PAIR);

$statuses = [
    ''           => 'Semua',
    'waiting'    => 'Menunggu',
    'inspection' => 'Inspeksi',
    'approved'   => 'Disetujui',
    'in_progress'=> 'Dikerjakan',
    'qc'         => 'QC',
    'done'       => 'Selesai',
    'delivered'  => 'Diserahkan',
    'cancelled'  => 'Dibatalkan',
];

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
  <div class="page-header-left">
    <h1>Work Order</h1>
    <p>Manajemen pekerjaan dan servis kendaraan</p>
  </div>
  <div class="page-header-right">
    <a href="<?= BASE_URL ?>/pages/work-orders/create.php" class="btn btn-primary">
      <i class="fas fa-plus"></i> Buat WO Baru
    </a>
  </div>
</div>

<!-- STATUS TABS -->
<div style="display:flex;gap:6px;flex-wrap:wrap;margin-bottom:16px">
  <?php foreach ($statuses as $val => $label):
    $cnt = $val ? ($statusCounts[$val] ?? 0) : array_sum($statusCounts);
    $isActive = $status === $val;
  ?>
  <a href="?status=<?= $val ?>&search=<?= urlencode($search) ?>&date=<?= $date ?>"
     style="padding:7px 14px;border-radius:20px;font-size:13px;font-weight:500;text-decoration:none;border:1.5px solid <?= $isActive?'var(--primary)':'var(--border)' ?>;background:<?= $isActive?'var(--primary)':'#fff' ?>;color:<?= $isActive?'#fff':'var(--text-secondary)' ?>;transition:all .2s;white-space:nowrap">
    <?= $label ?> <?php if ($cnt > 0): ?><span style="opacity:.7;font-size:11px">(<?= $cnt ?>)</span><?php endif; ?>
  </a>
  <?php endforeach; ?>
</div>

<!-- SEARCH & DATE FILTER -->
<div class="card mb-16">
  <div class="card-body" style="padding:14px 20px">
    <form method="GET" class="d-flex gap-8 align-center flex-wrap">
      <input type="hidden" name="status" value="<?= htmlspecialchars($status) ?>">
      <div class="search-box" style="flex:1;min-width:200px">
        <i class="fas fa-search search-icon"></i>
        <input type="text" name="search" class="form-control" placeholder="Cari no. WO, pelanggan, plat..." value="<?= htmlspecialchars($search) ?>">
      </div>
      <input type="date" name="date" class="form-control" style="width:160px" value="<?= $date ?>">
      <button type="submit" class="btn btn-primary">Cari</button>
      <?php if ($search || $date): ?><a href="?status=<?= $status ?>" class="btn btn-outline">Reset</a><?php endif; ?>
    </form>
  </div>
</div>

<!-- WORK ORDERS TABLE -->
<div class="card">
  <div class="card-header">
    <div class="card-header-title">
      <i class="fas fa-clipboard-list" style="color:var(--primary);margin-right:8px"></i>
      Daftar Work Order <span style="color:var(--text-muted);font-weight:400;font-size:13px">(<?= $pg['total'] ?> data)</span>
    </div>
  </div>
  <div class="table-responsive">
    <table class="table">
      <thead>
        <tr>
          <th>No. WO</th>
          <th>Pelanggan</th>
          <th>Kendaraan</th>
          <th>Mekanik</th>
          <th>Status</th>
          <th>Pembayaran</th>
          <th>Total</th>
          <th>Tgl Masuk</th>
          <th>Aksi</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($workOrders)): ?>
        <tr><td colspan="9"><div class="empty-state"><i class="fas fa-clipboard-list"></i><h3>Tidak ada work order</h3><p>Buat work order baru untuk memulai</p></div></td></tr>
        <?php else: foreach ($workOrders as $wo): ?>
        <tr style="cursor:pointer" onclick="window.location='<?= BASE_URL ?>/pages/work-orders/view.php?id=<?= $wo['wo_id'] ?>'">
          <td>
            <div style="font-weight:700;color:var(--primary);font-size:13px"><?= $wo['wo_number'] ?></div>
            <?php if ($wo['priority']==='urgent'): ?><span class="badge badge-urgent" style="font-size:10px;margin-top:3px">URGENT</span><?php endif; ?>
          </td>
          <td>
            <div style="font-weight:600;font-size:13px"><?= htmlspecialchars($wo['customer_name']) ?></div>
            <div style="font-size:11px;color:var(--text-muted)"><?= $wo['customer_phone'] ?></div>
          </td>
          <td>
            <div style="font-weight:500;font-size:13px"><?= $wo['brand'] . ' ' . $wo['model'] ?></div>
            <div style="font-size:11px;color:var(--text-muted)">
              <i class="fas <?= $wo['vehicle_type']==='mobil'?'fa-car':'fa-motorcycle' ?>" style="color:var(--<?= $wo['vehicle_type']==='mobil'?'info':'success' ?>)"></i>
              <?= $wo['plate_number'] ?>
            </div>
          </td>
          <td style="font-size:13px"><?= $wo['mechanic_name'] ? htmlspecialchars($wo['mechanic_name']) : '<span class="text-muted">—</span>' ?></td>
          <td><span class="badge badge-<?= $wo['status'] ?>"><?= statusLabel($wo['status']) ?></span></td>
          <td><span class="badge badge-<?= $wo['payment_status'] ?>"><?= paymentLabel($wo['payment_status']) ?></span></td>
          <td style="font-weight:700;font-size:13px;white-space:nowrap"><?= formatRupiah((float)$wo['total']) ?></td>
          <td style="font-size:12px;color:var(--text-muted);white-space:nowrap"><?= date('d M Y', strtotime($wo['created_at'])) ?></td>
          <td onclick="event.stopPropagation();">
            <div class="table-actions">
              <a href="<?= BASE_URL ?>/pages/work-orders/view.php?id=<?= $wo['wo_id'] ?>" class="btn btn-info btn-sm btn-icon" title="Detail" onclick="event.stopPropagation();">
                <i class="fas fa-eye"></i>
              </a>
              <?php if (!in_array($wo['status'],['done','delivered','cancelled'])): ?>
              <a href="<?= BASE_URL ?>/pages/work-orders/edit.php?id=<?= $wo['wo_id'] ?>" class="btn btn-warning btn-sm btn-icon" title="Edit" onclick="event.stopPropagation();">
                <i class="fas fa-pencil-alt"></i>
              </a>
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
    <div class="d-flex justify-between align-center">
      <span style="font-size:13px;color:var(--text-muted)">
        Menampilkan <?= $pg['offset']+1 ?>–<?= min($pg['offset']+$pg['per_page'],$pg['total']) ?> dari <?= $pg['total'] ?> data
      </span>
      <div class="pagination">
        <?php if ($pg['current']>1): ?>
        <a href="?page=<?= $pg['current']-1 ?>&status=<?= $status ?>&search=<?= urlencode($search) ?>&date=<?= $date ?>">‹</a>
        <?php endif; ?>
        <?php for ($i=max(1,$pg['current']-2);$i<=min($pg['total_pages'],$pg['current']+2);$i++): ?>
        <a href="?page=<?= $i ?>&status=<?= $status ?>&search=<?= urlencode($search) ?>&date=<?= $date ?>" class="<?= $i===$pg['current']?'active':'' ?>"><?= $i ?></a>
        <?php endfor; ?>
        <?php if ($pg['current']<$pg['total_pages']): ?>
        <a href="?page=<?= $pg['current']+1 ?>&status=<?= $status ?>&search=<?= urlencode($search) ?>&date=<?= $date ?>">›</a>
        <?php endif; ?>
      </div>
    </div>
  </div>
  <?php endif; ?>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
