<?php
require_once 'config/database.php';
require_once 'includes/session.php';

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$userId = $_SESSION['user_id'];
$data = json_decode(file_get_contents('php://input'), true);
$notificationId = $data['notification_id'] ?? null;

if (!$notificationId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Notification ID is required']);
    exit();
}

try {
    $conn = getDBConnection();
    
    // Delete the notification
    $stmt = $conn->prepare("DELETE FROM notifications WHERE id = ? AND user_id = ?");
    $stmt->execute([$notificationId, $userId]);
    
    // Get the new unread count
    $countStmt = $conn->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
    $countStmt->execute([$userId]);
    $unreadCount = $countStmt->fetchColumn();

    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => true, 'unreadCount' => $unreadCount]);
    } else {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Notification not found or not owned by user']);
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error']);
} 