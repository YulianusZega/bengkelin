<?php
require_once __DIR__ . '/../../includes/auth.php';
requireLogin();

if (!isset($_GET['id'])) { header('Location: ' . BASE_URL . '/pages/customers/index.php'); exit; }
$id = (int)$_GET['id'];

$db       = getDB();
$customer = $db->prepare("SELECT * FROM customers WHERE id=?");
$customer->execute([$id]);
$customer = $customer->fetch();
if (!$customer) { header('Location: ' . BASE_URL . '/pages/customers/index.php'); exit; }

$vehicles = $db->prepare("SELECT * FROM vehicles WHERE customer_id=? ORDER BY created_at DESC");
$vehicles->execute([$id]);
$vehicles = $vehicles->fetchAll();

$workOrders = $db->prepare("
    SELECT wo.*, v.brand, v.model, v.plate_number, v.type AS vehicle_type
    FROM work_orders wo
    LEFT JOIN vehicles v ON wo.vehicle_id = v.id
    WHERE wo.customer_id = ?
    ORDER BY wo.created_at DESC LIMIT 20
");
$workOrders->execute([$id]);
$workOrders = $workOrders->fetchAll();

$totalSpent = $db->prepare("SELECT COALESCE(SUM(total),0) FROM work_orders WHERE customer_id=? AND payment_status='paid'");
$totalSpent->execute([$id]);
$totalSpent = (float)$totalSpent->fetchColumn();

$title = 'Detail Pelanggan';
$activePage = 'customers';
include __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
  <div class="page-header-left">
    <h1><?= htmlspecialchars($customer['name']) ?></h1>
    <p>Detail informasi, kendaraan, dan riwayat servis pelanggan</p>
  </div>
  <div class="page-header-right">
    <a href="<?= BASE_URL ?>/pages/customers/index.php" class="btn btn-outline"><i class="fas fa-arrow-left"></i> Kembali</a>
    <a href="<?= BASE_URL ?>/pages/work-orders/create.php?customer_id=<?= $id ?>" class="btn btn-primary"><i class="fas fa-plus"></i> Buat WO</a>
  </div>
</div>

<div class="grid-2 mb-24">
  <!-- Customer Info Card -->
  <div class="card">
    <div class="card-header">
      <div class="card-header-title"><i class="fas fa-user" style="color:var(--primary);margin-right:8px"></i>Informasi Pelanggan</div>
      <span class="badge badge-<?= $customer['segment'] ?>"><?= ucfirst($customer['segment']) ?></span>
    </div>
    <div class="card-body">
      <div style="display:flex;align-items:center;gap:16px;margin-bottom:20px">
        <div style="width:58px;height:58px;border-radius:50%;background:var(--primary-bg);color:var(--primary);font-size:22px;font-weight:700;display:flex;align-items:center;justify-content:center;flex-shrink:0">
          <?= strtoupper(substr($customer['name'],0,1)) ?>
        </div>
        <div>
          <div style="font-size:18px;font-weight:700"><?= htmlspecialchars($customer['name']) ?></div>
          <div style="font-size:12px;color:var(--text-muted)"><?= $customer['customer_code'] ?></div>
        </div>
      </div>
      <div class="info-row">
        <div class="info-item"><span class="info-label"><i class="fas fa-phone" style="width:14px"></i> Telepon</span><span class="info-value"><?= $customer['phone'] ?></span></div>
        <div class="info-item"><span class="info-label"><i class="fas fa-envelope" style="width:14px"></i> Email</span><span class="info-value"><?= $customer['email'] ?: '—' ?></span></div>
        <div class="info-item"><span class="info-label"><i class="fas fa-map-marker-alt" style="width:14px"></i> Alamat</span><span class="info-value"><?= $customer['address'] ? nl2br(htmlspecialchars($customer['address'])) : '—' ?></span></div>
        <div class="info-item"><span class="info-label"><i class="fas fa-calendar" style="width:14px"></i> Terdaftar</span><span class="info-value"><?= date('d M Y', strtotime($customer['created_at'])) ?></span></div>
      </div>
    </div>
  </div>

  <!-- Stats -->
  <div style="display:flex;flex-direction:column;gap:16px">
    <div class="stat-card">
      <div class="stat-icon green"><i class="fas fa-money-bill-wave"></i></div>
      <div class="stat-info"><div class="stat-label">Total Transaksi</div><div class="stat-value" style="font-size:20px"><?= formatRupiah($totalSpent) ?></div></div>
    </div>
    <div class="stat-card">
      <div class="stat-icon blue"><i class="fas fa-clipboard-list"></i></div>
      <div class="stat-info"><div class="stat-label">Kunjungan</div><div class="stat-value"><?= count($workOrders) ?>x</div></div>
    </div>
    <div class="stat-card">
      <div class="stat-icon orange"><i class="fas fa-car"></i></div>
      <div class="stat-info"><div class="stat-label">Kendaraan</div><div class="stat-value"><?= count($vehicles) ?> unit</div></div>
    </div>
  </div>
</div>

<!-- Vehicles -->
<div class="card mb-24">
  <div class="card-header">
    <div class="card-header-title"><i class="fas fa-car" style="color:var(--primary);margin-right:8px"></i>Kendaraan (<?= count($vehicles) ?>)</div>
    <button class="btn btn-primary btn-sm" onclick="window.location='<?= BASE_URL ?>/pages/vehicles/index.php?customer_id=<?= $id ?>'">
      <i class="fas fa-plus"></i> Tambah Kendaraan
    </button>
  </div>
  <div class="card-body" style="padding:12px 16px">
    <?php if (empty($vehicles)): ?>
    <p class="text-muted text-center" style="padding:20px 0">Belum ada kendaraan terdaftar</p>
    <?php else: foreach ($vehicles as $v): ?>
    <div style="display:flex;align-items:center;gap:14px;padding:12px 0;border-bottom:1px solid var(--border-light)">
      <div style="width:44px;height:44px;border-radius:10px;background:var(--<?= $v['type']==='mobil'?'info':'success' ?>-bg??var(--info-bg));background:<?= $v['type']==='mobil'?'var(--info-bg)':'var(--success-bg)' ?>;display:flex;align-items:center;justify-content:center;color:<?= $v['type']==='mobil'?'var(--info)':'var(--success)' ?>;font-size:18px;flex-shrink:0">
        <i class="fas <?= $v['type']==='mobil'?'fa-car':'fa-motorcycle' ?>"></i>
      </div>
      <div style="flex:1">
        <div style="font-weight:700"><?= $v['brand'] . ' ' . $v['model'] ?> <span style="color:var(--text-muted);font-weight:400;font-size:13px">(<?= $v['year'] ?>)</span></div>
        <div style="font-size:12px;color:var(--text-muted)"><?= $v['plate_number'] ?> · <?= $v['color'] ?> · <?= number_format($v['km_current']) ?> km</div>
      </div>
      <a href="<?= BASE_URL ?>/pages/work-orders/create.php?vehicle_id=<?= $v['id'] ?>&customer_id=<?= $id ?>" class="btn btn-primary btn-sm">Buat WO</a>
    </div>
    <?php endforeach; endif; ?>
  </div>
</div>

<!-- Work Order History -->
<div class="card">
  <div class="card-header">
    <div class="card-header-title"><i class="fas fa-history" style="color:var(--primary);margin-right:8px"></i>Riwayat Servis</div>
  </div>
  <div class="table-responsive">
    <table class="table">
      <thead><tr><th>No. WO</th><th>Tanggal</th><th>Kendaraan</th><th>Status</th><th>Pembayaran</th><th>Total</th><th></th></tr></thead>
      <tbody>
        <?php if (empty($workOrders)): ?>
        <tr><td colspan="7" class="text-center text-muted" style="padding:24px">Belum ada riwayat servis</td></tr>
        <?php else: foreach ($workOrders as $wo): ?>
        <tr>
          <td><span style="font-weight:600;color:var(--primary)"><?= $wo['wo_number'] ?></span></td>
          <td style="font-size:13px"><?= date('d M Y', strtotime($wo['created_at'])) ?></td>
          <td><div style="font-size:13px;font-weight:500"><?= $wo['brand'] . ' ' . $wo['model'] ?></div><div style="font-size:11px;color:var(--text-muted)"><?= $wo['plate_number'] ?></div></td>
          <td><span class="badge badge-<?= $wo['status'] ?>"><?= statusLabel($wo['status']) ?></span></td>
          <td><span class="badge badge-<?= $wo['payment_status'] ?>"><?= paymentLabel($wo['payment_status']) ?></span></td>
          <td style="font-weight:600"><?= formatRupiah((float)$wo['total']) ?></td>
          <td><a href="<?= BASE_URL ?>/pages/work-orders/view.php?id=<?= $wo['id'] ?>" class="btn btn-info btn-sm btn-icon"><i class="fas fa-eye"></i></a></td>
        </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
