<?php
require_once __DIR__ . '/config/database.php';

try {
    $db = getDB();
    
    // Disable foreign keys
    $db->exec("SET FOREIGN_KEY_CHECKS = 0;");

    // 4. INSERT Data
    $db->exec("TRUNCATE TABLE users;");
    $db->exec("TRUNCATE TABLE employees;");
    $users = [
        ['name' => 'Yulianus Zega, S.Kom', 'email' => 'yulianus@bengkelin.com', 'role' => 'owner',          'position' => 'owner',          'pass' => 'bengkelin2026'],
        ['name' => 'Ryan Perwira Harefa',  'email' => 'ian@bengkelin.com',      'role' => 'senior_teknisi', 'position' => 'senior_teknisi', 'pass' => 'bengkelin2026'],
        ['name' => 'Defelinu',             'email' => 'defelinu@bengkelin.com', 'role' => 'senior_teknisi', 'position' => 'senior_teknisi', 'pass' => 'bengkelin2026'],
        ['name' => 'Devan',                'email' => 'devan@bengkelin.com',    'role' => 'junior_teknisi', 'position' => 'junior_teknisi', 'pass' => 'bengkelin2026'],
        ['name' => 'Valintinus',           'email' => 'valentinus@bengkelin.com', 'role' => 'junior_teknisi', 'position' => 'junior_teknisi', 'pass' => 'bengkelin2026'],
        ['name' => 'Harvest',              'email' => 'harvest@bengkelin.com',  'role' => 'junior_teknisi', 'position' => 'junior_teknisi', 'pass' => 'bengkelin2026'],
    ];

    $insUser = $db->prepare("INSERT INTO users (name, email, password, role, status) VALUES (?, ?, ?, ?, 'active')");
    $insEmp  = $db->prepare("INSERT INTO employees (user_id, employee_id, name, position, join_date, status) VALUES (?, ?, ?, ?, CURDATE(), 'active')");

    $i = 1;
    foreach ($users as $u) {
        $hash = password_hash($u['pass'], PASSWORD_DEFAULT);
        $insUser->execute([$u['name'], $u['email'], $hash, $u['role']]);
        $userId = $db->lastInsertId();
        
        $empId = 'EMP-' . str_pad($i, 3, '0', STR_PAD_LEFT);
        $insEmp->execute([$userId, $empId, $u['name'], $u['position']]);
        $i++;
    }

    $db->exec("SET FOREIGN_KEY_CHECKS = 1;");

    echo "✅ Database modifikasi dan setup 6 user berhasil!";

} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage();
}
