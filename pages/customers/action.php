<?php
require_once __DIR__ . '/../../includes/auth.php';
requireLogin();
header('Content-Type: application/json');

$db     = getDB();
$action = $_POST['action'] ?? '';

if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
    echo json_encode(['success' => false, 'message' => 'Token tidak valid']);
    exit;
}

if ($action === 'add') {
    $name    = trim($_POST['name'] ?? '');
    $phone   = trim($_POST['phone'] ?? '');
    $email   = trim($_POST['email'] ?? '') ?: null;
    $address = trim($_POST['address'] ?? '') ?: null;
    $segment = $_POST['segment'] ?? 'new';
    $notes   = trim($_POST['notes'] ?? '') ?: null;

    if (!$name || !$phone) {
        echo json_encode(['success' => false, 'message' => 'Nama dan telepon wajib diisi']);
        exit;
    }
    $code = generateCode('CST', 'customers', 'customer_code');
    $stmt = $db->prepare("INSERT INTO customers (customer_code,name,phone,email,address,segment,notes) VALUES (?,?,?,?,?,?,?)");
    $stmt->execute([$code, $name, $phone, $email, $address, $segment, $notes]);
    flashSet('success', "Pelanggan {$name} berhasil ditambahkan.");

} elseif ($action === 'edit') {
    $id      = (int)($_POST['id'] ?? 0);
    $name    = trim($_POST['name'] ?? '');
    $phone   = trim($_POST['phone'] ?? '');
    $email   = trim($_POST['email'] ?? '') ?: null;
    $address = trim($_POST['address'] ?? '') ?: null;
    $segment = $_POST['segment'] ?? 'new';
    $notes   = trim($_POST['notes'] ?? '') ?: null;

    $stmt = $db->prepare("UPDATE customers SET name=?,phone=?,email=?,address=?,segment=?,notes=?,updated_at=NOW() WHERE id=?");
    $stmt->execute([$name, $phone, $email, $address, $segment, $notes, $id]);
    flashSet('success', "Data pelanggan berhasil diperbarui.");

} elseif ($action === 'delete') {
    $id = (int)($_POST['id'] ?? 0);
    // Check no work orders
    $cnt = $db->prepare("SELECT COUNT(*) FROM work_orders WHERE customer_id=?");
    $cnt->execute([$id]);
    if ($cnt->fetchColumn() > 0) {
        echo json_encode(['success' => false, 'message' => 'Pelanggan memiliki riwayat work order, tidak bisa dihapus']);
        exit;
    }
    $db->prepare("DELETE FROM customers WHERE id=?")->execute([$id]);
    echo json_encode(['success' => true]);
    exit;
}

// Default: redirect back
header('Location: ' . BASE_URL . '/pages/customers/index.php');
exit;
