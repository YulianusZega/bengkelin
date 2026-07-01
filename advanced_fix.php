<?php
require_once __DIR__ . '/config/database.php';

try {
    $db = getDB();
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<h3>Memperbaiki Tabel Spesifik yang Gagal...</h3>";
    
    // Matikan Foreign Keys sementara
    $db->exec("SET FOREIGN_KEY_CHECKS = 0;");
    
    $tablesToFix = ['parts', 'wo_assistants', 'salary_records'];
    
    foreach ($tablesToFix as $table) {
        echo "<h4>Memperbaiki $table</h4>";
        try {
            // 1. Assign sequential IDs to fix any "id = 0" duplicate issues
            $db->exec("SET @n = 0;");
            $db->exec("UPDATE `$table` SET `id` = (@n := @n + 1);");
            echo "-> ID diurutkan ulang berhasil.<br>";
            
            // 2. Drop existing primary key if any (might fail if none exists, which is fine)
            try {
                $db->exec("ALTER TABLE `$table` DROP PRIMARY KEY;");
                echo "-> Primary Key lama dihapus.<br>";
            } catch (Exception $e) {}
            
            // 3. Add Primary Key to id
            try {
                $db->exec("ALTER TABLE `$table` ADD PRIMARY KEY (`id`);");
                echo "-> Primary Key baru ditambahkan.<br>";
            } catch (Exception $e) {}
            
            // 4. Set Auto Increment
            $db->exec("ALTER TABLE `$table` MODIFY `id` INT(11) NOT NULL AUTO_INCREMENT;");
            echo "<span style='color:green'>&#10004; $table berhasil di-AUTO_INCREMENT!</span><br>";
            
        } catch (Exception $e) {
            echo "<span style='color:red'>&#10008; Error pada $table: " . $e->getMessage() . "</span><br>";
        }
    }
    
    // Hidupkan kembali Foreign Keys
    $db->exec("SET FOREIGN_KEY_CHECKS = 1;");
    
    echo "<h3>&#9989; Perbaikan Khusus Selesai!</h3>";
    echo "<p>Tabel parts, wo_assistants, dan salary_records kini seharusnya sudah diperbaiki. Silakan coba buat Work Order lagi.</p>";
    
} catch (PDOException $e) {
    die("<h3 style='color:red'>Fatal Error: " . $e->getMessage() . "</h3>");
}
?>
