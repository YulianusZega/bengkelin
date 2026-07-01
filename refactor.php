<?php
$files = [
    __DIR__ . '/pages/reports/export.php',
    __DIR__ . '/pages/reports/mechanic.php',
    __DIR__ . '/pages/services/action.php',
    __DIR__ . '/pages/services/index.php',
    __DIR__ . '/pages/work-orders/create.php',
    __DIR__ . '/pages/work-orders/edit.php',
    __DIR__ . '/pages/work-orders/index.php',
    __DIR__ . '/pages/bookings/index.php',
    __DIR__ . '/pages/bookings/action.php',
    __DIR__ . '/pages/customers/index.php',
    __DIR__ . '/pages/customers/action.php',
    __DIR__ . '/pages/vehicles/index.php',
    __DIR__ . '/pages/vehicles/action.php',
    __DIR__ . '/pages/inventory/index.php',
    __DIR__ . '/pages/inventory/action.php',
];

foreach ($files as $file) {
    if (file_exists($file)) {
        $content = file_get_contents($file);
        
        // Database values & roles
        $content = str_replace("'kabeng'", "'senior_teknisi'", $content);
        $content = str_replace("'mekanik'", "'junior_teknisi'", $content);
        $content = str_replace('"kabeng"', '"senior_teknisi"', $content);
        $content = str_replace('"mekanik"', '"junior_teknisi"', $content);
        
        // Variables
        $content = str_replace('kabeng_share_pct', 'senior_share_pct', $content);
        $content = str_replace('kabeng_min_guarantee', 'senior_min_guarantee', $content);
        $content = str_replace('$kabengPct', '$seniorPct', $content);
        $content = str_replace('$kabengMin', '$seniorMin', $content);
        $content = str_replace('$kabengCount', '$seniorCount', $content);
        $content = str_replace('$kabengList', '$seniorList', $content);
        $content = str_replace('kabengBonus', 'seniorBonus', $content);
        $content = str_replace('KABENG_PCT', 'SENIOR_PCT', $content);
        
        // UI Text
        $content = str_replace('Kepala Bengkel', 'Senior Teknisi', $content);
        $content = str_replace('Kabeng', 'Senior Teknisi', $content);
        $content = str_replace('kabeng', 'senior', $content);
        $content = str_replace('Mekanik', 'Teknisi', $content); // Be careful with general "Teknisi"
        $content = str_replace('mekanik', 'teknisi', $content);
        
        file_put_contents($file, $content);
        echo "Updated $file\n";
    }
}
echo "Selesai!";
