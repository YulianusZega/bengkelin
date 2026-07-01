<?php
require_once __DIR__ . '/config/database.php';

// SCRIPT SEMENTARA UNTUK IMPORT DATA INVENTORI
try {
    $db = getDB();
    
    // Baca isi file SQL
    $sql = file_get_contents(__DIR__ . '/database/seed_inventory.sql');
    
    // Eksekusi query
    $db->exec($sql);
    
    echo "<h1>✅ Import Berhasil!</h1>";
    echo "<p>18 data inventori baru dari faktur SUMATRA JAYA telah berhasil dimasukkan ke database.</p>";
    echo "<ul>
            <li>Harga beli dihitung per-satuan (botol/pcs) berdasarkan total harga DUS.</li>
            <li>Harga jual dinaikkan 20% dari harga beli.</li>
            <li>Kategori barang telah disusun ulang.</li>
          </ul>";
    echo "<p><strong>PENTING:</strong> Segera hapus file ini demi keamanan!</p>";
    echo "<a href='" . BASE_URL . "/pages/inventory/index.php'>Lihat Inventori</a>";
    
} catch (PDOException $e) {
    echo "<h1>❌ Gagal!</h1>";
    echo "<p>Error: " . $e->getMessage() . "</p>";
}
?>
