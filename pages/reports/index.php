<?php
require_once __DIR__ . '/../../includes/auth.php';
requireLogin();
requireRole('owner','admin');

$title      = 'Laporan';
$activePage = 'reports';
$db         = getDB();

$month = $_GET['month'] ?? date('Y-m');
[$yr, $mo] = explode('-', $month);

// Revenue Summary
$revenue = $db->prepare("SELECT COALESCE(SUM(total),0) FROM work_orders WHERE payment_status='paid' AND YEAR(updated_at)=? AND MONTH(updated_at)=?");
$revenue->execute([$yr,$mo]);
$revenue = (float)$revenue->fetchColumn();

$totalWo = $db->prepare("SELECT COUNT(*) FROM work_orders WHERE YEAR(created_at)=? AND MONTH(created_at)=?");
$totalWo->execute([$yr,$mo]);
$totalWo = (int)$totalWo->fetchColumn();

$newCust = $db->prepare("SELECT COUNT(*) FROM customers WHERE YEAR(created_at)=? AND MONTH(created_at)=?");
$newCust->execute([$yr,$mo]);
$newCust = (int)$newCust->fetchColumn();

$doneWo = $db->prepare("SELECT COUNT(*) FROM work_orders WHERE status IN ('done','delivered') AND YEAR(updated_at)=? AND MONTH(updated_at)=?");
$doneWo->execute([$yr,$mo]);
$doneWo = (int)$doneWo->fetchColumn();

// Daily revenue chart
$dailyRev = $db->prepare("SELECT DAY(updated_at) AS day, SUM(total) AS rev FROM work_orders WHERE payment_status='paid' AND YEAR(updated_at)=? AND MONTH(updated_at)=? GROUP BY DAY(updated_at) ORDER BY day");
$dailyRev->execute([$yr,$mo]);
$dailyRevData = $dailyRev->fetchAll();
$daysInMonth  = cal_days_in_month(CAL_GREGORIAN, (int)$mo, (int)$yr);
$chartDays    = range(1, $daysInMonth);
$chartRevs    = array_fill(0, $daysInMonth, 0);
foreach ($dailyRevData as $row) { $chartRevs[$row['day']-1] = (float)$row['rev']; }

// Top services
$topSvc = $db->prepare("SELECT ws.service_name, COUNT(*) cnt, SUM(ws.price) rev FROM wo_services ws JOIN work_orders wo ON ws.wo_id=wo.id WHERE YEAR(wo.created_at)=? AND MONTH(wo.created_at)=? GROUP BY ws.service_name ORDER BY cnt DESC LIMIT 8");
$topSvc->execute([$yr,$mo]);
$topSvc = $topSvc->fetchAll();

// Top parts sold
$topParts = $db->prepare("SELECT wp.part_name, SUM(wp.quantity) qty, SUM(wp.subtotal) rev FROM wo_parts wp JOIN work_orders wo ON wp.wo_id=wo.id WHERE YEAR(wo.created_at)=? AND MONTH(wo.created_at)=? GROUP BY wp.part_name ORDER BY qty DESC LIMIT 8");
$topParts->execute([$yr,$mo]);
$topParts = $topParts->fetchAll();

// WO list for this month
$woList = $db->prepare("SELECT wo.id AS wo_id, wo.*, c.name AS customer_name, v.plate_number, v.brand, v.model FROM work_orders wo LEFT JOIN customers c ON wo.customer_id=c.id LEFT JOIN vehicles v ON wo.vehicle_id=v.id WHERE YEAR(wo.created_at)=? AND MONTH(wo.created_at)=? ORDER BY wo.created_at DESC");
$woList->execute([$yr,$mo]);
$woList = $woList->fetchAll();

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
  <div class="page-header-left">
    <h1>Laporan Bulanan</h1>
    <p>Ringkasan performa bengkel bulan <?= date('F Y', mktime(0,0,0,(int)$mo,1,(int)$yr)) ?></p>
  </div>
  <div class="page-header-right">
    <a href="<?= BASE_URL ?>/pages/reports/export.php?type=monthly&month=<?= $month ?>" class="btn btn-outline"><i class="fas fa-file-csv"></i> Export CSV</a>
    <a href="<?= BASE_URL ?>/pages/reports/mechanic.php?month=<?= $month ?>" class="btn btn-outline"><i class="fas fa-user-cog"></i> Performa Mekanik</a>
    <form method="GET" class="d-flex gap-8 align-center" style="display:inline-flex">
      <input type="month" name="month" class="form-control" value="<?= $month ?>" style="width:180px">
      <button type="submit" class="btn btn-primary">Tampilkan</button>
    </form>
  </div>
</div>

<!-- STATS -->
<div class="stats-grid mb-24">
  <div class="stat-card">
    <div class="stat-icon green"><i class="fas fa-money-bill-wave"></i></div>
    <div class="stat-info"><div class="stat-label">Total Pendapatan</div><div class="stat-value" style="font-size:20px"><?= formatRupiah($revenue) ?></div></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon blue"><i class="fas fa-clipboard-list"></i></div>
    <div class="stat-info"><div class="stat-label">Total Work Order</div><div class="stat-value"><?= $totalWo ?></div><div class="stat-change neutral">Selesai: <?= $doneWo ?></div></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon orange"><i class="fas fa-users"></i></div>
    <div class="stat-info"><div class="stat-label">Pelanggan Baru</div><div class="stat-value"><?= $newCust ?></div></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon purple"><i class="fas fa-chart-line"></i></div>
    <div class="stat-info"><div class="stat-label">Rata-rata per WO</div><div class="stat-value" style="font-size:18px"><?= $totalWo ? formatRupiah($revenue/$totalWo) : 'Rp 0' ?></div></div>
  </div>
</div>

<!-- REVENUE CHART -->
<div class="card mb-24">
  <div class="card-header"><div class="card-header-title"><i class="fas fa-chart-bar" style="color:var(--primary);margin-right:8px"></i>Pendapatan Harian — <?= date('F Y', mktime(0,0,0,(int)$mo,1,(int)$yr)) ?></div></div>
  <div class="card-body"><canvas id="dailyChart" height="120"></canvas></div>
</div>

<div class="grid-2 mb-24">
  <!-- Top Services -->
  <div class="card">
    <div class="card-header"><div class="card-header-title"><i class="fas fa-star" style="color:var(--warning);margin-right:8px"></i>Layanan Terlaris</div></div>
    <div class="table-responsive">
      <table class="table">
        <thead><tr><th>#</th><th>Layanan</th><th style="text-align:center">Qty</th><th style="text-align:right">Pendapatan</th></tr></thead>
        <tbody>
          <?php if (empty($topSvc)): ?>
          <tr><td colspan="4" class="text-center text-muted" style="padding:24px">Belum ada data</td></tr>
          <?php else: foreach ($topSvc as $i=>$s): ?>
          <tr>
            <td><strong style="color:var(--primary)"><?= $i+1 ?></strong></td>
            <td><?= htmlspecialchars($s['service_name']) ?></td>
            <td style="text-align:center;font-weight:600"><?= $s['cnt'] ?>x</td>
            <td style="text-align:right;font-weight:600;color:var(--success)"><?= formatRupiah((float)$s['rev']) ?></td>
          </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Top Parts -->
  <div class="card">
    <div class="card-header"><div class="card-header-title"><i class="fas fa-box" style="color:var(--info);margin-right:8px"></i>Part Terbanyak Digunakan</div></div>
    <div class="table-responsive">
      <table class="table">
        <thead><tr><th>#</th><th>Part</th><th style="text-align:center">Qty</th><th style="text-align:right">Nilai</th></tr></thead>
        <tbody>
          <?php if (empty($topParts)): ?>
          <tr><td colspan="4" class="text-center text-muted" style="padding:24px">Belum ada data</td></tr>
          <?php else: foreach ($topParts as $i=>$p): ?>
          <tr>
            <td><strong style="color:var(--info)"><?= $i+1 ?></strong></td>
            <td><?= htmlspecialchars($p['part_name']) ?></td>
            <td style="text-align:center;font-weight:600"><?= $p['qty'] ?></td>
            <td style="text-align:right;font-weight:600;color:var(--success)"><?= formatRupiah((float)$p['rev']) ?></td>
          </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- WO LIST FOR MONTH -->
<div class="card">
  <div class="card-header">
    <div class="card-header-title"><i class="fas fa-list" style="color:var(--primary);margin-right:8px"></i>Semua Work Order — <?= date('F Y', mktime(0,0,0,(int)$mo,1,(int)$yr)) ?></div>
    <span style="font-size:12px;color:var(--text-muted)"><?= count($woList) ?> WO</span>
  </div>
  <div class="table-responsive">
    <table class="table">
      <thead><tr><th>No. WO</th><th>Tanggal</th><th>Pelanggan</th><th>Kendaraan</th><th>Status</th><th>Pembayaran</th><th style="text-align:right">Total</th></tr></thead>
      <tbody>
        <?php if (empty($woList)): ?>
        <tr><td colspan="7" class="text-center text-muted" style="padding:24px">Belum ada work order bulan ini</td></tr>
        <?php else: foreach ($woList as $wo): ?>
        <tr style="cursor:pointer" onclick="window.location='<?= BASE_URL ?>/pages/work-orders/view.php?id=<?= $wo['wo_id'] ?>'">
          <td><span style="font-weight:700;color:var(--primary);font-size:12px"><?= $wo['wo_number'] ?></span></td>
          <td style="font-size:12px"><?= date('d M', strtotime($wo['created_at'])) ?></td>
          <td><?= htmlspecialchars($wo['customer_name']) ?></td>
          <td style="font-size:13px"><?= $wo['brand'].' '.$wo['model'] ?> · <?= $wo['plate_number'] ?></td>
          <td><span class="badge badge-<?= $wo['status'] ?>"><?= statusLabel($wo['status']) ?></span></td>
          <td><span class="badge badge-<?= $wo['payment_status'] ?>"><?= paymentLabel($wo['payment_status']) ?></span></td>
          <td style="text-align:right;font-weight:700"><?= formatRupiah((float)$wo['total']) ?></td>
        </tr>
        <?php endforeach; endif; ?>
      </tbody>
      <?php if (!empty($woList)): ?>
      <tfoot>
        <tr style="background:var(--border-light);font-weight:700">
          <td colspan="6" style="padding:12px 14px">TOTAL PENDAPATAN (LUNAS)</td>
          <td style="text-align:right;padding:12px 14px;color:var(--success)"><?= formatRupiah($revenue) ?></td>
        </tr>
      </tfoot>
      <?php endif; ?>
    </table>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
const dailyCtx = document.getElementById('dailyChart').getContext('2d');
new Chart(dailyCtx, {
  type: 'bar',
  data: {
    labels: <?= json_encode(array_map(fn($d)=>"{$d}/".str_pad($mo,2,'0',STR_PAD_LEFT), $chartDays)) ?>,
    datasets: [{
      label: 'Pendapatan',
      data: <?= json_encode($chartRevs) ?>,
      backgroundColor: 'rgba(255,107,43,0.12)',
      borderColor: '#FF6B2B',
      borderWidth: 2,
      borderRadius: 4,
      borderSkipped: false,
    }]
  },
  options: {
    responsive: true,
    plugins: { legend:{display:false}, tooltip:{ callbacks:{ label: ctx=>' Rp '+new Intl.NumberFormat('id-ID').format(ctx.raw) } } },
    scales: {
      x: { grid:{display:false}, ticks:{font:{size:10},maxTicksLimit:16} },
      y: { ticks:{ callback: v=>'Rp '+new Intl.NumberFormat('id-ID').format(v), font:{size:10} } }
    }
  }
});
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
