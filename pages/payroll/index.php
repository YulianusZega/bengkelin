<?php
require_once __DIR__ . '/../../includes/auth.php';
requireLogin();
requireRole('owner', 'admin');

$title      = 'Penggajian';
$activePage = 'payroll';
$db         = getDB();

$month = $_GET['month'] ?? date('Y-m');
[$yr, $mo] = explode('-', $month);

// Load revenue settings
$cfg = $db->query("SELECT setting_key, setting_value FROM settings WHERE setting_key IN
    ('service_mechanic_pct','service_owner_pct','kabeng_share_pct','junior_share_pct',
     'parts_owner_pct','admin_bonus_pct','kabeng_min_guarantee')")
    ->fetchAll(PDO::FETCH_KEY_PAIR);

$svcMechPct   = (float)($cfg['service_mechanic_pct'] ?? 60);
$svcOwnerPct  = (float)($cfg['service_owner_pct']    ?? 40);
$kabengPct    = (float)($cfg['kabeng_share_pct']     ?? 80);
$juniorPct    = (float)($cfg['junior_share_pct']     ?? 20);
$adminBonusPct= (float)($cfg['admin_bonus_pct']      ?? 1);
$kabengMin    = (float)($cfg['kabeng_min_guarantee'] ?? 0);

// Total omset bulan ini (untuk bonus admin)
$omset = $db->prepare("SELECT COALESCE(SUM(total),0) FROM work_orders WHERE payment_status='paid' AND YEAR(updated_at)=? AND MONTH(updated_at)=?");
$omset->execute([$yr, $mo]);
$totalOmset = (float)$omset->fetchColumn();

// Total jasa bulan ini
$jasa = $db->prepare("SELECT COALESCE(SUM(subtotal_services),0) FROM work_orders WHERE payment_status='paid' AND YEAR(updated_at)=? AND MONTH(updated_at)=?");
$jasa->execute([$yr, $mo]);
$totalJasa = (float)$jasa->fetchColumn();

// Total part bulan ini
$part = $db->prepare("SELECT COALESCE(SUM(subtotal_parts),0) FROM work_orders WHERE payment_status='paid' AND YEAR(updated_at)=? AND MONTH(updated_at)=?");
$part->execute([$yr, $mo]);
$totalParts = (float)$part->fetchColumn();

// Semua karyawan aktif
$employees = $db->query("SELECT e.*, u.email FROM employees e LEFT JOIN users u ON e.user_id=u.id WHERE e.status='active' ORDER BY FIELD(e.position,'owner','kabeng','mekanik','admin') ASC, e.name ASC")->fetchAll();

// Per mechanic revenue — Multi-Assistant aware
$woStmt = $db->prepare("
    SELECT 
        wo.id, 
        wo.mechanic_id, 
        wo.subtotal_services,
        GROUP_CONCAT(wa.employee_id) as assistant_ids
    FROM work_orders wo
    LEFT JOIN wo_assistants wa ON wo.id = wa.wo_id
    WHERE wo.payment_status='paid' 
      AND wo.status IN ('done','delivered')
      AND YEAR(wo.updated_at)=? 
      AND MONTH(wo.updated_at)=?
    GROUP BY wo.id
");
$woStmt->execute([$yr, $mo]);
$woList = $woStmt->fetchAll();

$mechRevMap = [];
foreach ($woList as $wo) {
    $svcTotal = (float)$wo['subtotal_services'];
    $primId   = $wo['mechanic_id'];
    $asstIds  = $wo['assistant_ids'] ? explode(',', $wo['assistant_ids']) : [];
    $isSolo   = empty($asstIds);
    
    // Process Primary
    if ($primId) {
        if (!isset($mechRevMap[$primId])) $mechRevMap[$primId] = ['wo_count'=>0, 'service_rev'=>0, 'bonus'=>0, 'notes'=>[]];
        $mechRevMap[$primId]['wo_count']++;
        $mechRevMap[$primId]['service_rev'] += $svcTotal;
        
        if ($isSolo) {
            $bonus = $svcTotal * ($svcMechPct / 100);
            $mechRevMap[$primId]['bonus'] += $bonus;
            // Notes for transparency
            $mechRevMap[$primId]['notes'][] = "Solo: " . formatRupiah($svcTotal);
        } else {
            $bonus = $svcTotal * ($svcMechPct / 100) * ($kabengPct / 100);
            $mechRevMap[$primId]['bonus'] += $bonus;
            $mechRevMap[$primId]['notes'][] = "Utama (+" . count($asstIds) . " asst): " . formatRupiah($svcTotal);
        }
    }
    
    // Process Assistants
    if (!$isSolo) {
        $totalJuniorBonus = $svcTotal * ($svcMechPct / 100) * ($juniorPct / 100);
        $perJuniorBonus   = $totalJuniorBonus / count($asstIds);
        
        foreach ($asstIds as $aid) {
            if (!isset($mechRevMap[$aid])) $mechRevMap[$aid] = ['wo_count'=>0, 'service_rev'=>0, 'bonus'=>0, 'notes'=>[]];
            $mechRevMap[$aid]['wo_count']++;
            $mechRevMap[$aid]['service_rev'] += $svcTotal;
            $mechRevMap[$aid]['bonus'] += $perJuniorBonus;
            $mechRevMap[$aid]['notes'][] = "Asisten (rata): " . formatRupiah($svcTotal);
        }
    }
}
// Summarize notes to avoid duplicates in display
foreach ($mechRevMap as $eid => &$m) {
    if (!empty($m['notes'])) {
        $counts = array_count_values($m['notes']);
        $summary = [];
        foreach ($counts as $note => $c) {
            $summary[] = ($c > 1 ? "$c× " : "") . $note;
        }
        $m['note_text'] = implode(' | ', $summary);
    } else {
        $m['note_text'] = '';
    }
}
unset($m);

// Existing salary records
$salaryStmt = $db->prepare("SELECT * FROM salary_records WHERE period_year=? AND period_month=?");
$salaryStmt->execute([$yr, $mo]);
$salaryMap = [];
foreach ($salaryStmt->fetchAll() as $r) {
    $salaryMap[$r['employee_id']] = $r;
}

// Build payroll rows
$rows = [];
$ownerSvcIncome    = $totalJasa * ($svcOwnerPct / 100);
$ownerPartsIncome  = $totalParts;
$ownerTotalIncome  = $ownerSvcIncome + $ownerPartsIncome;
$totalPayroll      = 0;

// Hitung total mekanik & kabeng untuk distribusi
$kabengCount = 0;
$juniorCount = 0;
foreach ($employees as $e) {
    if ($e['position'] === 'kabeng') $kabengCount++;
    if ($e['position'] === 'mekanik') $juniorCount++;
}

foreach ($employees as $e) {
    $pos       = $e['position'];
    $baseSal   = (float)$e['salary'];
    $svcBonus  = 0;
    $omsetBonus= 0;
    $woCount   = 0;
    $svcRev    = 0;
    $note      = '';

    if ($pos === 'kabeng' || $pos === 'mekanik') {
        $rev     = $mechRevMap[$e['id']] ?? null;
        $svcRev  = $rev ? (float)$rev['service_rev'] : 0;
        $woCount = $rev ? (int)$rev['wo_count']       : 0;

        if ($rev) {
            $svcBonus = (float)$rev['bonus'];

            // Apply minimum guarantee for kabeng
            if ($pos === 'kabeng' && $svcBonus < $kabengMin) $svcBonus = $kabengMin;
            
            $note = $rev['note_text'];
        }
    } elseif ($pos === 'admin') {
        $omsetBonus = $totalOmset * ($adminBonusPct / 100);
        $note = "Omset bulan ini: " . formatRupiah($totalOmset) . " × " . $adminBonusPct . "% = " . formatRupiah($omsetBonus);
    } elseif ($pos === 'owner') {
        $svcBonus = $ownerSvcIncome;
        $note = "Pendapatan Jasa (" . $svcOwnerPct . "%): " . formatRupiah($ownerSvcIncome) . " + Part: " . formatRupiah($ownerPartsIncome);
        $omsetBonus = $ownerPartsIncome;
    }

    $total = $baseSal + $svcBonus + $omsetBonus;
    if ($pos !== 'owner') $totalPayroll += $total;

    $existing = $salaryMap[$e['id']] ?? null;

    $rows[] = [
        'employee'     => $e,
        'base_salary'  => $baseSal,
        'service_bonus'=> $svcBonus,
        'omset_bonus'  => $omsetBonus,
        'total'        => $total,
        'wo_count'     => $woCount,
        'service_rev'  => $svcRev,
        'note'         => $note,
        'existing'     => $existing,
        'parts_bonus'  => ($pos === 'owner') ? $ownerPartsIncome : 0,
    ];
}

// Handle generate/approve salary
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrf($_POST['csrf_token'] ?? '')) {
    $action = $_POST['action'] ?? '';

    if ($action === 'generate_all') {
        foreach ($rows as $row) {
            $e = $row['employee'];
            if ($e['position'] === 'owner') continue;
            $db->prepare("
                INSERT INTO salary_records
                  (employee_id, period_year, period_month, base_salary, service_bonus, omset_bonus, total_salary, wo_count, service_revenue, notes, created_by)
                VALUES (?,?,?,?,?,?,?,?,?,?,?)
                ON DUPLICATE KEY UPDATE
                  base_salary=VALUES(base_salary), service_bonus=VALUES(service_bonus),
                  omset_bonus=VALUES(omset_bonus), total_salary=VALUES(total_salary),
                  wo_count=VALUES(wo_count), service_revenue=VALUES(service_revenue),
                  notes=VALUES(notes), updated_at=NOW()
            ")->execute([
                $e['id'], $yr, $mo,
                $row['base_salary'], $row['service_bonus'], $row['omset_bonus'],
                $row['total'], $row['wo_count'], $row['service_rev'],
                $row['note'], $_SESSION['user_id'] ?? 1
            ]);
        }
        flashSet('success', 'Data gaji bulan ' . date('F Y', mktime(0,0,0,(int)$mo,1,(int)$yr)) . ' berhasil dibuat/diperbarui.');
        header('Location: ' . BASE_URL . '/pages/payroll/index.php?month=' . $month); exit;
    }

    if ($action === 'approve' && isset($_POST['salary_id'])) {
        $db->prepare("UPDATE salary_records SET status='approved', approved_by=?, approved_at=NOW() WHERE id=?")
           ->execute([$_SESSION['user_id'] ?? 1, (int)$_POST['salary_id']]);
        flashSet('success', 'Gaji disetujui.');
        header('Location: ' . BASE_URL . '/pages/payroll/index.php?month=' . $month); exit;
    }

    if ($action === 'mark_paid' && isset($_POST['salary_id'])) {
        $db->prepare("UPDATE salary_records SET status='paid', paid_at=NOW() WHERE id=?")
           ->execute([(int)$_POST['salary_id']]);
        flashSet('success', 'Gaji ditandai sudah dibayar.');
        header('Location: ' . BASE_URL . '/pages/payroll/index.php?month=' . $month); exit;
    }
}

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
  <div class="page-header-left">
    <h1>Penggajian & Bagi Hasil</h1>
    <p>Kalkulasi gaji karyawan dan bagi hasil jasa — <?= date('F Y', mktime(0,0,0,(int)$mo,1,(int)$yr)) ?></p>
  </div>
  <div class="page-header-right">
    <a href="<?= BASE_URL ?>/pages/payroll/settings.php" class="btn btn-outline">
      <i class="fas fa-percentage"></i> Atur Persentase
    </a>
    <a href="<?= BASE_URL ?>/pages/payroll/slip.php?month=<?= $month ?>" class="btn btn-outline" target="_blank">
      <i class="fas fa-file-invoice"></i> Slip Gaji
    </a>
    <form method="GET" style="display:inline-flex;gap:8px;align-items:center">
      <input type="month" name="month" class="form-control" value="<?= $month ?>" style="width:180px">
      <button type="submit" class="btn btn-primary">Tampilkan</button>
    </form>
  </div>
</div>

<!-- REVENUE SUMMARY -->
<div class="stats-grid mb-24">
  <div class="stat-card">
    <div class="stat-icon green"><i class="fas fa-cash-register"></i></div>
    <div class="stat-info">
      <div class="stat-label">Total Omset Bulan Ini</div>
      <div class="stat-value" style="font-size:18px"><?= formatRupiah($totalOmset) ?></div>
      <div class="stat-change neutral">Lunas</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon blue"><i class="fas fa-tools"></i></div>
    <div class="stat-info">
      <div class="stat-label">Pendapatan Jasa</div>
      <div class="stat-value" style="font-size:18px"><?= formatRupiah($totalJasa) ?></div>
      <div class="stat-change neutral">Mekanik: <?= formatRupiah($totalJasa * $svcMechPct/100) ?> | Owner: <?= formatRupiah($totalJasa * $svcOwnerPct/100) ?></div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon orange"><i class="fas fa-boxes"></i></div>
    <div class="stat-info">
      <div class="stat-label">Pendapatan Spare Part</div>
      <div class="stat-value" style="font-size:18px"><?= formatRupiah($totalParts) ?></div>
      <div class="stat-change neutral">100% untuk Owner</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon purple"><i class="fas fa-users"></i></div>
    <div class="stat-info">
      <div class="stat-label">Total Beban Gaji Karyawan</div>
      <div class="stat-value" style="font-size:18px"><?= formatRupiah($totalPayroll) ?></div>
      <div class="stat-change neutral">Tidak termasuk owner</div>
    </div>
  </div>
</div>

<!-- REVENUE SPLIT INFO -->
<div class="card mb-24" style="border-left:4px solid var(--primary)">
  <div class="card-body" style="padding:16px 20px">
    <div style="display:flex;gap:32px;flex-wrap:wrap;align-items:center">
      <div style="font-weight:700;color:var(--text-muted);font-size:12px;text-transform:uppercase;letter-spacing:.5px">
        <i class="fas fa-sliders-h" style="color:var(--primary);margin-right:6px"></i>Skema Bagi Hasil Aktif
      </div>
      <div style="display:flex;gap:24px;flex-wrap:wrap">
        <div style="text-align:center">
          <div style="font-size:22px;font-weight:800;color:var(--primary)"><?= $svcMechPct ?>%</div>
          <div style="font-size:11px;color:var(--text-muted)">Jasa → Mekanik</div>
        </div>
        <div style="text-align:center">
          <div style="font-size:22px;font-weight:800;color:var(--success)"><?= $svcOwnerPct ?>%</div>
          <div style="font-size:11px;color:var(--text-muted)">Jasa → Owner</div>
        </div>
        <div style="border-left:1px solid var(--border);padding-left:24px;text-align:center">
          <div style="font-size:22px;font-weight:800;color:var(--warning)"><?= $kabengPct ?>%</div>
          <div style="font-size:11px;color:var(--text-muted)">dari Mekanik → Kabeng</div>
        </div>
        <div style="text-align:center">
          <div style="font-size:22px;font-weight:800;color:var(--info)"><?= $juniorPct ?>%</div>
          <div style="font-size:11px;color:var(--text-muted)">dari Mekanik → Junior</div>
        </div>
        <div style="border-left:1px solid var(--border);padding-left:24px;text-align:center">
          <div style="font-size:22px;font-weight:800;color:var(--danger)">100%</div>
          <div style="font-size:11px;color:var(--text-muted)">Part → Owner</div>
        </div>
        <div style="text-align:center">
          <div style="font-size:22px;font-weight:800;color:var(--text-muted)"><?= $adminBonusPct ?>%</div>
          <div style="font-size:11px;color:var(--text-muted)">Omset → Bonus Admin</div>
        </div>
      </div>
      <a href="<?= BASE_URL ?>/pages/payroll/settings.php" style="margin-left:auto;font-size:12px;color:var(--primary);font-weight:600;text-decoration:none">
        <i class="fas fa-edit"></i> Edit
      </a>
    </div>
  </div>
</div>

<!-- ACTION GENERATE -->
<div class="card mb-24">
  <div class="card-body" style="display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap">
    <div>
      <div style="font-weight:700;margin-bottom:4px">Generate Slip Gaji — <?= date('F Y', mktime(0,0,0,(int)$mo,1,(int)$yr)) ?></div>
      <div style="font-size:13px;color:var(--text-muted)">Hitung otomatis berdasarkan WO yang sudah lunas bulan ini</div>
    </div>
    <form method="POST" style="display:inline">
      <input type="hidden" name="csrf_token" value="<?= csrf() ?>">
      <input type="hidden" name="action" value="generate_all">
      <button type="submit" class="btn btn-primary">
        <i class="fas fa-calculator"></i> Generate / Refresh Gaji
      </button>
    </form>
  </div>
</div>

<!-- PAYROLL TABLE -->
<div class="card">
  <div class="card-header">
    <div class="card-header-title">
      <i class="fas fa-money-check-alt" style="color:var(--primary);margin-right:8px"></i>
      Rincian Gaji Karyawan — <?= date('F Y', mktime(0,0,0,(int)$mo,1,(int)$yr)) ?>
    </div>
  </div>
  <div class="table-responsive">
    <table class="table">
      <thead>
        <tr>
          <th>Karyawan</th>
          <th>Jabatan</th>
          <th style="text-align:center">WO</th>
          <th style="text-align:right">Gaji Pokok</th>
          <th style="text-align:right">Bonus Jasa</th>
          <th style="text-align:right">Bonus Lainnya</th>
          <th style="text-align:right">Total Take-Home</th>
          <th style="text-align:center">Status</th>
          <th style="text-align:center">Aksi</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $row):
            $e = $row['employee'];
            $ex = $row['existing'];
            $statusBadge = '';
            $statusColor = 'var(--text-muted)';
            if ($ex) {
                if ($ex['status'] === 'paid')     { $statusBadge = 'Lunas';    $statusColor = 'var(--success)'; }
                elseif ($ex['status'] === 'approved') { $statusBadge = 'Disetujui'; $statusColor = 'var(--info)'; }
                else                                   { $statusBadge = 'Draft';    $statusColor = 'var(--warning)'; }
            } else {
                $statusBadge = '— Belum generate'; $statusColor = 'var(--text-muted)';
            }
            $posIcons = ['owner'=>'fa-crown','kabeng'=>'fa-user-tie','mekanik'=>'fa-wrench','admin'=>'fa-user-cog'];
            $posColors= ['owner'=>'var(--primary)','kabeng'=>'var(--warning)','mekanik'=>'var(--info)','admin'=>'var(--success)'];
        ?>
        <tr>
          <td>
            <div style="font-weight:600"><?= htmlspecialchars($e['name']) ?></div>
            <div style="font-size:12px;color:var(--text-muted)"><?= $e['employee_id'] ?></div>
          </td>
          <td>
            <span style="display:inline-flex;align-items:center;gap:5px;font-size:12px;font-weight:600;padding:3px 8px;border-radius:99px;background:rgba(0,0,0,.06);color:<?= $posColors[$e['position']] ?? '' ?>">
              <i class="fas <?= $posIcons[$e['position']] ?? 'fa-user' ?>"></i>
              <?= strtoupper($e['position']) ?>
            </span>
          </td>
          <td style="text-align:center;font-weight:700">
            <?php if ($row['wo_count'] > 0): ?>
              <span style="color:var(--primary)"><?= $row['wo_count'] ?></span>
              <div style="font-size:11px;color:var(--text-muted)"><?= formatRupiah($row['service_rev']) ?></div>
            <?php else: echo '—'; endif; ?>
          </td>
          <td style="text-align:right;font-weight:600"><?= $e['position'] === 'owner' ? '—' : formatRupiah($row['base_salary']) ?></td>
          <td style="text-align:right;color:var(--success);font-weight:600">
            <?= $row['service_bonus'] > 0 ? formatRupiah($row['service_bonus']) : '—' ?>
            <?php if ($e['position'] === 'owner' && $row['parts_bonus'] > 0): ?>
              <div style="font-size:11px;color:var(--text-muted)">+ Part: <?= formatRupiah($row['parts_bonus']) ?></div>
            <?php endif; ?>
          </td>
          <td style="text-align:right;color:var(--info);font-weight:600">
            <?= ($row['omset_bonus'] > 0 && $e['position'] !== 'owner') ? formatRupiah($row['omset_bonus']) : '—' ?>
          </td>
          <td style="text-align:right">
            <strong style="font-size:15px;color:<?= $e['position'] === 'owner' ? 'var(--primary)' : 'var(--text-primary)' ?>">
              <?= $e['position'] === 'owner'
                    ? formatRupiah($ownerSvcIncome + $ownerPartsIncome)
                    : formatRupiah($row['total']) ?>
            </strong>
          </td>
          <td style="text-align:center">
            <span style="font-size:12px;font-weight:600;color:<?= $statusColor ?>"><?= $statusBadge ?></span>
          </td>
          <td style="text-align:center">
            <?php if ($e['position'] !== 'owner' && $ex): ?>
              <?php if ($ex['status'] === 'draft'): ?>
                <form method="POST" style="display:inline">
                  <input type="hidden" name="csrf_token" value="<?= csrf() ?>">
                  <input type="hidden" name="action" value="approve">
                  <input type="hidden" name="salary_id" value="<?= $ex['id'] ?>">
                  <button class="btn btn-outline btn-sm"><i class="fas fa-check"></i> Setujui</button>
                </form>
              <?php elseif ($ex['status'] === 'approved'): ?>
                <form method="POST" style="display:inline">
                  <input type="hidden" name="csrf_token" value="<?= csrf() ?>">
                  <input type="hidden" name="action" value="mark_paid">
                  <input type="hidden" name="salary_id" value="<?= $ex['id'] ?>">
                  <button class="btn btn-success btn-sm"><i class="fas fa-money-bill"></i> Bayar</button>
                </form>
              <?php else: ?>
                <a href="<?= BASE_URL ?>/pages/payroll/slip_single.php?salary_id=<?= $ex['id'] ?>" target="_blank" class="btn btn-outline btn-sm">
                  <i class="fas fa-print"></i> Slip
                </a>
              <?php endif; ?>
            <?php elseif ($e['position'] !== 'owner'): ?>
              <span style="font-size:12px;color:var(--text-muted)">Generate dulu</span>
            <?php else: ?>
              <span style="font-size:12px;color:var(--text-muted)">Owner</span>
            <?php endif; ?>

            <?php if ($ex && $row['note']): ?>
              <button type="button" class="btn btn-outline btn-sm" style="margin-left:4px"
                onclick="document.getElementById('note-<?= $e['id'] ?>').style.display=document.getElementById('note-<?= $e['id'] ?>').style.display==='none'?'block':'none'"
                title="Lihat Detail"><i class="fas fa-info-circle"></i></button>
            <?php endif; ?>
          </td>
        </tr>
        <?php if ($row['note']): ?>
        <tr id="note-<?= $e['id'] ?>" style="display:none;background:var(--bg-light)">
          <td colspan="9" style="padding:10px 20px;font-size:12px;color:var(--text-muted);font-style:italic">
            <i class="fas fa-calculator" style="margin-right:6px;color:var(--primary)"></i><?= htmlspecialchars($row['note']) ?>
          </td>
        </tr>
        <?php endif; ?>
        <?php endforeach; ?>
      </tbody>
      <tfoot>
        <tr style="background:var(--border-light);font-weight:700">
          <td colspan="6" style="padding:12px 14px">TOTAL BEBAN GAJI KARYAWAN</td>
          <td style="text-align:right;padding:12px 14px;color:var(--success);font-size:15px"><?= formatRupiah($totalPayroll) ?></td>
          <td colspan="2"></td>
        </tr>
        <tr style="background:rgba(255,107,43,.08)">
          <td colspan="6" style="padding:10px 14px;font-weight:700;color:var(--primary)">PENDAPATAN BERSIH OWNER (setelah beban gaji)</td>
          <td style="text-align:right;padding:10px 14px;font-weight:800;color:var(--primary);font-size:15px">
            <?= formatRupiah(($ownerSvcIncome + $ownerPartsIncome) - $totalPayroll) ?>
          </td>
          <td colspan="2"></td>
        </tr>
      </tfoot>
    </table>
  </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
