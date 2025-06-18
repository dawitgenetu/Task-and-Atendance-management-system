<?php

function sendNotification($userId, $title, $message, $type = 'info') {
    try {
        $conn = getDBConnection();
        $stmt = $conn->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, ?)");
        return $stmt->execute([$userId, $title, $message, $type]);
    } catch (PDOException $e) {
        error_log("Error sending notification: " . $e->getMessage());
        return false;
    }
} 