<?php
// ============================================================
// BENGKELIN - Public Tracking Page (no login required)
// ============================================================
require_once __DIR__ . '/config/database.php';

$db = getDB();
$settings = $db->query("SELECT setting_key, setting_value FROM settings")->fetchAll(PDO::FETCH_KEY_PAIR);
$bengkelName = $settings['bengkel_name'] ?? 'Bengkelin';

$query  = trim($_GET['q'] ?? '');
$result = null;
$type   = null; // 'booking' or 'wo'

if ($query) {
    // Try booking first
    $stmt = $db->prepare("SELECT * FROM bookings WHERE booking_number = ? OR customer_phone = ? ORDER BY created_at DESC LIMIT 1");
    $stmt->execute([$query, $query]);
    $result = $stmt->fetch();
    if ($result) { $type = 'booking'; }

    // Try work order if no booking
    if (!$result) {
        $stmt = $db->prepare("
            SELECT wo.*, c.name AS customer_name, c.phone AS customer_phone,
                   v.plate_number, v.brand, v.model, v.year AS vehicle_year, v.color, v.type AS vehicle_type,
                   e.name AS mechanic_name
            FROM work_orders wo
            LEFT JOIN customers c ON wo.customer_id = c.id
            LEFT JOIN vehicles v  ON wo.vehicle_id = v.id
            LEFT JOIN employees e ON wo.mechanic_id = e.id
            WHERE wo.wo_number = ? ORDER BY wo.created_at DESC LIMIT 1
        ");
        $stmt->execute([$query]);
        $result = $stmt->fetch();
        if ($result) { $type = 'wo'; }
    }

    // Services & Parts for WO
    if ($type === 'wo') {
        $svc = $db->prepare("SELECT * FROM wo_services WHERE wo_id=?");
        $svc->execute([$result['id']]);
        $services = $svc->fetchAll();
        $prt = $db->prepare("SELECT * FROM wo_parts WHERE wo_id=?");
        $prt->execute([$result['id']]);
        $parts = $prt->fetchAll();
    }
}

$statusFlow = ['waiting','inspection','approved','in_progress','qc','done','delivered'];
$statusLabels = [
    'waiting'=>'Menunggu', 'inspection'=>'Inspeksi', 'approved'=>'Disetujui',
    'in_progress'=>'Dikerjakan', 'qc'=>'Quality Check', 'done'=>'Selesai', 'delivered'=>'Diserahkan',
    'cancelled'=>'Dibatalkan'
];
$bookingLabels = [
    'pending'=>'Menunggu Konfirmasi', 'confirmed'=>'Dikonfirmasi',
    'cancelled'=>'Dibatalkan', 'converted'=>'Sudah Jadi WO'
];

function fmtRp(float $v): string { return 'Rp ' . number_format($v, 0, ',', '.'); }
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Cek Status — <?= htmlspecialchars($bengkelName) ?></title>
  <meta name="description" content="Cek status booking dan work order kendaraan Anda di <?= htmlspecialchars($bengkelName) ?>">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap">
  <style>
    :root {
      --primary: #FF6B2B; --primary-dark: #E55A1B; --primary-bg: #FFF4EF;
      --secondary: #1A1F2E; --success: #10B981; --warning: #F59E0B; --danger: #EF4444; --info: #3B82F6;
      --text-primary: #1A1F2E; --text-secondary: #6B7280; --text-muted: #9CA3AF;
      --border: #E5E7EB; --bg: #F0F2F5;
    }
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: 'Inter', sans-serif; background: var(--bg); color: var(--text-primary); line-height: 1.6; min-height: 100vh; }

    .track-hero {
      background: linear-gradient(135deg, var(--secondary), #2A3040);
      padding: 60px 24px 90px;
      text-align: center;
      color: #fff;
      position: relative;
      overflow: hidden;
    }
    .track-hero::before {
      content: '';
      position: absolute;
      width: 500px; height: 500px;
      background: radial-gradient(circle, rgba(255,107,43,.15) 0%, transparent 70%);
      top: -200px; right: -100px;
      border-radius: 50%;
    }
    .track-hero h1 { font-size: clamp(24px, 4vw, 36px); font-weight: 800; margin-bottom: 8px; position: relative; }
    .track-hero h1 span { color: var(--primary); }
    .track-hero p { color: rgba(255,255,255,.6); font-size: 15px; position: relative; }

    .container { max-width: 800px; margin: -50px auto 0; padding: 0 20px 60px; position: relative; z-index: 10; }

    .search-card {
      background: #fff;
      border-radius: 16px;
      box-shadow: 0 8px 40px rgba(0,0,0,.1);
      padding: 28px 30px;
      margin-bottom: 24px;
    }
    .search-form { display: flex; gap: 10px; }
    .search-form input {
      flex: 1;
      padding: 12px 16px;
      border: 2px solid var(--border);
      border-radius: 12px;
      font-size: 15px;
      font-family: inherit;
      outline: none;
      transition: all .2s;
    }
    .search-form input:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(255,107,43,.1); }
    .search-form button {
      padding: 12px 24px;
      background: var(--primary);
      color: #fff;
      border: none;
      border-radius: 12px;
      font-size: 15px;
      font-weight: 600;
      font-family: inherit;
      cursor: pointer;
      transition: all .2s;
      white-space: nowrap;
    }
    .search-form button:hover { background: var(--primary-dark); }
    .search-hint { font-size: 13px; color: var(--text-muted); margin-top: 10px; }
    .search-hint i { color: var(--primary); }

    .result-card {
      background: #fff;
      border-radius: 16px;
      box-shadow: 0 4px 20px rgba(0,0,0,.06);
      overflow: hidden;
    }
    .result-header {
      padding: 20px 28px;
      border-bottom: 1px solid #F3F4F6;
      display: flex;
      align-items: center;
      justify-content: space-between;
      flex-wrap: wrap;
      gap: 12px;
    }
    .result-header h2 { font-size: 18px; }
    .result-body { padding: 24px 28px; }

    .badge {
      display: inline-flex; align-items: center; gap: 5px;
      padding: 5px 12px; border-radius: 20px; font-size: 12px; font-weight: 600;
    }
    .badge::before { content: ''; width: 6px; height: 6px; border-radius: 50%; flex-shrink: 0; }
    .badge-pending, .badge-waiting { background: #FFFBEB; color: #D97706; }
    .badge-pending::before, .badge-waiting::before { background: #F59E0B; }
    .badge-confirmed, .badge-info, .badge-inspection { background: #EFF6FF; color: #3B82F6; }
    .badge-confirmed::before, .badge-info::before, .badge-inspection::before { background: #3B82F6; }
    .badge-in_progress, .badge-approved { background: #FFF4EF; color: #E55A1B; }
    .badge-in_progress::before, .badge-approved::before { background: #FF6B2B; }
    .badge-qc { background: #F5F3FF; color: #7C3AED; }
    .badge-qc::before { background: #7C3AED; }
    .badge-done, .badge-converted, .badge-delivered { background: #ECFDF5; color: #059669; }
    .badge-done::before, .badge-converted::before, .badge-delivered::before { background: #10B981; }
    .badge-cancelled { background: #FEF2F2; color: #EF4444; }
    .badge-cancelled::before { background: #EF4444; }

    /* Progress Steps */
    .progress-steps {
      display: flex;
      align-items: flex-start;
      gap: 0;
      overflow-x: auto;
      padding: 30px 20px 20px;
      margin-bottom: 24px;
      background: #F8FAFC;
      border-radius: 12px;
      border: 1px solid #F1F5F9;
    }
    .step { display: flex; flex-direction: column; align-items: center; gap: 8px; flex-shrink: 0; position: relative; }
    .step-circle {
      width: 40px; height: 40px;
      border-radius: 50%;
      display: flex; align-items: center; justify-content: center;
      color: #fff;
      font-size: 15px;
      position: relative;
      z-index: 2;
      box-shadow: 0 4px 10px rgba(0,0,0,0.05);
      transition: all .3s;
    }
    .step-label { font-size: 12px; font-weight: 600; white-space: nowrap; text-align: center; }
    .step-line {
      flex: 1; height: 4px; min-width: 40px; border-radius: 2px;
      margin-top: 18px;
      position: relative;
      z-index: 1;
      transition: background .3s;
    }

    .info-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; }
    .info-box { background: var(--bg); border-radius: 10px; padding: 14px; }
    .info-box-label { font-size: 11px; color: var(--text-muted); text-transform: uppercase; font-weight: 600; letter-spacing: .5px; margin-bottom: 4px; }
    .info-box-value { font-size: 14px; font-weight: 600; }

    .svc-list { margin-top: 20px; }
    .svc-list h3 { font-size: 14px; font-weight: 700; margin-bottom: 10px; display: flex; align-items: center; gap: 8px; }
    .svc-list h3 i { color: var(--primary); }
    .svc-item { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #F3F4F6; font-size: 14px; }
    .svc-item:last-child { border-bottom: none; }
    .total-row { display: flex; justify-content: space-between; padding: 14px 0; font-size: 18px; font-weight: 700; border-top: 2px solid var(--border); margin-top: 12px; }
    .total-row span:last-child { color: var(--primary); }

    .empty-result { text-align: center; padding: 60px 20px; }
    .empty-result i { font-size: 48px; color: var(--text-muted); opacity: .3; margin-bottom: 16px; }
    .empty-result h3 { font-size: 16px; color: var(--text-secondary); margin-bottom: 6px; }
    .empty-result p { font-size: 13px; color: var(--text-muted); }

    .public-footer { text-align: center; padding: 30px 0; font-size: 13px; color: var(--text-muted); }
    .public-footer a { color: var(--primary); text-decoration: none; }

    @media (max-width: 640px) {
      .search-form { flex-direction: column; }
      .result-header { flex-direction: column; align-items: flex-start; }
      .info-grid { grid-template-columns: 1fr; }
    }
  </style>
</head>
<body>

  <div class="track-hero">
    <a href="<?= BASE_URL ?>/booking.php" style="text-decoration:none;color:#fff;margin-bottom:20px;display:inline-flex;align-items:center;gap:8px;font-size:14px;opacity:.6">
      <i class="fas fa-arrow-left"></i> Kembali ke Booking
    </a>
    <h1>Cek <span>Status</span> Kendaraan</h1>
    <p>Lacak booking atau work order kendaraan Anda secara real-time</p>
  </div>

  <div class="container">
    <!-- SEARCH -->
    <div class="search-card">
      <form method="GET" class="search-form">
        <input type="text" name="q" placeholder="Masukkan No. Booking, No. WO, atau No. HP..." value="<?= htmlspecialchars($query) ?>" required autofocus>
        <button type="submit"><i class="fas fa-search"></i> Lacak</button>
      </form>
      <div class="search-hint">
        <i class="fas fa-info-circle"></i> Contoh: <strong>BK-20260331-001</strong>, <strong>WO-20260331-001</strong>, atau <strong>08123456789</strong>
      </div>
    </div>

    <?php if ($query && !$result): ?>
    <!-- NOT FOUND -->
    <div class="result-card">
      <div class="empty-result">
        <i class="fas fa-search"></i>
        <h3>Tidak Ditemukan</h3>
        <p>Tidak ada data booking atau work order dengan pencarian "<strong><?= htmlspecialchars($query) ?></strong>"</p>
      </div>
    </div>

    <?php elseif ($type === 'booking'): ?>
    <!-- BOOKING RESULT -->
    <div class="result-card">
      <div class="result-header">
        <div>
          <h2><i class="fas fa-calendar-check" style="color:var(--primary);margin-right:8px"></i><?= $result['booking_number'] ?></h2>
          <span style="font-size:13px;color:var(--text-muted)">Dibuat: <?= date('d M Y, H:i', strtotime($result['created_at'])) ?></span>
        </div>
        <span class="badge badge-<?= $result['status'] ?>"><?= $bookingLabels[$result['status']] ?? ucfirst($result['status']) ?></span>
      </div>
      <div class="result-body">
        <div class="info-grid">
          <div class="info-box">
            <div class="info-box-label">Pelanggan</div>
            <div class="info-box-value"><?= htmlspecialchars($result['customer_name']) ?></div>
          </div>
          <div class="info-box">
            <div class="info-box-label">Kendaraan</div>
            <div class="info-box-value"><?= $result['vehicle_brand'] . ' ' . $result['vehicle_model'] ?></div>
          </div>
          <div class="info-box">
            <div class="info-box-label">Jadwal</div>
            <div class="info-box-value"><?= date('d M Y', strtotime($result['preferred_date'])) ?> · <?= substr($result['preferred_time'], 0, 5) ?> WIB</div>
          </div>
          <div class="info-box">
            <div class="info-box-label">Layanan</div>
            <div class="info-box-value"><?= htmlspecialchars($result['service_type']) ?></div>
          </div>
        </div>
        <?php if ($result['complaint']): ?>
        <div class="info-box" style="margin-top:16px">
          <div class="info-box-label">Keluhan</div>
          <div class="info-box-value" style="font-weight:400;font-size:14px"><?= nl2br(htmlspecialchars($result['complaint'])) ?></div>
        </div>
        <?php endif; ?>
        <?php if ($result['status'] === 'converted' && $result['converted_to_wo']): ?>
        <div style="margin-top:20px;background:var(--primary-bg);padding:14px 18px;border-radius:10px;font-size:14px">
          <i class="fas fa-info-circle" style="color:var(--primary)"></i>
          Booking ini telah dikonversi menjadi Work Order.
          <a href="?q=<?= urlencode('WO-') ?><?= $result['converted_to_wo'] ?>" style="color:var(--primary);font-weight:600;text-decoration:none"> Lihat WO →</a>
        </div>
        <?php endif; ?>
      </div>
    </div>

    <?php elseif ($type === 'wo'): ?>
    <!-- WORK ORDER RESULT -->
    <div class="result-card">
      <div class="result-header">
        <div>
          <h2><i class="fas fa-clipboard-list" style="color:var(--primary);margin-right:8px"></i><?= $result['wo_number'] ?></h2>
          <span style="font-size:13px;color:var(--text-muted)">Masuk: <?= date('d M Y, H:i', strtotime($result['check_in_at'])) ?></span>
        </div>
        <span class="badge badge-<?= $result['status'] ?>"><?= $statusLabels[$result['status']] ?? ucfirst($result['status']) ?></span>
      </div>
      <div class="result-body">

        <!-- PROGRESS BAR -->
        <?php if ($result['status'] !== 'cancelled'):
          $curIdx = array_search($result['status'], $statusFlow);
        ?>
        <div class="progress-steps">
          <?php foreach ($statusFlow as $si => $st):
            $past    = $curIdx !== false && $si < $curIdx;
            $current = $si === $curIdx;
            $color   = $current ? 'var(--primary)' : ($past ? 'var(--success)' : 'var(--border)');
          ?>
          <?php if ($si > 0): ?>
          <div class="step-line" style="background:<?= $past ? 'var(--success)' : 'var(--border)' ?>"></div>
          <?php endif; ?>
          <div class="step">
            <div class="step-circle" style="background:<?= $color ?>">
              <?php if ($past): ?><i class="fas fa-check"></i>
              <?php elseif ($current): ?><i class="fas fa-circle-notch fa-spin" style="font-size:12px"></i>
              <?php else: ?><i class="fas fa-circle" style="font-size:8px;opacity:.4"></i>
              <?php endif; ?>
            </div>
            <span class="step-label" style="color:<?= ($current||$past)?'var(--text-primary)':'var(--text-muted)' ?>;font-weight:<?= $current?700:500 ?>"><?= $statusLabels[$st] ?></span>
          </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- INFO -->
        <div class="info-grid">
          <div class="info-box">
            <div class="info-box-label">Pelanggan</div>
            <div class="info-box-value"><?= htmlspecialchars($result['customer_name']) ?></div>
          </div>
          <div class="info-box">
            <div class="info-box-label">Kendaraan</div>
            <div class="info-box-value"><?= $result['brand'] . ' ' . $result['model'] ?> (<?= $result['vehicle_year'] ?>)</div>
          </div>
          <div class="info-box">
            <div class="info-box-label">Plat Nomor</div>
            <div class="info-box-value" style="letter-spacing:1px"><?= $result['plate_number'] ?></div>
          </div>
          <div class="info-box">
            <div class="info-box-label">Mekanik</div>
            <div class="info-box-value"><?= $result['mechanic_name'] ?: '— Belum ditugaskan' ?></div>
          </div>
          <?php if ($result['estimated_finish']): ?>
          <div class="info-box">
            <div class="info-box-label">Estimasi Selesai</div>
            <div class="info-box-value"><?= date('d M Y, H:i', strtotime($result['estimated_finish'])) ?></div>
          </div>
          <?php endif; ?>
          <div class="info-box">
            <div class="info-box-label">Prioritas</div>
            <div class="info-box-value"><?= $result['priority'] === 'urgent' ? '🔴 Urgent' : '🟢 Normal' ?></div>
          </div>
        </div>

        <?php if ($result['complaint']): ?>
        <div class="info-box" style="margin-top:16px">
          <div class="info-box-label">Keluhan</div>
          <div class="info-box-value" style="font-weight:400;font-size:14px"><?= nl2br(htmlspecialchars($result['complaint'])) ?></div>
        </div>
        <?php endif; ?>

        <!-- SERVICES -->
        <?php if (!empty($services)): ?>
        <div class="svc-list">
          <h3><i class="fas fa-tools"></i> Layanan</h3>
          <?php foreach ($services as $s): ?>
          <div class="svc-item">
            <span><?= htmlspecialchars($s['service_name']) ?></span>
            <strong><?= fmtRp((float)$s['price']) ?></strong>
          </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- PARTS -->
        <?php if (!empty($parts)): ?>
        <div class="svc-list">
          <h3><i class="fas fa-boxes"></i> Part / Sparepart</h3>
          <?php foreach ($parts as $p): ?>
          <div class="svc-item">
            <span><?= htmlspecialchars($p['part_name']) ?> × <?= $p['quantity'] ?></span>
            <strong><?= fmtRp((float)$p['subtotal']) ?></strong>
          </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- TOTAL -->
        <?php if ((float)$result['total'] > 0): ?>
        <div class="total-row">
          <span>Total Biaya</span>
          <span><?= fmtRp((float)$result['total']) ?></span>
        </div>
        <div style="display:flex;justify-content:space-between;font-size:14px;padding:6px 0">
          <span>Status Pembayaran</span>
          <span class="badge badge-<?= $result['payment_status'] ?>"><?= ucfirst($result['payment_status']) ?></span>
        </div>
        <?php endif; ?>

      </div>
    </div>
    <?php endif; ?>
  </div>

  <div class="public-footer">
    <p>&copy; <?= date('Y') ?> <a href="<?= BASE_URL ?>/booking.php"><?= htmlspecialchars($bengkelName) ?></a> &mdash; SMKS Pembda Nias &mdash; Teaching Factory</p>
  </div>
</body>
</html>
