<?php
require_once 'config/database.php';
require_once 'includes/auth.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if user is authorized (admin or manager)
$role = getUserRole();
if ($role !== 'admin' && $role !== 'manager') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Get the photo path from POST data
$photoPath = $_POST['photo_path'] ?? '';

if (empty($photoPath)) {
    echo json_encode(['success' => false, 'message' => 'No photo path provided']);
    exit();
}

try {
    // Log the original path
    error_log("Original photo path: " . $photoPath);
    
    // Clean up the photo path
    $photoPath = urldecode($photoPath);
    
    // Remove any URL components if present
    $photoPath = preg_replace('/^https?:\/\/[^\/]+\//', '', $photoPath);
    
    // Remove leading slash if present
    $photoPath = ltrim($photoPath, '/');
    
    // Log the cleaned path
    error_log("Cleaned photo path: " . $photoPath);
    
    // Get the absolute path
    $absolutePath = __DIR__ . '/' . $photoPath;
    error_log("Absolute path: " . $absolutePath);
    
    // Check if file exists
    if (!file_exists($absolutePath)) {
        error_log("File not found at path: " . $absolutePath);
        throw new Exception('Photo file not found at: ' . $photoPath);
    }
    
    // Check if file is writable
    if (!is_writable($absolutePath)) {
        error_log("File not writable at path: " . $absolutePath);
        throw new Exception('Photo file is not writable');
    }
    
    // Try to delete the file
    if (!unlink($absolutePath)) {
        error_log("Failed to delete file at path: " . $absolutePath);
        throw new Exception('Failed to delete photo file');
    }
    
    error_log("Successfully deleted file at path: " . $absolutePath);
    
    // Update the database
    $conn = getDBConnection();
    $stmt = $conn->prepare("UPDATE attendance SET photo_path = NULL WHERE photo_path = ?");
    $stmt->execute([$photoPath]);
    
    if ($stmt->rowCount() === 0) {
        error_log("No database record updated for photo path: " . $photoPath);
        // Don't throw an error here, as the file was deleted successfully
    } else {
        error_log("Successfully updated database record");
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Photo deleted successfully'
    ]);
    
} catch (Exception $e) {
    error_log("Error in delete_photo.php: " . $e->getMessage());
    error_log("Photo path that caused error: " . $photoPath);
    echo json_encode([
        'success' => false,
        'message' => 'Error deleting photo: ' . $e->getMessage()
    ]);
} 