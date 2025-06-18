<?php
require_once 'config/database.php';
require_once 'includes/session.php';
require_once 'includes/functions.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}

$conn = getDBConnection();
$message = '';
$error = '';

// Send a test notification to the current user
if (sendNotification(
    $_SESSION['user_id'],
    'Test Notification',
    'This is a test notification to verify the notification system is working.',
    'info'
)) {
    $message = 'Test notification sent successfully!';
} else {
    $error = 'Failed to send test notification.';
}

// Redirect back to the previous page
header('Location: ' . $_SERVER['HTTP_REFERER']);
exit();
?> 