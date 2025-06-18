<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include database configuration
require_once __DIR__ . '/../config/database.php';

// Get database connection
$conn = getDBConnection();

// Set timezone
date_default_timezone_set('Asia/Manila');

// Define base URL
define('BASE_URL', 'http://localhost/work');

// Define upload directory
define('UPLOAD_DIR', __DIR__ . '/../uploads/');

// Create upload directory if it doesn't exist
if (!file_exists(UPLOAD_DIR)) {
    mkdir(UPLOAD_DIR, 0777, true);
} 