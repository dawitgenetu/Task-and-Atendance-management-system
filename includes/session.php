<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit();
    }
}

function getUserRole() {
    return $_SESSION['role'] ?? null;
}

function requireRole($requiredRole) {
    requireLogin();
    if (getUserRole() !== $requiredRole) {
        header('Location: unauthorized.php');
        exit();
    }
}

function logout() {
    session_destroy();
    header('Location: login.php');
    exit();
} 