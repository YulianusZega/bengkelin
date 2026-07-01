<?php
require_once __DIR__ . '/config/database.php';

try {
    $db = getDB();
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // 1. Matikan Foreign Keys
    $db->exec("SET FOREIGN_KEY_CHECKS = 0;");
    
    // 2. Kosongkan semua data transaksi & penggajian
    $tables = [
        'salary_records',
        'wo_assistants',
        'wo_parts',
        'wo_services',
        'work_orders',
        'bookings'
    ];
    
    foreach ($tables as $table) {
        $db->exec("TRUNCATE TABLE $table;");
    }
    
    // 3. Set Aturan Penggajian 60% Teknisi, 40% Owner
    $db->exec("INSERT INTO settings (setting_key, setting_value) VALUES ('service_mechanic_pct', '60') ON DUPLICATE KEY UPDATE setting_value = '60'");
    $db->exec("INSERT INTO settings (setting_key, setting_value) VALUES ('service_owner_pct', '40') ON DUPLICATE KEY UPDATE setting_value = '40'");
    
    // 4. Hidupkan Foreign Keys
    $db->exec("SET FOREIGN_KEY_CHECKS = 1;");
    
    echo "<h2 style='color:green'>✅ Semua data penggajian & transaksi telah dihapus!</h2>";
    echo "<p>Aturan pembagian jasa pelayanan (60% Teknisi, 40% Owner) telah diterapkan.</p>";

} catch (PDOException $e) {
    echo "<h2 style='color:red'>❌ Error: " . $e->getMessage() . "</h2>";
}
