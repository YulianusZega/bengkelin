<?php
// ============================================================
// BENGKELIN - Stock Movement Action Handler
// ============================================================
require_once __DIR__ . '/../../includes/auth.php';
requireLogin();
requireRole('owner','admin','kabeng');

$db = getDB();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verifyCsrf($_POST['csrf_token'] ?? '')) {
    flashSet('danger', 'Token tidak valid.');
    header('Location: ' . BASE_URL . '/pages/inventory/movements.php'); exit;
}

$action = $_POST['action'] ?? '';

if ($action === 'add_movement') {
    $partId   = (int)($_POST['part_id'] ?? 0);
    $type     = $_POST['type'] ?? 'in';
    $quantity = abs((int)($_POST['quantity'] ?? 0));
    $notes    = trim($_POST['notes'] ?? '');
    $user     = currentUser();

    if (!$partId || !$quantity) {
        flashSet('danger', 'Barang dan jumlah harus diisi.');
        header('Location: ' . BASE_URL . '/pages/inventory/movements.php'); exit;
    }

    // Validate type
    if (!in_array($type, ['in', 'out', 'adjustment'])) {
        flashSet('danger', 'Tipe pergerakan tidak valid.');
        header('Location: ' . BASE_URL . '/pages/inventory/movements.php'); exit;
    }

    // Check if part exists
    $part = $db->prepare("SELECT * FROM parts WHERE id = ?");
    $part->execute([$partId]);
    $part = $part->fetch();
    if (!$part) {
        flashSet('danger', 'Barang tidak ditemukan.');
        header('Location: ' . BASE_URL . '/pages/inventory/movements.php'); exit;
    }

    // For 'out' type, check available stock
    if ($type === 'out' && $part['stock'] < $quantity) {
        flashSet('danger', "Stok tidak cukup. Stok tersedia: {$part['stock']} {$part['unit']}.");
        header('Location: ' . BASE_URL . '/pages/inventory/movements.php'); exit;
    }

    $db->beginTransaction();
    try {
        // Insert movement
        $stmt = $db->prepare("
            INSERT INTO stock_movements (part_id, type, quantity, reference_type, notes, created_by)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $refType = $type === 'in' ? 'purchase' : ($type === 'adjustment' ? 'adjustment' : 'adjustment');
        $stmt->execute([$partId, $type, $quantity, $refType, $notes, $user['id']]);

        // Update stock
        if ($type === 'in') {
            $db->prepare("UPDATE parts SET stock = stock + ? WHERE id = ?")->execute([$quantity, $partId]);
        } elseif ($type === 'out') {
            $db->prepare("UPDATE parts SET stock = stock - ? WHERE id = ?")->execute([$quantity, $partId]);
        } else {
            // Adjustment sets to specific quantity
            $db->prepare("UPDATE parts SET stock = ? WHERE id = ?")->execute([$quantity, $partId]);
        }

        $db->commit();
        flashSet('success', "Pergerakan stok berhasil dicatat: {$quantity} {$part['unit']} {$part['name']}");
    } catch (Exception $e) {
        $db->rollBack();
        flashSet('danger', 'Gagal mencatat pergerakan stok: ' . $e->getMessage());
    }
}

header('Location: ' . BASE_URL . '/pages/inventory/movements.php');
exit;
