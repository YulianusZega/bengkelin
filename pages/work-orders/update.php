<?php
require_once __DIR__ . '/../../includes/auth.php';
requireLogin();

$db    = getDB();
if (!isset($_POST['wo_id']) || !verifyCsrf($_POST['csrf_token'] ?? '')) {
    flashSet('danger','Akses tidak valid.'); header('Location: ' . BASE_URL . '/pages/work-orders/index.php'); exit;
}
$woId = (int)$_POST['wo_id'];

// Fetch original WO for stock adjustment reference
$origWo = $db->prepare("SELECT * FROM work_orders WHERE id=?"); $origWo->execute([$woId]); $origWo=$origWo->fetch();
if (!$origWo) { header('Location: ' . BASE_URL . '/pages/work-orders/index.php'); exit; }

// 1. Update WO info
$db->prepare("UPDATE work_orders SET mechanic_id=?,priority=?,estimated_finish=?,complaint=?,diagnosis=?,recommendation=?,notes=?,updated_at=NOW(),subtotal_services=?,subtotal_parts=?,discount=?,total=? WHERE id=?")
   ->execute([
       (int)($_POST['mechanic_id']??0)?:null,
       $_POST['priority']??'normal',
       $_POST['estimated_finish']??null?:null,
       trim($_POST['complaint']??''),
       trim($_POST['diagnosis']??''),
       trim($_POST['recommendation']??''),
       trim($_POST['notes']??''),
       (float)($_POST['subtotal_services']??0),
       (float)($_POST['subtotal_parts']??0),
       (float)($_POST['discount']??0),
       (float)($_POST['total']??0),
       $woId,
   ]);

// 1b. Sync Assistants
$db->prepare("DELETE FROM wo_assistants WHERE wo_id=?")->execute([$woId]);
if (!empty($_POST['assistant_ids']) && is_array($_POST['assistant_ids'])) {
    $aStmt = $db->prepare("INSERT IGNORE INTO wo_assistants (wo_id, employee_id) VALUES (?,?)");
    foreach ($_POST['assistant_ids'] as $aid) {
        $aid = (int)$aid;
        if ($aid > 0) $aStmt->execute([$woId, $aid]);
    }
}

// 2. Restore original parts stock
$origParts = $db->prepare("SELECT * FROM wo_parts WHERE wo_id=?"); $origParts->execute([$woId]); $origParts=$origParts->fetchAll();
foreach ($origParts as $op) {
    if ($op['part_id']) {
        $db->prepare("UPDATE parts SET stock = stock + ? WHERE id=?")->execute([$op['quantity'], $op['part_id']]);
    }
}

// 3. Delete old services & parts
$db->prepare("DELETE FROM wo_services WHERE wo_id=?")->execute([$woId]);
$db->prepare("DELETE FROM wo_parts WHERE wo_id=?")->execute([$woId]);

// 4. Re-insert services
if (!empty($_POST['services'])) {
    $sStmt = $db->prepare("INSERT INTO wo_services (wo_id,service_name,price) VALUES (?,?,?)");
    foreach ($_POST['services'] as $svc) {
        if (trim($svc['name']??'')) $sStmt->execute([$woId, $svc['name'], (float)($svc['price']??0)]);
    }
}

// 5. Re-insert parts & deduct stock
if (!empty($_POST['parts'])) {
    $pStmt  = $db->prepare("INSERT INTO wo_parts (wo_id,part_id,part_name,quantity,buy_price,sell_price,subtotal) VALUES (?,?,?,?,?,?,?)");
    $smStmt = $db->prepare("INSERT INTO stock_movements (part_id,type,quantity,reference_type,reference_id,created_by) VALUES (?,?,?,?,?,?)");
    $upStmt = $db->prepare("UPDATE parts SET stock = stock - ? WHERE id=?");
    foreach ($_POST['parts'] as $p) {
        $pId = (int)($p['part_id']??0); $qty=(int)($p['quantity']??1);
        if (!$pId || $qty<1) continue;
        $pr = $db->prepare("SELECT * FROM parts WHERE id=?"); $pr->execute([$pId]); $pr=$pr->fetch();
        if (!$pr) continue;
        $sell = (float)($p['sell_price']??$pr['sell_price']);
        $pStmt->execute([$woId,$pId,$pr['name'],$qty,$pr['buy_price'],$sell,$sell*$qty]);
        $upStmt->execute([$qty,$pId]);
        $smStmt->execute([$pId,'out',$qty,'wo',$woId,$_SESSION['user_id']]);
    }
}

flashSet('success','Work order berhasil diperbarui.');
header('Location: ' . BASE_URL . '/pages/work-orders/view.php?id=' . $woId);
exit;
