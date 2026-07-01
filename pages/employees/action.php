<?php
require_once __DIR__ . '/../../includes/auth.php';
requireLogin();
requireRole('owner','admin');

$db     = getDB();
$action = $_POST['action'] ?? '';
$isJson = in_array($action, ['toggle_status']);
if ($isJson) header('Content-Type: application/json');

if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
    if ($isJson) { echo json_encode(['success'=>false]); exit; }
    flashSet('danger','Token tidak valid'); header('Location: '.BASE_URL.'/pages/employees/index.php'); exit;
}

if ($action === 'add') {
    $empId = generateCode('EMP', 'employees', 'employee_id');
    // Create user account if email provided
    $userId = null;
    if (!empty($_POST['email']) && !empty($_POST['password'])) {
        $hash = password_hash($_POST['password'], PASSWORD_DEFAULT);
        try {
            $db->prepare("INSERT INTO users (name,email,password,role,phone,status) VALUES (?,?,?,?,?,'active')")
               ->execute([$_POST['name'], $_POST['email'], $hash, $_POST['position'], $_POST['phone']]);
            $userId = $db->lastInsertId();
        } catch (Exception $e) { /* email already exists */ }
    }
    $db->prepare("INSERT INTO employees (user_id,employee_id,name,phone,position,specialization,join_date,salary,commission_rate,status) VALUES (?,?,?,?,?,?,?,?,?,?)")
       ->execute([$userId, $empId, $_POST['name'], $_POST['phone']??'', $_POST['position'], $_POST['specialization']??'', $_POST['join_date']??null, (float)$_POST['salary'], (float)$_POST['commission_rate'], $_POST['status']]);
    flashSet('success', 'Karyawan berhasil ditambahkan.');

} elseif ($action === 'edit') {
    $id = (int)$_POST['id'];
    $db->prepare("UPDATE employees SET name=?,phone=?,position=?,specialization=?,join_date=?,salary=?,commission_rate=?,status=? WHERE id=?")
       ->execute([$_POST['name'],$_POST['phone']??'',$_POST['position'],$_POST['specialization']??'',$_POST['join_date']??null,(float)$_POST['salary'],(float)$_POST['commission_rate'],$_POST['status'],$id]);
    // Update user if exists
    if (!empty($_POST['email'])) {
        $emp = $db->prepare("SELECT user_id FROM employees WHERE id=?"); $emp->execute([$id]); $emp=$emp->fetch();
        if ($emp['user_id']) {
            $sql = "UPDATE users SET name=?,role=?,phone=? WHERE id=?";
            $p   = [$_POST['name'],$_POST['position'],$_POST['phone'],$emp['user_id']];
            if (!empty($_POST['password'])) { $sql = "UPDATE users SET name=?,role=?,phone=?,password=? WHERE id=?"; $p=[$_POST['name'],$_POST['position'],$_POST['phone'],password_hash($_POST['password'],PASSWORD_DEFAULT),$emp['user_id']]; }
            $db->prepare($sql)->execute($p);
        }
    }
    flashSet('success','Data karyawan diperbarui.');

} elseif ($action === 'toggle_status') {
    $id = (int)$_POST['id']; $status = $_POST['status'];
    $db->prepare("UPDATE employees SET status=? WHERE id=?")->execute([$status,$id]);
    echo json_encode(['success'=>true]); exit;
}

header('Location: '.BASE_URL.'/pages/employees/index.php');
exit;
