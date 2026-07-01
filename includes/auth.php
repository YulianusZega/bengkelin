<?php
// ============================================================
// BENGKELIN - Session & Auth Helpers
// ============================================================
require_once __DIR__ . '/../config/database.php';

session_start();

function isLoggedIn(): bool {
    return isset($_SESSION['user_id']);
}

function requireLogin(): void {
    if (!isLoggedIn()) {
        header('Location: ' . BASE_URL . '/login.php');
        exit;
    }
}

function currentUser(): ?array {
    return $_SESSION['user'] ?? null;
}

function hasRole(string ...$roles): bool {
    $user = currentUser();
    return $user && in_array($user['role'], $roles);
}

function requireRole(string ...$roles): void {
    if (!hasRole(...$roles)) {
        http_response_code(403);
        echo '<p style="padding:40px;font-family:sans-serif">Akses ditolak. Anda tidak memiliki izin.</p>';
        exit;
    }
}

function csrf(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrf(string $token): bool {
    return hash_equals($_SESSION['csrf_token'] ?? '', $token);
}

function flashSet(string $type, string $message): void {
    $_SESSION['flash'] = compact('type', 'message');
}

function flashGet(): ?array {
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $flash;
}

function formatRupiah(float $amount): string {
    return 'Rp ' . number_format($amount, 0, ',', '.');
}

function generateCode(string $prefix, string $table, string $column): string {
    $db = getDB();
    $stmt = $db->query("SELECT MAX({$column}) as last FROM `{$table}`");
    $row = $stmt->fetch();
    $last = $row['last'] ?? null;
    if ($last) {
        $num = (int) substr($last, strlen($prefix) + 1) + 1;
    } else {
        $num = 1;
    }
    return $prefix . '-' . str_pad($num, 4, '0', STR_PAD_LEFT);
}

function generateWoNumber(): string {
    $db = getDB();
    $today = date('Ymd');
    $stmt = $db->query("SELECT COUNT(*) as cnt FROM `work_orders` WHERE DATE(created_at) = CURDATE()");
    $row = $stmt->fetch();
    $seq = ($row['cnt'] ?? 0) + 1;
    return 'WO-' . $today . '-' . str_pad($seq, 3, '0', STR_PAD_LEFT);
}

function paginate(int $total, int $perPage, int $current): array {
    $totalPages = max(1, (int) ceil($total / $perPage));
    $current = max(1, min($current, $totalPages));
    $offset = ($current - 1) * $perPage;
    return ['total' => $total, 'per_page' => $perPage, 'current' => $current, 'total_pages' => $totalPages, 'offset' => $offset];
}

function statusLabel(string $status): string {
    $map = [
        'waiting' => 'Menunggu', 'inspection' => 'Inspeksi', 'approved' => 'Disetujui',
        'in_progress' => 'Dikerjakan', 'qc' => 'QC', 'done' => 'Selesai',
        'delivered' => 'Diserahkan', 'cancelled' => 'Dibatalkan',
    ];
    return $map[$status] ?? ucfirst(str_replace('_', ' ', $status));
}

function paymentLabel(string $status): string {
    $map = ['unpaid' => 'Belum Bayar', 'partial' => 'Sebagian', 'paid' => 'Lunas'];
    return $map[$status] ?? ucfirst($status);
}
