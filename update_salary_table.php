<?php
require_once __DIR__ . '/config/database.php';
try {
    $db = getDB();
    $db->exec("ALTER TABLE salary_records ADD COLUMN period_start DATE NULL, ADD COLUMN period_end DATE NULL;");
    echo "✅ Success";
} catch(Exception $e) {
    echo "Error: " . $e->getMessage();
}
