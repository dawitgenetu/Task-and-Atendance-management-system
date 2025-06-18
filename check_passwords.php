<?php
require_once 'config/database.php';

try {
    $conn = getDBConnection();
    
    // Get all users
    $stmt = $conn->query("SELECT username, password FROM users");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h2>Password Verification Results:</h2>";
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Username</th><th>Stored Hash</th><th>Verification</th></tr>";
    
    foreach ($users as $user) {
        $verify = password_verify('123', $user['password']);
        echo "<tr>";
        echo "<td>" . htmlspecialchars($user['username']) . "</td>";
        echo "<td>" . htmlspecialchars($user['password']) . "</td>";
        echo "<td>" . ($verify ? "✅ Password '123' matches" : "❌ Password '123' does not match") . "</td>";
        echo "</tr>";
    }
    
    echo "</table>";
    
} catch (Exception $e) {
    echo "Error checking passwords: " . $e->getMessage();
}
?> 