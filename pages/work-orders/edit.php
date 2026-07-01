<?php
require_once __DIR__ . '/../../includes/auth.php';
requireLogin();

if (!isset($_GET['id'])) { header('Location: ' . BASE_URL . '/pages/work-orders/index.php'); exit; }
$id = (int)$_GET['id'];

$db = getDB();
$wo = $db->prepare("SELECT * FROM work_orders WHERE id=?");
$wo->execute([$id]); $wo = $wo->fetch();
if (!$wo || in_array($wo['status'], ['done','delivered','cancelled'])) {
    flashSet('danger','Work order tidak dapat diedit.'); header('Location: ' . BASE_URL . '/pages/work-orders/view.php?id=' . $id); exit;
}

$mechanics         = $db->query("SELECT id,name,position,specialization FROM employees WHERE position IN ('junior_teknisi','senior_teknisi') AND status='active' ORDER BY FIELD(position,'senior_teknisi','junior_teknisi'),name")->fetchAll();
$revCfg = $db->query("SELECT setting_key,setting_value FROM settings WHERE setting_key IN ('service_mechanic_pct','senior_share_pct','junior_share_pct')")->fetchAll(PDO::FETCH_KEY_PAIR);
$svcMechPct = (float)($revCfg['service_mechanic_pct'] ?? 60);
$seniorPct  = (float)($revCfg['senior_share_pct']     ?? 80);
$juniorPct  = (float)($revCfg['junior_share_pct']     ?? 20);
$woServices        = $db->prepare("SELECT * FROM wo_services WHERE wo_id=?"); $woServices->execute([$id]); $woServices=$woServices->fetchAll();
$woParts           = $db->prepare("SELECT * FROM wo_parts WHERE wo_id=?"); $woParts->execute([$id]); $woParts=$woParts->fetchAll();
$parts             = $db->query("SELECT id,name,sell_price,stock,unit FROM parts ORDER BY name")->fetchAll();
$customer          = $db->prepare("SELECT * FROM customers WHERE id=?"); $customer->execute([$wo['customer_id']]); $customer=$customer->fetch();
$vehicle           = $db->prepare("SELECT * FROM vehicles WHERE id=?");  $vehicle->execute([$wo['vehicle_id']]);  $vehicle=$vehicle->fetch();
$serviceCategories = $db->query("SELECT sc.*,GROUP_CONCAT(s.id,'::',s.name,'::',s.price_car,'::',s.price_motorcycle ORDER BY s.name SEPARATOR '||') as services FROM service_categories sc LEFT JOIN services s ON sc.id=s.category_id AND s.status='active' GROUP BY sc.id ORDER BY sc.name")->fetchAll();

// Fetch current assistants
$curAssistants = $db->prepare("SELECT employee_id FROM wo_assistants WHERE wo_id=?");
$curAssistants->execute([$id]);
$curAssistantIds = $curAssistants->fetchAll(PDO::FETCH_COLUMN);

$seniorList = array_filter($mechanics, fn($m) => $m['position'] === 'senior_teknisi');
$juniorList = array_filter($mechanics, fn($m) => $m['position'] === 'junior_teknisi');

$title      = 'Edit Work Order';
$activePage = 'work-orders';
include __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
  <div class="page-header-left">
    <h1>Edit Work Order</h1>
    <p><?= $wo['wo_number'] ?> — <?= htmlspecialchars($customer['name'] ?? '') ?> · <?= $vehicle['plate_number'] ?? '' ?></p>
  </div>
  <div class="page-header-right">
    <a href="<?= BASE_URL ?>/pages/work-orders/view.php?id=<?= $id ?>" class="btn btn-outline"><i class="fas fa-arrow-left"></i> Kembali</a>
  </div>
</div>

<form method="POST" action="<?= BASE_URL ?>/pages/work-orders/update.php">
<input type="hidden" name="wo_id" value="<?= $id ?>">
<input type="hidden" name="csrf_token" value="<?= csrf() ?>">

<div class="grid-2">

  <!-- LEFT -->
  <div style="display:flex;flex-direction:column;gap:20px">

    <!-- Readonly info -->
    <div class="card">
      <div class="card-header"><div class="card-header-title"><i class="fas fa-info-circle" style="color:var(--primary);margin-right:8px"></i>Informasi WO</div></div>
      <div class="card-body">
        <div class="info-row">
          <div class="info-item"><span class="info-label">Pelanggan</span><span class="info-value"><?= htmlspecialchars($customer['name'] ?? '') ?></span></div>
          <div class="info-item"><span class="info-label">Kendaraan</span><span class="info-value"><?= ($vehicle['brand']??'').' '.($vehicle['model']??'') ?> · <?= $vehicle['plate_number']??'' ?></span></div>
        </div>
        <div style="background:var(--primary-bg);border-radius:10px;padding:16px;border:1px solid rgba(255,107,43,.2)">
            <div style="font-size:12px;font-weight:700;color:var(--primary);text-transform:uppercase;letter-spacing:.5px;margin-bottom:12px">
              <i class="fas fa-user-cog"></i> Penugasan Teknisi
            </div>
            <div class="form-group">
              <label class="form-label">Teknisi Utama / Senior Teknisi</label>
              <select name="mechanic_id" id="mechanic_id" class="form-control" onchange="updateRevenuePreview()">
                <option value="">— Belum ditugaskan —</option>
                <?php foreach ($seniorList as $m): ?>
                <option value="<?= $m['id'] ?>" <?= $wo['mechanic_id']==$m['id']?'selected':'' ?>>
                  👑 <?= htmlspecialchars($m['name']) ?>
                </option>
                <?php endforeach; ?>
              </select>
              <small style="color:var(--text-muted);font-size:12px">Teknisi yang memimpin pengerjaan</small>
            </div>
            <div class="form-group" style="margin-top:4px">
              <label class="form-label">
                Junior Teknisi / Asisten
                <span style="color:var(--text-muted);font-weight:400">(opsional, bisa lebih dari 1)</span>
              </label>
              <?php if (empty($juniorList)): ?>
              <div style="font-size:13px;color:var(--text-muted);padding:10px;background:#f9fafb;border-radius:8px">Belum ada Junior Teknisi aktif</div>
              <?php else: ?>
              <div style="display:flex;flex-wrap:wrap;gap:8px;padding:10px;background:#f9fafb;border-radius:8px" id="junior-checkboxes">
                <?php foreach ($juniorList as $m): $checked = in_array($m['id'], $curAssistantIds); ?>
                <label id="jlabel-<?= $m['id'] ?>" style="display:flex;align-items:center;gap:6px;padding:7px 12px;border-radius:8px;background:<?= $checked?'var(--primary-bg)':'#fff' ?>;border:1px solid <?= $checked?'var(--primary)':'var(--border)' ?>;cursor:pointer;font-size:13px;font-weight:<?= $checked?'700':'500' ?>;transition:all .15s"
                       onmouseover="this.style.borderColor='var(--primary)'" onmouseout="if(!document.getElementById('ast-<?= $m['id'] ?>').checked)this.style.borderColor='var(--border)'">
                  <input type="checkbox" name="assistant_ids[]" id="ast-<?= $m['id'] ?>" value="<?= $m['id'] ?>"
                         <?= $checked ? 'checked' : '' ?>
                         onchange="checkboxChanged(this, <?= $m['id'] ?>)" style="accent-color:var(--primary);width:15px;height:15px">
                  <i class="fas fa-wrench" style="color:var(--info);font-size:11px"></i>
                  <?= htmlspecialchars($m['name']) ?>
                </label>
                <?php endforeach; ?>
              </div>
              <small style="color:var(--text-muted);font-size:12px">Centang yang membantu eksekusi</small>
              <?php endif; ?>
            </div>
            <!-- Revenue Preview -->
            <div id="revenue-preview" style="display:none;border-top:1px solid rgba(255,107,43,.2);padding-top:12px;margin-top:4px">
              <div style="font-size:11px;font-weight:700;color:var(--text-muted);text-transform:uppercase;margin-bottom:8px">Preview Bagi Hasil Jasa</div>
              <div id="rev-rows" style="display:flex;flex-direction:column;gap:6px"></div>
            </div>
          </div>
        <div class="form-group">
          <label class="form-label">Estimasi Selesai</label>
          <input type="datetime-local" name="estimated_finish" class="form-control" value="<?= $wo['estimated_finish'] ? date('Y-m-d\TH:i', strtotime($wo['estimated_finish'])) : '' ?>">
        </div>
        <div class="form-group">
          <label class="form-label">Keluhan</label>
          <textarea name="complaint" class="form-control" rows="3"><?= htmlspecialchars($wo['complaint']) ?></textarea>
        </div>
        <div class="form-group">
          <label class="form-label">Diagnosa</label>
          <textarea name="diagnosis" class="form-control" rows="3"><?= htmlspecialchars($wo['diagnosis'] ?? '') ?></textarea>
        </div>
        <div class="form-group">
          <label class="form-label">Rekomendasi</label>
          <textarea name="recommendation" class="form-control" rows="2"><?= htmlspecialchars($wo['recommendation'] ?? '') ?></textarea>
        </div>
        <div class="form-group">
          <label class="form-label">Catatan Internal</label>
          <textarea name="notes" class="form-control" rows="2"><?= htmlspecialchars($wo['notes'] ?? '') ?></textarea>
        </div>
      </div>
    </div>

  </div>

  <!-- RIGHT -->
  <div style="display:flex;flex-direction:column;gap:20px">

    <!-- Services -->
    <div class="card">
      <div class="card-header">
        <div class="card-header-title"><i class="fas fa-tools" style="color:var(--primary);margin-right:8px"></i>Layanan / Jasa</div>
        <button type="button" class="btn btn-outline-primary btn-sm" onclick="addServiceRow()"><i class="fas fa-plus"></i> Tambah</button>
      </div>
      <div class="card-body" style="padding:12px 16px">
        <div id="service-list">
          <?php foreach ($woServices as $i => $svc): ?>
          <div id="svc-<?= $i ?>" style="display:flex;gap:8px;align-items:center;margin-bottom:8px">
            <input type="text" name="services[<?= $i ?>][name]" class="form-control" style="flex:1" value="<?= htmlspecialchars($svc['service_name']) ?>" oninput="recalcTotal()" required>
            <input type="number" name="services[<?= $i ?>][price]" class="form-control" style="width:130px" value="<?= $svc['price'] ?>" min="0" oninput="recalcTotal()">
            <button type="button" onclick="document.getElementById('svc-<?= $i ?>').remove();recalcTotal()" style="border:none;background:var(--danger-bg);color:var(--danger);width:32px;height:32px;border-radius:6px;cursor:pointer;flex-shrink:0;font-size:14px">×</button>
          </div>
          <?php endforeach; ?>
        </div>
        <!-- Quick select -->
        <div style="margin-top:10px;border-top:1px solid var(--border-light);padding-top:10px">
          <?php foreach ($serviceCategories as $cat): if (!$cat['services']) continue; ?>
          <details style="margin-bottom:4px">
            <summary style="cursor:pointer;font-size:12px;font-weight:600;color:var(--text-secondary);padding:4px 0"><?= htmlspecialchars($cat['name']) ?></summary>
            <div style="padding:4px 0 0 10px;display:flex;flex-wrap:wrap;gap:4px">
              <?php foreach (explode('||', $cat['services']) as $svc):
                [$sid, $sname, $pcar, $pmoto] = explode('::', $svc . ':::');
              ?>
              <button type="button" class="btn btn-outline btn-sm" style="font-size:11px"
                onclick="addServiceFromList('<?= htmlspecialchars(addslashes($sname), ENT_QUOTES) ?>', <?= $pcar ?>, <?= $pmoto ?>)">
                <?= htmlspecialchars($sname) ?>
              </button>
              <?php endforeach; ?>
            </div>
          </details>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <!-- Parts -->
    <div class="card">
      <div class="card-header">
        <div class="card-header-title"><i class="fas fa-boxes" style="color:var(--primary);margin-right:8px"></i>Sparepart</div>
        <button type="button" class="btn btn-outline-primary btn-sm" onclick="addPartRow()"><i class="fas fa-plus"></i> Tambah</button>
      </div>
      <div class="card-body" style="padding:12px 16px">
        <div id="part-list">
          <?php foreach ($woParts as $i => $p): ?>
          <div id="part-<?= $i ?>" style="display:grid;grid-template-columns:1fr 70px 130px 32px;gap:8px;align-items:center;margin-bottom:8px">
            <select name="parts[<?= $i ?>][part_id]" class="form-control" onchange="onPartSelect(this, <?= $i ?>)">
              <option value="">— Pilih Part —</option>
              <?php foreach ($parts as $pt): ?>
              <option value="<?= $pt['id'] ?>" <?= $pt['id']==$p['part_id']?'selected':'' ?> data-price="<?= $pt['sell_price'] ?>"><?= htmlspecialchars($pt['name']) ?></option>
              <?php endforeach; ?>
            </select>
            <input type="number" name="parts[<?= $i ?>][quantity]" class="form-control" value="<?= $p['quantity'] ?>" min="1" oninput="recalcTotal()">
            <input type="number" name="parts[<?= $i ?>][sell_price]" id="p-price-<?= $i ?>" class="form-control" value="<?= $p['sell_price'] ?>" min="0" oninput="recalcTotal()">
            <button type="button" onclick="document.getElementById('part-<?= $i ?>').remove();recalcTotal()" style="border:none;background:var(--danger-bg);color:var(--danger);width:32px;height:32px;border-radius:6px;cursor:pointer;font-size:14px">×</button>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <!-- Totals -->
    <div class="card" style="position:sticky;top:80px">
      <div class="card-header"><div class="card-header-title"><i class="fas fa-calculator" style="color:var(--primary);margin-right:8px"></i>Ringkasan Biaya</div></div>
      <div class="card-body">
        <div style="display:flex;flex-direction:column;gap:10px">
          <div class="d-flex justify-between" style="font-size:14px"><span>Subtotal Jasa</span><strong id="sum-services">Rp 0</strong></div>
          <div class="d-flex justify-between" style="font-size:14px"><span>Subtotal Part</span><strong id="sum-parts">Rp 0</strong></div>
          <div class="d-flex justify-between align-center" style="font-size:14px">
            <label style="display:flex;align-items:center;gap:8px">Diskon
              <input type="number" name="discount" id="inp-discount" class="form-control" style="width:120px" min="0" value="<?= $wo['discount'] ?>" oninput="recalcTotal()">
            </label>
            <strong id="sum-discount">Rp 0</strong>
          </div>
          <hr style="border:none;border-top:1px solid var(--border)">
          <div class="d-flex justify-between" style="font-size:16px;font-weight:700"><span>Total</span><span id="sum-total" style="color:var(--primary)">Rp 0</span></div>
        </div>
        <input type="hidden" name="subtotal_services" id="hid-services">
        <input type="hidden" name="subtotal_parts" id="hid-parts">
        <input type="hidden" name="total" id="hid-total">
        <button type="submit" class="btn btn-primary w-100 btn-lg mt-16"><i class="fas fa-save"></i> Simpan Perubahan</button>
      </div>
    </div>

  </div>
</div>
</form>

<script>
const partsData = <?= json_encode($parts) ?>;
let svcIdx = <?= count($woServices) ?>;
let partIdx = <?= count($woParts) ?>;

function addServiceRow(name='', price=0) {
  const i = svcIdx++;
  const div = document.createElement('div');
  div.id = `svc-${i}`;
  div.style.cssText = 'display:flex;gap:8px;align-items:center;margin-bottom:8px';
  div.innerHTML = `<input type="text" name="services[${i}][name]" class="form-control" style="flex:1" placeholder="Nama layanan" value="${name}" oninput="recalcTotal()" required>
    <input type="number" name="services[${i}][price]" class="form-control" style="width:130px" value="${price}" min="0" oninput="recalcTotal()">
    <button type="button" onclick="document.getElementById('svc-${i}').remove();recalcTotal()" style="border:none;background:var(--danger-bg);color:var(--danger);width:32px;height:32px;border-radius:6px;cursor:pointer;flex-shrink:0;font-size:14px">×</button>`;
  document.getElementById('service-list').appendChild(div);
  recalcTotal();
}
function addServiceFromList(name,pCar,pMoto) { addServiceRow(name,pCar); }

function addPartRow() {
  const i = partIdx++;
  const opts = partsData.map(p=>`<option value="${p.id}" data-price="${p.sell_price}">${p.name}</option>`).join('');
  const div = document.createElement('div');
  div.id = `part-${i}`;
  div.style.cssText = 'display:grid;grid-template-columns:1fr 70px 130px 32px;gap:8px;align-items:center;margin-bottom:8px';
  div.innerHTML = `<select name="parts[${i}][part_id]" class="form-control" onchange="onPartSelect(this,${i})"><option value="">— Pilih —</option>${opts}</select>
    <input type="number" name="parts[${i}][quantity]" class="form-control" value="1" min="1" oninput="recalcTotal()">
    <input type="number" name="parts[${i}][sell_price]" id="p-price-${i}" class="form-control" min="0" oninput="recalcTotal()">
    <button type="button" onclick="document.getElementById('part-${i}').remove();recalcTotal()" style="border:none;background:var(--danger-bg);color:var(--danger);width:32px;height:32px;border-radius:6px;cursor:pointer;font-size:14px">×</button>`;
  document.getElementById('part-list').appendChild(div);
}
function onPartSelect(sel,i) { const v=sel.selectedOptions[0]; if(v.dataset.price) document.getElementById(`p-price-${i}`).value=v.dataset.price; recalcTotal(); }

function fmt(n){return 'Rp '+new Intl.NumberFormat('id-ID').format(n);}
function recalcTotal() {
  let svc=0,pts=0;
  document.querySelectorAll('[name^="services["]').forEach(inp=>{ if(inp.name.includes('[price]')) svc+=parseFloat(inp.value||0); });
  document.querySelectorAll('[name^="parts["]').forEach(inp=>{
    if(inp.name.includes('[sell_price]')){
      const row=inp.closest('[id^="part-"]');
      const qty=row?(parseFloat(row.querySelector('[name*="[quantity]"]')?.value||1)||1):1;
      pts+=parseFloat(inp.value||0)*qty;
    }
  });
  const disc=parseFloat(document.getElementById('inp-discount').value||0);
  const total=Math.max(0,svc+pts-disc);
  document.getElementById('sum-services').textContent=fmt(svc);
  document.getElementById('sum-parts').textContent=fmt(pts);
  document.getElementById('sum-discount').textContent=fmt(disc);
  document.getElementById('sum-total').textContent=fmt(total);
  document.getElementById('hid-services').value=svc;
  document.getElementById('hid-parts').value=pts;
  document.getElementById('hid-total').value=total;
  updateRevenuePreview();
}

// Revenue Preview
const MECH_PCT   = <?= $svcMechPct ?>;
const SENIOR_PCT = <?= $seniorPct ?>;
const JUNIOR_PCT = <?= $juniorPct ?>;
const OWNER_PCT  = 100 - MECH_PCT;
function checkboxChanged(cb, id) {
  const lbl = document.getElementById('jlabel-' + id);
  if (lbl) {
    lbl.style.borderColor  = cb.checked ? 'var(--primary)' : 'var(--border)';
    lbl.style.background   = cb.checked ? 'var(--primary-bg)' : '#fff';
    lbl.style.fontWeight   = cb.checked ? '700' : '500';
  }
  updateRevenuePreview();
}

function getCheckedJuniors() {
  const names = [];
  document.querySelectorAll('[name="assistant_ids[]"]:checked').forEach(cb => {
    const lbl = cb.closest('label');
    const text = lbl ? lbl.textContent.trim() : 'Junior';
    names.push(text);
  });
  return names;
}

function updateRevenuePreview() {
  const mecSel  = document.getElementById('mechanic_id');
  const prevBox = document.getElementById('revenue-preview');
  const revRows = document.getElementById('rev-rows');

  const mechId  = mecSel ? mecSel.value : '';
  if (!mechId) { if(prevBox) prevBox.style.display='none'; return; }

  let svcTotal = 0;
  document.querySelectorAll('[name^="services["]').forEach(inp => {
    if (inp.name.includes('[price]')) svcTotal += parseFloat(inp.value||0);
  });
  if (svcTotal <= 0) { if(prevBox) prevBox.style.display='none'; return; }

  const mechName   = mecSel.selectedOptions[0]?.text?.replace(/^[\u{1F451}\s]+/u,'').trim() || 'Senior Teknisi';
  const juniorNames= getCheckedJuniors();
  const juniorCount= juniorNames.length;

  const mechShare  = svcTotal * MECH_PCT  / 100;
  const ownerShare = svcTotal * OWNER_PCT / 100;

  let rows = [
    { label: mechName + ' (Teknisi Utama)', pct: MECH_PCT+'%', val: mechShare, color:'#F59E0B' },
    { label: 'Owner (Jasa)',        pct: OWNER_PCT+'%', val: ownerShare, color:'#10B981' },
  ];

  if (juniorCount > 0) {
    juniorNames.forEach((n, i) => {
      rows.push({
        label: n.replace(/\s*\(.*\)/, '').trim() + ' (Asisten - Gaji Pokok)',
        pct: 'Mingguan',
        val: 0,
        color:'#3B82F6'
      });
    });
  }

  if(prevBox) prevBox.style.display = 'block';
  if(revRows) revRows.innerHTML = rows.map(r =>
    `<div style="display:flex;justify-content:space-between;align-items:center;background:#fff;border-radius:7px;padding:7px 10px;font-size:12px">
      <span style="color:${r.color};font-weight:700">● ${r.label}</span>
      <span>
        <span style="color:var(--text-muted);font-size:11px">(${r.pct})</span>
        <strong style="margin-left:8px;color:${r.color}">${fmt(r.val)}</strong>
      </span>
    </div>`
  ).join('');
}
document.addEventListener('DOMContentLoaded', () => { recalcTotal(); updateRevenuePreview(); });
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
