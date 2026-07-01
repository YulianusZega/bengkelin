<?php
require_once __DIR__ . '/../../includes/auth.php';
requireLogin();

$title      = 'Pelanggan';
$activePage = 'customers';

$db = getDB();

// Search & pagination
$search  = trim($_GET['search'] ?? '');
$segment = $_GET['segment'] ?? '';
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 15;

$where  = ['1=1'];
$params = [];
if ($search) {
    $where[]  = "(c.name LIKE ? OR c.phone LIKE ? OR c.customer_code LIKE ? OR c.email LIKE ?)";
    $s = "%{$search}%";
    $params   = array_merge($params, [$s, $s, $s, $s]);
}
if ($segment) {
    $where[]  = "c.segment = ?";
    $params[] = $segment;
}
$whereStr = implode(' AND ', $where);

$total = $db->prepare("SELECT COUNT(*) FROM customers c WHERE {$whereStr}");
$total->execute($params);
$pg = paginate((int)$total->fetchColumn(), $perPage, $page);

$stmt = $db->prepare("
    SELECT c.*,
        (SELECT COUNT(*) FROM vehicles v WHERE v.customer_id = c.id) AS vehicle_count,
        (SELECT COUNT(*) FROM work_orders wo WHERE wo.customer_id = c.id) AS visit_count,
        (SELECT COALESCE(SUM(wo2.total),0) FROM work_orders wo2 WHERE wo2.customer_id = c.id AND wo2.payment_status='paid') AS total_spent
    FROM customers c
    WHERE {$whereStr}
    ORDER BY c.created_at DESC
    LIMIT {$pg['per_page']} OFFSET {$pg['offset']}
");
$stmt->execute($params);
$customers = $stmt->fetchAll();

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
  <div class="page-header-left">
    <h1>Data Pelanggan</h1>
    <p>Kelola informasi pelanggan dan riwayat kunjungan</p>
  </div>
  <div class="page-header-right">
    <button class="btn btn-primary" onclick="App.openModal('modal-add-customer')">
      <i class="fas fa-plus"></i> Tambah Pelanggan
    </button>
  </div>
</div>

<!-- FILTER BAR -->
<div class="card mb-16">
  <div class="card-body" style="padding:14px 20px">
    <form method="GET" class="d-flex gap-8 align-center flex-wrap">
      <div class="search-box" style="flex:1;min-width:220px">
        <i class="fas fa-search search-icon"></i>
        <input type="text" name="search" class="form-control" placeholder="Cari nama, telepon, kode..." value="<?= htmlspecialchars($search) ?>">
      </div>
      <select name="segment" class="form-control" style="width:150px">
        <option value="">Semua Segmen</option>
        <option value="new"     <?= $segment==='new'     ?'selected':'' ?>>Baru</option>
        <option value="regular" <?= $segment==='regular' ?'selected':'' ?>>Regular</option>
        <option value="vip"     <?= $segment==='vip'     ?'selected':'' ?>>VIP</option>
      </select>
      <button type="submit" class="btn btn-primary">Filter</button>
      <?php if ($search || $segment): ?>
      <a href="?" class="btn btn-outline">Reset</a>
      <?php endif; ?>
    </form>
  </div>
</div>

<!-- TABLE -->
<div class="card">
  <div class="card-header">
    <div class="card-header-title">
      <i class="fas fa-users" style="color:var(--primary);margin-right:8px"></i>
      Daftar Pelanggan <span style="color:var(--text-muted);font-weight:400;font-size:13px">(<?= $pg['total'] ?> data)</span>
    </div>
  </div>
  <div class="table-responsive">
    <table class="table" id="customers-table">
      <thead>
        <tr>
          <th>Kode</th>
          <th>Nama Pelanggan</th>
          <th>Telepon</th>
          <th>Segmen</th>
          <th>Kendaraan</th>
          <th>Kunjungan</th>
          <th>Total Transaksi</th>
          <th>Aksi</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($customers)): ?>
        <tr><td colspan="8"><div class="empty-state"><i class="fas fa-users"></i><h3>Belum ada pelanggan</h3><p>Tambahkan pelanggan pertama Anda</p></div></td></tr>
        <?php else: foreach ($customers as $c): ?>
        <tr>
          <td><span style="font-size:12px;color:var(--text-muted);font-weight:600"><?= $c['customer_code'] ?></span></td>
          <td>
            <div style="font-weight:600"><?= htmlspecialchars($c['name']) ?></div>
            <?php if ($c['email']): ?><div style="font-size:12px;color:var(--text-muted)"><?= htmlspecialchars($c['email']) ?></div><?php endif; ?>
          </td>
          <td><a href="tel:<?= $c['phone'] ?>" style="color:var(--text-primary);text-decoration:none"><?= $c['phone'] ?></a></td>
          <td><span class="badge badge-<?= $c['segment'] ?>"><?= ucfirst($c['segment']) ?></span></td>
          <td style="text-align:center"><span style="font-weight:600"><?= $c['vehicle_count'] ?></span></td>
          <td style="text-align:center"><span style="font-weight:600"><?= $c['visit_count'] ?></span></td>
          <td style="font-weight:600;color:var(--success)"><?= formatRupiah((float)$c['total_spent']) ?></td>
          <td>
            <div class="table-actions">
              <a href="<?= BASE_URL ?>/pages/customers/view.php?id=<?= $c['id'] ?>" class="btn btn-info btn-sm btn-icon" title="Detail"><i class="fas fa-eye"></i></a>
              <button class="btn btn-warning btn-sm btn-icon" title="Edit" onclick="editCustomer(<?= htmlspecialchars(json_encode($c)) ?>)"><i class="fas fa-pencil-alt"></i></button>
              <?php if ($c['visit_count'] == 0): ?>
              <button class="btn btn-danger btn-sm btn-icon" title="Hapus" onclick="deleteCustomer(<?= $c['id'] ?>, '<?= htmlspecialchars($c['name']) ?>')"><i class="fas fa-trash"></i></button>
              <?php endif; ?>
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
      <span style="font-size:13px;color:var(--text-muted)">
        Menampilkan <?= ($pg['offset']+1) ?>–<?= min($pg['offset']+$pg['per_page'], $pg['total']) ?> dari <?= $pg['total'] ?> data
      </span>
      <div class="pagination">
        <?php if ($pg['current'] > 1): ?>
        <a href="?page=<?= $pg['current']-1 ?>&search=<?= urlencode($search) ?>&segment=<?= $segment ?>">‹</a>
        <?php endif; ?>
        <?php for ($i = max(1,$pg['current']-2); $i <= min($pg['total_pages'],$pg['current']+2); $i++): ?>
        <a href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&segment=<?= $segment ?>" class="<?= $i===$pg['current']?'active':'' ?>"><?= $i ?></a>
        <?php endfor; ?>
        <?php if ($pg['current'] < $pg['total_pages']): ?>
        <a href="?page=<?= $pg['current']+1 ?>&search=<?= urlencode($search) ?>&segment=<?= $segment ?>">›</a>
        <?php endif; ?>
      </div>
    </div>
  </div>
  <?php endif; ?>
</div>

<!-- MODAL ADD/EDIT CUSTOMER -->
<div class="modal-overlay" id="modal-add-customer" onclick="App.closeModalOnOverlay(event)">
  <div class="modal">
    <div class="modal-header">
      <div class="modal-title" id="modal-customer-title">Tambah Pelanggan</div>
      <button class="modal-close" onclick="App.closeModal('modal-add-customer')"><i class="fas fa-times"></i></button>
    </div>
    <form method="POST" action="<?= BASE_URL ?>/pages/customers/action.php" id="form-customer">
      <input type="hidden" name="action" id="customer-action" value="add">
      <input type="hidden" name="id" id="customer-id" value="">
      <input type="hidden" name="csrf_token" value="<?= csrf() ?>">
      <div class="modal-body">
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Nama Lengkap <span class="req">*</span></label>
            <input type="text" name="name" id="c-name" class="form-control" placeholder="Nama pelanggan" required>
          </div>
          <div class="form-group">
            <label class="form-label">No. Telepon <span class="req">*</span></label>
            <input type="text" name="phone" id="c-phone" class="form-control" placeholder="08xxxxxxxxxx" required>
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Email</label>
          <input type="email" name="email" id="c-email" class="form-control" placeholder="email@domain.com">
        </div>
        <div class="form-group">
          <label class="form-label">Alamat</label>
          <textarea name="address" id="c-address" class="form-control" rows="2" placeholder="Alamat lengkap"></textarea>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Segmen</label>
            <select name="segment" id="c-segment" class="form-control">
              <option value="new">Baru</option>
              <option value="regular">Regular</option>
              <option value="vip">VIP</option>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Catatan</label>
            <input type="text" name="notes" id="c-notes" class="form-control" placeholder="Catatan opsional">
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="App.closeModal('modal-add-customer')">Batal</button>
        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Simpan</button>
      </div>
    </form>
  </div>
</div>

<script>
function editCustomer(c) {
  document.getElementById('modal-customer-title').textContent = 'Edit Pelanggan';
  document.getElementById('customer-action').value = 'edit';
  document.getElementById('customer-id').value = c.id;
  document.getElementById('c-name').value = c.name;
  document.getElementById('c-phone').value = c.phone;
  document.getElementById('c-email').value = c.email || '';
  document.getElementById('c-address').value = c.address || '';
  document.getElementById('c-segment').value = c.segment;
  document.getElementById('c-notes').value = c.notes || '';
  App.openModal('modal-add-customer');
}

function deleteCustomer(id, name) {
  App.confirm(`Hapus pelanggan "${name}"? Tindakan ini tidak dapat dikembalikan.`, () => {
    const fd = new FormData();
    fd.append('action', 'delete');
    fd.append('id', id);
    fd.append('csrf_token', '<?= csrf() ?>');
    App.postForm('<?= BASE_URL ?>/pages/customers/action.php', fd).then(res => {
      if (res?.success) { App.toast('Pelanggan dihapus'); location.reload(); }
      else App.toast(res?.message || 'Gagal menghapus', 'error');
    });
  });
}
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
