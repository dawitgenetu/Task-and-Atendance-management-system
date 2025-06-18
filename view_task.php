<?php
require_once 'config/database.php';
require_once 'includes/session.php';
require_once 'includes/functions.php';

// Get task ID from URL
$taskId = $_GET['id'] ?? null;
if (!$taskId) {
    header('Location: dashboard.php');
    exit();
}

// Get task details before any output
$conn = getDBConnection();
$role = getUserRole();
$userId = $_SESSION['user_id'];

$stmt = $conn->prepare("
    SELECT t.*, 
        CONCAT(u1.first_name, ' ', u1.last_name) as assigned_by_name,
        CONCAT(u2.first_name, ' ', u2.last_name) as assigned_to_name
    FROM tasks t
    JOIN users u1 ON t.assigned_by = u1.id
    JOIN users u2 ON t.assigned_to = u2.id
    WHERE t.id = ? AND (t.assigned_to = ? OR t.assigned_by = ? OR ? = 'admin')
");
$stmt->execute([$taskId, $userId, $userId, $role]);
$task = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$task) {
    header('Location: dashboard.php');
    exit();
}

$message = '';
$error = '';

// Handle file upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'upload_file') {
    if (isset($_FILES['task_file']) && $_FILES['task_file']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['task_file'];
        $fileName = basename($file['name']);
        $fileType = $file['type'];
        $fileSize = $file['size'];
        
        // Generate unique filename
        $uniqueFileName = uniqid() . '_' . $fileName;
        $uploadPath = 'uploads/task_files/' . $uniqueFileName;
        
        if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
            try {
                $stmt = $conn->prepare("INSERT INTO task_files (task_id, user_id, file_name, file_path, file_type, file_size) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$taskId, $userId, $fileName, $uploadPath, $fileType, $fileSize]);
                $message = 'File uploaded successfully!';
            } catch (Exception $e) {
                $error = 'Error saving file information to database.';
                unlink($uploadPath); // Delete the uploaded file if database insert fails
            }
        } else {
            $error = 'Error uploading file. Please try again.';
        }
    } else {
        $error = 'Please select a file to upload.';
    }
}

// Handle task status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status') {
    $newStatus = $_POST['status'] ?? '';
    if (in_array($newStatus, ['pending', 'in-progress', 'completed', 'cancelled'])) {
        try {
            // For employees, check if task is completed
            if ($role === 'employee') {
                $checkStmt = $conn->prepare("SELECT status FROM tasks WHERE id = ? AND assigned_to = ?");
                $checkStmt->execute([$taskId, $userId]);
                $currentStatus = $checkStmt->fetchColumn();
                
                if ($currentStatus === 'completed') {
                    $error = 'Cannot change the status of a completed task.';
                } else {
                    $stmt = $conn->prepare("UPDATE tasks SET status = ? WHERE id = ? AND assigned_to = ?");
                    $stmt->execute([$newStatus, $taskId, $userId]);
                    
                    // If task is being marked as completed, notify the manager
                    if ($newStatus === 'completed') {
                        // Get task details for the notification
                        $taskStmt = $conn->prepare("
                            SELECT t.title, t.assigned_by, CONCAT(u.first_name, ' ', u.last_name) as employee_name 
                            FROM tasks t 
                            JOIN users u ON t.assigned_to = u.id 
                            WHERE t.id = ?
                        ");
                        $taskStmt->execute([$taskId]);
                        $taskDetails = $taskStmt->fetch(PDO::FETCH_ASSOC);
                        
                        if ($taskDetails) {
                            // Send notification to the manager
                            $notificationTitle = "Task Completed";
                            $notificationMessage = sprintf(
                                "Employee %s has completed the task: '%s'. Click here to view the task.",
                                $taskDetails['employee_name'],
                                $taskDetails['title']
                            );
                            
                            // Debug information
                            error_log("Sending notification to manager ID: " . $taskDetails['assigned_by']);
                            error_log("Notification message: " . $notificationMessage);
                            
                            // Send notification to the manager who assigned the task
                            $notificationSent = sendNotification(
                                $taskDetails['assigned_by'],
                                $notificationTitle,
                                $notificationMessage,
                                'success'
                            );
                            
                            if (!$notificationSent) {
                                error_log("Failed to send notification");
                            }
                        } else {
                            error_log("Could not fetch task details for notification");
                        }
                    }
                    
                    $message = 'Task status updated successfully!';
                }
            } else {
                // For managers and admins, allow status change
                $stmt = $conn->prepare("UPDATE tasks SET status = ? WHERE id = ?");
                $stmt->execute([$newStatus, $taskId]);
                $message = 'Task status updated successfully!';
            }
        } catch (Exception $e) {
            error_log("Error in task status update: " . $e->getMessage());
            $error = 'Error updating task status. Please try again.';
        }
    }
}

// Handle new comment submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_comment') {
    $comment = $_POST['comment'] ?? '';
    if (!empty($comment)) {
        try {
            $stmt = $conn->prepare("INSERT INTO task_comments (task_id, user_id, comment) VALUES (?, ?, ?)");
            $stmt->execute([$taskId, $userId, $comment]);
            header("Location: view_task.php?id=" . $taskId);
            exit();
        } catch (Exception $e) {
            $error = 'Error adding comment. Please try again.';
        }
    }
}

// Get task comments
$stmt = $conn->prepare("
    SELECT c.*, CONCAT(u.first_name, ' ', u.last_name) as commenter_name
    FROM task_comments c
    JOIN users u ON c.user_id = u.id
    WHERE c.task_id = ?
    ORDER BY c.created_at DESC
");
$stmt->execute([$taskId]);
$comments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get task files
$stmt = $conn->prepare("
    SELECT f.*, CONCAT(u.first_name, ' ', u.last_name) as uploader_name
    FROM task_files f
    JOIN users u ON f.user_id = u.id
    WHERE f.task_id = ?
    ORDER BY f.uploaded_at DESC
");
$stmt->execute([$taskId]);
$files = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Now include files that output HTML
require_once 'includes/header.php';
?>

<div class="bg-white shadow rounded-lg p-6">
    <div class="flex justify-between items-center mb-6">
        <h2 class="text-2xl font-bold">Task Details</h2>
        <a href="<?php echo $role === 'employee' ? 'my_tasks.php' : 'tasks.php'; ?>" 
           class="text-indigo-600 hover:text-indigo-900">
            Back to <?php echo $role === 'employee' ? 'My Tasks' : 'Tasks'; ?>
        </a>
    </div>
    
    <?php if ($message): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">
            <span class="block sm:inline"><?php echo htmlspecialchars($message); ?></span>
        </div>
    <?php endif; ?>
    
    <?php if ($error): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
            <span class="block sm:inline"><?php echo htmlspecialchars($error); ?></span>
        </div>
    <?php endif; ?>
    
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <!-- Task Information -->
        <div class="bg-gray-50 rounded-lg p-6">
            <h3 class="text-lg font-semibold mb-4">Task Information</h3>
            <div class="space-y-4">
                <div>
                    <p class="text-sm font-medium text-gray-500">Title</p>
                    <p class="mt-1 text-lg"><?php echo htmlspecialchars($task['title']); ?></p>
                </div>
                <div>
                    <p class="text-sm font-medium text-gray-500">Description</p>
                    <p class="mt-1"><?php echo nl2br(htmlspecialchars($task['description'])); ?></p>
                </div>
                <div>
                    <p class="text-sm font-medium text-gray-500">Priority</p>
                    <span class="mt-1 px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                        <?php
                        switch($task['priority']) {
                            case 'high':
                                echo 'bg-red-100 text-red-800';
                                break;
                            case 'medium':
                                echo 'bg-yellow-100 text-yellow-800';
                                break;
                            case 'low':
                                echo 'bg-green-100 text-green-800';
                                break;
                        }
                        ?>">
                        <?php echo ucfirst($task['priority']); ?>
                    </span>
                </div>
                <div>
                    <p class="text-sm font-medium text-gray-500">Status</p>
                    <span class="mt-1 px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                        <?php
                        switch($task['status']) {
                            case 'completed':
                                echo 'bg-green-100 text-green-800';
                                break;
                            case 'in-progress':
                                echo 'bg-yellow-100 text-yellow-800';
                                break;
                            case 'cancelled':
                                echo 'bg-red-100 text-red-800';
                                break;
                            default:
                                echo 'bg-gray-100 text-gray-800';
                        }
                        ?>">
                        <?php echo ucfirst($task['status']); ?>
                    </span>
                </div>
                <?php if ($task['status'] === 'completed' && ($role === 'manager' || $role === 'admin')): ?>
                    <?php
                    // Check if task is already rated
                    $ratingStmt = $conn->prepare("SELECT id FROM task_ratings WHERE task_id = ?");
                    $ratingStmt->execute([$taskId]);
                    $isRated = $ratingStmt->fetch();
                    ?>
                    <div class="mt-4">
                        <?php if (!$isRated): ?>
                            <a href="rate_task.php?id=<?php echo $taskId; ?>" 
                               class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700">
                                Rate Task
                            </a>
                        <?php else: ?>
                            <span class="text-sm text-gray-500">Task has been rated</span>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Assignment Details -->
        <div class="bg-gray-50 rounded-lg p-6">
            <h3 class="text-lg font-semibold mb-4">Assignment Details</h3>
            <div class="space-y-4">
                <div>
                    <p class="text-sm font-medium text-gray-500">Assigned By</p>
                    <p class="mt-1"><?php echo htmlspecialchars($task['assigned_by_name']); ?></p>
                </div>
                <div>
                    <p class="text-sm font-medium text-gray-500">Assigned To</p>
                    <p class="mt-1"><?php echo htmlspecialchars($task['assigned_to_name']); ?></p>
                </div>
                <div>
                    <p class="text-sm font-medium text-gray-500">Assigned Date</p>
                    <p class="mt-1"><?php echo date('M d, Y', strtotime($task['assigned_date'])); ?></p>
                </div>
                <div>
                    <p class="text-sm font-medium text-gray-500">Due Date</p>
                    <p class="mt-1"><?php echo date('M d, Y', strtotime($task['due_date'])); ?></p>
                </div>
            </div>
        </div>
    </div>
    
    <?php if (($role === 'employee' && $task['assigned_to'] == $userId && $task['status'] !== 'completed') || $role === 'manager' || $role === 'admin'): ?>
        <!-- Status Update Form -->
        <div class="mt-6 bg-gray-50 rounded-lg p-6">
            <h3 class="text-lg font-semibold mb-4">Update Status</h3>
            <form method="POST" class="flex items-center space-x-4">
                <input type="hidden" name="action" value="update_status">
                <select name="status" class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="pending" <?php echo $task['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                    <option value="in-progress" <?php echo $task['status'] === 'in-progress' ? 'selected' : ''; ?>>In Progress</option>
                    <option value="completed" <?php echo $task['status'] === 'completed' ? 'selected' : ''; ?>>Completed</option>
                    <option value="cancelled" <?php echo $task['status'] === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                </select>
                <button type="submit" class="bg-indigo-600 text-white px-4 py-2 rounded hover:bg-indigo-700">
                    Update Status
                </button>
            </form>
        </div>
    <?php endif; ?>
    
    <!-- File Upload Section -->
    <div class="mt-6 bg-gray-50 rounded-lg p-6">
        <h3 class="text-lg font-semibold mb-4">Files</h3>
        
        <!-- Upload Form -->
        <form method="POST" enctype="multipart/form-data" class="mb-6">
            <input type="hidden" name="action" value="upload_file">
            <div class="flex items-center space-x-4">
                <input type="file" name="task_file" required
                    class="block w-full text-sm text-gray-500
                        file:mr-4 file:py-2 file:px-4
                        file:rounded-md file:border-0
                        file:text-sm file:font-semibold
                        file:bg-indigo-50 file:text-indigo-700
                        hover:file:bg-indigo-100">
                <button type="submit" class="bg-indigo-600 text-white px-4 py-2 rounded hover:bg-indigo-700">
                    Upload File
                </button>
            </div>
        </form>
        
        <!-- Files List -->
        <?php if (!empty($files)): ?>
            <div class="space-y-4">
                <?php foreach ($files as $file): ?>
                    <div class="bg-white rounded-lg p-4 shadow">
                        <div class="flex justify-between items-start">
                            <div>
                                <p class="font-medium text-gray-900"><?php echo htmlspecialchars($file['file_name']); ?></p>
                                <p class="text-sm text-gray-500">
                                    Uploaded by <?php echo htmlspecialchars($file['uploader_name']); ?> on 
                                    <?php echo date('M d, Y H:i', strtotime($file['uploaded_at'])); ?>
                                </p>
                                <p class="text-sm text-gray-500">
                                    Size: <?php echo number_format($file['file_size'] / 1024, 2); ?> KB
                                </p>
                            </div>
                            <a href="<?php echo htmlspecialchars($file['file_path']); ?>" 
                               download="<?php echo htmlspecialchars($file['file_name']); ?>"
                               class="text-indigo-600 hover:text-indigo-900">
                                Download
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p class="text-gray-500">No files uploaded yet.</p>
        <?php endif; ?>
    </div>
    
    <!-- Comments Section -->
    <div class="mt-6 bg-gray-50 rounded-lg p-6">
        <h3 class="text-lg font-semibold mb-4">Comments</h3>
        
        <!-- Add Comment Form -->
        <form method="POST" class="mb-6">
            <input type="hidden" name="action" value="add_comment">
            <div class="mb-4">
                <textarea name="comment" rows="3" required
                    class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    placeholder="Add a comment..."></textarea>
            </div>
            <button type="submit" class="bg-indigo-600 text-white px-4 py-2 rounded hover:bg-indigo-700">
                Add Comment
            </button>
        </form>
        
        <!-- Comments List -->
        <div class="space-y-4">
            <?php foreach ($comments as $comment): ?>
                <div class="bg-white rounded-lg p-4 shadow">
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="font-medium text-gray-900"><?php echo htmlspecialchars($comment['commenter_name']); ?></p>
                            <p class="text-sm text-gray-500"><?php echo date('M d, Y H:i', strtotime($comment['created_at'])); ?></p>
                        </div>
                    </div>
                    <p class="mt-2 text-gray-700"><?php echo nl2br(htmlspecialchars($comment['comment'])); ?></p>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?> 