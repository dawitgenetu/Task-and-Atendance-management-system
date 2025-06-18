<?php
// Set the path to the includes directory
$includePath = dirname(__DIR__) . '/includes';

// Include the auto-cancel tasks functionality
require_once $includePath . '/auto_cancel_tasks.php';

// Run the check
$result = checkAndCancelPendingTasks();

// Log the result
if ($result) {
    echo "Successfully checked and processed pending tasks.\n";
} else {
    echo "Error occurred while processing pending tasks.\n";
} 