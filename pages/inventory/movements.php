<?php
require_once __DIR__ . '/../../includes/auth.php';
requireLogin();
requireRole('owner','admin','kabeng');

$title      = 'Pergerakan Stok';
$activePage = 'inventory';
$db         = getDB();

// Filters
$partFilter = (int)($_GET['part_id'] ?? 0);
$typeFilter = $_GET['type'] ?? '';
$dateFrom   = $_GET['from'] ?? date('Y-m-01');
$dateTo     = $_GET['to'] ?? date('Y-m-d');
$page       = max(1, (int)($_GET['page'] ?? 1));
$perPage    = 20;

$where  = ["sm.created_at >= ?", "sm.created_at <= ?"];
$params = [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59'];

if ($partFilter) { $where[] = 'sm.part_id = ?'; $params[] = $partFilter; }
if ($typeFilter) { $where[] = 'sm.type = ?'; $params[] = $typeFilter; }
$whereStr = implode(' AND ', $where);

// Total count
$cnt = $db->prepare("SELECT COUNT(*) FROM stock_movements sm WHERE {$whereStr}");
$cnt->execute($params);
$pg = paginate((int)$cnt->fetchColumn(), $perPage, $page);

// Fetch
$stmt = $db->prepare("
    SELECT sm.*, p.name AS part_name, p.code AS part_code, p.unit,
           u.name AS user_name
    FROM stock_movements sm
    LEFT JOIN parts p ON sm.part_id = p.id
    LEFT JOIN users u ON sm.created_by = u.id
    WHERE {$whereStr}
    ORDER BY sm.created_at DESC
    LIMIT {$pg['per_page']} OFFSET {$pg['offset']}
");
$stmt->execute($params);
$movements = $stmt->fetchAll();

// Parts list for filter dropdown
$allParts = $db->query("SELECT id, code, name FROM parts ORDER BY name")->fetchAll();

// Summary stats for the date range
$summaryStmt = $db->prepare("
    SELECT
        SUM(CASE WHEN type='in' THEN quantity ELSE 0 END) AS total_in,
        SUM(CASE WHEN type='out' THEN quantity ELSE 0 END) AS total_out,
        SUM(CASE WHEN type='adjustment' THEN ABS(quantity) ELSE 0 END) AS total_adj,
        COUNT(*) AS total_transactions
    FROM stock_movements sm
    WHERE {$whereStr}
");
$summaryStmt->execute($params);
$summary = $summaryStmt->fetch();

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
  <div class="page-header-left">
    <h1>Pergerakan Stok</h1>
    <p>Riwayat keluar-masuknya barang di gudang</p>
  </div>
  <div class="page-header-right">
    <a href="<?= BASE_URL ?>/pages/inventory/index.php" class="btn btn-outline"><i class="fas fa-arrow-left"></i> Kembali ke Inventori</a>
    <button class="btn btn-primary" onclick="App.openModal('add-modal')"><i class="fas fa-plus"></i> Tambah Manual</button>
  </div>
</div>

<!-- STATS -->
<div class="stats-grid mb-24" style="grid-template-columns:repeat(4, 1fr)">
  <div class="stat-card">
    <div class="stat-icon green"><i class="fas fa-arrow-down"></i></div>
    <div class="stat-info"><div class="stat-label">Stok Masuk</div><div class="stat-value"><?= $summary['total_in'] ?? 0 ?></div></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon red"><i class="fas fa-arrow-up"></i></div>
    <div class="stat-info"><div class="stat-label">Stok Keluar</div><div class="stat-value"><?= $summary['total_out'] ?? 0 ?></div></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon yellow"><i class="fas fa-sliders-h"></i></div>
    <div class="stat-info"><div class="stat-label">Penyesuaian</div><div class="stat-value"><?= $summary['total_adj'] ?? 0 ?></div></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon blue"><i class="fas fa-exchange-alt"></i></div>
    <div class="stat-info"><div class="stat-label">Total Transaksi</div><div class="stat-value"><?= $summary['total_transactions'] ?? 0 ?></div></div>
  </div>
</div>

<!-- FILTER -->
<div class="card mb-16">
  <div class="card-body" style="padding:14px 20px">
    <form method="GET" class="d-flex gap-8 align-center" style="flex-wrap:wrap">
      <input type="date" name="from" class="form-control" value="<?= $dateFrom ?>" style="width:150px">
      <span style="color:var(--text-muted)">—</span>
      <input type="date" name="to" class="form-control" value="<?= $dateTo ?>" style="width:150px">
      <select name="type" class="form-control" style="width:150px">
        <option value="">Semua Tipe</option>
        <option value="in" <?= $typeFilter==='in'?'selected':'' ?>>Masuk</option>
        <option value="out" <?= $typeFilter==='out'?'selected':'' ?>>Keluar</option>
        <option value="adjustment" <?= $typeFilter==='adjustment'?'selected':'' ?>>Penyesuaian</option>
      </select>
      <select name="part_id" class="form-control" style="width:200px">
        <option value="">Semua Barang</option>
        <?php foreach ($allParts as $p): ?>
        <option value="<?= $p['id'] ?>" <?= $partFilter===$p['id']?'selected':'' ?>><?= htmlspecialchars($p['code'].' - '.$p['name']) ?></option>
        <?php endforeach; ?>
      </select>
      <button type="submit" class="btn btn-primary"><i class="fas fa-filter"></i> Filter</button>
    </form>
  </div>
</div>

<!-- TABLE -->
<div class="card">
  <div class="card-header">
    <div class="card-header-title"><i class="fas fa-history" style="color:var(--primary);margin-right:8px"></i>Riwayat Pergerakan <span style="color:var(--text-muted);font-weight:400;font-size:13px">(<?= $pg['total'] ?> data)</span></div>
  </div>
  <div class="table-responsive">
    <table class="table">
      <thead>
        <tr>
          <th>Tanggal</th>
          <th>Part</th>
          <th>Tipe</th>
          <th style="text-align:center">Qty</th>
          <th>Referensi</th>
          <th>Catatan</th>
          <th>Oleh</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($movements)): ?>
        <tr><td colspan="7"><div class="empty-state"><i class="fas fa-exchange-alt"></i><h3>Belum ada pergerakan stok</h3><p>Data akan muncul saat ada transaksi</p></div></td></tr>
        <?php else: foreach ($movements as $m):
          $typeColors = ['in'=>'success','out'=>'danger','adjustment'=>'warning'];
          $typeLabels = ['in'=>'Masuk','out'=>'Keluar','adjustment'=>'Penyesuaian'];
          $refLabels  = ['purchase'=>'Pembelian','wo'=>'Work Order','adjustment'=>'Penyesuaian','return'=>'Retur'];
        ?>
        <tr>
          <td style="white-space:nowrap;font-size:13px"><?= date('d M Y', strtotime($m['created_at'])) ?><br><span style="color:var(--text-muted);font-size:11px"><?= date('H:i', strtotime($m['created_at'])) ?></span></td>
          <td>
            <div style="font-weight:600;font-size:13px"><?= htmlspecialchars($m['part_name']) ?></div>
            <div style="font-size:11px;color:var(--text-muted)"><?= $m['part_code'] ?></div>
          </td>
          <td>
            <span class="badge badge-<?= $typeColors[$m['type']]??'info' ?>" style="font-size:11px">
              <i class="fas fa-arrow-<?= $m['type']==='in'?'down':'up' ?>" style="font-size:10px"></i>
              <?= $typeLabels[$m['type']]??$m['type'] ?>
            </span>
          </td>
          <td style="text-align:center;font-weight:700;font-size:15px;color:var(--<?= $m['type']==='in'?'success':'danger' ?>)">
            <?= $m['type']==='in'?'+':'-' ?><?= abs($m['quantity']) ?> <span style="font-size:11px;font-weight:400;color:var(--text-muted)"><?= $m['unit'] ?></span>
          </td>
          <td>
            <span style="font-size:12px;color:var(--text-secondary)"><?= $refLabels[$m['reference_type']]??$m['reference_type'] ?></span>
            <?php if ($m['reference_type']==='wo' && $m['reference_id']): ?>
            <br><a href="<?= BASE_URL ?>/pages/work-orders/view.php?id=<?= $m['reference_id'] ?>" style="font-size:11px;color:var(--primary)">WO #<?= $m['reference_id'] ?></a>
            <?php endif; ?>
          </td>
          <td style="font-size:13px;max-width:200px"><?= htmlspecialchars($m['notes'] ?: '—') ?></td>
          <td style="font-size:12px;color:var(--text-secondary)"><?= htmlspecialchars($m['user_name'] ?: '—') ?></td>
        </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
  <?php if ($pg['total_pages'] > 1): ?>
  <div class="card-footer">
    <div class="pagination">
      <?php for ($i=max(1,$pg['current']-2);$i<=min($pg['total_pages'],$pg['current']+2);$i++): ?>
      <a href="?page=<?= $i ?>&from=<?= $dateFrom ?>&to=<?= $dateTo ?>&type=<?= $typeFilter ?>&part_id=<?= $partFilter ?>" class="<?= $i===$pg['current']?'active':'' ?>"><?= $i ?></a>
      <?php endfor; ?>
    </div>
  </div>
  <?php endif; ?>
</div>

<!-- ADD STOCK MOVEMENT MODAL -->
<div class="modal-overlay" id="add-modal" onclick="App.closeModalOnOverlay(event)">
  <div class="modal">
    <div class="modal-header">
      <div class="modal-title">Tambah Pergerakan Stok</div>
      <button class="modal-close" onclick="App.closeModal('add-modal')"><i class="fas fa-times"></i></button>
    </div>
    <form method="POST" action="<?= BASE_URL ?>/pages/inventory/stock_action.php">
      <input type="hidden" name="csrf_token" value="<?= csrf() ?>">
      <input type="hidden" name="action" value="add_movement">
      <div class="modal-body">
        <div class="form-group">
          <label class="form-label">Barang <span class="req">*</span></label>
          <select name="part_id" class="form-control" required>
            <option value="">— Pilih Barang —</option>
            <?php foreach ($allParts as $p): ?>
            <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['code'].' - '.$p['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Tipe <span class="req">*</span></label>
            <select name="type" class="form-control" required>
              <option value="in">Masuk (Beli/Restock)</option>
              <option value="out">Keluar (Manual)</option>
              <option value="adjustment">Penyesuaian Stok</option>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Jumlah <span class="req">*</span></label>
            <input type="number" name="quantity" class="form-control" min="1" required placeholder="0">
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Catatan</label>
          <textarea name="notes" class="form-control" rows="2" placeholder="Keterangan pergerakan stok..."></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="App.closeModal('add-modal')">Batal</button>
        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Simpan</button>
      </div>
    </form>
  </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
