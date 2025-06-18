<?php
// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    $_SESSION['error'] = "Please log in to access this page.";
    header("Location: login.php");
    exit();
}

// Check if user is active
if (isset($_SESSION['status']) && $_SESSION['status'] !== 'active') {
    $_SESSION['error'] = "Your account is not active. Please contact the administrator.";
    header("Location: login.php");
    exit();
}

// Function to check if user has required role
function requireRole($requiredRole) {
    if (!isset($_SESSION['role']) || $_SESSION['role'] !== $requiredRole) {
        $_SESSION['error'] = "You don't have permission to access this page.";
        header("Location: unauthorized.php");
        exit();
    }
} 