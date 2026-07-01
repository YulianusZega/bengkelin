<?php
// ============================================================
// BENGKELIN - Services & Categories Management
// ============================================================
require_once __DIR__ . '/../../includes/auth.php';
requireLogin();
requireRole('owner','admin','senior_teknisi');

$title      = 'Layanan';
$activePage = 'services';
$db         = getDB();

// Categories
$categories = $db->query("SELECT sc.*, (SELECT COUNT(*) FROM service_categories s2 WHERE s2.id = sc.id) AS svc_count FROM service_categories sc ORDER BY sc.name")->fetchAll();

// All services list
$services = $db->query("
    SELECT s.service_name, s.price, COUNT(*) AS used_count, SUM(s.price) AS total_revenue,
           MAX(wo.created_at) AS last_used
    FROM wo_services s
    JOIN work_orders wo ON s.wo_id = wo.id
    GROUP BY s.service_name
    ORDER BY used_count DESC
")->fetchAll();

// Stats
$totalCategories = count($categories);
$totalServiceTypes = count($services);
$totalSvcRevenue = $db->query("SELECT COALESCE(SUM(price),0) FROM wo_services")->fetchColumn();
$avgSvcPrice = $db->query("SELECT COALESCE(AVG(price),0) FROM wo_services")->fetchColumn();

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
  <div class="page-header-left">
    <h1>Manajemen Layanan</h1>
    <p>Kelola kategori layanan dan daftar jasa bengkel</p>
  </div>
  <div class="page-header-right">
    <button class="btn btn-primary" onclick="App.openModal('modal-add-cat')"><i class="fas fa-plus"></i> Tambah Kategori</button>
  </div>
</div>

<!-- STATS -->
<div class="stats-grid mb-24" style="grid-template-columns:repeat(4, 1fr)">
  <div class="stat-card">
    <div class="stat-icon blue"><i class="fas fa-th-large"></i></div>
    <div class="stat-info"><div class="stat-label">Kategori</div><div class="stat-value"><?= $totalCategories ?></div></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon orange"><i class="fas fa-tools"></i></div>
    <div class="stat-info"><div class="stat-label">Jenis Layanan</div><div class="stat-value"><?= $totalServiceTypes ?></div></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon green"><i class="fas fa-money-bill-wave"></i></div>
    <div class="stat-info"><div class="stat-label">Total Pendapatan Jasa</div><div class="stat-value" style="font-size:16px"><?= formatRupiah((float)$totalSvcRevenue) ?></div></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon purple"><i class="fas fa-calculator"></i></div>
    <div class="stat-info"><div class="stat-label">Rata-rata Harga</div><div class="stat-value" style="font-size:16px"><?= formatRupiah((float)$avgSvcPrice) ?></div></div>
  </div>
</div>

<!-- CATEGORIES GRID -->
<div class="card mb-24">
  <div class="card-header">
    <div class="card-header-title"><i class="fas fa-th-large" style="color:var(--primary);margin-right:8px"></i>Kategori Layanan</div>
  </div>
  <div class="card-body">
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:14px">
      <?php foreach ($categories as $cat): ?>
      <div style="background:var(--bg);border-radius:12px;padding:18px;border:1px solid var(--border-light);transition:transform .2s" onmouseover="this.style.transform='translateY(-2px)'" onmouseout="this.style.transform=''">
        <div style="display:flex;align-items:center;gap:12px;margin-bottom:12px">
          <div style="width:44px;height:44px;background:var(--primary-bg);border-radius:10px;display:flex;align-items:center;justify-content:center;color:var(--primary);font-size:18px">
            <i class="fas <?= $cat['icon'] ?: 'fa-wrench' ?>"></i>
          </div>
          <div>
            <div style="font-weight:700;font-size:15px"><?= htmlspecialchars($cat['name']) ?></div>
            <?php if ($cat['description']): ?>
            <div style="font-size:12px;color:var(--text-muted)"><?= htmlspecialchars($cat['description']) ?></div>
            <?php endif; ?>
          </div>
        </div>
        <div style="display:flex;gap:8px;margin-top:10px">
          <button class="btn btn-warning btn-sm" onclick="editCat(<?= htmlspecialchars(json_encode($cat)) ?>)"><i class="fas fa-pencil-alt"></i> Edit</button>
          <button class="btn btn-outline btn-sm" style="color:var(--danger);border-color:var(--danger)" onclick="deleteCat(<?= $cat['id'] ?>)"><i class="fas fa-trash"></i></button>
        </div>
      </div>
      <?php endforeach; ?>
      <?php if (empty($categories)): ?>
      <div style="grid-column:1/-1;text-align:center;padding:40px;color:var(--text-muted)">
        <i class="fas fa-th-large" style="font-size:40px;opacity:.2;display:block;margin-bottom:12px"></i>
        Belum ada kategori. Klik "Tambah Kategori" untuk memulai.
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- TOP SERVICES TABLE -->
<div class="card">
  <div class="card-header">
    <div class="card-header-title"><i class="fas fa-star" style="color:var(--warning);margin-right:8px"></i>Layanan yang Sering Digunakan <span style="color:var(--text-muted);font-weight:400;font-size:13px">(<?= count($services) ?> jenis)</span></div>
  </div>
  <div class="table-responsive">
    <table class="table">
      <thead>
        <tr>
          <th>#</th>
          <th>Nama Layanan</th>
          <th style="text-align:center">Digunakan</th>
          <th style="text-align:right">Harga Terakhir</th>
          <th style="text-align:right">Total Pendapatan</th>
          <th>Terakhir</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($services)): ?>
        <tr><td colspan="6"><div class="empty-state"><i class="fas fa-tools"></i><h3>Belum ada data layanan</h3><p>Data akan muncul setelah ada work order yang dibuat</p></div></td></tr>
        <?php else: foreach ($services as $i => $s): ?>
        <tr>
          <td><strong style="color:var(--primary)"><?= $i + 1 ?></strong></td>
          <td style="font-weight:600"><?= htmlspecialchars($s['service_name']) ?></td>
          <td style="text-align:center">
            <span style="font-weight:700;background:var(--primary-bg);color:var(--primary);padding:3px 10px;border-radius:12px;font-size:12px"><?= $s['used_count'] ?>x</span>
          </td>
          <td style="text-align:right;font-size:13px"><?= formatRupiah((float)$s['price']) ?></td>
          <td style="text-align:right;font-weight:700;color:var(--success)"><?= formatRupiah((float)$s['total_revenue']) ?></td>
          <td style="font-size:12px;color:var(--text-muted)"><?= $s['last_used'] ? date('d M Y', strtotime($s['last_used'])) : '—' ?></td>
        </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- MODAL ADD/EDIT CATEGORY -->
<div class="modal-overlay" id="modal-add-cat" onclick="App.closeModalOnOverlay(event)">
  <div class="modal">
    <div class="modal-header">
      <div class="modal-title" id="modal-cat-title">Tambah Kategori</div>
      <button class="modal-close" onclick="App.closeModal('modal-add-cat')"><i class="fas fa-times"></i></button>
    </div>
    <form method="POST" action="<?= BASE_URL ?>/pages/services/action.php">
      <input type="hidden" name="action" id="cat-action" value="add_category">
      <input type="hidden" name="id" id="cat-id" value="">
      <input type="hidden" name="csrf_token" value="<?= csrf() ?>">
      <div class="modal-body">
        <div class="form-group">
          <label class="form-label">Nama Kategori <span class="req">*</span></label>
          <input type="text" name="name" id="cat-name" class="form-control" required placeholder="Servis Berkala, Perbaikan Mesin, dll.">
        </div>
        <div class="form-group">
          <label class="form-label">Deskripsi</label>
          <textarea name="description" id="cat-desc" class="form-control" rows="2" placeholder="Deskripsi singkat kategori"></textarea>
        </div>
        <div class="form-group">
          <label class="form-label">Icon (Font Awesome)</label>
          <div style="display:flex;gap:8px;align-items:center">
            <input type="text" name="icon" id="cat-icon" class="form-control" placeholder="fa-wrench" value="fa-wrench" style="flex:1">
            <div id="icon-preview" style="width:40px;height:40px;background:var(--primary-bg);border-radius:8px;display:flex;align-items:center;justify-content:center;color:var(--primary);font-size:16px">
              <i class="fas fa-wrench"></i>
            </div>
          </div>
          <div style="margin-top:8px;display:flex;flex-wrap:wrap;gap:6px">
            <?php
            $icons = ['fa-wrench','fa-oil-can','fa-cogs','fa-car-battery','fa-paint-roller','fa-bolt','fa-fan','fa-clipboard-check','fa-shield-alt','fa-tachometer-alt','fa-compress-arrows-alt','fa-snowflake'];
            foreach ($icons as $ic):
            ?>
            <button type="button" class="btn btn-outline btn-sm" style="padding:6px 10px;font-size:13px" onclick="selectIcon('<?= $ic ?>')"><i class="fas <?= $ic ?>"></i></button>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="App.closeModal('modal-add-cat')">Batal</button>
        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Simpan</button>
      </div>
    </form>
  </div>
</div>

<script>
function selectIcon(icon) {
  document.getElementById('cat-icon').value = icon;
  document.getElementById('icon-preview').innerHTML = '<i class="fas ' + icon + '"></i>';
}

document.getElementById('cat-icon').addEventListener('input', function() {
  document.getElementById('icon-preview').innerHTML = '<i class="fas ' + this.value + '"></i>';
});

function editCat(cat) {
  document.getElementById('modal-cat-title').textContent = 'Edit Kategori';
  document.getElementById('cat-action').value = 'edit_category';
  document.getElementById('cat-id').value = cat.id;
  document.getElementById('cat-name').value = cat.name;
  document.getElementById('cat-desc').value = cat.description || '';
  document.getElementById('cat-icon').value = cat.icon || 'fa-wrench';
  document.getElementById('icon-preview').innerHTML = '<i class="fas ' + (cat.icon || 'fa-wrench') + '"></i>';
  App.openModal('modal-add-cat');
}

function deleteCat(id) {
  if (!confirm('Hapus kategori ini? Data layanan terkait tidak akan terpengaruh.')) return;
  const fd = new FormData();
  fd.append('action', 'delete_category');
  fd.append('id', id);
  fd.append('csrf_token', '<?= csrf() ?>');
  App.postForm('<?= BASE_URL ?>/pages/services/action.php', fd).then(r => {
    if (r?.success) { App.toast('Kategori dihapus'); location.reload(); }
    else App.toast(r?.message || 'Gagal menghapus', 'error');
  });
}
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
