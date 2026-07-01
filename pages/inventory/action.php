<?php
require_once __DIR__ . '/../../includes/auth.php';
requireLogin();

$db     = getDB();
$action = $_POST['action'] ?? '';

if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
    flashSet('danger', 'Token tidak valid'); header('Location: ' . BASE_URL . '/pages/inventory/index.php'); exit;
}

if ($action === 'add') {
    $stmt = $db->prepare("INSERT INTO parts (category_id,code,name,brand,unit,stock,min_stock,buy_price,sell_price,vehicle_type,shelf_location) VALUES (?,?,?,?,?,?,?,?,?,?,?)");
    $stmt->execute([
        $_POST['category_id'] ?: null,
        $_POST['code'] ?: null,
        $_POST['name'],
        $_POST['brand'] ?: null,
        $_POST['unit'],
        (int)$_POST['stock'],
        (int)$_POST['min_stock'],
        (float)$_POST['buy_price'],
        (float)$_POST['sell_price'],
        $_POST['vehicle_type'],
        $_POST['shelf_location'] ?: null
    ]);
    // Log stock movement
    if ((int)$_POST['stock'] > 0) {
        $partId = $db->lastInsertId();
        $db->prepare("INSERT INTO stock_movements (part_id,type,quantity,reference_type,notes,created_by) VALUES (?,?,?,?,?,?)")
           ->execute([$partId,'in',(int)$_POST['stock'],'purchase','Stok awal',$_SESSION['user_id']]);
    }
    flashSet('success', 'Sparepart berhasil ditambahkan.');

} elseif ($action === 'edit') {
    $id = (int)$_POST['id'];
    $stmt = $db->prepare("UPDATE parts SET category_id=?,code=?,name=?,brand=?,unit=?,min_stock=?,buy_price=?,sell_price=?,vehicle_type=?,shelf_location=? WHERE id=?");
    $stmt->execute([
        $_POST['category_id'] ?: null,
        $_POST['code'] ?: null,
        $_POST['name'],
        $_POST['brand'] ?: null,
        $_POST['unit'],
        (int)$_POST['min_stock'],
        (float)$_POST['buy_price'],
        (float)$_POST['sell_price'],
        $_POST['vehicle_type'],
        $_POST['shelf_location'] ?: null,
        $id
    ]);
    flashSet('success', 'Data sparepart diperbarui.');

} elseif ($action === 'adjust') {
    $partId  = (int)$_POST['part_id'];
    $type    = $_POST['type'];
    $qty     = (int)$_POST['quantity'];
    $notes   = trim($_POST['notes'] ?? '');

    if ($type === 'in' || $type === 'adjustment') {
        $db->prepare("UPDATE parts SET stock = stock + ? WHERE id=?")->execute([$qty, $partId]);
    } else {
        $current = $db->prepare("SELECT stock FROM parts WHERE id=?");
        $current->execute([$partId]);
        $cur = (int)$current->fetchColumn();
        if ($cur < $qty) {
            flashSet('danger', 'Stok tidak mencukupi.');
            header('Location: ' . BASE_URL . '/pages/inventory/index.php'); exit;
        }
        $db->prepare("UPDATE parts SET stock = stock - ? WHERE id=?")->execute([$qty, $partId]);
    }
    $db->prepare("INSERT INTO stock_movements (part_id,type,quantity,reference_type,notes,created_by) VALUES (?,?,?,?,?,?)")
       ->execute([$partId, $type, $qty, 'adjustment', $notes, $_SESSION['user_id']]);
    flashSet('success', 'Stok berhasil disesuaikan.');
}

header('Location: ' . BASE_URL . '/pages/inventory/index.php');
exit;
