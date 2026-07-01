<?php
require_once __DIR__ . '/../../includes/auth.php';
requireLogin();

$title      = 'Buat Work Order';
$activePage = 'work-orders';

$db = getDB();

// Pre-fill from query params (from customer/vehicle page)
$preCustomerId = (int)($_GET['customer_id'] ?? 0);
$preVehicleId  = (int)($_GET['vehicle_id'] ?? 0);
$bookingId     = (int)($_GET['booking_id'] ?? 0);

// Pre-fetch customer/vehicle if set
$preCustomer = null; $preVehicle = null;
if ($preCustomerId) {
    $s = $db->prepare("SELECT * FROM customers WHERE id=?"); $s->execute([$preCustomerId]); $preCustomer = $s->fetch();
}
if ($preVehicleId) {
    $s = $db->prepare("SELECT * FROM vehicles WHERE id=?"); $s->execute([$preVehicleId]); $preVehicle = $s->fetch();
}

// From booking
$preBooking = null;
if ($bookingId) {
    $s = $db->prepare("SELECT * FROM bookings WHERE id=?"); $s->execute([$bookingId]); $preBooking = $s->fetch();
}

$kabengList   = $db->query("SELECT id,name,specialization FROM employees WHERE position='kabeng' AND status='active' ORDER BY name")->fetchAll();
$juniorList   = $db->query("SELECT id,name,specialization FROM employees WHERE position='mekanik' AND status='active' ORDER BY name")->fetchAll();
$revCfg = $db->query("SELECT setting_key,setting_value FROM settings WHERE setting_key IN ('service_mechanic_pct','kabeng_share_pct','junior_share_pct')")->fetchAll(PDO::FETCH_KEY_PAIR);
$svcMechPct = (float)($revCfg['service_mechanic_pct'] ?? 60);
$kabengPct  = (float)($revCfg['kabeng_share_pct']     ?? 80);
$juniorPct  = (float)($revCfg['junior_share_pct']     ?? 20);
$serviceCategories= $db->query("SELECT sc.*,GROUP_CONCAT(s.id,'::',s.name,'::',s.price_car,'::',s.price_motorcycle ORDER BY s.name SEPARATOR '||') as services FROM service_categories sc LEFT JOIN services s ON sc.id=s.category_id AND s.status='active' GROUP BY sc.id ORDER BY sc.name")->fetchAll();
$parts            = $db->query("SELECT id,code,name,sell_price,stock,unit FROM parts WHERE stock>0 ORDER BY name")->fetchAll();

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
  <div class="page-header-left">
    <h1>Buat Work Order Baru</h1>
    <p>Isi data kendaraan dan layanan yang akan dikerjakan</p>
  </div>
  <div class="page-header-right">
    <a href="<?= BASE_URL ?>/pages/work-orders/index.php" class="btn btn-outline"><i class="fas fa-arrow-left"></i> Kembali</a>
  </div>
</div>

<form method="POST" action="<?= BASE_URL ?>/pages/work-orders/action.php" id="form-wo">
<input type="hidden" name="action" value="create">
<input type="hidden" name="csrf_token" value="<?= csrf() ?>">
<?php if ($bookingId): ?><input type="hidden" name="booking_id" value="<?= $bookingId ?>"><?php endif; ?>

<div class="grid-2">

  <!-- LEFT COLUMN -->
  <div style="display:flex;flex-direction:column;gap:20px">

    <!-- CUSTOMER -->
    <div class="card">
      <div class="card-header">
        <div class="card-header-title"><i class="fas fa-user" style="color:var(--primary);margin-right:8px"></i>Data Pelanggan</div>
      </div>
      <div class="card-body">
        <div class="form-group">
          <label class="form-label">Cari Pelanggan</label>
          <div class="search-box">
            <i class="fas fa-search search-icon"></i>
            <input type="text" id="customer-search" class="form-control" placeholder="Ketik nama atau telepon pelanggan..." autocomplete="off" value="<?= $preCustomer ? htmlspecialchars($preCustomer['name']) : '' ?>">
          </div>
          <div id="customer-results" style="border:1px solid var(--border);border-radius:8px;max-height:200px;overflow-y:auto;display:none;margin-top:4px;background:#fff;box-shadow:var(--card-shadow);z-index:10;position:relative"></div>
          <input type="hidden" name="customer_id" id="customer-id" value="<?= $preCustomerId ?>">
        </div>

        <div id="customer-info" style="<?= $preCustomer ? '' : 'display:none' ?>">
          <div style="background:var(--primary-bg);border-radius:10px;padding:14px 16px;display:flex;gap:12px;align-items:center">
            <div style="width:40px;height:40px;border-radius:50%;background:var(--primary);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;flex-shrink:0" id="c-avatar">
              <?= $preCustomer ? strtoupper(substr($preCustomer['name'],0,1)) : '' ?>
            </div>
            <div>
              <div style="font-weight:700" id="c-name"><?= $preCustomer ? htmlspecialchars($preCustomer['name']) : '' ?></div>
              <div style="font-size:12px;color:var(--text-muted)" id="c-phone"><?= $preCustomer ? $preCustomer['phone'] : '' ?></div>
            </div>
            <button type="button" onclick="clearCustomer()" style="margin-left:auto;border:none;background:none;cursor:pointer;color:var(--text-muted);font-size:16px">×</button>
          </div>
        </div>

        <!-- Add new customer inline -->
        <div id="new-customer-form" style="display:none;margin-top:12px;border-top:1px solid var(--border-light);padding-top:12px">
          <div style="font-size:12px;font-weight:600;color:var(--text-secondary);margin-bottom:10px">Pelanggan Baru</div>
          <div class="form-row">
            <div class="form-group">
              <label class="form-label">Nama <span class="req">*</span></label>
              <input type="text" name="new_customer_name" id="new-c-name" class="form-control" placeholder="Nama lengkap">
            </div>
            <div class="form-group">
              <label class="form-label">Telepon <span class="req">*</span></label>
              <input type="text" name="new_customer_phone" id="new-c-phone" class="form-control" placeholder="08xxxxxxxxxx">
            </div>
          </div>
        </div>
        <button type="button" onclick="toggleNewCustomer()" class="btn btn-outline btn-sm mt-8" id="btn-new-customer">
          <i class="fas fa-user-plus"></i> Pelanggan Baru
        </button>
      </div>
    </div>

    <!-- VEHICLE -->
    <div class="card">
      <div class="card-header">
        <div class="card-header-title"><i class="fas fa-car" style="color:var(--primary);margin-right:8px"></i>Data Kendaraan</div>
      </div>
      <div class="card-body">
        <!-- Existing vehicle selector -->
        <div id="vehicle-select-area">
          <select name="vehicle_id" id="vehicle-id" class="form-control mb-12" onchange="onVehicleChange(this.value)">
            <option value="">— Pilih Kendaraan —</option>
            <?php if ($preCustomer): $vs=$db->prepare("SELECT * FROM vehicles WHERE customer_id=?");$vs->execute([$preCustomerId]);foreach($vs->fetchAll() as $v): ?>
            <option value="<?= $v['id'] ?>" <?= $v['id']==$preVehicleId?'selected':'' ?> data-v='<?= htmlspecialchars(json_encode($v)) ?>'><?= $v['plate_number'] . ' — ' . $v['brand'] . ' ' . $v['model'] ?></option>
            <?php endforeach; endif; ?>
          </select>
        </div>

        <!-- Or new vehicle -->
        <div id="new-vehicle-form">
          <div class="form-row">
            <div class="form-group">
              <label class="form-label">Jenis <span class="req">*</span></label>
              <select name="vehicle_type" id="v-type" class="form-control" onchange="onVtypeChange(this.value)">
                <option value="mobil">Mobil</option>
                <option value="motor">Motor</option>
              </select>
            </div>
            <div class="form-group">
              <label class="form-label">Plat Nomor <span class="req">*</span></label>
              <input type="text" name="plate_number" id="v-plate" class="form-control" placeholder="B 1234 ABC" oninput="this.value=this.value.toUpperCase()" value="<?= $preVehicle ? $preVehicle['plate_number'] : ($preBooking ? $preBooking['plate_number'] : '') ?>">
            </div>
          </div>
          <div class="form-row">
            <div class="form-group">
              <label class="form-label">Merek <span class="req">*</span></label>
              <input type="text" name="vehicle_brand" id="v-brand" class="form-control" placeholder="Toyota, Honda, dst." value="<?= $preVehicle ? $preVehicle['brand'] : ($preBooking ? $preBooking['vehicle_brand'] : '') ?>">
            </div>
            <div class="form-group">
              <label class="form-label">Model <span class="req">*</span></label>
              <input type="text" name="vehicle_model" id="v-model" class="form-control" placeholder="Avanza, Vario, dst." value="<?= $preVehicle ? $preVehicle['model'] : ($preBooking ? $preBooking['vehicle_model'] : '') ?>">
            </div>
          </div>
          <div class="form-row-3">
            <div class="form-group">
              <label class="form-label">Tahun</label>
              <input type="number" name="vehicle_year" class="form-control" min="1990" max="<?= date('Y')+1 ?>" value="<?= $preVehicle ? $preVehicle['year'] : ($preBooking ? $preBooking['vehicle_year'] : date('Y')) ?>">
            </div>
            <div class="form-group">
              <label class="form-label">Warna</label>
              <input type="text" name="vehicle_color" class="form-control" placeholder="Putih" value="<?= $preVehicle ? $preVehicle['color'] : '' ?>">
            </div>
            <div class="form-group">
              <label class="form-label">Km Masuk</label>
              <input type="number" name="km_in" id="v-km" class="form-control" min="0" placeholder="0" value="<?= $preVehicle ? $preVehicle['km_current'] : '0' ?>">
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- WO INFO -->
    <div class="card">
      <div class="card-header">
        <div class="card-header-title"><i class="fas fa-info-circle" style="color:var(--primary);margin-right:8px"></i>Informasi WO</div>
      </div>
      <div class="card-body">
        <div class="form-group">
          <label class="form-label">Prioritas</label>
          <select name="priority" class="form-control">
            <option value="normal">Normal</option>
            <option value="urgent">🔴 Urgent</option>
          </select>
        </div>

        <!-- PENUGASAN MEKANIK -->
        <div style="background:var(--primary-bg);border-radius:10px;padding:16px;border:1px solid rgba(255,107,43,.2);margin-bottom:12px">
          <div style="font-size:12px;font-weight:700;color:var(--primary);text-transform:uppercase;letter-spacing:.5px;margin-bottom:12px">
            <i class="fas fa-hard-hat"></i> Penugasan Teknisi
          </div>

          <!-- KABENG: dropdown hanya kabeng -->
          <div class="form-group">
            <label class="form-label">
              <span style="background:#FEF3C7;color:#B45309;padding:2px 8px;border-radius:99px;font-size:11px;font-weight:700">KABENG</span>
              Kepala Bengkel
            </label>
            <select name="mechanic_id" id="mechanic_id" class="form-control" onchange="updateRevenuePreview()">
              <option value="">— Belum ditugaskan —</option>
              <?php foreach ($kabengList as $m): ?>
              <option value="<?= $m['id'] ?>">
                👑 <?= htmlspecialchars($m['name']) ?><?= $m['specialization'] ? ' (' . $m['specialization'] . ')' : '' ?>
              </option>
              <?php endforeach; ?>
              <?php if (empty($kabengList)): ?>
              <option disabled style="color:#999">Belum ada Kepala Bengkel aktif</option>
              <?php endif; ?>
            </select>
            <small style="color:var(--text-muted);font-size:12px">Pimpinan pengerjaan — On-call, tanpa gaji pokok</small>
          </div>

          <!-- JUNIOR: checkbox multi-select, hanya mekanik -->
          <div class="form-group" style="margin-top:4px">
            <label class="form-label">
              <span style="background:#DBEAFE;color:#1D4ED8;padding:2px 8px;border-radius:99px;font-size:11px;font-weight:700">JUNIOR</span>
              Mekanik Asisten
              <span style="color:var(--text-muted);font-weight:400">(opsional, bisa lebih dari 1)</span>
            </label>
            <?php if (empty($juniorList)): ?>
            <div style="font-size:13px;color:var(--text-muted);padding:10px;background:#f9fafb;border-radius:8px">Belum ada Junior Mekanik aktif</div>
            <?php else: ?>
            <div style="display:flex;flex-wrap:wrap;gap:8px;padding:10px;background:#f9fafb;border-radius:8px" id="junior-checkboxes">
              <?php foreach ($juniorList as $m): ?>
              <label id="jlabel-<?= $m['id'] ?>" style="display:flex;align-items:center;gap:6px;padding:7px 12px;border-radius:8px;background:#fff;border:1px solid var(--border);cursor:pointer;font-size:13px;font-weight:500;transition:all .15s"
                     onmouseover="this.style.borderColor='var(--primary)'" onmouseout="if(!document.getElementById('ast-<?= $m['id'] ?>').checked)this.style.borderColor='var(--border)'">
                <input type="checkbox" name="assistant_ids[]" id="ast-<?= $m['id'] ?>" value="<?= $m['id'] ?>"
                       onchange="checkboxChanged(this, <?= $m['id'] ?>)" style="accent-color:var(--primary);width:15px;height:15px">
                <i class="fas fa-wrench" style="color:var(--info);font-size:11px"></i>
                <?= htmlspecialchars($m['name']) ?>
                <?php if ($m['specialization']): ?><span style="font-size:11px;color:var(--text-muted)">(<?= $m['specialization'] ?>)</span><?php endif; ?>
              </label>
              <?php endforeach; ?>
            </div>
            <small style="color:var(--text-muted);font-size:12px">Centang yang membantu eksekusi</small>
            <?php endif; ?>
          </div>

          <!-- Revenue Preview Box -->
          <div id="revenue-preview" style="display:none;border-top:1px solid rgba(255,107,43,.2);padding-top:12px;margin-top:8px">
            <div style="font-size:11px;font-weight:700;color:var(--text-muted);text-transform:uppercase;margin-bottom:8px">Preview Bagi Hasil Jasa</div>
            <div id="rev-rows" style="display:flex;flex-direction:column;gap:6px"></div>
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Estimasi Selesai</label>
          <input type="datetime-local" name="estimated_finish" class="form-control">
        </div>
        <div class="form-group">
          <label class="form-label">Keluhan / Permintaan <span class="req">*</span></label>
          <textarea name="complaint" class="form-control" rows="3" required placeholder="Jelaskan keluhan pelanggan..."><?= $preBooking ? htmlspecialchars($preBooking['complaint']) : '' ?></textarea>
        </div>
        <div class="form-group">
          <label class="form-label">Catatan Internal</label>
          <textarea name="notes" class="form-control" rows="2" placeholder="Catatan untuk mekanik (opsional)"></textarea>
        </div>
      </div>
    </div>

  </div><!-- end left -->

  <!-- RIGHT COLUMN -->
  <div style="display:flex;flex-direction:column;gap:20px">

    <!-- SERVICES -->
    <div class="card">
      <div class="card-header">
        <div class="card-header-title"><i class="fas fa-tools" style="color:var(--primary);margin-right:8px"></i>Layanan / Jasa</div>
        <button type="button" class="btn btn-outline-primary btn-sm" onclick="addServiceRow()"><i class="fas fa-plus"></i> Tambah</button>
      </div>
      <div class="card-body" style="padding:12px 16px">
        <div id="service-list">
          <!-- rows injected by JS -->
        </div>
        <!-- Quick select by category -->
        <div style="margin-top:12px;border-top:1px solid var(--border-light);padding-top:12px">
          <div style="font-size:12px;font-weight:600;color:var(--text-secondary);margin-bottom:8px">Pilih dari daftar layanan:</div>
          <?php foreach ($serviceCategories as $cat): if (!$cat['services']) continue; ?>
          <details style="margin-bottom:6px">
            <summary style="cursor:pointer;font-size:13px;font-weight:600;color:var(--text-primary);padding:6px 0"><?= htmlspecialchars($cat['name']) ?></summary>
            <div style="padding:6px 0 0 12px;display:flex;flex-wrap:wrap;gap:6px">
              <?php foreach (explode('||', $cat['services']) as $svc):
                [$sid, $sname, $pcar, $pmoto] = explode('::', $svc . ':::');
              ?>
              <button type="button" class="btn btn-outline btn-sm" style="font-size:12px"
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

    <!-- PARTS -->
    <div class="card">
      <div class="card-header">
        <div class="card-header-title"><i class="fas fa-boxes" style="color:var(--primary);margin-right:8px"></i>Sparepart / Part</div>
        <button type="button" class="btn btn-outline-primary btn-sm" onclick="addPartRow()"><i class="fas fa-plus"></i> Tambah</button>
      </div>
      <div class="card-body" style="padding:12px 16px">
        <div id="part-list"></div>
      </div>
    </div>

    <!-- TOTALS -->
    <div class="card" style="position:sticky;top:80px">
      <div class="card-header">
        <div class="card-header-title"><i class="fas fa-calculator" style="color:var(--primary);margin-right:8px"></i>Ringkasan Biaya</div>
      </div>
      <div class="card-body">
        <div style="display:flex;flex-direction:column;gap:10px">
          <div class="d-flex justify-between" style="font-size:14px"><span>Subtotal Jasa</span><strong id="sum-services">Rp 0</strong></div>
          <div class="d-flex justify-between" style="font-size:14px"><span>Subtotal Part</span><strong id="sum-parts">Rp 0</strong></div>
          <div class="d-flex justify-between align-center" style="font-size:14px">
            <label style="display:flex;align-items:center;gap:8px">
              Diskon
              <input type="number" name="discount" id="inp-discount" class="form-control" style="width:120px" min="0" placeholder="0" oninput="recalcTotal()">
            </label>
            <strong id="sum-discount">Rp 0</strong>
          </div>
          <hr style="border:none;border-top:1px solid var(--border)">
          <div class="d-flex justify-between" style="font-size:16px;font-weight:700">
            <span>Total</span>
            <span id="sum-total" style="color:var(--primary)">Rp 0</span>
          </div>
        </div>
        <input type="hidden" name="subtotal_services" id="hid-services">
        <input type="hidden" name="subtotal_parts" id="hid-parts">
        <input type="hidden" name="total" id="hid-total">

        <button type="submit" class="btn btn-primary w-100 btn-lg mt-16">
          <i class="fas fa-save"></i> Buat Work Order
        </button>
      </div>
    </div>

  </div><!-- end right -->
</div>
</form>

<script>
// Parts data from PHP
const partsData = <?= json_encode($parts) ?>;
const vehicleType = document.getElementById('v-type');

// ── SERVICE ROWS ──────────────────────────────────────────
let svcIdx = 0;
function addServiceRow(name = '', price = 0) {
  const i = svcIdx++;
  const div = document.createElement('div');
  div.id = `svc-${i}`;
  div.style.cssText = 'display:flex;gap:8px;align-items:center;margin-bottom:8px';
  div.innerHTML = `
    <input type="text" name="services[${i}][name]" class="form-control" style="flex:1" placeholder="Nama layanan" value="${name}" oninput="recalcTotal()" required>
    <input type="number" name="services[${i}][price]" class="form-control" style="width:130px" placeholder="Harga" value="${price}" min="0" oninput="recalcTotal()">
    <button type="button" onclick="document.getElementById('svc-${i}').remove();recalcTotal()" style="border:none;background:var(--danger-bg);color:var(--danger);width:32px;height:32px;border-radius:6px;cursor:pointer;flex-shrink:0;font-size:14px">×</button>`;
  document.getElementById('service-list').appendChild(div);
  recalcTotal();
}

function addServiceFromList(name, pCar, pMoto) {
  const vt = vehicleType ? vehicleType.value : 'mobil';
  const price = vt === 'motor' ? pMoto : pCar;
  addServiceRow(name, price);
}

// ── PART ROWS ─────────────────────────────────────────────
let partIdx = 0;
function addPartRow() {
  const i = partIdx++;
  const opts = partsData.map(p => `<option value="${p.id}" data-price="${p.sell_price}" data-stock="${p.stock}">${p.name} (stok: ${p.stock})</option>`).join('');
  const div = document.createElement('div');
  div.id = `part-${i}`;
  div.style.cssText = 'display:grid;grid-template-columns:1fr 70px 130px 32px;gap:8px;align-items:center;margin-bottom:8px';
  div.innerHTML = `
    <select name="parts[${i}][part_id]" class="form-control" onchange="onPartSelect(this, ${i})">
      <option value="">— Pilih Part —</option>${opts}
    </select>
    <input type="number" name="parts[${i}][quantity]" class="form-control" placeholder="Qty" value="1" min="1" oninput="recalcTotal()">
    <input type="number" name="parts[${i}][sell_price]" id="p-price-${i}" class="form-control" placeholder="Harga" min="0" oninput="recalcTotal()">
    <button type="button" onclick="document.getElementById('part-${i}').remove();recalcTotal()" style="border:none;background:var(--danger-bg);color:var(--danger);width:32px;height:32px;border-radius:6px;cursor:pointer;font-size:14px">×</button>`;
  document.getElementById('part-list').appendChild(div);
}

function onPartSelect(sel, i) {
  const opt = sel.selectedOptions[0];
  if (opt.dataset.price) document.getElementById(`p-price-${i}`).value = opt.dataset.price;
  recalcTotal();
}

// ── CALCULATIONS ──────────────────────────────────────────
function fmt(n) { return 'Rp ' + new Intl.NumberFormat('id-ID').format(n); }

function recalcTotal() {
  let services = 0, parts = 0;
  document.querySelectorAll('[name^="services["]').forEach(inp => {
    if (inp.name.includes('[price]')) services += parseFloat(inp.value||0);
  });
  document.querySelectorAll('[name^="parts["]').forEach(inp => {
    if (inp.name.includes('[sell_price]')) {
      const row = inp.closest('[id^="part-"]');
      const qty = row ? (parseFloat(row.querySelector('[name*="[quantity]"]')?.value||1)||1) : 1;
      parts += parseFloat(inp.value||0) * qty;
    }
  });
  const discount = parseFloat(document.getElementById('inp-discount').value||0);
  const total = Math.max(0, services + parts - discount);
  document.getElementById('sum-services').textContent = fmt(services);
  document.getElementById('sum-parts').textContent     = fmt(parts);
  document.getElementById('sum-discount').textContent  = fmt(discount);
  document.getElementById('sum-total').textContent     = fmt(total);
  document.getElementById('hid-services').value = services;
  document.getElementById('hid-parts').value    = parts;
  document.getElementById('hid-total').value    = total;
  updateRevenuePreview();
}

// ── CUSTOMER SEARCH ───────────────────────────────────────
let searchTimer;
document.getElementById('customer-search').addEventListener('input', function() {
  clearTimeout(searchTimer);
  const q = this.value.trim();
  if (q.length < 2) { document.getElementById('customer-results').style.display='none'; return; }
  searchTimer = setTimeout(async () => {
    const res = await fetch(`<?= BASE_URL ?>/pages/customers/search.php?q=${encodeURIComponent(q)}`);
    const data = await res.json();
    const results = document.getElementById('customer-results');
    if (!data.length) { results.style.display='none'; return; }
    results.innerHTML = data.map(c =>
      `<div onclick="selectCustomer(${c.id},'${c.name.replace(/'/g,"\\'")}','${c.phone}')" style="padding:10px 14px;cursor:pointer;border-bottom:1px solid var(--border-light);font-size:13px;transition:background .15s" onmouseover="this.style.background='var(--border-light)'" onmouseout="this.style.background=''">
        <strong>${c.name}</strong> <span style="color:var(--text-muted);font-size:12px">${c.phone}</span>
       </div>`
    ).join('');
    results.style.display = 'block';
  }, 250);
});

function selectCustomer(id, name, phone) {
  document.getElementById('customer-id').value = id;
  document.getElementById('customer-search').value = name;
  document.getElementById('c-avatar').textContent = name.charAt(0).toUpperCase();
  document.getElementById('c-name').textContent = name;
  document.getElementById('c-phone').textContent = phone;
  document.getElementById('customer-info').style.display = '';
  document.getElementById('customer-results').style.display = 'none';
  // Load vehicles
  loadVehicles(id);
}

async function loadVehicles(customerId) {
  const res = await fetch(`<?= BASE_URL ?>/pages/vehicles/search.php?customer_id=${customerId}`);
  const data = await res.json();
  const sel  = document.getElementById('vehicle-id');
  sel.innerHTML = '<option value="">— Pilih Kendaraan —</option>' +
    data.map(v => `<option value="${v.id}" data-v='${JSON.stringify(v)}'>${v.plate_number} — ${v.brand} ${v.model}</option>`).join('');
}

function clearCustomer() {
  document.getElementById('customer-id').value = '';
  document.getElementById('customer-search').value = '';
  document.getElementById('customer-info').style.display = 'none';
  document.getElementById('vehicle-id').innerHTML = '<option value="">— Pilih Kendaraan —</option>';
}

function toggleNewCustomer() {
  const f = document.getElementById('new-customer-form');
  f.style.display = f.style.display === 'none' ? '' : 'none';
}

function onVehicleChange(id) {
  if (!id) return;
  const sel = document.getElementById('vehicle-id');
  const opt = sel.selectedOptions[0];
  const v = JSON.parse(opt.dataset.v || '{}');
  if (v.km_current) document.getElementById('v-km').value = v.km_current;
  if (v.type) document.getElementById('v-type').value = v.type;
}
// ── REVENUE PREVIEW (Multi-Asisten) ──────────────────────
const MECH_PCT   = <?= $svcMechPct ?>;
const KABENG_PCT = <?= $kabengPct ?>;
const JUNIOR_PCT = <?= $juniorPct ?>;
const OWNER_PCT  = 100 - MECH_PCT;

function checkboxChanged(cb, id) {
  // Update border warna label
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

  // Total jasa
  let svcTotal = 0;
  document.querySelectorAll('[name^="services["]').forEach(inp => {
    if (inp.name.includes('[price]')) svcTotal += parseFloat(inp.value||0);
  });
  if (svcTotal <= 0) { if(prevBox) prevBox.style.display='none'; return; }

  const mechName   = mecSel.selectedOptions[0]?.text?.replace(/^[\u{1F451}\s]+/u,'').trim() || 'Kepala Bengkel';
  const juniorNames= getCheckedJuniors();
  const juniorCount= juniorNames.length;

  const mechShare  = svcTotal * MECH_PCT  / 100;
  const ownerShare = svcTotal * OWNER_PCT / 100;

  let rows = [];

  if (juniorCount === 0) {
    // Solo: Kabeng/mekanik dapat 100% bagian mekanik
    rows = [
      { label: mechName + ' (Solo)', pct: MECH_PCT+'%', val: mechShare, color:'#F59E0B' },
      { label: 'Owner (Jasa)',        pct: OWNER_PCT+'%', val: ownerShare, color:'#10B981' },
    ];
  } else {
    // Duo/Trio: Kabeng dapat kabengPct%, Junior dibagi rata
    const kabengBonus = mechShare * KABENG_PCT / 100;
    const totalJunior = mechShare * JUNIOR_PCT / 100;
    const perJunior   = totalJunior / juniorCount;

    rows.push({ label: mechName + ' (Kabeng)', pct: MECH_PCT+'%×'+KABENG_PCT+'%', val: kabengBonus, color:'#F59E0B' });
    juniorNames.forEach((n, i) => {
      rows.push({
        label: n.replace(/\s*\(.*\)/, '').trim() + ' (Asisten)',
        pct: MECH_PCT+'%×'+JUNIOR_PCT+'%÷'+juniorCount,
        val: perJunior,
        color:'#3B82F6'
      });
    });
    rows.push({ label: 'Owner (Jasa)', pct: OWNER_PCT+'%', val: ownerShare, color:'#10B981' });
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
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>

