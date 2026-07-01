<?php
require_once __DIR__ . '/../../includes/auth.php';
requireLogin();
requireRole('owner');

$title      = 'Pengaturan Bagi Hasil';
$activePage = 'payroll';
$db         = getDB();

$keys = ['service_mechanic_pct','service_owner_pct','kabeng_share_pct','junior_share_pct',
         'parts_owner_pct','admin_bonus_pct','kabeng_min_guarantee'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrf($_POST['csrf_token'] ?? '')) {
    $mechPct  = max(0, min(100, (float)($_POST['service_mechanic_pct'] ?? 60)));
    $ownerPct = 100 - $mechPct;
    $kabPct   = max(0, min(100, (float)($_POST['kabeng_share_pct'] ?? 80)));
    $junPct   = 100 - $kabPct;

    $stmt = $db->prepare("INSERT INTO settings (setting_key,setting_value) VALUES (?,?) ON DUPLICATE KEY UPDATE setting_value=?");
    $stmt->execute(['service_mechanic_pct', $mechPct, $mechPct]);
    $stmt->execute(['service_owner_pct',    $ownerPct, $ownerPct]);
    $stmt->execute(['kabeng_share_pct',     $kabPct, $kabPct]);
    $stmt->execute(['junior_share_pct',     $junPct, $junPct]);
    $stmt->execute(['parts_owner_pct',      '100', '100']);
    $ab = max(0, min(10, (float)($_POST['admin_bonus_pct'] ?? 1)));
    $stmt->execute(['admin_bonus_pct',      $ab, $ab]);
    $km = max(0, (float)($_POST['kabeng_min_guarantee'] ?? 0));
    $stmt->execute(['kabeng_min_guarantee', $km, $km]);

    flashSet('success', 'Pengaturan bagi hasil berhasil disimpan!');
    header('Location: '.BASE_URL.'/pages/payroll/settings.php'); exit;
}

$cfg = $db->query("SELECT setting_key,setting_value FROM settings WHERE setting_key IN
    ('service_mechanic_pct','service_owner_pct','kabeng_share_pct','junior_share_pct',
     'parts_owner_pct','admin_bonus_pct','kabeng_min_guarantee')")->fetchAll(PDO::FETCH_KEY_PAIR);

function cfgVal(array $c, string $k, $def): string { return htmlspecialchars((string)($c[$k] ?? $def)); }

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
  <div class="page-header-left">
    <h1>Pengaturan Bagi Hasil</h1>
    <p>Konfigurasi persentase pembagian pendapatan jasa & bonus karyawan</p>
  </div>
  <div class="page-header-right">
    <a href="<?= BASE_URL ?>/pages/payroll/index.php" class="btn btn-outline">
      <i class="fas fa-arrow-left"></i> Kembali ke Penggajian
    </a>
  </div>
</div>

<form method="POST" id="revenueForm">
<input type="hidden" name="csrf_token" value="<?= csrf() ?>">

<div class="grid-2">

  <!-- JASA SPLIT -->
  <div class="card">
    <div class="card-header">
      <div class="card-header-title">
        <i class="fas fa-tools" style="color:var(--primary);margin-right:8px"></i>Pembagian Pendapatan Jasa
      </div>
    </div>
    <div class="card-body">
      <div class="alert alert-info" style="margin-bottom:20px">
        <i class="fas fa-info-circle"></i>
        <strong>Pendapatan Jasa</strong> adalah uang jasa yang dibayar pelanggan untuk pekerjaan mekanik, tidak termasuk spare part.
      </div>

      <!-- Slider: Mekanik vs Owner -->
      <div class="form-group">
        <label class="form-label">Porsi Jasa untuk Mekanik (Total)</label>
        <div style="display:flex;align-items:center;gap:12px">
          <input type="range" id="mechSlider" min="0" max="100" step="5"
                 value="<?= cfgVal($cfg,'service_mechanic_pct',60) ?>"
                 style="flex:1;accent-color:var(--primary)"
                 oninput="updateSplit(this.value)">
          <span id="mechVal" style="font-size:20px;font-weight:800;color:var(--primary);min-width:42px;text-align:right">
            <?= cfgVal($cfg,'service_mechanic_pct',60) ?>%
          </span>
        </div>
        <input type="hidden" name="service_mechanic_pct" id="service_mechanic_pct" value="<?= cfgVal($cfg,'service_mechanic_pct',60) ?>">
      </div>

      <!-- Visual Split Bar -->
      <div style="border-radius:12px;overflow:hidden;height:40px;display:flex;margin:16px 0;box-shadow:0 2px 8px rgba(0,0,0,.08)">
        <div id="barMekanik" style="background:linear-gradient(90deg,#FF6B2B,#ff8c5a);display:flex;align-items:center;justify-content:center;color:#fff;font-size:12px;font-weight:700;transition:width .3s;width:<?= cfgVal($cfg,'service_mechanic_pct',60) ?>%">
          Mekanik
        </div>
        <div id="barOwner" style="background:linear-gradient(90deg,#10B981,#34d399);display:flex;align-items:center;justify-content:center;color:#fff;font-size:12px;font-weight:700;transition:width .3s;width:<?= cfgVal($cfg,'service_owner_pct',40) ?>%">
          Owner
        </div>
      </div>
      <div style="display:flex;justify-content:space-between;margin-bottom:20px">
        <div style="text-align:center;flex:1">
          <div id="mechPct2" style="font-weight:700;color:var(--primary);font-size:18px"><?= cfgVal($cfg,'service_mechanic_pct',60) ?>%</div>
          <div style="font-size:12px;color:var(--text-muted)">untuk Mekanik</div>
        </div>
        <div style="text-align:center;flex:1">
          <div id="ownerPct" style="font-weight:700;color:var(--success);font-size:18px"><?= cfgVal($cfg,'service_owner_pct',40) ?>%</div>
          <div style="font-size:12px;color:var(--text-muted)">untuk Owner</div>
        </div>
      </div>

      <!-- Spare Part -->
      <div style="background:rgba(255,107,43,.06);border-radius:10px;padding:14px;border:1px dashed var(--primary)">
        <div style="display:flex;align-items:center;justify-content:space-between">
          <div>
            <div style="font-weight:700;margin-bottom:3px"><i class="fas fa-boxes" style="color:var(--primary);margin-right:6px"></i>Spare Part</div>
            <div style="font-size:12px;color:var(--text-muted)">Seluruh pendapatan spare part menjadi milik owner</div>
          </div>
          <div style="font-size:22px;font-weight:800;color:var(--primary)">100%</div>
        </div>
      </div>
    </div>
  </div>

  <!-- MEKANIK INTERNAL SPLIT -->
  <div style="display:flex;flex-direction:column;gap:20px">
    <div class="card">
      <div class="card-header">
        <div class="card-header-title">
          <i class="fas fa-user-cog" style="color:var(--warning);margin-right:8px"></i>Pembagian Internal Mekanik
        </div>
      </div>
      <div class="card-body">
        <div class="alert alert-info" style="margin-bottom:20px">
          <i class="fas fa-info-circle"></i>
          Dari <strong>total bagian mekanik</strong>, dibagi lagi antara Kepala Bengkel dan Junior Mekanik berdasarkan WO masing-masing.
        </div>

        <!-- Slider: Kabeng vs Junior -->
        <div class="form-group">
          <label class="form-label">Porsi Kepala Bengkel (dari bagian mekanik)</label>
          <div style="display:flex;align-items:center;gap:12px">
            <input type="range" id="kabengSlider" min="0" max="100" step="5"
                   value="<?= cfgVal($cfg,'kabeng_share_pct',80) ?>"
                   style="flex:1;accent-color:var(--warning)"
                   oninput="updateKabeng(this.value)">
            <span id="kabengVal" style="font-size:20px;font-weight:800;color:var(--warning);min-width:42px;text-align:right">
              <?= cfgVal($cfg,'kabeng_share_pct',80) ?>%
            </span>
          </div>
          <input type="hidden" name="kabeng_share_pct" id="kabeng_share_pct" value="<?= cfgVal($cfg,'kabeng_share_pct',80) ?>">
        </div>

        <div style="border-radius:12px;overflow:hidden;height:36px;display:flex;margin:12px 0;box-shadow:0 2px 8px rgba(0,0,0,.08)">
          <div id="barKabeng" style="background:linear-gradient(90deg,#F59E0B,#fbbf24);display:flex;align-items:center;justify-content:center;color:#fff;font-size:12px;font-weight:700;transition:width .3s;width:<?= cfgVal($cfg,'kabeng_share_pct',80) ?>%">
            Kabeng
          </div>
          <div id="barJunior" style="background:linear-gradient(90deg,#3B82F6,#60a5fa);display:flex;align-items:center;justify-content:center;color:#fff;font-size:12px;font-weight:700;transition:width .3s;width:<?= cfgVal($cfg,'junior_share_pct',20) ?>%">
            Junior
          </div>
        </div>
        <div style="display:flex;justify-content:space-between;margin-bottom:16px">
          <div style="text-align:center;flex:1">
            <div id="kabengPct2" style="font-weight:700;color:var(--warning);font-size:18px"><?= cfgVal($cfg,'kabeng_share_pct',80) ?>%</div>
            <div style="font-size:12px;color:var(--text-muted)">Kepala Bengkel (On-Call)</div>
          </div>
          <div style="text-align:center;flex:1">
            <div id="juniorPct" style="font-weight:700;color:var(--info);font-size:18px"><?= cfgVal($cfg,'junior_share_pct',20) ?>%</div>
            <div style="font-size:12px;color:var(--text-muted)">Junior Mekanik</div>
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">Minimum Garantee Kepala Bengkel / Bulan (Rp)</label>
          <div class="input-group">
            <span class="input-addon">Rp</span>
            <input type="number" name="kabeng_min_guarantee" class="form-control" min="0" step="50000"
                   value="<?= cfgVal($cfg,'kabeng_min_guarantee',0) ?>" placeholder="0 = tidak ada minimum">
          </div>
          <small style="color:var(--text-muted);font-size:12px">Jika bonus jasa Kabeng kurang dari nilai ini, akan otomatis dinaikkan ke nilai ini</small>
        </div>
      </div>
    </div>

    <!-- ADMIN BONUS -->
    <div class="card">
      <div class="card-header">
        <div class="card-header-title">
          <i class="fas fa-user-cog" style="color:var(--success);margin-right:8px"></i>Bonus Tenaga Administrasi
        </div>
      </div>
      <div class="card-body">
        <div class="alert alert-info" style="margin-bottom:16px">
          <i class="fas fa-info-circle"></i>
          Admin mendapat <strong>gaji pokok</strong> + <strong>bonus % dari total omset</strong> bengkel sebagai apresiasi kinerja.
        </div>
        <div class="form-group">
          <label class="form-label">Bonus Admin (% dari Total Omset Bulanan)</label>
          <div class="input-group">
            <input type="number" name="admin_bonus_pct" class="form-control"
                   min="0" max="10" step="0.5"
                   value="<?= cfgVal($cfg,'admin_bonus_pct',1) ?>">
            <span class="input-addon">%</span>
          </div>
          <small style="color:var(--text-muted);font-size:12px">Contoh: 1% dari omset Rp 10 juta = Bonus Rp 100.000</small>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- SIMULATION PREVIEW -->
<div class="card mt-24 mb-24" id="simCard">
  <div class="card-header">
    <div class="card-header-title"><i class="fas fa-calculator" style="color:var(--primary);margin-right:8px"></i>Simulasi Kalkulasi</div>
    <span style="font-size:12px;color:var(--text-muted)">Masukkan nilai contoh untuk melihat hasil</span>
  </div>
  <div class="card-body">
    <div style="display:flex;gap:16px;flex-wrap:wrap;margin-bottom:20px">
      <div class="form-group" style="flex:1;min-width:160px">
        <label class="form-label">Contoh Total Jasa (Rp)</label>
        <div class="input-group">
          <span class="input-addon">Rp</span>
          <input type="number" id="simJasa" class="form-control" value="500000" oninput="simulate()">
        </div>
      </div>
      <div class="form-group" style="flex:1;min-width:160px">
        <label class="form-label">Contoh Total Part (Rp)</label>
        <div class="input-group">
          <span class="input-addon">Rp</span>
          <input type="number" id="simPart" class="form-control" value="300000" oninput="simulate()">
        </div>
      </div>
    </div>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:12px" id="simResult">
      <!-- Filled by JS -->
    </div>
  </div>
</div>

<div style="display:flex;gap:10px;justify-content:flex-end;margin-bottom:32px">
  <a href="<?= BASE_URL ?>/pages/payroll/index.php" class="btn btn-outline">Batal</a>
  <button type="submit" class="btn btn-primary btn-lg"><i class="fas fa-save"></i> Simpan Pengaturan</button>
</div>

</form>

<script>
function updateSplit(v) {
  document.getElementById('mechVal').textContent = v + '%';
  document.getElementById('mechPct2').textContent = v + '%';
  document.getElementById('ownerPct').textContent = (100-v) + '%';
  document.getElementById('barMekanik').style.width = v + '%';
  document.getElementById('barOwner').style.width = (100-v) + '%';
  document.getElementById('service_mechanic_pct').value = v;
  simulate();
}
function updateKabeng(v) {
  document.getElementById('kabengVal').textContent = v + '%';
  document.getElementById('kabengPct2').textContent = v + '%';
  document.getElementById('juniorPct').textContent = (100-v) + '%';
  document.getElementById('barKabeng').style.width = v + '%';
  document.getElementById('barJunior').style.width = (100-v) + '%';
  document.getElementById('kabeng_share_pct').value = v;
  simulate();
}
function fmtRp(n) { return 'Rp ' + new Intl.NumberFormat('id-ID').format(Math.round(n)); }
function simulate() {
  const jasa  = parseFloat(document.getElementById('simJasa').value) || 0;
  const part  = parseFloat(document.getElementById('simPart').value) || 0;
  const mPct  = parseFloat(document.getElementById('service_mechanic_pct').value) || 60;
  const kPct  = parseFloat(document.getElementById('kabeng_share_pct').value) || 80;
  const jPct  = 100 - kPct;
  const oPct  = 100 - mPct;

  const mechTotal  = jasa * mPct / 100;
  const ownerJasa  = jasa * oPct / 100;
  const kabengBonus = mechTotal * kPct / 100;
  const juniorBonus = mechTotal * jPct / 100;

  document.getElementById('simResult').innerHTML = `
    <div style="background:rgba(255,107,43,.08);border-radius:10px;padding:14px;text-align:center">
      <div style="font-size:11px;color:var(--text-muted);text-transform:uppercase;font-weight:600;margin-bottom:6px">Owner (Jasa)</div>
      <div style="font-size:18px;font-weight:800;color:var(--primary)">${fmtRp(ownerJasa)}</div>
      <div style="font-size:11px;color:var(--text-muted)">${oPct}% dari jasa</div>
    </div>
    <div style="background:rgba(16,185,129,.08);border-radius:10px;padding:14px;text-align:center">
      <div style="font-size:11px;color:var(--text-muted);text-transform:uppercase;font-weight:600;margin-bottom:6px">Owner (Part)</div>
      <div style="font-size:18px;font-weight:800;color:var(--success)">${fmtRp(part)}</div>
      <div style="font-size:11px;color:var(--text-muted)">100% part</div>
    </div>
    <div style="background:rgba(245,158,11,.08);border-radius:10px;padding:14px;text-align:center">
      <div style="font-size:11px;color:var(--text-muted);text-transform:uppercase;font-weight:600;margin-bottom:6px">Kepala Bengkel</div>
      <div style="font-size:18px;font-weight:800;color:var(--warning)">${fmtRp(kabengBonus)}</div>
      <div style="font-size:11px;color:var(--text-muted)">${kPct}% dari Rp${fmtRp(mechTotal).replace('Rp ','')}</div>
    </div>
    <div style="background:rgba(59,130,246,.08);border-radius:10px;padding:14px;text-align:center">
      <div style="font-size:11px;color:var(--text-muted);text-transform:uppercase;font-weight:600;margin-bottom:6px">Junior Mekanik</div>
      <div style="font-size:18px;font-weight:800;color:var(--info)">${fmtRp(juniorBonus)}</div>
      <div style="font-size:11px;color:var(--text-muted)">${jPct}% dari Rp${fmtRp(mechTotal).replace('Rp ','')}</div>
    </div>
    <div style="background:rgba(0,0,0,.04);border-radius:10px;padding:14px;text-align:center">
      <div style="font-size:11px;color:var(--text-muted);text-transform:uppercase;font-weight:600;margin-bottom:6px">Total Owner</div>
      <div style="font-size:18px;font-weight:800;color:var(--danger)">${fmtRp(ownerJasa + part)}</div>
      <div style="font-size:11px;color:var(--text-muted)">Jasa + Part</div>
    </div>
  `;
}
simulate();
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
