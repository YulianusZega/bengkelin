<?php
require_once __DIR__ . '/../../includes/auth.php';
requireLogin();
header('Content-Type: application/json');
$q  = trim($_GET['q'] ?? '');
if (strlen($q) < 2) { echo json_encode([]); exit; }
$db  = getDB();
$s   = $db->prepare("SELECT id,customer_code,name,phone,email FROM customers WHERE name LIKE ? OR phone LIKE ? OR customer_code LIKE ? LIMIT 8");
$pct = "%{$q}%";
$s->execute([$pct,$pct,$pct]);
echo json_encode($s->fetchAll());
