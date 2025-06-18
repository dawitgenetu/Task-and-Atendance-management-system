<?php
require_once 'config/database.php';

try {
    $conn = getDBConnection();
    
   
    $adminPassword = password_hash('123', PASSWORD_BCRYPT);
    $stmt = $conn->prepare("UPDATE users SET password = ? WHERE username = 'admin'");
    $stmt->execute([$adminPassword]);
    
    
    $managerPassword = password_hash('123', PASSWORD_BCRYPT);
    $stmt = $conn->prepare("UPDATE users SET password = ? WHERE username = 'manager'");
    $stmt->execute([$managerPassword]);
    
    
    $employeePassword = password_hash('123', PASSWORD_BCRYPT);
    $stmt = $conn->prepare("UPDATE users SET password = ? WHERE username = 'employee'");
    $stmt->execute([$employeePassword]);
    
    echo "All passwords have been reset successfully to '123'";
} catch (Exception $e) {
    echo "Error resetting passwords: " . $e->getMessage();
}
?> 