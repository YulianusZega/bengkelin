<?php
// ============================================================
// BENGKELIN - Services Action Handler
// ============================================================
require_once __DIR__ . '/../../includes/auth.php';
requireLogin();
requireRole('owner','admin','kabeng');

$db     = getDB();
$action = $_POST['action'] ?? '';
$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
    if ($isAjax) { echo json_encode(['success'=>false,'message'=>'Token tidak valid']); exit; }
    flashSet('danger', 'Token tidak valid.');
    header('Location: ' . BASE_URL . '/pages/services/index.php'); exit;
}

// ── ADD CATEGORY ──
if ($action === 'add_category') {
    $name = trim($_POST['name'] ?? '');
    $desc = trim($_POST['description'] ?? '');
    $icon = trim($_POST['icon'] ?? 'fa-wrench');
    if (!$name) {
        flashSet('danger', 'Nama kategori wajib diisi.');
    } else {
        $db->prepare("INSERT INTO service_categories (name, description, icon) VALUES (?,?,?)")
           ->execute([$name, $desc, $icon]);
        flashSet('success', "Kategori \"{$name}\" berhasil ditambahkan.");
    }
    header('Location: ' . BASE_URL . '/pages/services/index.php'); exit;
}

// ── EDIT CATEGORY ──
if ($action === 'edit_category') {
    $id   = (int)($_POST['id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $desc = trim($_POST['description'] ?? '');
    $icon = trim($_POST['icon'] ?? 'fa-wrench');
    if ($id && $name) {
        $db->prepare("UPDATE service_categories SET name=?, description=?, icon=? WHERE id=?")
           ->execute([$name, $desc, $icon, $id]);
        flashSet('success', "Kategori berhasil diperbarui.");
    }
    header('Location: ' . BASE_URL . '/pages/services/index.php'); exit;
}

// ── DELETE CATEGORY ──
if ($action === 'delete_category') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id) {
        $db->prepare("DELETE FROM service_categories WHERE id=?")->execute([$id]);
    }
    if ($isAjax) { echo json_encode(['success'=>true]); exit; }
    flashSet('success', 'Kategori dihapus.');
    header('Location: ' . BASE_URL . '/pages/services/index.php'); exit;
}

header('Location: ' . BASE_URL . '/pages/services/index.php');
exit;
