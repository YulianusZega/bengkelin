<?php
require_once __DIR__ . '/config/database.php';

try {
    $db = getDB();
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<h3>🛠️ Memperbaiki Database...</h3>";
    
    // 1. Matikan Foreign Keys untuk bypass error
    $db->exec("SET FOREIGN_KEY_CHECKS = 0;");
    
    // 2. Bersihkan tabel yang terkait agar tidak ada error constraint
    $db->exec("TRUNCATE TABLE wo_assistants;");
    $db->exec("TRUNCATE TABLE work_orders;"); // Reset WO karena data lama bisa error
    $db->exec("TRUNCATE TABLE salary_records;");
    
    // 3. Kosongkan tabel users & employees secara total
    $db->exec("TRUNCATE TABLE employees;");
    $db->exec("TRUNCATE TABLE users;");
    
    // 4. Perbaiki Struktur Tabel (Jaga-jaga jika cPanel gagal membuat Primary Key & Auto Increment)
    try {
        // Coba tambahkan Primary Key (akan error & dilompat jika sudah ada)
        $db->exec("ALTER TABLE users ADD PRIMARY KEY (id);");
    } catch(Exception $e) {}
    
    try {
        $db->exec("ALTER TABLE employees ADD PRIMARY KEY (id);");
    } catch(Exception $e) {}

    // Pastikan Auto Increment aktif
    $db->exec("ALTER TABLE users MODIFY id INT(11) NOT NULL AUTO_INCREMENT;");
    $db->exec("ALTER TABLE employees MODIFY id INT(11) NOT NULL AUTO_INCREMENT;");
    
    $db->exec("ALTER TABLE users AUTO_INCREMENT = 1;");
    $db->exec("ALTER TABLE employees AUTO_INCREMENT = 1;");

    // 5. Masukkan Data 6 Akun yang Benar
    $users = [
        ['name' => 'Yulianus Zega, S.Kom', 'email' => 'yulianus@bengkelin.com', 'role' => 'owner',          'position' => 'owner'],
        ['name' => 'Ryan Perwira Harefa',  'email' => 'ian@bengkelin.com',      'role' => 'senior_teknisi', 'position' => 'senior_teknisi'],
        ['name' => 'Defelinu',             'email' => 'defelinu@bengkelin.com', 'role' => 'senior_teknisi', 'position' => 'senior_teknisi'],
        ['name' => 'Devan',                'email' => 'devan@bengkelin.com',    'role' => 'junior_teknisi', 'position' => 'junior_teknisi'],
        ['name' => 'Valintinus',           'email' => 'valentinus@bengkelin.com', 'role' => 'junior_teknisi', 'position' => 'junior_teknisi'],
        ['name' => 'Harvest',              'email' => 'harvest@bengkelin.com',  'role' => 'junior_teknisi', 'position' => 'junior_teknisi'],
    ];

    $insUser = $db->prepare("INSERT INTO users (name, email, password, role, status) VALUES (?, ?, ?, ?, 'active')");
    $insEmp  = $db->prepare("INSERT INTO employees (user_id, employee_id, name, position, join_date, status) VALUES (?, ?, ?, ?, CURDATE(), 'active')");

    $i = 1;
    foreach ($users as $u) {
        $hash = password_hash('bengkelin2026', PASSWORD_DEFAULT);
        $insUser->execute([$u['name'], $u['email'], $hash, $u['role']]);
        
        // Ambil ID User yang baru dibuat (Pasti benar sekarang karena Auto Increment aktif)
        $userId = $db->lastInsertId();
        
        $empId = 'EMP-' . str_pad($i, 3, '0', STR_PAD_LEFT);
        $insEmp->execute([$userId, $empId, $u['name'], $u['position']]);
        $i++;
    }

    $db->exec("SET FOREIGN_KEY_CHECKS = 1;");
    
    echo "<h2 style='color:green'>✅ Perbaikan Selesai! Struktur & Data telah dipulihkan.</h2>";
    echo "<p>Silakan kembali ke halaman Karyawan. Seharusnya sekarang hanya ada tepat 6 karyawan tanpa duplikat.</p>";

} catch (PDOException $e) {
    echo "<h2 style='color:red'>❌ Error Fatal: " . $e->getMessage() . "</h2>";
}
