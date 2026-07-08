<?php
// ============================================================
// BENGKELIN - Report Export (CSV)
// ============================================================
require_once __DIR__ . '/../../includes/auth.php';
requireLogin();
requireRole('owner','admin');

$db   = getDB();
$type = $_GET['type'] ?? 'monthly';
$month = $_GET['month'] ?? date('Y-m');
[$yr, $mo] = explode('-', $month);

$monthLabel = date('F_Y', mktime(0,0,0,(int)$mo,1,(int)$yr));

if ($type === 'monthly') {
    $filename = "Laporan_Bulanan_{$monthLabel}.csv";

    $woList = $db->prepare("
        SELECT wo.wo_number, wo.created_at, wo.status, wo.payment_status, wo.payment_method,
               wo.subtotal_services, wo.subtotal_parts, wo.discount, wo.total, wo.paid_amount,
               c.name AS customer_name, c.phone AS customer_phone,
               v.plate_number, v.brand, v.model,
               e.name AS mechanic_name
        FROM work_orders wo
        LEFT JOIN customers c ON wo.customer_id=c.id
        LEFT JOIN vehicles v  ON wo.vehicle_id=v.id
        LEFT JOIN employees e ON wo.mechanic_id=e.id
        WHERE YEAR(wo.created_at)=? AND MONTH(wo.created_at)=?
        ORDER BY wo.created_at ASC
    ");
    $woList->execute([$yr, $mo]);
    $rows = $woList->fetchAll();

    header('Content-Type: text/csv; charset=utf-8');
    header("Content-Disposition: attachment; filename={$filename}");
    $out = fopen('php://output', 'w');
    // BOM for Excel
    fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));

    fputcsv($out, ['No. WO','Tanggal','Pelanggan','Telepon','Kendaraan','Plat Nomor','Teknisi','Status','Jasa','Part','Diskon','Total','Dibayar','Status Bayar','Metode']);
    foreach ($rows as $r) {
        fputcsv($out, [
            $r['wo_number'],
            date('d/m/Y H:i', strtotime($r['created_at'])),
            $r['customer_name'],
            $r['customer_phone'],
            $r['brand'].' '.$r['model'],
            $r['plate_number'],
            $r['mechanic_name'] ?? '—',
            ucfirst(str_replace('_',' ', $r['status'])),
            $r['subtotal_services'],
            $r['subtotal_parts'],
            $r['discount'],
            $r['total'],
            $r['paid_amount'],
            ucfirst($r['payment_status']),
            $r['payment_method'] ?? '—',
        ]);
    }
    fclose($out);
    exit;
}

if ($type === 'mechanic') {
    $filename = "Performa_Teknisi_{$monthLabel}.csv";

    $mechanics = $db->prepare("
        SELECT e.employee_id, e.name, e.specialization,
               COUNT(DISTINCT wo.id) AS total_wo,
               COUNT(DISTINCT CASE WHEN wo.status IN ('done','delivered') THEN wo.id END) AS completed,
               COALESCE(SUM(DISTINCT CASE WHEN wo.payment_status='paid' THEN wo.total ELSE 0 END),0) AS revenue,
               COALESCE(SUM(DISTINCT wo.subtotal_services),0) AS service_revenue,
               e.commission_rate
        FROM employees e
        LEFT JOIN (
            SELECT id, mechanic_id as emp_id, updated_at, payment_status, status, subtotal_services, total, created_at FROM work_orders
            UNION ALL
            SELECT w.id, wa.employee_id as emp_id, w.updated_at, w.payment_status, w.status, w.subtotal_services, w.total, w.created_at 
            FROM work_orders w JOIN wo_assistants wa ON w.id = wa.wo_id
        ) wo ON e.id = wo.emp_id 
            AND YEAR(wo.created_at)=? AND MONTH(wo.created_at)=?
        WHERE e.position IN ('junior_teknisi','senior_teknisi') AND e.status = 'active'
        GROUP BY e.id, e.employee_id, e.name, e.specialization, e.commission_rate
        ORDER BY revenue DESC
    ");
    $mechanics->execute([$yr, $mo]);
    $rows = $mechanics->fetchAll();

    header('Content-Type: text/csv; charset=utf-8');
    header("Content-Disposition: attachment; filename={$filename}");
    $out = fopen('php://output', 'w');
    fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));

    fputcsv($out, ['ID Karyawan','Nama','Spesialisasi','Total WO','WO Selesai','Completion %','Revenue','Service Revenue','Komisi %','Komisi Rp']);
    foreach ($rows as $r) {
        $rate = $r['total_wo'] > 0 ? round(($r['completed'] / $r['total_wo']) * 100) : 0;
        $commission = $r['commission_rate'] > 0 ? round($r['service_revenue'] * $r['commission_rate'] / 100) : 0;
        fputcsv($out, [
            $r['employee_id'],
            $r['name'],
            $r['specialization'] ?? '—',
            $r['total_wo'],
            $r['completed'],
            $rate.'%',
            $r['revenue'],
            $r['service_revenue'],
            $r['commission_rate'].'%',
            $commission,
        ]);
    }
    fclose($out);
    exit;
}

if ($type === 'inventory') {
    $filename = "Inventori_" . date('dmY') . ".xls";

    $rows = $db->query("
        SELECT p.code, p.name, pc.name AS category, p.brand, p.unit,
               p.stock, p.min_stock, p.buy_price, p.sell_price, p.shelf_location, p.vehicle_type
        FROM parts p
        LEFT JOIN part_categories pc ON p.category_id=pc.id
        ORDER BY p.name
    ")->fetchAll();

    header("Content-Type: application/vnd.ms-excel; charset=utf-8");
    header("Content-Disposition: attachment; filename={$filename}");
    header("Pragma: no-cache");
    header("Expires: 0");

    echo '<html xmlns:x="urn:schemas-microsoft-com:office:excel">';
    echo '<head><meta charset="UTF-8"></head><body>';
    echo '<table border="1">';
    echo '<tr style="background-color:#FF6B2B;color:white;font-weight:bold;">
            <th>Kode</th><th>Nama Part</th><th>Kategori</th><th>Merek</th><th>Satuan</th>
            <th>Stok</th><th>Min Stok</th><th>Harga Beli</th><th>Harga Jual</th><th>Rak</th><th>Kendaraan</th>
          </tr>';
    
    foreach ($rows as $r) {
        echo '<tr>';
        echo '<td>' . htmlspecialchars($r['code']) . '</td>';
        echo '<td>' . htmlspecialchars($r['name']) . '</td>';
        echo '<td>' . htmlspecialchars($r['category'] ?? '') . '</td>';
        echo '<td>' . htmlspecialchars($r['brand'] ?? '') . '</td>';
        echo '<td>' . htmlspecialchars($r['unit'] ?? '') . '</td>';
        echo '<td>' . $r['stock'] . '</td>';
        echo '<td>' . $r['min_stock'] . '</td>';
        echo '<td>' . $r['buy_price'] . '</td>';
        echo '<td>' . $r['sell_price'] . '</td>';
        echo '<td>' . htmlspecialchars($r['shelf_location'] ?? '') . '</td>';
        echo '<td>' . htmlspecialchars($r['vehicle_type'] ?? '') . '</td>';
        echo '</tr>';
    }
    echo '</table></body></html>';
    exit;
}

if ($type === 'services') {
    $filename = "Layanan_" . date('dmY') . ".xls";

    $rows = $db->query("
        SELECT s.service_name, s.price, COUNT(*) AS used_count, SUM(s.price) AS total_revenue,
               MAX(wo.created_at) AS last_used
        FROM wo_services s
        JOIN work_orders wo ON s.wo_id = wo.id
        GROUP BY s.service_name
        ORDER BY used_count DESC
    ")->fetchAll();

    header("Content-Type: application/vnd.ms-excel; charset=utf-8");
    header("Content-Disposition: attachment; filename={$filename}");
    header("Pragma: no-cache");
    header("Expires: 0");

    echo '<html xmlns:x="urn:schemas-microsoft-com:office:excel">';
    echo '<head><meta charset="UTF-8"></head><body>';
    echo '<table border="1">';
    echo '<tr style="background-color:#FF6B2B;color:white;font-weight:bold;">
            <th>No.</th><th>Nama Layanan</th><th>Digunakan</th><th>Harga (Biaya) Terakhir</th><th>Total Pendapatan</th><th>Terakhir Digunakan</th>
          </tr>';
    
    $i = 1;
    foreach ($rows as $r) {
        $lastUsed = $r['last_used'] ? date('d/m/Y', strtotime($r['last_used'])) : '-';
        echo '<tr>';
        echo '<td>' . $i++ . '</td>';
        echo '<td>' . htmlspecialchars($r['service_name']) . '</td>';
        echo '<td>' . $r['used_count'] . '</td>';
        echo '<td>' . $r['price'] . '</td>';
        echo '<td>' . $r['total_revenue'] . '</td>';
        echo '<td>' . $lastUsed . '</td>';
        echo '</tr>';
    }
    echo '</table></body></html>';
    exit;
}

// Fallback
header('Location: ' . BASE_URL . '/pages/reports/index.php');
exit;
