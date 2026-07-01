<?php
// ============================================================
// BENGKELIN - Mechanic Performance Report
// ============================================================
require_once __DIR__ . '/../../includes/auth.php';
requireLogin();
requireRole('owner','admin');

$title      = 'Performa Mekanik';
$activePage = 'reports';
$db         = getDB();

$month = $_GET['month'] ?? date('Y-m');
[$yr, $mo] = explode('-', $month);

// Mechanic performance data
$mechanics = $db->prepare("
    SELECT e.id, e.name, e.specialization, e.employee_id,
           COUNT(DISTINCT wo.id) AS total_wo,
           COUNT(DISTINCT CASE WHEN wo.status IN ('done','delivered') THEN wo.id END) AS completed_wo,
           COUNT(DISTINCT CASE WHEN wo.status = 'cancelled' THEN wo.id END) AS cancelled_wo,
           COALESCE(SUM(DISTINCT CASE WHEN wo.payment_status='paid' THEN wo.total ELSE 0 END),0) AS revenue,
           COALESCE(SUM(DISTINCT wo.subtotal_services),0) AS service_revenue,
           COALESCE(AVG(CASE WHEN wo.actual_finish IS NOT NULL AND wo.check_in_at IS NOT NULL 
               THEN TIMESTAMPDIFF(HOUR, wo.check_in_at, wo.actual_finish) END), 0) AS avg_hours,
           e.commission_rate
    FROM employees e
    LEFT JOIN (
        SELECT id, status, payment_status, total, subtotal_services, actual_finish, check_in_at, created_at, mechanic_id, NULL as assistant_id 
        FROM work_orders
        UNION ALL
        SELECT w.id, w.status, w.payment_status, w.total, w.subtotal_services, w.actual_finish, w.check_in_at, w.created_at, NULL as mechanic_id, wa.employee_id as assistant_id
        FROM work_orders w
        JOIN wo_assistants wa ON w.id = wa.wo_id
    ) wo ON (e.id = wo.mechanic_id OR e.id = wo.assistant_id) 
        AND YEAR(wo.created_at)=? AND MONTH(wo.created_at)=?
    WHERE e.position IN ('mekanik','kabeng') AND e.status = 'active'
    GROUP BY e.id, e.name, e.specialization, e.employee_id, e.commission_rate
    ORDER BY total_wo DESC, revenue DESC
");
$mechanics->execute([$yr, $mo]);
$mechanics = $mechanics->fetchAll();

// Total summary
$totalWO     = array_sum(array_column($mechanics, 'total_wo'));
$totalRev    = array_sum(array_column($mechanics, 'revenue'));
$totalDone   = array_sum(array_column($mechanics, 'completed_wo'));
$bestMech    = !empty($mechanics) ? $mechanics[0]['name'] : '—';

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
  <div class="page-header-left">
    <h1>Performa Mekanik</h1>
    <p>Analitik kinerja mekanik — <?= date('F Y', mktime(0,0,0,(int)$mo,1,(int)$yr)) ?></p>
  </div>
  <div class="page-header-right">
    <a href="<?= BASE_URL ?>/pages/reports/export.php?type=mechanic&month=<?= $month ?>" class="btn btn-outline"><i class="fas fa-file-csv"></i> Export CSV</a>
    <a href="<?= BASE_URL ?>/pages/reports/index.php?month=<?= $month ?>" class="btn btn-outline"><i class="fas fa-arrow-left"></i> Laporan Utama</a>
    <form method="GET" class="d-flex gap-8 align-center" style="display:inline-flex">
      <input type="month" name="month" class="form-control" value="<?= $month ?>" style="width:180px">
      <button type="submit" class="btn btn-primary">Tampilkan</button>
    </form>
  </div>
</div>

<!-- SUMMARY STATS -->
<div class="stats-grid mb-24" style="grid-template-columns:repeat(4,1fr)">
  <div class="stat-card">
    <div class="stat-icon blue"><i class="fas fa-users"></i></div>
    <div class="stat-info"><div class="stat-label">Total Mekanik Aktif</div><div class="stat-value"><?= count($mechanics) ?></div></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon orange"><i class="fas fa-clipboard-list"></i></div>
    <div class="stat-info"><div class="stat-label">Total WO Ditangani</div><div class="stat-value"><?= $totalWO ?></div></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon green"><i class="fas fa-money-bill-wave"></i></div>
    <div class="stat-info"><div class="stat-label">Revenue dari Mekanik</div><div class="stat-value" style="font-size:16px"><?= formatRupiah($totalRev) ?></div></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon purple"><i class="fas fa-trophy"></i></div>
    <div class="stat-info"><div class="stat-label">Mekanik Terbaik</div><div class="stat-value" style="font-size:16px"><?= htmlspecialchars($bestMech) ?></div></div>
  </div>
</div>

<!-- MECHANIC LEADERBOARD -->
<div class="card mb-24">
  <div class="card-header">
    <div class="card-header-title"><i class="fas fa-trophy" style="color:var(--warning);margin-right:8px"></i>Leaderboard Mekanik</div>
  </div>
  <div class="card-body" style="padding:20px">
    <?php if (empty($mechanics)): ?>
    <div class="empty-state"><i class="fas fa-users"></i><h3>Belum ada data mekanik</h3></div>
    <?php else: ?>
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(320px,1fr));gap:16px">
      <?php foreach ($mechanics as $i => $m):
        $completionRate = $m['total_wo'] > 0 ? round(($m['completed_wo'] / $m['total_wo']) * 100) : 0;
        $medalColors = ['#FFD700','#C0C0C0','#CD7F32'];
        $medalColor = $medalColors[$i] ?? 'var(--text-muted)';
        $commission = $m['commission_rate'] > 0 ? ($m['service_revenue'] * $m['commission_rate'] / 100) : 0;
      ?>
      <div style="background:var(--bg);border-radius:14px;padding:20px;border:1px solid var(--border-light);position:relative;overflow:hidden">
        <?php if ($i < 3 && $m['total_wo'] > 0): ?>
        <div style="position:absolute;top:-2px;right:14px;width:26px;height:38px;background:<?= $medalColor ?>;clip-path:polygon(0 0,100% 0,100% 100%,50% 75%,0 100%);display:flex;align-items:flex-start;justify-content:center;padding-top:4px;font-size:11px;color:#fff;font-weight:800">
          <?= $i + 1 ?>
        </div>
        <?php endif; ?>
        
        <div style="display:flex;align-items:center;gap:14px;margin-bottom:16px">
          <div style="width:52px;height:52px;border-radius:50%;background:var(--primary);display:flex;align-items:center;justify-content:center;color:#fff;font-size:22px;font-weight:700;flex-shrink:0">
            <?= strtoupper(substr($m['name'], 0, 1)) ?>
          </div>
          <div>
            <div style="font-weight:700;font-size:16px"><?= htmlspecialchars($m['name']) ?></div>
            <div style="font-size:12px;color:var(--text-muted)"><?= $m['specialization'] ?: $m['employee_id'] ?></div>
          </div>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:14px">
          <div style="background:#fff;border-radius:10px;padding:10px 14px;text-align:center">
            <div style="font-size:11px;color:var(--text-muted);font-weight:600">WORK ORDER</div>
            <div style="font-size:22px;font-weight:800;color:var(--text-primary)"><?= $m['total_wo'] ?></div>
            <div style="font-size:11px;color:var(--success)">✅ <?= $m['completed_wo'] ?> selesai</div>
          </div>
          <div style="background:#fff;border-radius:10px;padding:10px 14px;text-align:center">
            <div style="font-size:11px;color:var(--text-muted);font-weight:600">REVENUE</div>
            <div style="font-size:16px;font-weight:800;color:var(--success)"><?= formatRupiah($m['revenue']) ?></div>
            <div style="font-size:11px;color:var(--text-muted)"><?= $m['total_wo'] > 0 ? formatRupiah($m['revenue']/$m['total_wo']) . '/WO' : '—' ?></div>
          </div>
        </div>

        <!-- Completion Rate Bar -->
        <div style="margin-bottom:10px">
          <div style="display:flex;justify-content:space-between;font-size:12px;margin-bottom:4px">
            <span style="font-weight:600">Completion Rate</span>
            <span style="font-weight:700;color:<?= $completionRate >= 80 ? 'var(--success)' : ($completionRate >= 50 ? 'var(--warning)' : 'var(--danger)') ?>"><?= $completionRate ?>%</span>
          </div>
          <div style="width:100%;height:6px;background:var(--border);border-radius:3px;overflow:hidden">
            <div style="height:100%;width:<?= $completionRate ?>%;background:<?= $completionRate >= 80 ? 'var(--success)' : ($completionRate >= 50 ? 'var(--warning)' : 'var(--danger)') ?>;border-radius:3px;transition:width .5s"></div>
          </div>
        </div>

        <div style="display:flex;justify-content:space-between;font-size:12px;color:var(--text-secondary)">
          <span><i class="fas fa-clock"></i> Avg: <?= round($m['avg_hours'],1) ?> jam</span>
          <?php if ($commission > 0): ?>
          <span style="color:var(--primary);font-weight:600"><i class="fas fa-coins"></i> Komisi: <?= formatRupiah($commission) ?></span>
          <?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
</div>

<!-- CHART -->
<div class="card">
  <div class="card-header">
    <div class="card-header-title"><i class="fas fa-chart-bar" style="color:var(--primary);margin-right:8px"></i>Perbandingan WO per Mekanik</div>
  </div>
  <div class="card-body">
    <canvas id="mechChart" height="100"></canvas>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
const mechCtx = document.getElementById('mechChart').getContext('2d');
new Chart(mechCtx, {
  type: 'bar',
  data: {
    labels: <?= json_encode(array_column($mechanics, 'name')) ?>,
    datasets: [
      {
        label: 'Selesai',
        data: <?= json_encode(array_map('intval', array_column($mechanics, 'completed_wo'))) ?>,
        backgroundColor: 'rgba(16,185,129,0.7)',
        borderRadius: 4,
      },
      {
        label: 'Dalam Proses',
        data: <?= json_encode(array_map(fn($m) => $m['total_wo'] - $m['completed_wo'] - $m['cancelled_wo'], $mechanics)) ?>,
        backgroundColor: 'rgba(255,107,43,0.7)',
        borderRadius: 4,
      },
      {
        label: 'Dibatalkan',
        data: <?= json_encode(array_map('intval', array_column($mechanics, 'cancelled_wo'))) ?>,
        backgroundColor: 'rgba(239,68,68,0.5)',
        borderRadius: 4,
      }
    ]
  },
  options: {
    responsive: true,
    plugins: { legend:{position:'top'} },
    scales: {
      x: { stacked: true, grid:{display:false} },
      y: { stacked: true, beginAtZero: true, ticks:{ stepSize:1 } }
    }
  }
});
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
