<?php
require_once 'config/database.php';

try {
    $conn = getDBConnection();
    
    // Add new columns
    $conn->exec("ALTER TABLE attendance 
        ADD COLUMN photo_path_front VARCHAR(255) AFTER photo_path,
        ADD COLUMN photo_path_left VARCHAR(255) AFTER photo_path_front,
        ADD COLUMN photo_path_right VARCHAR(255) AFTER photo_path_left");
    
    // Migrate existing data
    $conn->exec("UPDATE attendance SET photo_path_front = photo_path WHERE photo_path IS NOT NULL");
    
    // Drop old column
    $conn->exec("ALTER TABLE attendance DROP COLUMN photo_path");
    
    echo "Database updated successfully!";
} catch (PDOException $e) {
    echo "Error updating database: " . $e->getMessage();
}
?> 