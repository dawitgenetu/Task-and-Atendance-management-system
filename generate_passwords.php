<?php
// Generate encrypted passwords
$password = '123';
$encrypted = password_hash($password, PASSWORD_BCRYPT);

echo "Encrypted password for '123': " . $encrypted . "\n";

// Generate SQL statements
$sql = "INSERT INTO users (employee_number, username, password, email, first_name, last_name, role_id) VALUES 
('ADMIN001', 'admin', '" . $encrypted . "', 'admin@example.com', 'Admin', 'User', 1),
('MGR001', 'manager', '" . $encrypted . "', 'manager@example.com', 'Manager', 'User', 2),
('EMP001', 'employee', '" . $encrypted . "', 'employee@example.com', 'Employee', 'User', 3);";

echo "\nSQL Statement:\n" . $sql;
?> 