<?php
require_once __DIR__ . '/includes/auth.php';
requireLogin();

$title      = 'Dashboard';
$pageTitle  = 'Dashboard';
$activePage = 'dashboard';

$db = getDB();

// ── STAT CARDS ────────────────────────────────────────────────────────
$todayRevenue  = $db->query("SELECT COALESCE(SUM(total),0) FROM work_orders WHERE DATE(updated_at)=CURDATE() AND payment_status='paid'")->fetchColumn();
$monthRevenue  = $db->query("SELECT COALESCE(SUM(total),0) FROM work_orders WHERE MONTH(updated_at)=MONTH(NOW()) AND YEAR(updated_at)=YEAR(NOW()) AND payment_status='paid'")->fetchColumn();
$lastMonthRevenue = $db->query("SELECT COALESCE(SUM(total),0) FROM work_orders WHERE MONTH(updated_at)=MONTH(NOW() - INTERVAL 1 MONTH) AND YEAR(updated_at)=YEAR(NOW() - INTERVAL 1 MONTH) AND payment_status='paid'")->fetchColumn();
$activeWo      = $db->query("SELECT COUNT(*) FROM work_orders WHERE status NOT IN ('done','delivered','cancelled')")->fetchColumn();
$todayWo       = $db->query("SELECT COUNT(*) FROM work_orders WHERE DATE(created_at)=CURDATE()")->fetchColumn();
$totalCustomer = $db->query("SELECT COUNT(*) FROM customers")->fetchColumn();
$newCustomer   = $db->query("SELECT COUNT(*) FROM customers WHERE MONTH(created_at)=MONTH(NOW()) AND YEAR(created_at)=YEAR(NOW())")->fetchColumn();
$pendingBook   = $db->query("SELECT COUNT(*) FROM bookings WHERE status='pending'")->fetchColumn();
$lowStock      = $db->query("SELECT COUNT(*) FROM parts WHERE stock <= min_stock")->fetchColumn();

// Revenue Comparison
$revenueDiff = $monthRevenue - $lastMonthRevenue;
$revenueDiffPercent = $lastMonthRevenue > 0 ? ($revenueDiff / $lastMonthRevenue) * 100 : ($monthRevenue > 0 ? 100 : 0);
$revenueChangeClass = $revenueDiff >= 0 ? 'up' : 'down';
$revenueChangeIcon = $revenueDiff >= 0 ? 'fa-arrow-up' : 'fa-arrow-down';

// Dynamic Greeting
$hour = date('H');
if ($hour < 11) $greeting = "Selamat Pagi";
elseif ($hour < 15) $greeting = "Selamat Siang";
elseif ($hour < 18) $greeting = "Selamat Sore";
else $greeting = "Selamat Malam";

// Mechanic Workload
$mechanicWorkload = $db->query("
    SELECT e.name, COUNT(wo.id) as active_wo
    FROM employees e
    LEFT JOIN work_orders wo ON e.id = wo.mechanic_id AND wo.status NOT IN ('done','delivered','cancelled')
    WHERE e.position = 'junior_teknisi' AND e.status = 'active'
    GROUP BY e.id
    ORDER BY active_wo DESC
")->fetchAll();

// ── RECENT WORK ORDERS ────────────────────────────────────────────────
$recentWo = $db->query("
    SELECT wo.id AS wo_id, wo.*, c.name AS customer_name, v.plate_number, v.brand, v.model, v.type AS vehicle_type,
           e.name AS mechanic_name
    FROM work_orders wo
    LEFT JOIN customers c ON wo.customer_id = c.id
    LEFT JOIN vehicles v ON wo.vehicle_id = v.id
    LEFT JOIN employees e ON wo.mechanic_id = e.id
    ORDER BY wo.created_at DESC LIMIT 8
")->fetchAll();

// ── ACTIVE WO BY STATUS ───────────────────────────────────────────────
$woByStatus = $db->query("
    SELECT status, COUNT(*) as cnt FROM work_orders
    WHERE status NOT IN ('delivered','cancelled')
    GROUP BY status
")->fetchAll(PDO::FETCH_KEY_PAIR);

// ── PENDING BOOKINGS ──────────────────────────────────────────────────
$pendingBookings = $db->query("
    SELECT * FROM bookings WHERE status='pending'
    ORDER BY preferred_date ASC, preferred_time ASC LIMIT 5
")->fetchAll();

// ── TOP SERVICES (this month) ─────────────────────────────────────────
$topServices = $db->query("
    SELECT ws.service_name, COUNT(*) as cnt, SUM(ws.price) as revenue
    FROM wo_services ws
    JOIN work_orders wo ON ws.wo_id = wo.id
    WHERE MONTH(wo.created_at)=MONTH(NOW()) AND YEAR(wo.created_at)=YEAR(NOW())
    GROUP BY ws.service_name ORDER BY cnt DESC LIMIT 5
")->fetchAll();

// ── REVENUE LAST 7 DAYS ───────────────────────────────────────────────
$revenueChart = $db->query("
    SELECT DATE(updated_at) as day, SUM(total) as revenue
    FROM work_orders
    WHERE payment_status='paid' AND updated_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    GROUP BY DATE(updated_at) ORDER BY day ASC
")->fetchAll();

$chartDays = []; $chartRevenue = [];
for ($i = 6; $i >= 0; $i--) {
    $day = date('Y-m-d', strtotime("-{$i} days"));
    $chartDays[] = date('d/m', strtotime($day));
    $found = array_filter($revenueChart, fn($r) => $r['day'] === $day);
    $chartRevenue[] = $found ? array_values($found)[0]['revenue'] : 0;
}

include __DIR__ . '/includes/header.php';
?>

<!-- ── QUICK ACTIONS & GREETING ── -->
<div class="mb-24" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:16px;">
  <div>
    <h2 style="font-size:24px; font-weight:700; margin-bottom:4px;"><?= $greeting ?>, <?= htmlspecialchars(explode(' ', $user['name'])[0]) ?>! 👋</h2>
    <p style="color:var(--text-secondary); font-size:14px;">Berikut adalah ringkasan performa bengkel hari ini.</p>
  </div>
  <div style="display:flex; gap:12px;">
    <a href="<?= BASE_URL ?>/pages/work-orders/create.php" class="btn btn-primary" style="box-shadow: 0 4px 15px rgba(255,107,43,.3);"><i class="fas fa-plus"></i> Buat WO</a>
    <a href="<?= BASE_URL ?>/pages/bookings/index.php" class="btn btn-white"><i class="fas fa-calendar-alt" style="color:var(--info);"></i> Cek Booking</a>
    <a href="<?= BASE_URL ?>/pages/inventory/index.php?stock=low" class="btn btn-white"><i class="fas fa-boxes" style="color:var(--danger);"></i> Stok Menipis</a>
  </div>
</div>

<!-- ── STAT CARDS ── -->
<div class="stats-grid">
  <div class="stat-card glass-effect">
    <div class="stat-icon orange"><i class="fas fa-money-bill-wave"></i></div>
    <div class="stat-info">
      <div class="stat-label">Pendapatan Bulan Ini</div>
      <div class="stat-value" style="font-size:18px"><?= formatRupiah((float)$monthRevenue) ?></div>
      <div class="stat-change <?= $revenueChangeClass ?>">
        <i class="fas <?= $revenueChangeIcon ?>"></i> <?= number_format(abs($revenueDiffPercent), 1) ?>% vs Bulan Lalu
      </div>
    </div>
  </div>
  <div class="stat-card glass-effect">
    <div class="stat-icon blue"><i class="fas fa-clipboard-list"></i></div>
    <div class="stat-info">
      <div class="stat-label">Work Order Aktif</div>
      <div class="stat-value"><?= $activeWo ?></div>
      <div class="stat-change neutral"><i class="fas fa-plus-circle"></i> Hari ini: <?= $todayWo ?> WO baru</div>
    </div>
  </div>
  <div class="stat-card glass-effect">
    <div class="stat-icon green"><i class="fas fa-users"></i></div>
    <div class="stat-info">
      <div class="stat-label">Total Pelanggan</div>
      <div class="stat-value"><?= $totalCustomer ?></div>
      <div class="stat-change up"><i class="fas fa-arrow-up"></i> +<?= $newCustomer ?> bulan ini</div>
    </div>
  </div>
  <div class="stat-card glass-effect">
    <div class="stat-icon yellow"><i class="fas fa-calendar-check"></i></div>
    <div class="stat-info">
      <div class="stat-label">Booking Menunggu</div>
      <div class="stat-value"><?= $pendingBook ?></div>
      <div class="stat-change <?= $pendingBook > 0 ? 'down' : 'neutral' ?>">
        <?= $pendingBook > 0 ? '<i class="fas fa-exclamation-circle"></i> Perlu konfirmasi' : '<i class="fas fa-check-circle"></i> Semua terkonfirmasi' ?>
      </div>
    </div>
  </div>
  <div class="stat-card glass-effect">
    <div class="stat-icon red"><i class="fas fa-boxes"></i></div>
    <div class="stat-info">
      <div class="stat-label">Stok Menipis</div>
      <div class="stat-value"><?= $lowStock ?></div>
      <div class="stat-change <?= $lowStock > 0 ? 'down' : 'neutral' ?>">
        <?= $lowStock > 0 ? '<i class="fas fa-exclamation-triangle"></i> Perlu restock' : '<i class="fas fa-check-circle"></i> Semua aman' ?>
      </div>
    </div>
  </div>
</div>

<!-- ── CHARTS + PENDING BOOKING ROW ── -->
<div class="grid-2 mb-24">

  <!-- Revenue Chart -->
  <div class="card">
    <div class="card-header">
      <div class="card-header-title"><i class="fas fa-chart-line" style="color:var(--primary);margin-right:8px"></i>Pendapatan 7 Hari Terakhir</div>
    </div>
    <div class="card-body">
      <canvas id="revenueChart" height="180"></canvas>
    </div>
  </div>

  <!-- WO Status Distribution -->
  <div class="card">
    <div class="card-header">
      <div class="card-header-title"><i class="fas fa-chart-donut" style="color:var(--primary);margin-right:8px"></i>Distribusi Status WO</div>
    </div>
    <div class="card-body">
      <div style="display:flex;align-items:center;gap:16px;flex-wrap:wrap">
        <canvas id="statusChart" width="160" height="160" style="flex-shrink:0"></canvas>
        <div id="status-legend" style="flex:1;display:flex;flex-direction:column;gap:8px;font-size:13px"></div>
      </div>
    </div>
  </div>

</div>

<!-- ── WORK ORDERS + TOP SERVICES ROW ── -->
<div class="grid-2 mb-24">

  <!-- Recent Work Orders -->
  <div class="card">
    <div class="card-header">
      <div class="card-header-title"><i class="fas fa-clipboard-list" style="color:var(--primary);margin-right:8px"></i>Work Order Terbaru</div>
      <a href="<?= BASE_URL ?>/pages/work-orders/index.php" class="btn btn-outline btn-sm">Lihat Semua</a>
    </div>
    <div class="table-responsive">
      <table class="table" id="recent-wo-table">
        <thead>
          <tr>
            <th>No. WO</th>
            <th>Pelanggan</th>
            <th>Kendaraan</th>
            <th>Status</th>
            <th>Total</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($recentWo)): ?>
          <tr><td colspan="5" class="text-center text-muted" style="padding:30px">Belum ada work order</td></tr>
          <?php else: foreach ($recentWo as $wo): ?>
          <tr style="cursor:pointer" onclick="window.location='<?= BASE_URL ?>/pages/work-orders/view.php?id=<?= $wo['wo_id'] ?>'">
            <td>
              <span style="font-weight:600;color:var(--primary);font-size:12px"><?= $wo['wo_number'] ?></span>
              <?php if ($wo['priority']==='urgent'): ?><br><span class="badge badge-urgent" style="font-size:10px">URGENT</span><?php endif; ?>
            </td>
            <td>
              <div style="font-weight:600;font-size:13px"><?= htmlspecialchars($wo['customer_name']) ?></div>
              <div class="text-muted" style="font-size:11px"><?= $wo['mechanic_name'] ? '⚙ '.$wo['mechanic_name'] : '— belum ditugaskan' ?></div>
            </td>
            <td>
              <div style="font-size:13px;font-weight:500"><?= $wo['brand'] . ' ' . $wo['model'] ?></div>
              <div class="text-muted" style="font-size:11px">
                <i class="fas <?= $wo['vehicle_type']==='mobil'?'fa-car':'fa-motorcycle' ?>" style="color:var(--<?= $wo['vehicle_type']==='mobil'?'info':'success' ?>)"></i>
                <?= $wo['plate_number'] ?>
              </div>
            </td>
            <td><span class="badge badge-<?= $wo['status'] ?>"><?= statusLabel($wo['status']) ?></span></td>
            <td style="font-weight:600;font-size:13px;white-space:nowrap"><?= formatRupiah((float)$wo['total']) ?></td>
          </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Top Services + Pending Bookings -->
  <div style="display:flex;flex-direction:column;gap:20px">

    <!-- Top Services -->
    <div class="card">
      <div class="card-header">
        <div class="card-header-title"><i class="fas fa-star" style="color:var(--warning);margin-right:8px"></i>Layanan Terlaris Bulan Ini</div>
      </div>
      <div class="card-body" style="padding:12px 16px">
        <?php if (empty($topServices)): ?>
        <p class="text-muted text-center" style="padding:20px 0;font-size:13px">Belum ada data bulan ini</p>
        <?php else: foreach ($topServices as $i => $svc): ?>
        <div style="display:flex;align-items:center;gap:12px;padding:10px 0;border-bottom:1px solid var(--border-light)">
          <div style="width:24px;height:24px;border-radius:6px;background:var(--primary-bg);color:var(--primary);font-weight:700;font-size:12px;display:flex;align-items:center;justify-content:center;flex-shrink:0"><?= $i+1 ?></div>
          <div style="flex:1;min-width:0">
            <div style="font-weight:600;font-size:13px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?= htmlspecialchars($svc['service_name']) ?></div>
            <div style="font-size:11px;color:var(--text-muted)"><?= $svc['cnt'] ?>x dikerjakan</div>
          </div>
          <div style="font-weight:700;font-size:13px;white-space:nowrap;color:var(--success)"><?= formatRupiah((float)$svc['revenue']) ?></div>
        </div>
        <?php endforeach; endif; ?>
      </div>
    </div>

    <!-- Pending Bookings -->
    <?php if ($pendingBook > 0): ?>
    <div class="card" style="border:1px solid #FDE68A">
      <div class="card-header" style="background:var(--warning-bg)">
        <div class="card-header-title" style="color:#92400E"><i class="fas fa-bell fa-beat" style="color:var(--warning);margin-right:8px"></i>Booking Belum Dikonfirmasi (<?= $pendingBook ?>)</div>
        <a href="<?= BASE_URL ?>/pages/bookings/index.php" class="btn btn-warning btn-sm">Kelola</a>
      </div>
      <div class="card-body" style="padding:12px 16px">
        <?php foreach ($pendingBookings as $b): ?>
        <div style="display:flex;align-items:center;gap:12px;padding:8px 0;border-bottom:1px solid var(--border-light)">
          <div style="width:36px;height:36px;border-radius:8px;background:var(--warning-bg);color:var(--warning);display:flex;align-items:center;justify-content:center;flex-shrink:0">
            <i class="fas fa-calendar"></i>
          </div>
          <div style="flex:1">
            <div style="font-weight:600;font-size:13px"><?= htmlspecialchars($b['customer_name']) ?></div>
            <div style="font-size:11px;color:var(--text-muted)">
              <?= date('d M Y', strtotime($b['preferred_date'])) ?> · <?= substr($b['preferred_time'],0,5) ?> · <?= $b['vehicle_brand'] . ' ' . $b['vehicle_model'] ?>
            </div>
          </div>
          <a href="<?= BASE_URL ?>/pages/bookings/index.php?id=<?= $b['id'] ?>" class="btn btn-warning btn-sm">Konfirmasi</a>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

    <!-- Mechanic Workload -->
    <div class="card">
      <div class="card-header">
        <div class="card-header-title"><i class="fas fa-users-cog" style="color:var(--info);margin-right:8px"></i>Beban Kerja Mekanik</div>
      </div>
      <div class="card-body" style="padding:12px 16px">
        <?php if (empty($mechanicWorkload)): ?>
        <p class="text-muted text-center" style="padding:20px 0;font-size:13px">Belum ada data mekanik</p>
        <?php else: foreach ($mechanicWorkload as $mech): 
          $pct = min(100, ($mech['active_wo'] / max(1, 5)) * 100);
          $color = $mech['active_wo'] >= 4 ? 'var(--danger)' : ($mech['active_wo'] >= 2 ? 'var(--warning)' : 'var(--success)');
        ?>
        <div style="margin-bottom:12px">
          <div style="display:flex;justify-content:space-between;margin-bottom:4px;font-size:12px">
            <span style="font-weight:600"><?= htmlspecialchars($mech['name']) ?></span>
            <span style="color:var(--text-muted)"><?= $mech['active_wo'] ?> WO Aktif</span>
          </div>
          <div style="width:100%;height:6px;background:var(--border-light);border-radius:3px;overflow:hidden">
            <div style="width:<?= $pct ?>%;height:100%;background:<?= $color ?>;border-radius:3px;transition:width 1s ease"></div>
          </div>
        </div>
        <?php endforeach; endif; ?>
      </div>
    </div>

  </div>
</div>

<!-- ── TODAY'S SCHEDULE + ACTIVITY LOG ── -->
<?php
// Today's confirmed bookings
$todaySchedule = $db->query("
    SELECT * FROM bookings
    WHERE preferred_date = CURDATE() AND status IN ('confirmed','pending')
    ORDER BY preferred_time ASC
")->fetchAll();

// Recent activity log
$activityLog = $db->query("
    (SELECT 'wo' AS type, wo.wo_number AS ref, CONCAT('WO dibuat untuk ', c.name) AS description, wo.created_at AS ts
     FROM work_orders wo LEFT JOIN customers c ON wo.customer_id=c.id ORDER BY wo.created_at DESC LIMIT 5)
    UNION ALL
    (SELECT 'booking' AS type, booking_number AS ref, CONCAT('Booking dari ', customer_name) AS description, created_at AS ts
     FROM bookings ORDER BY created_at DESC LIMIT 5)
    UNION ALL
    (SELECT 'stock' AS type, CONCAT(p.name) AS ref, CONCAT(sm.type, ' stok: ', sm.quantity, ' ', p.unit) AS description, sm.created_at AS ts
     FROM stock_movements sm LEFT JOIN parts p ON sm.part_id=p.id ORDER BY sm.created_at DESC LIMIT 5)
    ORDER BY ts DESC LIMIT 10
")->fetchAll();
?>

<div class="grid-2 mb-24">

  <!-- Today's Schedule -->
  <div class="card">
    <div class="card-header">
      <div class="card-header-title"><i class="fas fa-calendar-day" style="color:var(--primary);margin-right:8px"></i>Jadwal Hari Ini <span style="color:var(--text-muted);font-weight:400;font-size:13px">(<?= count($todaySchedule) ?>)</span></div>
      <a href="<?= BASE_URL ?>/pages/bookings/index.php" class="btn btn-outline btn-sm">Semua Booking</a>
    </div>
    <div class="card-body" style="padding:12px 16px">
      <?php if (empty($todaySchedule)): ?>
      <div style="text-align:center;padding:30px 0;color:var(--text-muted)">
        <i class="fas fa-check-circle" style="font-size:32px;opacity:.2;display:block;margin-bottom:8px"></i>
        <div style="font-size:14px;font-weight:500">Tidak ada jadwal hari ini</div>
      </div>
      <?php else: foreach ($todaySchedule as $sched): ?>
      <div style="display:flex;align-items:center;gap:12px;padding:10px 0;border-bottom:1px solid var(--border-light)">
        <div style="text-align:center;min-width:48px">
          <div style="font-size:22px;font-weight:800;color:var(--primary);line-height:1"><?= substr($sched['preferred_time'],0,2) ?></div>
          <div style="font-size:11px;color:var(--text-muted)"><?= substr($sched['preferred_time'],3,2) ?> WIB</div>
        </div>
        <div style="flex:1">
          <div style="font-weight:600;font-size:13px"><?= htmlspecialchars($sched['customer_name']) ?></div>
          <div style="font-size:12px;color:var(--text-muted)">
            <i class="fas <?= $sched['vehicle_type']==='mobil'?'fa-car':'fa-motorcycle' ?>"></i>
            <?= $sched['vehicle_brand'] . ' ' . $sched['vehicle_model'] ?> · <?= $sched['service_type'] ?>
          </div>
        </div>
        <span class="badge badge-<?= $sched['status'] ?>"><?= ucfirst($sched['status']) ?></span>
      </div>
      <?php endforeach; endif; ?>
    </div>
  </div>

  <!-- Activity Log -->
  <div class="card">
    <div class="card-header">
      <div class="card-header-title"><i class="fas fa-stream" style="color:var(--primary);margin-right:8px"></i>Aktivitas Terbaru</div>
    </div>
    <div class="card-body" style="padding:12px 16px">
      <?php if (empty($activityLog)): ?>
      <p class="text-muted text-center" style="padding:30px 0;font-size:13px">Belum ada aktivitas</p>
      <?php else: foreach ($activityLog as $act):
        $actIcons = ['wo'=>'fa-clipboard-list','booking'=>'fa-calendar-alt','stock'=>'fa-boxes'];
        $actColors = ['wo'=>'var(--info)','booking'=>'var(--warning)','stock'=>'var(--success)'];
      ?>
      <div style="display:flex;gap:12px;padding:8px 0;border-bottom:1px solid var(--border-light)">
        <div style="width:30px;height:30px;border-radius:8px;background:<?= $actColors[$act['type']] ?? 'var(--primary)' ?>18;color:<?= $actColors[$act['type']] ?? 'var(--primary)' ?>;display:flex;align-items:center;justify-content:center;font-size:12px;flex-shrink:0;margin-top:2px">
          <i class="fas <?= $actIcons[$act['type']] ?? 'fa-circle' ?>"></i>
        </div>
        <div style="flex:1">
          <div style="font-size:13px;font-weight:500"><?= htmlspecialchars($act['description']) ?></div>
          <div style="font-size:11px;color:var(--text-muted)">
            <span style="font-weight:600;color:var(--primary)"><?= $act['ref'] ?></span>
            · <?= date('d M H:i', strtotime($act['ts'])) ?>
          </div>
        </div>
      </div>
      <?php endforeach; endif; ?>
    </div>
  </div>

</div>

<!-- Chart.js CDN -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
// ── Revenue Chart (Premium Gradient) ──────────────────
const revenueCtx = document.getElementById('revenueChart').getContext('2d');
const gradient = revenueCtx.createLinearGradient(0, 0, 0, 300);
gradient.addColorStop(0, 'rgba(255,107,43,0.35)');
gradient.addColorStop(1, 'rgba(255,107,43,0.02)');

new Chart(revenueCtx, {
  type: 'bar',
  data: {
    labels: <?= json_encode($chartDays) ?>,
    datasets: [{
      label: 'Pendapatan',
      data: <?= json_encode($chartRevenue) ?>,
      backgroundColor: gradient,
      borderColor: '#FF6B2B',
      borderWidth: 2,
      borderRadius: 8,
      borderSkipped: false,
      hoverBackgroundColor: 'rgba(255,107,43,0.5)',
    }]
  },
  options: {
    responsive: true,
    animation: { duration: 1200, easing: 'easeOutQuart' },
    plugins: {
      legend: { display: false },
      tooltip: {
        backgroundColor: '#1A1F2E',
        titleFont: { size: 12, weight: '600' },
        bodyFont: { size: 13 },
        padding: 12,
        cornerRadius: 10,
        displayColors: false,
        callbacks: {
          label: ctx => '💰 Rp ' + new Intl.NumberFormat('id-ID').format(ctx.raw)
        }
      }
    },
    scales: {
      x: { grid: { display: false }, ticks: { font: { size: 11, weight: '500' } } },
      y: {
        grid: { color: 'rgba(0,0,0,.04)', drawBorder: false },
        ticks: {
          font: { size: 11 },
          callback: v => 'Rp ' + new Intl.NumberFormat('id-ID').format(v)
        }
      }
    }
  }
});

// ── Status Donut Chart (Premium) ───────────────────────
const statusData = <?= json_encode(array_values($woByStatus)) ?>;
const statusLabels = <?= json_encode(array_keys($woByStatus)) ?>;
const statusColors = {
  waiting: '#9CA3AF', inspection: '#3B82F6', approved: '#8B5CF6',
  in_progress: '#F59E0B', qc: '#EC4899', done: '#10B981'
};
const labelMap = {
  waiting:'Menunggu', inspection:'Inspeksi', approved:'Disetujui',
  in_progress:'Dikerjakan', qc:'Quality Control', done:'Selesai'
};
const colors = statusLabels.map(s => statusColors[s] || '#ccc');

if (statusData.length > 0) {
  const statusCtx = document.getElementById('statusChart').getContext('2d');
  new Chart(statusCtx, {
    type: 'doughnut',
    data: {
      labels: statusLabels.map(s => labelMap[s] || s),
      datasets: [{
        data: statusData,
        backgroundColor: colors,
        borderWidth: 3,
        borderColor: '#fff',
        hoverOffset: 6
      }]
    },
    options: {
      cutout: '68%',
      animation: { animateRotate: true, duration: 1000 },
      plugins: {
        legend: { display: false },
        tooltip: {
          backgroundColor: '#1A1F2E',
          padding: 10,
          cornerRadius: 8,
          displayColors: true,
          boxWidth: 10,
          boxHeight: 10,
          boxPadding: 4
        }
      }
    }
  });

  const legend = document.getElementById('status-legend');
  statusLabels.forEach((s, i) => {
    legend.innerHTML += `
      <div style="display:flex;align-items:center;gap:8px;padding:4px 0">
        <div style="width:10px;height:10px;border-radius:3px;background:${colors[i]};flex-shrink:0;box-shadow:0 2px 4px ${colors[i]}44"></div>
        <span style="flex:1;font-weight:500">${labelMap[s]||s}</span>
        <strong style="font-size:15px">${statusData[i]}</strong>
      </div>`;
  });
} else {
  document.getElementById('statusChart').parentElement.innerHTML =
    '<div style="text-align:center;padding:40px 0"><i class="fas fa-chart-pie" style="font-size:40px;opacity:.1;display:block;margin-bottom:12px"></i><p style="color:#9CA3AF;font-size:13px">Belum ada data work order aktif</p></div>';
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
