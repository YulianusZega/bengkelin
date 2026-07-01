<?php
require_once __DIR__ . '/../../includes/auth.php';
requireLogin();

$title      = 'Kendaraan';
$activePage = 'vehicles';

$db = getDB();

$search     = trim($_GET['search'] ?? '');
$type       = $_GET['type'] ?? '';
$customerId = (int)($_GET['customer_id'] ?? 0);
$page       = max(1, (int)($_GET['page'] ?? 1));
$perPage    = 15;

$where  = ['1=1'];
$params = [];
if ($search) {
    $where[] = "(v.plate_number LIKE ? OR v.brand LIKE ? OR v.model LIKE ? OR c.name LIKE ?)";
    $s = "%{$search}%";
    $params = array_merge($params, [$s, $s, $s, $s]);
}
if ($type)       { $where[] = "v.type = ?"; $params[] = $type; }
if ($customerId) { $where[] = "v.customer_id = ?"; $params[] = $customerId; }
$whereStr = implode(' AND ', $where);

$total = $db->prepare("SELECT COUNT(*) FROM vehicles v LEFT JOIN customers c ON v.customer_id=c.id WHERE {$whereStr}");
$total->execute($params);
$pg = paginate((int)$total->fetchColumn(), $perPage, $page);

$stmt = $db->prepare("
    SELECT v.*, c.name AS customer_name, c.phone AS customer_phone, c.id AS cust_id,
           (SELECT COUNT(*) FROM work_orders wo WHERE wo.vehicle_id = v.id) AS visit_count,
           (SELECT MAX(wo2.created_at) FROM work_orders wo2 WHERE wo2.vehicle_id = v.id) AS last_visit
    FROM vehicles v
    LEFT JOIN customers c ON v.customer_id = c.id
    WHERE {$whereStr}
    ORDER BY v.created_at DESC
    LIMIT {$pg['per_page']} OFFSET {$pg['offset']}
");
$stmt->execute($params);
$vehicles = $stmt->fetchAll();

$preCustomer = null;
if ($customerId) {
    $s = $db->prepare("SELECT * FROM customers WHERE id=?");
    $s->execute([$customerId]);
    $preCustomer = $s->fetch();
}

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
  <div class="page-header-left">
    <h1>Data Kendaraan</h1>
    <p><?= $preCustomer ? 'Kendaraan milik ' . htmlspecialchars($preCustomer['name']) : 'Semua kendaraan terdaftar' ?></p>
  </div>
  <div class="page-header-right">
    <?php if ($preCustomer): ?>
    <a href="<?= BASE_URL ?>/pages/customers/view.php?id=<?= $customerId ?>" class="btn btn-outline"><i class="fas fa-arrow-left"></i> Kembali</a>
    <?php endif; ?>
    <button class="btn btn-primary" onclick="App.openModal('modal-add-vehicle')"><i class="fas fa-plus"></i> Tambah Kendaraan</button>
  </div>
</div>

<!-- FILTER -->
<div class="card mb-16">
  <div class="card-body" style="padding:14px 20px">
    <form method="GET" class="d-flex gap-8 align-center flex-wrap">
      <?php if ($customerId): ?><input type="hidden" name="customer_id" value="<?= $customerId ?>"><?php endif; ?>
      <div class="search-box" style="flex:1;min-width:200px">
        <i class="fas fa-search search-icon"></i>
        <input type="text" name="search" class="form-control" placeholder="Cari plat, merek, model, pemilik..." value="<?= htmlspecialchars($search) ?>">
      </div>
      <select name="type" class="form-control" style="width:140px">
        <option value="">Semua Jenis</option>
        <option value="mobil" <?= $type==='mobil'?'selected':'' ?>>🚗 Mobil</option>
        <option value="motor" <?= $type==='motor'?'selected':'' ?>>🏍 Motor</option>
      </select>
      <button type="submit" class="btn btn-primary">Filter</button>
      <?php if ($search || $type): ?><a href="?<?= $customerId?"customer_id={$customerId}":'' ?>" class="btn btn-outline">Reset</a><?php endif; ?>
    </form>
  </div>
</div>

<!-- TABLE -->
<div class="card">
  <div class="card-header">
    <div class="card-header-title">
      <i class="fas fa-car" style="color:var(--primary);margin-right:8px"></i>
      Daftar Kendaraan <span style="color:var(--text-muted);font-weight:400;font-size:13px">(<?= $pg['total'] ?> unit)</span>
    </div>
  </div>
  <div class="table-responsive">
    <table class="table">
      <thead>
        <tr>
          <th>Plat Nomor</th>
          <th>Kendaraan</th>
          <th>Pemilik</th>
          <th>Km Terakhir</th>
          <th>Kunjungan</th>
          <th>Servis Terakhir</th>
          <th>Aksi</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($vehicles)): ?>
        <tr><td colspan="7"><div class="empty-state"><i class="fas fa-car"></i><h3>Belum ada kendaraan</h3><p>Tambahkan kendaraan baru</p></div></td></tr>
        <?php else: foreach ($vehicles as $v): ?>
        <tr>
          <td>
            <div style="display:flex;align-items:center;gap:10px">
              <div style="width:36px;height:36px;border-radius:8px;background:<?= $v['type']==='mobil'?'var(--info-bg)':'var(--success-bg)' ?>;display:flex;align-items:center;justify-content:center;color:<?= $v['type']==='mobil'?'var(--info)':'var(--success)' ?>;font-size:16px">
                <i class="fas <?= $v['type']==='mobil'?'fa-car':'fa-motorcycle' ?>"></i>
              </div>
              <span style="font-weight:700;letter-spacing:.5px"><?= $v['plate_number'] ?></span>
            </div>
          </td>
          <td>
            <div style="font-weight:600"><?= htmlspecialchars($v['brand'] . ' ' . $v['model']) ?></div>
            <div style="font-size:12px;color:var(--text-muted)"><?= $v['year'] ?> · <?= $v['color'] ?> · <?= ucfirst($v['fuel_type']) ?></div>
          </td>
          <td>
            <a href="<?= BASE_URL ?>/pages/customers/view.php?id=<?= $v['cust_id'] ?>" style="font-weight:600;color:var(--text-primary);text-decoration:none">
              <?= htmlspecialchars($v['customer_name']) ?>
            </a>
            <div style="font-size:12px;color:var(--text-muted)"><?= $v['customer_phone'] ?></div>
          </td>
          <td style="font-weight:600"><?= number_format($v['km_current']) ?> km</td>
          <td style="text-align:center"><span style="font-weight:600"><?= $v['visit_count'] ?>x</span></td>
          <td style="font-size:13px;color:var(--text-muted)"><?= $v['last_visit'] ? date('d M Y', strtotime($v['last_visit'])) : '—' ?></td>
          <td>
            <div class="table-actions">
              <a href="<?= BASE_URL ?>/pages/work-orders/create.php?vehicle_id=<?= $v['id'] ?>&customer_id=<?= $v['cust_id'] ?>" class="btn btn-primary btn-sm" title="Buat WO">
                <i class="fas fa-plus"></i> WO
              </a>
              <button class="btn btn-warning btn-sm btn-icon" title="Edit" onclick="editVehicle(<?= htmlspecialchars(json_encode($v)) ?>)"><i class="fas fa-pencil-alt"></i></button>
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
        <a href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&type=<?= $type ?><?= $customerId?"&customer_id={$customerId}":'' ?>" class="<?= $i===$pg['current']?'active':'' ?>"><?= $i ?></a>
        <?php endfor; ?>
      </div>
    </div>
  </div>
  <?php endif; ?>
</div>

<!-- MODAL ADD/EDIT VEHICLE -->
<div class="modal-overlay" id="modal-add-vehicle" onclick="App.closeModalOnOverlay(event)">
  <div class="modal modal-lg">
    <div class="modal-header">
      <div class="modal-title" id="modal-vehicle-title">Tambah Kendaraan</div>
      <button class="modal-close" onclick="App.closeModal('modal-add-vehicle')"><i class="fas fa-times"></i></button>
    </div>
    <form method="POST" action="<?= BASE_URL ?>/pages/vehicles/action.php">
      <input type="hidden" name="action" id="vehicle-action" value="add">
      <input type="hidden" name="id" id="vehicle-id" value="">
      <input type="hidden" name="redirect_customer" value="<?= $customerId ?>">
      <input type="hidden" name="csrf_token" value="<?= csrf() ?>">
      <div class="modal-body">
        <div class="form-group">
          <label class="form-label">Pemilik (Pelanggan) <span class="req">*</span></label>
          <?php if ($preCustomer): ?>
          <input type="hidden" name="customer_id" value="<?= $customerId ?>">
          <input type="text" class="form-control" value="<?= htmlspecialchars($preCustomer['name']) ?>" disabled>
          <?php else: ?>
          <select name="customer_id" id="v-customer" class="form-control" required>
            <option value="">— Pilih Pelanggan —</option>
            <?php foreach ($db->query("SELECT id,name,phone FROM customers ORDER BY name") as $c): ?>
            <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?> (<?= $c['phone'] ?>)</option>
            <?php endforeach; ?>
          </select>
          <?php endif; ?>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Jenis <span class="req">*</span></label>
            <select name="type" id="vf-type" class="form-control" required>
              <option value="mobil">🚗 Mobil</option>
              <option value="motor">🏍 Motor</option>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Plat Nomor <span class="req">*</span></label>
            <input type="text" name="plate_number" id="vf-plate" class="form-control" required placeholder="B 1234 ABC" oninput="this.value=this.value.toUpperCase()">
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Merek <span class="req">*</span></label>
            <input type="text" name="brand" id="vf-brand" class="form-control" required placeholder="Toyota, Honda, Yamaha...">
          </div>
          <div class="form-group">
            <label class="form-label">Model <span class="req">*</span></label>
            <input type="text" name="model" id="vf-model" class="form-control" required placeholder="Avanza, Vario, NMAX...">
          </div>
        </div>
        <div class="form-row-3">
          <div class="form-group">
            <label class="form-label">Tahun</label>
            <input type="number" name="year" id="vf-year" class="form-control" value="<?= date('Y') ?>" min="1990" max="<?= date('Y')+1 ?>">
          </div>
          <div class="form-group">
            <label class="form-label">Warna</label>
            <input type="text" name="color" id="vf-color" class="form-control" placeholder="Putih">
          </div>
          <div class="form-group">
            <label class="form-label">Bahan Bakar</label>
            <select name="fuel_type" id="vf-fuel" class="form-control">
              <option value="bensin">Bensin</option>
              <option value="diesel">Diesel</option>
              <option value="listrik">Listrik</option>
            </select>
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Km Saat Ini</label>
            <input type="number" name="km_current" id="vf-km" class="form-control" value="0" min="0">
          </div>
          <div class="form-group">
            <label class="form-label">Catatan</label>
            <input type="text" name="notes" id="vf-notes" class="form-control" placeholder="Catatan opsional">
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="App.closeModal('modal-add-vehicle')">Batal</button>
        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Simpan</button>
      </div>
    </form>
  </div>
</div>

<script>
function editVehicle(v) {
  document.getElementById('modal-vehicle-title').textContent = 'Edit Kendaraan';
  document.getElementById('vehicle-action').value = 'edit';
  document.getElementById('vehicle-id').value = v.id;
  if (document.getElementById('v-customer')) document.getElementById('v-customer').value = v.customer_id;
  document.getElementById('vf-type').value   = v.type;
  document.getElementById('vf-plate').value  = v.plate_number;
  document.getElementById('vf-brand').value  = v.brand;
  document.getElementById('vf-model').value  = v.model;
  document.getElementById('vf-year').value   = v.year;
  document.getElementById('vf-color').value  = v.color || '';
  document.getElementById('vf-fuel').value   = v.fuel_type;
  document.getElementById('vf-km').value     = v.km_current;
  document.getElementById('vf-notes').value  = v.notes || '';
  App.openModal('modal-add-vehicle');
}
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
