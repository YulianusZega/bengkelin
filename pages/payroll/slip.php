<?php
require_once __DIR__ . '/../../includes/auth.php';
requireLogin();
requireRole('owner', 'admin');

$month = $_GET['month'] ?? date('Y-m');
[$yr, $mo] = explode('-', $month);

$db = getDB();

// Load settings
$cfg = $db->query("SELECT setting_key,setting_value FROM settings WHERE setting_key IN
    ('service_mechanic_pct','service_owner_pct','kabeng_share_pct','junior_share_pct',
     'admin_bonus_pct','kabeng_min_guarantee','bengkel_name','bengkel_address','bengkel_phone')")
    ->fetchAll(PDO::FETCH_KEY_PAIR);

$svcMechPct   = (float)($cfg['service_mechanic_pct'] ?? 60);
$svcOwnerPct  = (float)($cfg['service_owner_pct']    ?? 40);
$kabengPct    = (float)($cfg['kabeng_share_pct']     ?? 80);
$juniorPct    = (float)($cfg['junior_share_pct']     ?? 20);
$adminBonusPct= (float)($cfg['admin_bonus_pct']      ?? 1);
$kabengMin    = (float)($cfg['kabeng_min_guarantee'] ?? 0);

// Load all salary records for this month
$records = $db->prepare("
    SELECT sr.*, e.name, e.employee_id, e.position, e.salary AS base_sal
    FROM salary_records sr
    JOIN employees e ON sr.employee_id = e.id
    WHERE sr.period_year=? AND sr.period_month=?
    ORDER BY FIELD(e.position,'kabeng','mekanik','admin') ASC, e.name ASC
");
$records->execute([$yr, $mo]);
$records = $records->fetchAll();

$bengkelName = htmlspecialchars($cfg['bengkel_name'] ?? 'Bengkelin');
$bengkelAddr = htmlspecialchars($cfg['bengkel_address'] ?? '');
$bengkelPhone = htmlspecialchars($cfg['bengkel_phone'] ?? '');
$periodeLabel = date('F Y', mktime(0,0,0,(int)$mo,1,(int)$yr));
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Slip Gaji <?= $periodeLabel ?> — <?= $bengkelName ?></title>
  <style>
    * { margin:0;padding:0;box-sizing:border-box; }
    body { font-family: 'Segoe UI',Arial,sans-serif; background:#f0f0f0; padding:20px; }
    .slip-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(380px,1fr)); gap:20px; }
    .slip { background:#fff; border-radius:12px; padding:24px; box-shadow:0 2px 12px rgba(0,0,0,.1); page-break-inside:avoid; }
    .slip-header { border-bottom:2px solid #FF6B2B; padding-bottom:12px; margin-bottom:16px; }
    .slip-company { font-size:16px; font-weight:800; color:#FF6B2B; }
    .slip-sub { font-size:11px; color:#666; margin-top:2px; }
    .slip-period { background:#FF6B2B; color:#fff; border-radius:6px; padding:4px 10px; font-size:11px; font-weight:700; display:inline-block; margin-top:6px; }
    .emp-name { font-size:18px; font-weight:700; color:#1a1a2e; margin-bottom:3px; }
    .emp-id { font-size:12px; color:#888; }
    .pos-badge { display:inline-block; padding:3px 10px; border-radius:99px; font-size:11px; font-weight:700; text-transform:uppercase; margin-bottom:12px; }
    .pos-kabeng { background:#FEF3C7; color:#B45309; }
    .pos-mekanik { background:#DBEAFE; color:#1D4ED8; }
    .pos-admin   { background:#D1FAE5; color:#065F46; }
    .row { display:flex; justify-content:space-between; padding:7px 0; border-bottom:1px solid #f3f4f6; font-size:13px; }
    .row:last-child { border:none; }
    .row-label { color:#666; }
    .row-val { font-weight:600; }
    .total-row { background:#FFF7ED; border-radius:8px; padding:10px 14px; margin-top:12px; display:flex; justify-content:space-between; align-items:center; }
    .total-label { font-weight:700; color:#FF6B2B; }
    .total-val { font-size:20px; font-weight:800; color:#FF6B2B; }
    .status-badge { text-align:center; margin-top:10px; padding:6px; border-radius:6px; font-size:12px; font-weight:700; }
    .status-paid     { background:#D1FAE5; color:#065F46; }
    .status-approved { background:#DBEAFE; color:#1D4ED8; }
    .status-draft    { background:#FEF3C7; color:#92400E; }
    .note-box { background:#f9fafb; border-radius:6px; padding:8px; font-size:11px; color:#888; margin-top:10px; line-height:1.6; }
    .print-btn { position:fixed; bottom:24px; right:24px; background:#FF6B2B; color:#fff; border:none; border-radius:99px; padding:14px 24px; font-size:14px; font-weight:700; cursor:pointer; box-shadow:0 4px 20px rgba(255,107,43,.4); }
    .print-btn:hover { background:#e55a1f; }
    .no-print-msg { text-align:center; color:#999; font-size:14px; padding:40px; }
    @media print {
      body { background:#fff; padding:0; }
      .slip-grid { grid-template-columns:repeat(2,1fr); }
      .print-btn { display:none; }
    }
  </style>
</head>
<body>

<div style="max-width:1200px;margin:0 auto">
  <div style="background:#fff;border-radius:12px;padding:20px 28px;margin-bottom:20px;display:flex;align-items:center;justify-content:space-between;box-shadow:0 2px 8px rgba(0,0,0,.08)">
    <div>
      <h2 style="font-size:20px;font-weight:800;color:#1a1a2e">Slip Gaji Karyawan</h2>
      <div style="color:#888;font-size:13px">Periode: <?= $periodeLabel ?> · <?= $bengkelName ?></div>
    </div>
    <div style="display:flex;gap:10px">
      <a href="<?= BASE_URL ?>/pages/payroll/index.php?month=<?= $month ?>" style="padding:8px 16px;border:1px solid #ddd;border-radius:8px;text-decoration:none;color:#333;font-size:13px">← Kembali</a>
      <button onclick="window.print()" style="background:#FF6B2B;color:#fff;border:none;border-radius:8px;padding:8px 20px;font-size:13px;font-weight:700;cursor:pointer">🖨 Cetak Semua</button>
    </div>
  </div>

  <?php if (empty($records)): ?>
  <div class="no-print-msg">
    <i style="font-size:40px">📋</i><br>
    Belum ada data gaji bulan ini.<br>
    <a href="<?= BASE_URL ?>/pages/payroll/index.php?month=<?= $month ?>" style="color:#FF6B2B">Generate Gaji terlebih dahulu</a>
  </div>
  <?php else: ?>
  <div class="slip-grid">
    <?php foreach ($records as $r):
        $pos = $r['position'];
        $posCls = 'pos-' . $pos;
        $posLabel = ['kabeng'=>'Kepala Bengkel','mekanik'=>'Junior Mekanik','admin'=>'Administrasi'][$pos] ?? strtoupper($pos);
    ?>
    <div class="slip">
      <div class="slip-header">
        <div class="slip-company"><?= $bengkelName ?></div>
        <div class="slip-sub"><?= $bengkelAddr ?></div>
        <div class="slip-period">Periode: <?= $periodeLabel ?></div>
      </div>

      <div class="emp-name"><?= htmlspecialchars($r['name']) ?></div>
      <div class="emp-id"><?= htmlspecialchars($r['employee_id']) ?> · <?= $bengkelPhone ?></div>
      <div style="margin:8px 0">
        <span class="pos-badge <?= $posCls ?>"><?= $posLabel ?></span>
      </div>

      <div>
        <?php if ($r['wo_count'] > 0): ?>
        <div class="row">
          <span class="row-label">Work Order Dikerjakan</span>
          <span class="row-val"><?= $r['wo_count'] ?> WO</span>
        </div>
        <div class="row">
          <span class="row-label">Total Jasa Dikerjakan</span>
          <span class="row-val"><?= formatRupiah((float)$r['service_revenue']) ?></span>
        </div>
        <?php endif; ?>

        <?php if ((float)$r['base_salary'] > 0): ?>
        <div class="row">
          <span class="row-label">Gaji Pokok</span>
          <span class="row-val"><?= formatRupiah((float)$r['base_salary']) ?></span>
        </div>
        <?php endif; ?>

        <?php if ((float)$r['service_bonus'] > 0): ?>
        <div class="row">
          <span class="row-label">Bonus Jasa
            <?php if ($pos === 'kabeng'): ?>
              (<?= $svcMechPct ?>% Jasa × <?= $kabengPct ?>%)
            <?php elseif ($pos === 'mekanik'): ?>
              (<?= $svcMechPct ?>% Jasa × <?= $juniorPct ?>%)
            <?php endif; ?>
          </span>
          <span class="row-val" style="color:#059669"><?= formatRupiah((float)$r['service_bonus']) ?></span>
        </div>
        <?php endif; ?>

        <?php if ((float)$r['omset_bonus'] > 0): ?>
        <div class="row">
          <span class="row-label">Bonus Kinerja (<?= $adminBonusPct ?>% Omset)</span>
          <span class="row-val" style="color:#0284c7"><?= formatRupiah((float)$r['omset_bonus']) ?></span>
        </div>
        <?php endif; ?>
      </div>

      <div class="total-row">
        <div class="total-label">TOTAL TAKE-HOME</div>
        <div class="total-val"><?= formatRupiah((float)$r['total_salary']) ?></div>
      </div>

      <div class="status-badge status-<?= $r['status'] ?>">
        <?= ['draft'=>'⏳ Draft','approved'=>'✅ Disetujui','paid'=>'💰 Sudah Dibayar'][$r['status']] ?? $r['status'] ?>
      </div>

      <?php if ($r['notes']): ?>
      <div class="note-box">📝 <?= htmlspecialchars($r['notes']) ?></div>
      <?php endif; ?>

      <div style="margin-top:20px;padding-top:12px;border-top:1px dashed #e5e7eb;display:flex;justify-content:space-between;font-size:11px;color:#bbb">
        <span>Dicetak: <?= date('d/m/Y H:i') ?></span>
        <span>____________________<br>Tanda Tangan</span>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>

</body>
</html>
