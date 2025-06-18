<?php
require_once 'config/database.php';
require_once 'functions.php';

function checkAndCancelPendingTasks() {
    $conn = getDBConnection();
    
    try {
        // Find tasks that have been pending for more than 3 days
        $stmt = $conn->prepare("
            SELECT t.*, 
                   CONCAT(u.first_name, ' ', u.last_name) as employee_name,
                   CONCAT(m.first_name, ' ', m.last_name) as manager_name
            FROM tasks t
            JOIN users u ON t.assigned_to = u.id
            JOIN users m ON t.assigned_by = m.id
            WHERE t.status = 'pending'
            AND t.created_at <= DATE_SUB(NOW(), INTERVAL 3 DAY)
        ");
        $stmt->execute();
        $pendingTasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($pendingTasks as $task) {
            // Update task status to cancelled
            $updateStmt = $conn->prepare("
                UPDATE tasks 
                SET status = 'cancelled' 
                WHERE id = ?
            ");
            $updateStmt->execute([$task['id']]);
            
            // Send notification to manager
            $notificationTitle = "Task Automatically Cancelled";
            $notificationMessage = sprintf(
                "Task #%s '%s' assigned to %s has been automatically cancelled as it remained pending for more than 3 days.",
                $task['task_number'],
                $task['title'],
                $task['employee_name']
            );
            
            sendNotification(
                $task['assigned_by'],
                $notificationTitle,
                $notificationMessage,
                'warning'
            );
        }
        
        return true;
    } catch (Exception $e) {
        error_log("Error in auto-cancelling tasks: " . $e->getMessage());
        return false;
    }
} 