<?php
require_once __DIR__ . '/config/database.php';

// SCRIPT SEMENTARA UNTUK RESET DATABASE LIVE
try {
    $db = getDB();
    
    // Matikan Foreign Key Check sementara
    $db->exec("SET FOREIGN_KEY_CHECKS = 0;");
    
    // Daftar tabel yang di-truncate
    $tables = [
        'bookings',
        'customers',
        'salary_records',
        'stock_movements',
        'vehicles',
        'wa_logs',
        'wo_assistants',
        'wo_parts',
        'wo_services',
        'work_orders'
    ];
    
    foreach ($tables as $table) {
        $db->exec("TRUNCATE TABLE `$table`");
    }
    
    // Nyalakan kembali Foreign Key Check
    $db->exec("SET FOREIGN_KEY_CHECKS = 1;");
    
    echo "<h1>✅ Berhasil!</h1>";
    echo "<p>Database LIVE di bengkelin.cloud telah berhasil di-reset untuk simulasi.</p>";
    echo "<p>Data transaksi, pelanggan, dan kendaraan sudah dikosongkan. Data master (Karyawan, Layanan, Sparepart, User) tetap aman.</p>";
    echo "<p><strong>PENTING:</strong> Segera hapus file ini demi keamanan!</p>";
    echo "<a href='" . BASE_URL . "/dashboard.php'>Kembali ke Dashboard</a>";
    
} catch (PDOException $e) {
    echo "<h1>❌ Gagal!</h1>";
    echo "<p>Error: " . $e->getMessage() . "</p>";
}
?>
