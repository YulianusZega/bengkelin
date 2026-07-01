<?php
require_once __DIR__ . '/config/database.php';

try {
    $db = getDB();
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<h3>Debugging Table Structures...</h3><pre>";
    
    $tables = ['parts', 'wo_assistants', 'salary_records', 'work_orders', 'wo_parts'];
    
    foreach ($tables as $t) {
        echo "<b>Table: $t</b>\n";
        try {
            $stmt = $db->query("SHOW CREATE TABLE `$t`");
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            echo htmlspecialchars($row['Create Table'] ?? $row['Create View']) . "\n\n";
            
            // Check data for ID 0
            $count0 = $db->query("SELECT COUNT(*) FROM `$t` WHERE id = 0")->fetchColumn();
            $countAll = $db->query("SELECT COUNT(*) FROM `$t`")->fetchColumn();
            echo "Total Rows: $countAll | Rows with ID=0: $count0\n";
            
        } catch (Exception $e) {
            echo "Error: " . $e->getMessage() . "\n";
        }
        echo "--------------------------------------------------------\n\n";
    }
    
    echo "</pre>";
    
} catch (Exception $e) {
    die("Fatal: " . $e->getMessage());
}
?>
