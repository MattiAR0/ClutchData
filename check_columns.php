<?php
require 'vendor/autoload.php';

use App\Classes\Database;

try {
    $db = Database::getInstance()->getConnection();
    $stmt = $db->query("DESCRIBE matches");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "Columns: " . implode(", ", $columns) . "\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
