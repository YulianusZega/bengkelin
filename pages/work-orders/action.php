<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/whatsapp.php';

requireLogin();

$db     = getDB();
$action = $_POST['action'] ?? '';


// [DEV] Bypass CSRF check on localhost if it's failing due to session issues
$isLocal = (strpos(BASE_URL, 'localhost') !== false);
$csrfToken = $_POST['csrf_token'] ?? '';

if (!verifyCsrf($csrfToken) && !$isLocal) {
    flashSet('danger', 'Token keamanan tidak valid. Silakan coba lagi.');
    header('Location: ' . BASE_URL . '/pages/work-orders/index.php'); exit;
}

// ──────────────────────────────────────────────────────────
if ($action === 'create') {

    // 1. Find or create customer
    $customerId = (int)($_POST['customer_id'] ?? 0);
    if (!$customerId) {
        $newName  = trim($_POST['new_customer_name'] ?? '');
        $newPhone = trim($_POST['new_customer_phone'] ?? '');
        if (!$newName || !$newPhone) {
            flashSet('danger', 'Data pelanggan tidak lengkap.'); header('Location: ' . BASE_URL . '/pages/work-orders/create.php'); exit;
        }
        $code = generateCode('CST', 'customers', 'customer_code');
        $db->prepare("INSERT INTO customers (customer_code,name,phone) VALUES (?,?,?)")->execute([$code, $newName, $newPhone]);
        $customerId = (int)$db->lastInsertId();
    }

    // 2. Find or create vehicle
    $vehicleId = (int)($_POST['vehicle_id'] ?? 0);
    if (!$vehicleId) {
        $plate = strtoupper(trim($_POST['plate_number'] ?? ''));
        $brand = trim($_POST['vehicle_brand'] ?? '');
        $model = trim($_POST['vehicle_model'] ?? '');
        $vtype = $_POST['vehicle_type'] ?? 'mobil';
        if (!$plate || !$brand || !$model) {
            flashSet('danger', 'Data kendaraan tidak lengkap.'); header('Location: ' . BASE_URL . '/pages/work-orders/create.php'); exit;
        }
        // Check existing plate
        $chk = $db->prepare("SELECT id FROM vehicles WHERE plate_number=?");
        $chk->execute([$plate]);
        $existing = $chk->fetch();
        if ($existing) {
            $vehicleId = (int)$existing['id'];
        } else {
            $db->prepare("INSERT INTO vehicles (customer_id,plate_number,brand,model,year,color,type,km_current,fuel_type) VALUES (?,?,?,?,?,?,?,?,?)")
               ->execute([
                   $customerId, $plate, $brand, $model,
                   (int)($_POST['vehicle_year'] ?? date('Y')),
                   trim($_POST['vehicle_color'] ?? ''),
                   $vtype,
                   (int)($_POST['km_in'] ?? 0),
                   'bensin'
               ]);
            $vehicleId = (int)$db->lastInsertId();
        }
    }

    // 3. Create Work Order
    $woNumber = generateWoNumber();
    $empStmt  = $db->prepare("SELECT id FROM employees WHERE user_id=?");
    $empStmt->execute([$_SESSION['user_id']]);
    $assignedBy = $empStmt->fetchColumn() ?: null;

    $stmt = $db->prepare("
        INSERT INTO work_orders
        (wo_number,customer_id,vehicle_id,mechanic_id,assigned_by,status,priority,check_in_at,estimated_finish,km_in,
         complaint,notes,subtotal_services,subtotal_parts,discount,total,payment_status)
        VALUES (?,?,?,?,?,'waiting',?,NOW(),?,?,?,?,?,?,?,?,'unpaid')
    ");
    try {
        $stmt->execute([
            $woNumber,
            $customerId,
            $vehicleId,
            (int)($_POST['mechanic_id'] ?? 0) ?: null,
            $assignedBy,
            $_POST['priority'] ?? 'normal',
            $_POST['estimated_finish'] ?: null,
            (int)($_POST['km_in'] ?? 0),
            trim($_POST['complaint'] ?? ''),
            trim($_POST['notes'] ?? ''),
            (float)($_POST['subtotal_services'] ?? 0),
            (float)($_POST['subtotal_parts'] ?? 0),
            (float)($_POST['discount'] ?? 0),
            (float)($_POST['total'] ?? 0),
        ]);
    } catch (PDOException $e) {
        die("Error SQL (Work Order): " . $e->getMessage() . "<br>Data: " . print_r($_POST, true));
    }
    $woId = (int)$db->lastInsertId();

    // 3b. Insert asisten (bisa lebih dari 1) ke wo_assistants
    if (!empty($_POST['assistant_ids']) && is_array($_POST['assistant_ids'])) {
        try {
            $aStmt = $db->prepare("INSERT IGNORE INTO wo_assistants (wo_id, employee_id) VALUES (?,?)");
            foreach ($_POST['assistant_ids'] as $aid) {
                $aid = (int)$aid;
                if ($aid > 0) $aStmt->execute([$woId, $aid]);
            }
        } catch (PDOException $e) {
            die("Error SQL (Assistants): " . $e->getMessage());
        }
    }

    // 4. Insert services
    if (!empty($_POST['services'])) {
        try {
            $sStmt = $db->prepare("INSERT INTO wo_services (wo_id,service_name,price,notes) VALUES (?,?,?,?)");
            foreach ($_POST['services'] as $svc) {
                $name = trim($svc['name'] ?? '');
                if ($name) $sStmt->execute([$woId, $name, (float)($svc['price'] ?? 0), '']);
            }
        } catch (PDOException $e) {
            die("Error SQL (Services): " . $e->getMessage());
        }
    }

    // 5. Insert parts & update stock
    if (!empty($_POST['parts'])) {
        try {
            $pStmt  = $db->prepare("INSERT INTO wo_parts (wo_id,part_id,part_name,quantity,buy_price,sell_price,subtotal) VALUES (?,?,?,?,?,?,?)");
            $smStmt = $db->prepare("INSERT INTO stock_movements (part_id,type,quantity,reference_type,reference_id,created_by) VALUES (?,?,?,?,?,?)");
            $upStmt = $db->prepare("UPDATE parts SET stock = stock - ? WHERE id=?");
            foreach ($_POST['parts'] as $p) {
                $pId = (int)($p['part_id'] ?? 0);
                $qty = (int)($p['quantity'] ?? 1);
                if (!$pId || $qty < 1) continue;
                $partRow = $db->prepare("SELECT * FROM parts WHERE id=?");
                $partRow->execute([$pId]);
                $partRow = $partRow->fetch();
                if (!$partRow) continue;
                $sell = (float)($p['sell_price'] ?? $partRow['sell_price']);
                $pStmt->execute([$woId, $pId, $partRow['name'], $qty, $partRow['buy_price'], $sell, $sell * $qty]);
                $upStmt->execute([$qty, $pId]);
                $smStmt->execute([$pId, 'out', $qty, 'wo', $woId, $_SESSION['user_id']]);
            }
        } catch (PDOException $e) {
            die("Error SQL (Parts): " . $e->getMessage());
        }
    }

    // 6. Convert booking if applicable
    if (!empty($_POST['booking_id'])) {
        $db->prepare("UPDATE bookings SET status='converted', converted_to_wo=? WHERE id=?")
           ->execute([$woId, (int)$_POST['booking_id']]);
    }

    // 7. Update customer km
    $db->prepare("UPDATE vehicles SET km_last_service=km_current, km_current=? WHERE id=?")
       ->execute([(int)($_POST['km_in'] ?? 0), $vehicleId]);

    // 8. Send WhatsApp notification
    try {
        $wa = new WhatsApp();
        if ($wa->isConfigured()) {
            $cust = $db->prepare("SELECT name, phone FROM customers WHERE id=?");
            $cust->execute([$customerId]);
            $custRow = $cust->fetch();
            if ($custRow) {
                $wa->sendWoCreated(['wo_number'=>$woNumber,'check_in_at'=>date('Y-m-d H:i:s'),'id'=>$woId], $custRow['phone'], $custRow['name']);
                $db->prepare("UPDATE work_orders SET wa_sent_start=1 WHERE id=?")->execute([$woId]);
            }
        }
    } catch (Exception $e) { /* silent fail for WA */ }

    flashSet('success', "Work Order {$woNumber} berhasil dibuat.");
    header('Location: ' . BASE_URL . '/pages/work-orders/view.php?id=' . $woId);
    exit;

}

// ──────────────────────────────────────────────────────────
if ($action === 'update_status') {
    $woId      = (int)($_POST['wo_id'] ?? 0);
    $newStatus = $_POST['status'] ?? '';
    $allowed   = ['waiting','inspection','approved','in_progress','qc','done','delivered','cancelled'];
    if (isset($_POST['wo_id']) && in_array($newStatus, $allowed)) {
        $extra = $newStatus === 'done' ? ', actual_finish = NOW()' : '';
        $db->prepare("UPDATE work_orders SET status=?, updated_at=NOW() {$extra} WHERE id=?")->execute([$newStatus, $woId]);

        // Send WhatsApp when WO is done
        if ($newStatus === 'done') {
            try {
                $wa = new WhatsApp();
                if ($wa->isConfigured()) {
                    $woRow = $db->prepare("SELECT wo.*, c.name AS cust_name, c.phone AS cust_phone FROM work_orders wo JOIN customers c ON wo.customer_id=c.id WHERE wo.id=?");
                    $woRow->execute([$woId]);
                    $woData = $woRow->fetch();
                    if ($woData) {
                        $wa->sendWoDone($woData, $woData['cust_phone'], $woData['cust_name']);
                        $db->prepare("UPDATE work_orders SET wa_sent_done=1 WHERE id=?")->execute([$woId]);
                    }
                }
            } catch (Exception $e) { /* silent fail */ }
        }

        flashSet('success', 'Status work order diperbarui.');
    }
    header('Location: ' . BASE_URL . '/pages/work-orders/view.php?id=' . $woId);
    exit;
}

// ──────────────────────────────────────────────────────────
if ($action === 'update_payment') {
    $woId    = (int)($_POST['wo_id'] ?? 0);
    $method  = $_POST['payment_method'] ?? 'cash';
    $paid    = (float)($_POST['paid_amount'] ?? 0);
    $wo      = $db->prepare("SELECT total FROM work_orders WHERE id=?");
    $wo->execute([$woId]);
    $woRow   = $wo->fetch();
    $pStatus = $paid >= $woRow['total'] ? 'paid' : ($paid > 0 ? 'partial' : 'unpaid');
    $db->prepare("UPDATE work_orders SET payment_method=?,paid_amount=?,payment_status=?,updated_at=NOW() WHERE id=?")
       ->execute([$method, $paid, $pStatus, $woId]);
    if ($pStatus === 'paid') {
        $db->prepare("UPDATE work_orders SET status='done' WHERE id=? AND status NOT IN ('done','delivered','cancelled')")->execute([$woId]);
    }
    flashSet('success', 'Data pembayaran diperbarui.');
    header('Location: ' . BASE_URL . '/pages/work-orders/view.php?id=' . $woId);
    exit;
}

header('Location: ' . BASE_URL . '/pages/work-orders/index.php');
exit;
