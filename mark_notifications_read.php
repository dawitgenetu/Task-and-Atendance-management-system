<?php
require_once 'config/database.php';
require_once 'includes/functions.php';

// Check if user is logged in
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$userId = $_SESSION['user_id'];
$conn = getDBConnection();

try {
    // Update all unread notifications for the current user
    $stmt = $conn->prepare("
        UPDATE notifications 
        SET is_read = 1 
        WHERE user_id = ? AND is_read = 0
    ");
    $stmt->execute([$userId]);
    
    // Redirect back to the previous page
    $redirectUrl = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'dashboard.php';
    header("Location: " . $redirectUrl);
    exit();
} catch (Exception $e) {
    error_log("Error marking notifications as read: " . $e->getMessage());
    // Redirect back with error
    header("Location: " . $_SERVER['HTTP_REFERER'] . "?error=Failed to mark notifications as read");
    exit();
} 