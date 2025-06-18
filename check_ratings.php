<?php
require_once 'config/database.php';

$conn = getDBConnection();

// First, let's check if Dawit exists in the users table
$stmt = $conn->prepare("SELECT * FROM users WHERE first_name = 'Dawit'");
$stmt->execute();
$user = $stmt->fetch(PDO::FETCH_ASSOC);

echo "<h2>Debug Information</h2>";

if (!$user) {
    echo "<p>No user found with the name 'Dawit' in the database.</p>";
    
    // Let's show all users to help identify the correct name
    $stmt = $conn->prepare("SELECT id, first_name, last_name, role_id FROM users");
    $stmt->execute();
    $allUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h3>All Users in Database:</h3>";
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>ID</th><th>First Name</th><th>Last Name</th><th>Role ID</th></tr>";
    foreach ($allUsers as $u) {
        echo "<tr>";
        echo "<td>{$u['id']}</td>";
        echo "<td>{$u['first_name']}</td>";
        echo "<td>{$u['last_name']}</td>";
        echo "<td>{$u['role_id']}</td>";
        echo "</tr>";
    }
    echo "</table>";
    exit;
}

// If we found Dawit, let's get their ratings
$stmt = $conn->prepare("
    SELECT 
        tr.*,
        t.title as task_title,
        t.task_number,
        CONCAT(m.first_name, ' ', m.last_name) as manager_name
    FROM task_ratings tr
    JOIN tasks t ON tr.task_id = t.id
    JOIN users m ON tr.manager_id = m.id
    WHERE tr.employee_id = ?
");

$stmt->execute([$user['id']]);
$ratings = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<h2>Dawit's Information</h2>";
echo "<p>User ID: {$user['id']}</p>";
echo "<p>Name: {$user['first_name']} {$user['last_name']}</p>";
echo "<p>Average Rating: {$user['average_rating']}</p>";
echo "<p>Total Ratings: {$user['total_ratings']}</p>";

echo "<h3>Task Ratings:</h3>";
if (empty($ratings)) {
    echo "<p>No ratings found for this user.</p>";
} else {
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Task Number</th><th>Task Title</th><th>Rating</th><th>Overall Rating</th><th>Completion Time</th><th>Attendance</th><th>Manager</th><th>Date</th></tr>";
    
    foreach ($ratings as $rating) {
        echo "<tr>";
        echo "<td>{$rating['task_number']}</td>";
        echo "<td>{$rating['task_title']}</td>";
        echo "<td>{$rating['rating']}/5</td>";
        echo "<td>{$rating['overall_rating']}/5</td>";
        echo "<td>{$rating['completion_time_rating']}/5</td>";
        echo "<td>{$rating['attendance_rating']}/5</td>";
        echo "<td>{$rating['manager_name']}</td>";
        echo "<td>{$rating['created_at']}</td>";
        echo "</tr>";
    }
    echo "</table>";
}
?> 