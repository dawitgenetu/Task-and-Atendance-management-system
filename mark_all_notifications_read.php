<?php
require_once 'config/database.php';
require_once 'includes/session.php';

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$userId = $_SESSION['user_id'];

try {
    $conn = getDBConnection();
    
    // Mark all notifications as read
    $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$userId]);
    
    // The new unread count will be 0
    echo json_encode(['success' => true, 'unreadCount' => 0]);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error']);
} 