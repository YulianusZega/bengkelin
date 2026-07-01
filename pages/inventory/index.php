<?php
require_once __DIR__ . '/../../includes/auth.php';
requireLogin();

$title      = 'Inventori';
$activePage = 'inventory';

$db = getDB();

$search   = trim($_GET['search'] ?? '');
$catId    = (int)($_GET['category'] ?? 0);
$stockFlt = $_GET['stock'] ?? '';
$page     = max(1, (int)($_GET['page'] ?? 1));
$perPage  = 20;

$where  = ['1=1'];
$params = [];
if ($search) { $where[] = "(p.name LIKE ? OR p.code LIKE ? OR p.brand LIKE ?)"; $s="%{$search}%"; $params=array_merge($params,[$s,$s,$s]); }
if ($catId)  { $where[] = "p.category_id = ?"; $params[] = $catId; }
if ($stockFlt === 'low')     { $where[] = "p.stock <= p.min_stock AND p.stock > 0"; }
if ($stockFlt === 'empty')   { $where[] = "p.stock = 0"; }
if ($stockFlt === 'ok')      { $where[] = "p.stock > p.min_stock"; }
$whereStr = implode(' AND ', $where);

$total = $db->prepare("SELECT COUNT(*) FROM parts p WHERE {$whereStr}");
$total->execute($params);
$pg = paginate((int)$total->fetchColumn(), $perPage, $page);

$stmt = $db->prepare("
    SELECT p.*, pc.name AS category_name
    FROM parts p
    LEFT JOIN part_categories pc ON p.category_id = pc.id
    WHERE {$whereStr}
    ORDER BY p.name ASC
    LIMIT {$pg['per_page']} OFFSET {$pg['offset']}
");
$stmt->execute($params);
$parts = $stmt->fetchAll();

$categories = $db->query("SELECT * FROM part_categories ORDER BY name")->fetchAll();

// Summary stats
$totalValue  = $db->query("SELECT COALESCE(SUM(stock * buy_price),0) FROM parts")->fetchColumn();
$lowCount    = $db->query("SELECT COUNT(*) FROM parts WHERE stock <= min_stock AND stock > 0")->fetchColumn();
$emptyCount  = $db->query("SELECT COUNT(*) FROM parts WHERE stock = 0")->fetchColumn();
$totalItems  = $db->query("SELECT COUNT(*) FROM parts")->fetchColumn();

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
  <div class="page-header-left">
    <h1>Inventori & Sparepart</h1>
    <p>Kelola stok sparepart dan produk bengkel</p>
  </div>
  <div class="page-header-right">
    <a href="<?= BASE_URL ?>/pages/reports/export.php?type=inventory" class="btn btn-outline"><i class="fas fa-file-csv"></i> Export CSV</a>
    <a href="<?= BASE_URL ?>/pages/inventory/movements.php" class="btn btn-outline"><i class="fas fa-history"></i> Riwayat Stok</a>
    <button class="btn btn-outline" onclick="App.openModal('modal-adj-stock')"><i class="fas fa-exchange-alt"></i> Sesuaikan Stok</button>
    <button class="btn btn-primary" onclick="App.openModal('modal-add-part')"><i class="fas fa-plus"></i> Tambah Part</button>
  </div>
</div>

<!-- STAT CARDS -->
<div class="stats-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:20px">
  <div class="stat-card">
    <div class="stat-icon blue"><i class="fas fa-boxes"></i></div>
    <div class="stat-info"><div class="stat-label">Total Item</div><div class="stat-value"><?= $totalItems ?></div></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon green"><i class="fas fa-dollar-sign"></i></div>
    <div class="stat-info"><div class="stat-label">Nilai Stok</div><div class="stat-value" style="font-size:16px"><?= formatRupiah((float)$totalValue) ?></div></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon yellow"><i class="fas fa-exclamation-triangle"></i></div>
    <div class="stat-info"><div class="stat-label">Stok Menipis</div><div class="stat-value"><?= $lowCount ?></div><div class="stat-change <?= $lowCount>0?'down':'neutral' ?>"><?= $lowCount>0?'Perlu restock':'Aman' ?></div></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon red"><i class="fas fa-times-circle"></i></div>
    <div class="stat-info"><div class="stat-label">Stok Habis</div><div class="stat-value"><?= $emptyCount ?></div><div class="stat-change <?= $emptyCount>0?'down':'neutral' ?>"><?= $emptyCount>0?'Segera order':'Aman' ?></div></div>
  </div>
</div>

<!-- FILTER -->
<div class="card mb-16">
  <div class="card-body" style="padding:14px 20px">
    <form method="GET" class="d-flex gap-8 align-center flex-wrap">
      <div class="search-box" style="flex:1;min-width:200px">
        <i class="fas fa-search search-icon"></i>
        <input type="text" name="search" class="form-control" placeholder="Cari nama, kode, merek..." value="<?= htmlspecialchars($search) ?>">
      </div>
      <select name="category" class="form-control" style="width:170px">
        <option value="">Semua Kategori</option>
        <?php foreach ($categories as $cat): ?>
        <option value="<?= $cat['id'] ?>" <?= $catId===$cat['id']?'selected':'' ?>><?= htmlspecialchars($cat['name']) ?></option>
        <?php endforeach; ?>
      </select>
      <select name="stock" class="form-control" style="width:140px">
        <option value="">Semua Stok</option>
        <option value="ok"    <?= $stockFlt==='ok'   ?'selected':'' ?>>Stok Aman</option>
        <option value="low"   <?= $stockFlt==='low'  ?'selected':'' ?>>Stok Menipis</option>
        <option value="empty" <?= $stockFlt==='empty'?'selected':'' ?>>Stok Habis</option>
      </select>
      <button type="submit" class="btn btn-primary">Filter</button>
      <?php if ($search || $catId || $stockFlt): ?><a href="?" class="btn btn-outline">Reset</a><?php endif; ?>
    </form>
  </div>
</div>

<!-- TABLE -->
<div class="card">
  <div class="card-header">
    <div class="card-header-title"><i class="fas fa-box" style="color:var(--primary);margin-right:8px"></i>Daftar Sparepart <span style="color:var(--text-muted);font-weight:400;font-size:13px">(<?= $pg['total'] ?> item)</span></div>
  </div>
  <div class="table-responsive">
    <table class="table">
      <thead>
        <tr>
          <th>Kode</th>
          <th>Nama Part</th>
          <th>Kategori</th>
          <th>Stok</th>
          <th>Harga Beli</th>
          <th>Harga Jual</th>
          <th>Lokasi</th>
          <th>Aksi</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($parts)): ?>
        <tr><td colspan="8"><div class="empty-state"><i class="fas fa-box-open"></i><h3>Tidak ada part ditemukan</h3></div></td></tr>
        <?php else: foreach ($parts as $p):
          $pct = $p['min_stock'] > 0 ? min(100, ($p['stock'] / ($p['min_stock'] * 2)) * 100) : 100;
          $sc  = $p['stock'] <= 0 ? 'critical' : ($p['stock'] <= $p['min_stock'] ? 'low' : 'good');
        ?>
        <tr>
          <td><span style="font-family:monospace;font-size:12px;background:var(--border-light);padding:2px 8px;border-radius:4px"><?= $p['code'] ?></span></td>
          <td>
            <div style="font-weight:600"><?= htmlspecialchars($p['name']) ?></div>
            <div style="font-size:12px;color:var(--text-muted)"><?= $p['brand'] ?> · <?= $p['unit'] ?></div>
          </td>
          <td style="font-size:13px"><?= htmlspecialchars($p['category_name'] ?? '—') ?></td>
          <td>
            <div class="stock-indicator">
              <span style="font-weight:700;min-width:24px;color:<?= $sc==='critical'?'var(--danger)':($sc==='low'?'var(--warning)':'var(--success)') ?>"><?= $p['stock'] ?></span>
              <div class="stock-bar">
                <div class="stock-fill <?= $sc ?>" style="width:<?= $pct ?>%"></div>
              </div>
            </div>
            <div style="font-size:11px;color:var(--text-muted)">min <?= $p['min_stock'] ?></div>
          </td>
          <td><?= formatRupiah((float)$p['buy_price']) ?></td>
          <td style="font-weight:600;color:var(--success)"><?= formatRupiah((float)$p['sell_price']) ?></td>
          <td><span style="font-size:12px;font-weight:600"><?= $p['shelf_location'] ?: '—' ?></span></td>
          <td>
            <div class="table-actions">
              <button class="btn btn-warning btn-sm btn-icon" title="Edit" onclick="editPart(<?= htmlspecialchars(json_encode($p)) ?>)"><i class="fas fa-pencil-alt"></i></button>
              <button class="btn btn-success btn-sm btn-icon" title="Tambah Stok" onclick="adjStock(<?= $p['id'] ?>, '<?= htmlspecialchars($p['name']) ?>', <?= $p['stock'] ?>)"><i class="fas fa-plus"></i></button>
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
      <span style="font-size:13px;color:var(--text-muted)">Menampilkan <?= $pg['offset']+1 ?>–<?= min($pg['offset']+$pg['per_page'],$pg['total']) ?> dari <?= $pg['total'] ?></span>
      <div class="pagination">
        <?php for ($i=max(1,$pg['current']-2);$i<=min($pg['total_pages'],$pg['current']+2);$i++): ?>
        <a href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&category=<?= $catId ?>&stock=<?= $stockFlt ?>" class="<?= $i===$pg['current']?'active':'' ?>"><?= $i ?></a>
        <?php endfor; ?>
      </div>
    </div>
  </div>
  <?php endif; ?>
</div>

<!-- MODAL ADD/EDIT PART -->
<div class="modal-overlay" id="modal-add-part" onclick="App.closeModalOnOverlay(event)">
  <div class="modal modal-lg">
    <div class="modal-header">
      <div class="modal-title" id="modal-part-title">Tambah Sparepart</div>
      <button class="modal-close" onclick="App.closeModal('modal-add-part')"><i class="fas fa-times"></i></button>
    </div>
    <form method="POST" action="<?= BASE_URL ?>/pages/inventory/action.php" id="form-part">
      <input type="hidden" name="action" id="part-action" value="add">
      <input type="hidden" name="id" id="part-id" value="">
      <input type="hidden" name="csrf_token" value="<?= csrf() ?>">
      <div class="modal-body">
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Kode Part</label>
            <input type="text" name="code" id="p-code" class="form-control" placeholder="OLI-001">
          </div>
          <div class="form-group">
            <label class="form-label">Nama Part <span class="req">*</span></label>
            <input type="text" name="name" id="p-name" class="form-control" required placeholder="Nama sparepart">
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Kategori</label>
            <select name="category_id" id="p-category" class="form-control">
              <option value="">— Pilih Kategori —</option>
              <?php foreach ($categories as $cat): ?>
              <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Merek</label>
            <input type="text" name="brand" id="p-brand" class="form-control" placeholder="Merek / brand">
          </div>
        </div>
        <div class="form-row-3">
          <div class="form-group">
            <label class="form-label">Satuan</label>
            <select name="unit" id="p-unit" class="form-control">
              <option value="pcs">pcs</option><option value="liter">liter</option>
              <option value="botol">botol</option><option value="set">set</option>
              <option value="meter">meter</option><option value="kg">kg</option>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Stok Awal</label>
            <input type="number" name="stock" id="p-stock" class="form-control" value="0" min="0">
          </div>
          <div class="form-group">
            <label class="form-label">Minimum Stok</label>
            <input type="number" name="min_stock" id="p-min-stock" class="form-control" value="5" min="0">
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Harga Beli <span class="req">*</span></label>
            <div class="input-group">
              <span class="input-addon">Rp</span>
              <input type="number" name="buy_price" id="p-buy" class="form-control" required min="0" placeholder="0">
            </div>
          </div>
          <div class="form-group">
            <label class="form-label">Harga Jual <span class="req">*</span></label>
            <div class="input-group">
              <span class="input-addon">Rp</span>
              <input type="number" name="sell_price" id="p-sell" class="form-control" required min="0" placeholder="0">
            </div>
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Lokasi Rak</label>
            <input type="text" name="shelf_location" id="p-shelf" class="form-control" placeholder="A1, B2, dst.">
          </div>
          <div class="form-group">
            <label class="form-label">Jenis Kendaraan</label>
            <select name="vehicle_type" id="p-vtype" class="form-control">
              <option value="both">Semua</option>
              <option value="mobil">Mobil</option>
              <option value="motor">Motor</option>
            </select>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="App.closeModal('modal-add-part')">Batal</button>
        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Simpan</button>
      </div>
    </form>
  </div>
</div>

<!-- MODAL STOCK ADJUSTMENT -->
<div class="modal-overlay" id="modal-adj-stock" onclick="App.closeModalOnOverlay(event)">
  <div class="modal">
    <div class="modal-header">
      <div class="modal-title">Sesuaikan Stok</div>
      <button class="modal-close" onclick="App.closeModal('modal-adj-stock')"><i class="fas fa-times"></i></button>
    </div>
    <form method="POST" action="<?= BASE_URL ?>/pages/inventory/action.php">
      <input type="hidden" name="action" value="adjust">
      <input type="hidden" name="csrf_token" value="<?= csrf() ?>">
      <div class="modal-body">
        <div class="form-group">
          <label class="form-label">Part <span class="req">*</span></label>
          <select name="part_id" id="adj-part" class="form-control" required>
            <option value="">— Pilih Part —</option>
            <?php foreach ($db->query("SELECT id,name,stock FROM parts ORDER BY name") as $pt): ?>
            <option value="<?= $pt['id'] ?>"><?= htmlspecialchars($pt['name']) ?> (stok: <?= $pt['stock'] ?>)</option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Tipe <span class="req">*</span></label>
            <select name="type" class="form-control" required>
              <option value="in">Masuk (Pembelian)</option>
              <option value="out">Keluar (Penggunaan)</option>
              <option value="adjustment">Koreksi</option>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Jumlah <span class="req">*</span></label>
            <input type="number" name="quantity" class="form-control" required min="1" placeholder="0">
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Catatan</label>
          <textarea name="notes" class="form-control" rows="2" placeholder="Alasan penyesuaian stok"></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="App.closeModal('modal-adj-stock')">Batal</button>
        <button type="submit" class="btn btn-primary"><i class="fas fa-check"></i> Simpan</button>
      </div>
    </form>
  </div>
</div>

<script>
function editPart(p) {
  document.getElementById('modal-part-title').textContent = 'Edit Sparepart';
  document.getElementById('part-action').value = 'edit';
  document.getElementById('part-id').value = p.id;
  document.getElementById('p-code').value = p.code || '';
  document.getElementById('p-name').value = p.name;
  document.getElementById('p-category').value = p.category_id || '';
  document.getElementById('p-brand').value = p.brand || '';
  document.getElementById('p-unit').value = p.unit;
  document.getElementById('p-stock').value = p.stock;
  document.getElementById('p-min-stock').value = p.min_stock;
  document.getElementById('p-buy').value = p.buy_price;
  document.getElementById('p-sell').value = p.sell_price;
  document.getElementById('p-shelf').value = p.shelf_location || '';
  document.getElementById('p-vtype').value = p.vehicle_type;
  App.openModal('modal-add-part');
}

function adjStock(id, name, stock) {
  document.getElementById('adj-part').value = id;
  App.openModal('modal-adj-stock');
}
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
