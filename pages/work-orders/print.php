<?php
require_once __DIR__ . '/../../includes/auth.php';
requireLogin();

if (!isset($_GET['id'])) { header('Location: ' . BASE_URL . '/pages/work-orders/index.php'); exit; }
$id = (int)$_GET['id'];

$db = getDB();
$wo = $db->prepare("
    SELECT wo.*,
           c.name AS customer_name, c.phone AS customer_phone, c.address AS customer_address,
           v.plate_number, v.brand, v.model, v.year AS vehicle_year, v.color, v.type AS vehicle_type,
           e.name AS mechanic_name
    FROM work_orders wo
    LEFT JOIN customers c ON wo.customer_id = c.id
    LEFT JOIN vehicles v  ON wo.vehicle_id = v.id
    LEFT JOIN employees e ON wo.mechanic_id = e.id
    WHERE wo.id = ?
");
$wo->execute([$id]); $wo = $wo->fetch();
if (!$wo) { header('Location: ' . BASE_URL . '/pages/work-orders/index.php'); exit; }

$services = $db->prepare("SELECT * FROM wo_services WHERE wo_id=?"); $services->execute([$id]); $services=$services->fetchAll();
$parts    = $db->prepare("SELECT * FROM wo_parts WHERE wo_id=?");    $parts->execute([$id]);    $parts=$parts->fetchAll();
$settings = $db->query("SELECT setting_key,setting_value FROM settings")->fetchAll(PDO::FETCH_KEY_PAIR);
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Nota WO <?= $wo['wo_number'] ?></title>
  <style>
    @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');
    * { box-sizing:border-box; margin:0; padding:0; }
    body { font-family:'Inter',sans-serif; font-size:13px; color:#111; background:#f5f5f5; }
    .page { width:210mm; min-height:297mm; margin:0 auto; background:#fff; padding:24px 28px; }
    @media print {
      body { background:#fff; }
      .page { width:100%; padding:16px; box-shadow:none; }
      .no-print { display:none!important; }
    }

    .header { display:flex; justify-content:space-between; align-items:flex-start; padding-bottom:16px; border-bottom:2px solid #FF6B2B; margin-bottom:16px; }
    .brand-name { font-size:22px; font-weight:800; color:#FF6B2B; }
    .brand-sub  { font-size:11px; color:#666; }
    .wo-number  { font-size:26px; font-weight:800; color:#1A1F2E; }
    .wo-date    { font-size:12px; color:#666; text-align:right; }

    .info-grid { display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-bottom:16px; }
    .info-box { border:1px solid #E5E7EB; border-radius:8px; padding:12px; }
    .info-box h3 { font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.5px; color:#FF6B2B; margin-bottom:8px; }
    .info-row { display:flex; gap:6px; margin-bottom:4px; font-size:12px; }
    .info-key { color:#666; min-width:90px; flex-shrink:0; }
    .info-val { font-weight:600; }

    table { width:100%; border-collapse:collapse; margin-bottom:12px; font-size:12px; }
    th { background:#F3F4F6; padding:8px 10px; text-align:left; font-weight:700; font-size:11px; color:#374151; border-bottom:1px solid #E5E7EB; }
    td { padding:8px 10px; border-bottom:1px solid #F9FAFB; vertical-align:top; }
    .section-title { font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:.5px; color:#374151; margin:14px 0 6px; border-bottom:1px solid #E5E7EB; padding-bottom:4px; }

    .totals { margin-left:auto; width:260px; }
    .total-row { display:flex; justify-content:space-between; padding:5px 0; font-size:13px; }
    .total-final { font-size:16px; font-weight:800; color:#FF6B2B; border-top:2px solid #E5E7EB; padding-top:8px; margin-top:4px; }

    .status-badge { display:inline-block; padding:3px 10px; border-radius:12px; font-size:11px; font-weight:700; }
    .status-paid   { background:#ECFDF5; color:#059669; }
    .status-unpaid { background:#FEF2F2; color:#EF4444; }

    .footer { margin-top:24px; padding-top:16px; border-top:1px solid #E5E7EB; display:flex; justify-content:space-between; font-size:11px; color:#666; }
    .signature { text-align:center; }
    .signature-line { width:140px; height:60px; border-bottom:1px solid #333; margin:0 auto 6px; }

    .no-print { text-align:center; padding:16px; background:#1A1F2E; color:#fff; }
    .no-print button { background:#FF6B2B; color:#fff; border:none; padding:10px 24px; border-radius:8px; cursor:pointer; font-size:14px; font-weight:600; margin:0 6px; }
  </style>
</head>
<body>

<div class="no-print">
  <button onclick="window.print()"><i>🖨</i> Cetak Nota</button>
  <button onclick="window.close()" style="background:#6B7280">✕ Tutup</button>
</div>

<div class="page">
  <!-- HEADER -->
  <div class="header">
    <div>
      <div class="brand-name">🔧 <?= htmlspecialchars($settings['bengkel_name'] ?? 'Bengkelin') ?></div>
      <div class="brand-sub"><?= htmlspecialchars($settings['bengkel_tagline'] ?? '') ?></div>
      <div class="brand-sub" style="margin-top:4px"><?= htmlspecialchars($settings['bengkel_address'] ?? '') ?></div>
      <div class="brand-sub">Telp: <?= htmlspecialchars($settings['bengkel_phone'] ?? '') ?></div>
    </div>
    <div style="text-align:right">
      <div class="wo-number"><?= $wo['wo_number'] ?></div>
      <div class="wo-date">
        Tanggal: <?= date('d M Y H:i', strtotime($wo['check_in_at'])) ?><br>
        Status Bayar:
        <span class="status-badge status-<?= $wo['payment_status']==='paid'?'paid':'unpaid' ?>">
          <?= strtoupper($wo['payment_status']) ?>
        </span>
      </div>
    </div>
  </div>

  <!-- INFO -->
  <div class="info-grid">
    <div class="info-box">
      <h3>Data Pelanggan</h3>
      <div class="info-row"><span class="info-key">Nama</span><span class="info-val"><?= htmlspecialchars($wo['customer_name']) ?></span></div>
      <div class="info-row"><span class="info-key">Telepon</span><span class="info-val"><?= $wo['customer_phone'] ?></span></div>
      <div class="info-row"><span class="info-key">Alamat</span><span class="info-val"><?= htmlspecialchars($wo['customer_address'] ?? '—') ?></span></div>
    </div>
    <div class="info-box">
      <h3>Data Kendaraan</h3>
      <div class="info-row"><span class="info-key">Plat</span><span class="info-val" style="font-size:14px;letter-spacing:1px"><?= $wo['plate_number'] ?></span></div>
      <div class="info-row"><span class="info-key">Kendaraan</span><span class="info-val"><?= $wo['brand'].' '.$wo['model'].' ('.$wo['vehicle_year'].')' ?></span></div>
      <div class="info-row"><span class="info-key">Km Masuk</span><span class="info-val"><?= number_format($wo['km_in']) ?> km</span></div>
      <div class="info-row"><span class="info-key">Mekanik</span><span class="info-val"><?= htmlspecialchars($wo['mechanic_name'] ?? '—') ?></span></div>
    </div>
  </div>

  <!-- COMPLAINT -->
  <?php if ($wo['complaint']): ?>
  <div class="section-title">Keluhan</div>
  <div style="font-size:12px;padding:8px;background:#FFF9F7;border-radius:6px;margin-bottom:12px;border-left:3px solid #FF6B2B"><?= nl2br(htmlspecialchars($wo['complaint'])) ?></div>
  <?php endif; ?>

  <!-- SERVICES -->
  <?php if (!empty($services)): ?>
  <div class="section-title">Daftar Jasa</div>
  <table>
    <thead><tr><th style="width:60%">Nama Layanan</th><th style="text-align:right">Harga</th></tr></thead>
    <tbody>
      <?php foreach ($services as $s): ?>
      <tr><td><?= htmlspecialchars($s['service_name']) ?></td><td style="text-align:right"><?= formatRupiah((float)$s['price']) ?></td></tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>

  <!-- PARTS -->
  <?php if (!empty($parts)): ?>
  <div class="section-title">Daftar Sparepart</div>
  <table>
    <thead><tr><th>Nama Part</th><th style="text-align:center">Qty</th><th style="text-align:right">Harga Satuan</th><th style="text-align:right">Subtotal</th></tr></thead>
    <tbody>
      <?php foreach ($parts as $p): ?>
      <tr>
        <td><?= htmlspecialchars($p['part_name']) ?></td>
        <td style="text-align:center"><?= $p['quantity'] ?></td>
        <td style="text-align:right"><?= formatRupiah((float)$p['sell_price']) ?></td>
        <td style="text-align:right;font-weight:600"><?= formatRupiah((float)$p['subtotal']) ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>

  <!-- TOTALS -->
  <div class="totals">
    <div class="total-row"><span>Subtotal Jasa</span><strong><?= formatRupiah((float)$wo['subtotal_services']) ?></strong></div>
    <div class="total-row"><span>Subtotal Part</span><strong><?= formatRupiah((float)$wo['subtotal_parts']) ?></strong></div>
    <?php if ($wo['discount'] > 0): ?>
    <div class="total-row" style="color:#EF4444"><span>Diskon</span><strong>- <?= formatRupiah((float)$wo['discount']) ?></strong></div>
    <?php endif; ?>
    <div class="total-row total-final"><span>TOTAL</span><span><?= formatRupiah((float)$wo['total']) ?></span></div>
    <?php if ($wo['paid_amount'] > 0): ?>
    <div class="total-row" style="color:#059669"><span>Dibayar (<?= strtoupper($wo['payment_method']) ?>)</span><strong><?= formatRupiah((float)$wo['paid_amount']) ?></strong></div>
    <?php endif; ?>
  </div>

  <!-- SIGNATURE -->
  <div class="footer">
    <div class="signature">
      <div class="signature-line"></div>
      <div>Pelanggan</div>
    </div>
    <div style="text-align:center;font-size:11px;color:#9CA3AF;align-self:flex-end">
      Terima kasih telah mempercayakan kendaraan Anda<br>kepada kami. Semoga selalu dalam kondisi prima! 🙏
    </div>
    <div class="signature">
      <div class="signature-line"></div>
      <div><?= htmlspecialchars($settings['bengkel_name'] ?? 'Bengkelin') ?></div>
    </div>
  </div>
</div>

</body>
</html>
