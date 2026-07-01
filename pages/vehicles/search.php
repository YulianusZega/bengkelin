<?php
require_once __DIR__ . '/../../includes/auth.php';
requireLogin();
header('Content-Type: application/json');
$customerId = (int)($_GET['customer_id'] ?? 0);
if (!$customerId) { echo json_encode([]); exit; }
$db  = getDB();
$s   = $db->prepare("SELECT id,plate_number,brand,model,year,color,type,km_current FROM vehicles WHERE customer_id=? ORDER BY created_at DESC");
$s->execute([$customerId]);
echo json_encode($s->fetchAll());
