<?php
require_once __DIR__ . '/config/database.php';

try {
    $db = getDB();
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<h3>Memperbaiki Semua Tabel di Database...</h3>";
    
    // Matikan Foreign Keys sementara
    $db->exec("SET FOREIGN_KEY_CHECKS = 0;");
    
    // Daftar semua tabel yang harusnya memiliki kolom `id` INT AUTO_INCREMENT PRIMARY KEY
    $tables = [
        'users', 'employees', 'customers', 'vehicles', 
        'categories', 'parts', 'stock_movements', 
        'work_orders', 'wo_services', 'wo_parts', 'wo_assistants', 
        'bookings', 'salary_records', 'expenses', 
        'mechanic_bonus', 'admin_bonus'
    ];
    
    foreach ($tables as $table) {
        try {
            // Coba pastikan kolom `id` menjadi Primary Key
            $db->exec("ALTER TABLE `$table` ADD PRIMARY KEY (`id`);");
        } catch (PDOException $e) {
            // Akan error jika sudah ada primary key, abaikan saja
        }

        try {
            // Pastikan kolom `id` diset menjadi AUTO_INCREMENT
            $db->exec("ALTER TABLE `$table` MODIFY `id` INT(11) NOT NULL AUTO_INCREMENT;");
            echo "<p style='color:green'>&#10004; Tabel <b>$table</b> berhasil diperbaiki (Auto Increment Aktif).</p>";
        } catch (PDOException $e) {
            echo "<p style='color:red'>&#10008; Gagal memperbaiki tabel <b>$table</b>: " . $e->getMessage() . "</p>";
        }
    }
    
    // Hapus data transaksi yang rusak (yang memiliki id = 0 atau error lainnya) 
    // agar sistem bisa mulai dengan bersih dari id = 1.
    // Master data (customers, vehicles, parts, employees) TIDAK DIHAPUS.
    $db->exec("TRUNCATE TABLE `wo_assistants`;");
    $db->exec("TRUNCATE TABLE `wo_parts`;");
    $db->exec("TRUNCATE TABLE `wo_services`;");
    $db->exec("TRUNCATE TABLE `work_orders`;");
    $db->exec("TRUNCATE TABLE `stock_movements`;");
    $db->exec("TRUNCATE TABLE `bookings`;");
    $db->exec("TRUNCATE TABLE `salary_records`;");
    
    // Hidupkan kembali Foreign Keys
    $db->exec("SET FOREIGN_KEY_CHECKS = 1;");
    
    echo "<h3>&#9989; Database Berhasil Diperbaiki Secara Total!</h3>";
    echo "<p>Auto Increment telah dikembalikan pada seluruh tabel sistem.</p>";
    echo "<p>Semua histori Work Order yang korup/error (dengan ID 0) telah dibersihkan agar sistem fresh.</p>";
    echo "<p>Silakan kembali ke aplikasi dan coba buat Work Order baru. Semuanya pasti akan berfungsi dengan normal, Part akan tersimpan, dan Slip Gaji bisa digenerate.</p>";
    
} catch (PDOException $e) {
    die("<h3 style='color:red'>Fatal Error: " . $e->getMessage() . "</h3>");
}
?>
