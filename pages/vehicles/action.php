<?php
require_once __DIR__ . '/../../includes/auth.php';
requireLogin();

$db     = getDB();
$action = $_POST['action'] ?? '';
$redir  = (int)($_POST['redirect_customer'] ?? 0);
$back   = $redir ? BASE_URL . '/pages/vehicles/index.php?customer_id=' . $redir : BASE_URL . '/pages/vehicles/index.php';

if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
    flashSet('danger', 'Token tidak valid'); header("Location: {$back}"); exit;
}

if ($action === 'add') {
    $plate = strtoupper(trim($_POST['plate_number'] ?? ''));
    // Check duplicate plate
    $chk = $db->prepare("SELECT id FROM vehicles WHERE plate_number=?");
    $chk->execute([$plate]);
    if ($chk->fetch()) { flashSet('danger', "Plat {$plate} sudah terdaftar."); header("Location: {$back}"); exit; }

    $db->prepare("INSERT INTO vehicles (customer_id,plate_number,brand,model,year,color,type,fuel_type,km_current,notes) VALUES (?,?,?,?,?,?,?,?,?,?)")
       ->execute([
           (int)$_POST['customer_id'],
           $plate,
           trim($_POST['brand']),
           trim($_POST['model']),
           (int)$_POST['year'],
           trim($_POST['color'] ?? ''),
           $_POST['type'],
           $_POST['fuel_type'],
           (int)($_POST['km_current'] ?? 0),
           trim($_POST['notes'] ?? '') ?: null,
       ]);
    flashSet('success', "Kendaraan {$plate} berhasil ditambahkan.");

} elseif ($action === 'edit') {
    $id    = (int)$_POST['id'];
    $plate = strtoupper(trim($_POST['plate_number'] ?? ''));
    $db->prepare("UPDATE vehicles SET plate_number=?,brand=?,model=?,year=?,color=?,type=?,fuel_type=?,km_current=?,notes=? WHERE id=?")
       ->execute([
           $plate,
           trim($_POST['brand']),
           trim($_POST['model']),
           (int)$_POST['year'],
           trim($_POST['color'] ?? ''),
           $_POST['type'],
           $_POST['fuel_type'],
           (int)($_POST['km_current'] ?? 0),
           trim($_POST['notes'] ?? '') ?: null,
           $id,
       ]);
    flashSet('success', "Data kendaraan {$plate} diperbarui.");
}

header("Location: {$back}");
exit;
