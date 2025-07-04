<?php
require_once 'config/database.php';
require_once 'includes/session.php';

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

try {
    $conn = getDBConnection();
    
    // Delete all notifications for the current user
    $stmt = $conn->prepare("DELETE FROM notifications WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    
    // Since all notifications are deleted, unreadCount is 0
    $deletedCount = $stmt->rowCount();
    
    echo json_encode([
        'success' => true, 
        'count' => $deletedCount,
        'unreadCount' => 0
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error']);
} 