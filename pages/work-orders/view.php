<?php
require_once __DIR__ . '/../../includes/auth.php';
requireLogin();

if (!isset($_GET['id'])) { header('Location: ' . BASE_URL . '/pages/work-orders/index.php'); exit; }
$id = (int)$_GET['id'];

$db = getDB();
$wo = $db->prepare("
    SELECT wo.*,
           c.name AS customer_name, c.phone AS customer_phone, c.email AS customer_email, c.segment AS customer_segment,
           v.plate_number, v.brand, v.model, v.year AS vehicle_year, v.color, v.type AS vehicle_type, v.km_current,
           e.name AS mechanic_name, e.specialization AS mechanic_spec
    FROM work_orders wo
    LEFT JOIN customers c ON wo.customer_id = c.id
    LEFT JOIN vehicles v  ON wo.vehicle_id = v.id
    LEFT JOIN employees e ON wo.mechanic_id = e.id
    WHERE wo.id = ?
");
$wo->execute([$id]);
$wo = $wo->fetch();
if (!$wo) { header('Location: ' . BASE_URL . '/pages/work-orders/index.php'); exit; }

$services = $db->prepare("SELECT * FROM wo_services WHERE wo_id=?");
$services->execute([$id]);
$services = $services->fetchAll();

$parts = $db->prepare("SELECT * FROM wo_parts WHERE wo_id=?");
$parts->execute([$id]);
$parts = $parts->fetchAll();

$assistants = $db->prepare("
    SELECT e.name 
    FROM wo_assistants wa
    JOIN employees e ON wa.employee_id = e.id
    WHERE wa.wo_id = ?
");
$assistants->execute([$id]);
$assistants = $assistants->fetchAll(PDO::FETCH_COLUMN);

$statusFlow = ['waiting','inspection','approved','in_progress','qc','done','delivered'];
$statusIdx  = array_search($wo['status'], $statusFlow);

$title      = 'Detail Work Order';
$activePage = 'work-orders';

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
  <div class="page-header-left">
    <h1><?= $wo['wo_number'] ?></h1>
    <p>Detail dan manajemen work order</p>
  </div>
  <div class="page-header-right">
    <a href="<?= BASE_URL ?>/pages/work-orders/index.php" class="btn btn-outline"><i class="fas fa-arrow-left"></i> Kembali</a>
    <a href="<?= BASE_URL ?>/pages/work-orders/print.php?id=<?= $id ?>" class="btn btn-outline" target="_blank"><i class="fas fa-print"></i> Cetak</a>
    <?php if (!in_array($wo['status'], ['done','delivered','cancelled'])): ?>
    <a href="<?= BASE_URL ?>/pages/work-orders/edit.php?id=<?= $id ?>" class="btn btn-warning"><i class="fas fa-pencil-alt"></i> Edit</a>
    <?php endif; ?>
  </div>
</div>

<!-- STATUS PROGRESS -->
<div class="card mb-24">
  <div class="card-body" style="padding:20px 24px">
    <div style="display:flex;align-items:center;gap:0;overflow-x:auto">
      <?php
      $flowLabels = ['waiting'=>'Menunggu','inspection'=>'Inspeksi','approved'=>'Disetujui','in_progress'=>'Dikerjakan','qc'=>'QC','done'=>'Selesai','delivered'=>'Diserahkan'];
      $i = 0;
      foreach ($flowLabels as $s => $label):
        $past    = $statusIdx !== false && array_search($s, $statusFlow) < $statusIdx;
        $current = $wo['status'] === $s;
        $color   = $current ? 'var(--primary)' : ($past ? 'var(--success)' : 'var(--border)');
        $textCol = ($current || $past) ? 'var(--text-primary)' : 'var(--text-muted)';
      ?>
      <?php if ($i++ > 0): ?>
      <div style="flex:1;height:2px;background:<?= $past?'var(--success)':'var(--border)' ?>;min-width:20px"></div>
      <?php endif; ?>
      <div style="display:flex;flex-direction:column;align-items:center;gap:6px;flex-shrink:0">
        <div style="width:32px;height:32px;border-radius:50%;background:<?= $color ?>;display:flex;align-items:center;justify-content:center;color:#fff;font-size:14px">
          <?php if ($past): ?><i class="fas fa-check"></i><?php elseif ($current): ?><i class="fas fa-circle-notch fa-spin" style="font-size:12px"></i><?php else: ?><i class="fas fa-circle" style="font-size:10px;opacity:.4"></i><?php endif; ?>
        </div>
        <span style="font-size:11px;font-weight:<?= $current?'700':'500' ?>;color:<?= $textCol ?>;white-space:nowrap"><?= $label ?></span>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<!-- MAIN GRID -->
<div class="grid-2 mb-24">

  <!-- LEFT: WO INFO -->
  <div style="display:flex;flex-direction:column;gap:20px">

    <!-- Customer & Vehicle Card -->
    <div class="card">
      <div class="card-header">
        <div class="card-header-title"><i class="fas fa-car" style="color:var(--primary);margin-right:8px"></i>Kendaraan & Pelanggan</div>
        <span class="badge badge-<?= $wo['vehicle_type'] === 'mobil' ? 'info' : 'done' ?>"><?= ucfirst($wo['vehicle_type']) ?></span>
      </div>
      <div class="card-body">
        <div style="display:flex;gap:20px;flex-wrap:wrap">
          <div style="flex:1;min-width:180px">
            <div class="info-row">
              <div class="info-item"><span class="info-label">Plat Nomor</span><span class="info-value" style="font-size:15px;letter-spacing:1px"><?= $wo['plate_number'] ?></span></div>
              <div class="info-item"><span class="info-label">Kendaraan</span><span class="info-value"><?= $wo['brand'] . ' ' . $wo['model'] ?> (<?= $wo['vehicle_year'] ?>)</span></div>
              <div class="info-item"><span class="info-label">Warna</span><span class="info-value"><?= $wo['color'] ?: '—' ?></span></div>
              <div class="info-item"><span class="info-label">Km Masuk</span><span class="info-value"><?= number_format($wo['km_in']) ?> km</span></div>
            </div>
          </div>
          <div style="flex:1;min-width:180px;border-left:1px solid var(--border-light);padding-left:20px">
            <div class="info-row">
              <div class="info-item"><span class="info-label">Pelanggan</span><span class="info-value"><a href="<?= BASE_URL ?>/pages/customers/view.php?id=<?= $wo['customer_id'] ?>"><?= htmlspecialchars($wo['customer_name']) ?></a></span></div>
              <div class="info-item"><span class="info-label">Telepon</span><span class="info-value"><?= $wo['customer_phone'] ?></span></div>
              <div class="info-item"><span class="info-label">Segmen</span><span class="info-value"><span class="badge badge-<?= $wo['customer_segment'] ?>"><?= ucfirst($wo['customer_segment']) ?></span></span></div>
            </div>
          </div>
        </div>
        <hr style="border:none;border-top:1px solid var(--border-light);margin:16px 0">
        <div class="info-row">
          <div class="info-item">
            <span class="info-label">Mekanik Utama</span>
            <span class="info-value"><?= $wo['mechanic_name'] ? htmlspecialchars($wo['mechanic_name']) . ($wo['mechanic_spec']?' — '.$wo['mechanic_spec']:'') : '— Belum ditugaskan' ?></span>
          </div>
          <?php if (!empty($assistants)): ?>
          <div class="info-item">
            <span class="info-label">Asisten</span>
            <span class="info-value">
              <?php foreach ($assistants as $asst): ?>
                <span class="badge badge-info" style="margin-right:4px;font-weight:600"><?= htmlspecialchars($asst) ?></span>
              <?php endforeach; ?>
            </span>
          </div>
          <?php endif; ?>
          <div class="info-item"><span class="info-label">Prioritas</span><span class="info-value"><span class="badge badge-<?= $wo['priority'] === 'urgent' ? 'urgent' : 'done' ?>"><?= ucfirst($wo['priority']) ?></span></span></div>
          <div class="info-item"><span class="info-label">Masuk</span><span class="info-value"><?= date('d M Y H:i', strtotime($wo['check_in_at'])) ?></span></div>
          <div class="info-item"><span class="info-label">Est. Selesai</span><span class="info-value"><?= $wo['estimated_finish'] ? date('d M Y H:i', strtotime($wo['estimated_finish'])) : '—' ?></span></div>
        </div>
      </div>
    </div>

    <!-- Complaint & Diagnosis -->
    <div class="card">
      <div class="card-header"><div class="card-header-title"><i class="fas fa-stethoscope" style="color:var(--primary);margin-right:8px"></i>Keluhan & Diagnosa</div></div>
      <div class="card-body">
        <div style="margin-bottom:14px">
          <div style="font-size:12px;font-weight:600;color:var(--text-muted);text-transform:uppercase;margin-bottom:6px">Keluhan Pelanggan</div>
          <div style="font-size:13.5px;line-height:1.6"><?= nl2br(htmlspecialchars($wo['complaint'] ?: '—')) ?></div>
        </div>
        <div style="margin-bottom:14px">
          <div style="font-size:12px;font-weight:600;color:var(--text-muted);text-transform:uppercase;margin-bottom:6px">Diagnosa</div>
          <div style="font-size:13.5px;line-height:1.6"><?= nl2br(htmlspecialchars($wo['diagnosis'] ?: '—')) ?></div>
        </div>
        <div>
          <div style="font-size:12px;font-weight:600;color:var(--text-muted);text-transform:uppercase;margin-bottom:6px">Rekomendasi</div>
          <div style="font-size:13.5px;line-height:1.6"><?= nl2br(htmlspecialchars($wo['recommendation'] ?: '—')) ?></div>
        </div>
      </div>
    </div>

    <!-- Update Status -->
    <?php if ($wo['status'] !== 'cancelled'): ?>
    <div class="card">
      <div class="card-header"><div class="card-header-title"><i class="fas fa-exchange-alt" style="color:var(--primary);margin-right:8px"></i>Update Status</div></div>
      <div class="card-body">
        <form method="POST" action="<?= BASE_URL ?>/pages/work-orders/action.php">
          <input type="hidden" name="action" value="update_status">
          <input type="hidden" name="wo_id" value="<?= $wo['id'] ?>">
          <input type="hidden" name="csrf_token" value="<?= csrf() ?>">
          <div class="d-flex gap-8 flex-wrap">
            <?php
            $nextStatuses = [
              'waiting'    => ['inspection'=>'Mulai Inspeksi'],
              'inspection' => ['approved'=>'Setujui','cancelled'=>'Batalkan'],
              'approved'   => ['in_progress'=>'Mulai Kerjakan','cancelled'=>'Batalkan'],
              'in_progress'=> ['qc'=>'Kirim ke QC'],
              'qc'         => ['done'=>'Selesai / Siap Ambil'],
              'done'       => ['delivered'=>'Diserahkan ke Pelanggan'],
              'delivered'  => [],
            ];
            $btns = $nextStatuses[$wo['status']] ?? [];
            foreach ($btns as $st => $lbl):
              $cls = $st === 'cancelled' ? 'btn-danger' : ($st === 'delivered' ? 'btn-success' : 'btn-primary');
            ?>
            <button type="submit" name="status" value="<?= $st ?>" class="btn <?= $cls ?>">
              <i class="fas fa-arrow-right"></i> <?= $lbl ?>
            </button>
            <?php endforeach; ?>
          </div>
        </form>
      </div>
    </div>
    <?php endif; ?>

  </div>

  <!-- RIGHT: Services, Parts, Payment -->
  <div style="display:flex;flex-direction:column;gap:20px">

    <!-- Services -->
    <div class="card">
      <div class="card-header"><div class="card-header-title"><i class="fas fa-tools" style="color:var(--primary);margin-right:8px"></i>Daftar Jasa</div></div>
      <div class="table-responsive">
        <table class="table">
          <thead><tr><th>Nama Layanan</th><th style="text-align:right">Harga</th></tr></thead>
          <tbody>
            <?php if (empty($services)): ?>
            <tr><td colspan="2" class="text-center text-muted" style="padding:20px">Tidak ada jasa</td></tr>
            <?php else: foreach ($services as $s): ?>
            <tr><td><?= htmlspecialchars($s['service_name']) ?></td><td style="text-align:right;font-weight:600"><?= formatRupiah((float)$s['price']) ?></td></tr>
            <?php endforeach; endif; ?>
            <tr style="background:var(--border-light)"><td style="font-weight:600">Subtotal Jasa</td><td style="text-align:right;font-weight:700"><?= formatRupiah((float)$wo['subtotal_services']) ?></td></tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Parts -->
    <div class="card">
      <div class="card-header"><div class="card-header-title"><i class="fas fa-boxes" style="color:var(--primary);margin-right:8px"></i>Daftar Part / Sparepart</div></div>
      <div class="table-responsive">
        <table class="table">
          <thead><tr><th>Part</th><th style="text-align:center">Qty</th><th style="text-align:right">Harga</th><th style="text-align:right">Subtotal</th></tr></thead>
          <tbody>
            <?php if (empty($parts)): ?>
            <tr><td colspan="4" class="text-center text-muted" style="padding:20px">Tidak ada part</td></tr>
            <?php else: foreach ($parts as $p): ?>
            <tr>
              <td><?= htmlspecialchars($p['part_name']) ?></td>
              <td style="text-align:center"><?= $p['quantity'] ?></td>
              <td style="text-align:right"><?= formatRupiah((float)$p['sell_price']) ?></td>
              <td style="text-align:right;font-weight:600"><?= formatRupiah((float)$p['subtotal']) ?></td>
            </tr>
            <?php endforeach; endif; ?>
            <tr style="background:var(--border-light)"><td colspan="3" style="font-weight:600">Subtotal Part</td><td style="text-align:right;font-weight:700"><?= formatRupiah((float)$wo['subtotal_parts']) ?></td></tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Payment Summary -->
    <div class="card">
      <div class="card-header"><div class="card-header-title"><i class="fas fa-money-bill-wave" style="color:var(--primary);margin-right:8px"></i>Pembayaran</div><span class="badge badge-<?= $wo['payment_status'] ?>"><?= paymentLabel($wo['payment_status']) ?></span></div>
      <div class="card-body">
        <div style="display:flex;flex-direction:column;gap:10px;margin-bottom:20px">
          <div class="d-flex justify-between"><span>Subtotal Jasa</span><strong><?= formatRupiah((float)$wo['subtotal_services']) ?></strong></div>
          <div class="d-flex justify-between"><span>Subtotal Part</span><strong><?= formatRupiah((float)$wo['subtotal_parts']) ?></strong></div>
          <?php if ($wo['discount'] > 0): ?>
          <div class="d-flex justify-between"><span>Diskon</span><strong style="color:var(--danger)">- <?= formatRupiah((float)$wo['discount']) ?></strong></div>
          <?php endif; ?>
          <hr style="border:none;border-top:1px solid var(--border)">
          <div class="d-flex justify-between" style="font-size:18px;font-weight:700"><span>Total</span><span style="color:var(--primary)"><?= formatRupiah((float)$wo['total']) ?></span></div>
          <?php if ($wo['paid_amount'] > 0): ?>
          <div class="d-flex justify-between"><span>Dibayar (<em><?= $wo['payment_method'] ?></em>)</span><strong style="color:var(--success)"><?= formatRupiah((float)$wo['paid_amount']) ?></strong></div>
          <?php if ($wo['paid_amount'] < $wo['total']): ?>
          <div class="d-flex justify-between"><span>Sisa</span><strong style="color:var(--danger)"><?= formatRupiah((float)($wo['total']-$wo['paid_amount'])) ?></strong></div>
          <?php endif; ?>
          <?php endif; ?>
        </div>

        <?php if ($wo['payment_status'] !== 'paid'): ?>
        <form method="POST" action="<?= BASE_URL ?>/pages/work-orders/action.php">
          <input type="hidden" name="action" value="update_payment">
          <input type="hidden" name="wo_id" value="<?= $wo['id'] ?>">
          <input type="hidden" name="csrf_token" value="<?= csrf() ?>">
          <div class="form-row">
            <div class="form-group">
              <label class="form-label">Metode</label>
              <select name="payment_method" class="form-control">
                <option value="cash" <?= $wo['payment_method']==='cash'?'selected':'' ?>>Cash</option>
                <option value="transfer" <?= $wo['payment_method']==='transfer'?'selected':'' ?>>Transfer</option>
                <option value="qris" <?= $wo['payment_method']==='qris'?'selected':'' ?>>QRIS</option>
                <option value="debit" <?= $wo['payment_method']==='debit'?'selected':'' ?>>Debit</option>
              </select>
            </div>
            <div class="form-group">
              <label class="form-label">Jumlah Bayar</label>
              <div class="input-group">
                <span class="input-addon">Rp</span>
                <input type="number" name="paid_amount" class="form-control" value="<?= $wo['total'] ?>" min="0">
              </div>
            </div>
          </div>
          <button type="submit" class="btn btn-success w-100"><i class="fas fa-check"></i> Proses Pembayaran</button>
        </form>
        <?php else: ?>
        <div class="alert alert-success"><i class="fas fa-check-circle"></i> Pembayaran lunas via <?= strtoupper($wo['payment_method']) ?></div>
        <?php endif; ?>
      </div>
    </div>

  </div>

</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
