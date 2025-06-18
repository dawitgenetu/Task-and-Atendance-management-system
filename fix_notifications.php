<?php
require_once 'config/database.php';

try {
    $conn = getDBConnection();
    
    // Check if notifications table exists
    $stmt = $conn->query("SHOW TABLES LIKE 'notifications'");
    if ($stmt->rowCount() === 0) {
        // Create notifications table if it doesn't exist
        $conn->exec("
            CREATE TABLE notifications (
                id INT PRIMARY KEY AUTO_INCREMENT,
                user_id INT NOT NULL,
                title VARCHAR(255) NOT NULL,
                message TEXT NOT NULL,
                type ENUM('info', 'success', 'warning', 'error') DEFAULT 'info',
                is_read BOOLEAN DEFAULT FALSE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            )
        ");
        echo "Notifications table created successfully.\n";
    } else {
        // Check table structure
        $stmt = $conn->query("DESCRIBE notifications");
        $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        // Add missing columns if needed
        if (!in_array('title', $columns)) {
            $conn->exec("ALTER TABLE notifications ADD COLUMN title VARCHAR(255) NOT NULL AFTER user_id");
            echo "Added 'title' column to notifications table.\n";
        }
        
        if (!in_array('type', $columns)) {
            $conn->exec("ALTER TABLE notifications ADD COLUMN type ENUM('info', 'success', 'warning', 'error') DEFAULT 'info' AFTER message");
            echo "Added 'type' column to notifications table.\n";
        }
        
        if (!in_array('is_read', $columns)) {
            $conn->exec("ALTER TABLE notifications ADD COLUMN is_read BOOLEAN DEFAULT FALSE AFTER type");
            echo "Added 'is_read' column to notifications table.\n";
        }
        
        echo "Notifications table structure is up to date.\n";
    }
    
    // Test notification
    $testUserId = 2; // Manager's ID
    $stmt = $conn->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, ?)");
    $stmt->execute([
        $testUserId,
        'Test Notification',
        'This is a test notification to verify the system is working.',
        'info'
    ]);
    echo "Test notification sent successfully.\n";
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?> 