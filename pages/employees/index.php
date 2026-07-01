<?php
require_once __DIR__ . '/../../includes/auth.php';
requireLogin();
requireRole('owner','admin');

$title      = 'Karyawan';
$activePage = 'employees';
$db         = getDB();

$employees = $db->query("
    SELECT e.*, u.email, u.last_login,
           (SELECT COUNT(*) FROM work_orders wo WHERE wo.mechanic_id = e.id) AS total_wo
    FROM employees e
    LEFT JOIN users u ON e.user_id = u.id
    ORDER BY e.position, e.name
")->fetchAll();

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
  <div class="page-header-left">
    <h1>Data Karyawan</h1>
    <p>Kelola data dan akses karyawan bengkel</p>
  </div>
  <div class="page-header-right">
    <button class="btn btn-primary" onclick="App.openModal('modal-add-emp')"><i class="fas fa-plus"></i> Tambah Karyawan</button>
  </div>
</div>

<!-- EMPLOYEE CARDS -->
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:16px;margin-bottom:24px">
  <?php
  $posColors = ['owner'=>'var(--danger)','admin'=>'var(--info)','kabeng'=>'var(--warning)','mekanik'=>'var(--success)'];
  $posLabels = ['owner'=>'Owner','admin'=>'Admin','kabeng'=>'Kepala Bengkel','mekanik'=>'Mekanik'];
  foreach ($employees as $emp):
    $col = $posColors[$emp['position']] ?? 'var(--primary)';
  ?>
  <div class="card" style="transition:transform .2s" onmouseover="this.style.transform='translateY(-2px)'" onmouseout="this.style.transform=''">
    <div class="card-body">
      <div style="display:flex;gap:14px;align-items:flex-start;margin-bottom:16px">
        <div style="width:50px;height:50px;border-radius:50%;background:<?= $col ?>;display:flex;align-items:center;justify-content:center;color:#fff;font-size:20px;font-weight:700;flex-shrink:0">
          <?= strtoupper(substr($emp['name'],0,1)) ?>
        </div>
        <div style="flex:1">
          <div style="font-weight:700;font-size:15px"><?= htmlspecialchars($emp['name']) ?></div>
          <span style="font-size:11px;font-weight:600;padding:2px 10px;border-radius:12px;background:<?= $col ?>22;color:<?= $col ?>"><?= $posLabels[$emp['position']] ?? $emp['position'] ?></span>
        </div>
        <span class="badge badge-<?= $emp['status'] ?>"><?= ucfirst($emp['status']) ?></span>
      </div>
      <div class="info-row" style="gap:8px">
        <div class="info-item"><span class="info-label" style="min-width:90px"><i class="fas fa-id-card" style="width:14px"></i> ID</span><span class="info-value" style="font-size:13px"><?= $emp['employee_id'] ?></span></div>
        <div class="info-item"><span class="info-label" style="min-width:90px"><i class="fas fa-phone" style="width:14px"></i> Tel</span><span class="info-value" style="font-size:13px"><?= $emp['phone'] ?: '—' ?></span></div>
        <?php if ($emp['specialization']): ?>
        <div class="info-item"><span class="info-label" style="min-width:90px"><i class="fas fa-tools" style="width:14px"></i> Spesialis</span><span style="font-size:12px;color:var(--text-secondary)"><?= htmlspecialchars($emp['specialization']) ?></span></div>
        <?php endif; ?>
        <div class="info-item"><span class="info-label" style="min-width:90px"><i class="fas fa-clipboard" style="width:14px"></i> Total WO</span><span class="info-value"><?= $emp['total_wo'] ?> WO</span></div>
        <?php if ($emp['salary'] > 0): ?>
        <div class="info-item"><span class="info-label" style="min-width:90px"><i class="fas fa-money-bill" style="width:14px"></i> Gaji</span><span class="info-value" style="font-size:13px"><?= formatRupiah((float)$emp['salary']) ?></span></div>
        <?php endif; ?>
      </div>
      <div style="margin-top:14px;padding-top:14px;border-top:1px solid var(--border-light);display:flex;gap:8px">
        <button class="btn btn-warning btn-sm" onclick="editEmployee(<?= htmlspecialchars(json_encode($emp)) ?>)"><i class="fas fa-pencil-alt"></i> Edit</button>
        <?php if ($emp['status']==='active'): ?>
        <button class="btn btn-outline btn-sm" onclick="toggleStatus(<?= $emp['id'] ?>, 'inactive')"><i class="fas fa-ban"></i> Nonaktifkan</button>
        <?php else: ?>
        <button class="btn btn-success btn-sm" onclick="toggleStatus(<?= $emp['id'] ?>, 'active')"><i class="fas fa-check"></i> Aktifkan</button>
        <?php endif; ?>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<!-- MODAL ADD/EDIT EMPLOYEE -->
<div class="modal-overlay" id="modal-add-emp" onclick="App.closeModalOnOverlay(event)">
  <div class="modal modal-lg">
    <div class="modal-header">
      <div class="modal-title" id="modal-emp-title">Tambah Karyawan</div>
      <button class="modal-close" onclick="App.closeModal('modal-add-emp')"><i class="fas fa-times"></i></button>
    </div>
    <form method="POST" action="<?= BASE_URL ?>/pages/employees/action.php">
      <input type="hidden" name="action" id="emp-action" value="add">
      <input type="hidden" name="id" id="emp-id">
      <input type="hidden" name="csrf_token" value="<?= csrf() ?>">
      <div class="modal-body">
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Nama Lengkap <span class="req">*</span></label>
            <input type="text" name="name" id="emp-name" class="form-control" required placeholder="Nama karyawan">
          </div>
          <div class="form-group">
            <label class="form-label">No. Telepon</label>
            <input type="text" name="phone" id="emp-phone" class="form-control" placeholder="08xxxxxxxxxx">
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Jabatan <span class="req">*</span></label>
            <select name="position" id="emp-pos" class="form-control" required>
              <option value="mekanik">Mekanik</option>
              <option value="kabeng">Kepala Bengkel</option>
              <option value="admin">Admin</option>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Spesialisasi</label>
            <input type="text" name="specialization" id="emp-spec" class="form-control" placeholder="Mesin, Kelistrikan, dll.">
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Gaji Pokok</label>
            <div class="input-group">
              <span class="input-addon">Rp</span>
              <input type="number" name="salary" id="emp-salary" class="form-control" min="0" value="0">
            </div>
          </div>
          <div class="form-group">
            <label class="form-label">Komisi (%)</label>
            <input type="number" name="commission_rate" id="emp-comm" class="form-control" min="0" max="100" step="0.5" value="0">
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Tanggal Bergabung</label>
            <input type="date" name="join_date" id="emp-join" class="form-control" value="<?= date('Y-m-d') ?>">
          </div>
          <div class="form-group">
            <label class="form-label">Status</label>
            <select name="status" id="emp-status" class="form-control">
              <option value="active">Aktif</option>
              <option value="inactive">Tidak Aktif</option>
            </select>
          </div>
        </div>
        <hr style="border:none;border-top:1px solid var(--border-light);margin:4px 0 16px">
        <div style="font-size:12px;font-weight:600;color:var(--text-secondary);margin-bottom:10px">AKSES SISTEM (Opsional)</div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Email Login</label>
            <input type="email" name="email" id="emp-email" class="form-control" placeholder="email@bengkelin.com">
          </div>
          <div class="form-group" id="pw-group">
            <label class="form-label">Password</label>
            <input type="password" name="password" id="emp-pw" class="form-control" placeholder="Min. 8 karakter">
            <div class="form-text">Kosongkan jika tidak ingin mengubah password</div>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="App.closeModal('modal-add-emp')">Batal</button>
        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Simpan</button>
      </div>
    </form>
  </div>
</div>

<script>
function editEmployee(e) {
  document.getElementById('modal-emp-title').textContent = 'Edit Karyawan';
  document.getElementById('emp-action').value = 'edit';
  document.getElementById('emp-id').value     = e.id;
  document.getElementById('emp-name').value   = e.name;
  document.getElementById('emp-phone').value  = e.phone || '';
  document.getElementById('emp-pos').value    = e.position;
  document.getElementById('emp-spec').value   = e.specialization || '';
  document.getElementById('emp-salary').value = e.salary;
  document.getElementById('emp-comm').value   = e.commission_rate;
  document.getElementById('emp-join').value   = e.join_date || '';
  document.getElementById('emp-status').value = e.status;
  document.getElementById('emp-email').value  = e.email || '';
  document.getElementById('emp-pw').value     = '';
  App.openModal('modal-add-emp');
}
function toggleStatus(id, status) {
  const fd = new FormData();
  fd.append('action','toggle_status'); fd.append('id',id); fd.append('status',status);
  fd.append('csrf_token','<?= csrf() ?>');
  App.postForm('<?= BASE_URL ?>/pages/employees/action.php',fd).then(r=>{
    if(r?.success){ App.toast('Status diperbarui'); location.reload(); }
    else App.toast(r?.message||'Gagal','error');
  });
}
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
