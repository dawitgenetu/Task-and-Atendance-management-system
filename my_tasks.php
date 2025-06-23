<?php
require_once 'config/database.php';
require_once 'includes/header.php';
require_once 'includes/functions.php';

$role = getUserRole();
if ($role !== 'employee') {
    header('Location: unauthorized.php');
    exit();
}

$userId = $_SESSION['user_id'];
$conn = getDBConnection();
$message = '';
$error = '';

// Handle task status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status') {
    $taskId = $_POST['task_id'] ?? '';
    $newStatus = $_POST['status'] ?? '';
    
    if (!empty($taskId) && in_array($newStatus, ['pending', 'in-progress', 'completed', 'cancelled'])) {
        try {
            // First check if the task is already completed
            $checkStmt = $conn->prepare("SELECT status, assigned_by FROM tasks WHERE id = ? AND assigned_to = ?");
            $checkStmt->execute([$taskId, $userId]);
            $currentTask = $checkStmt->fetch(PDO::FETCH_ASSOC);
            
            if ($currentTask['status'] === 'completed') {
                $error = 'Cannot change the status of a completed task.';
            } else {
                $stmt = $conn->prepare("UPDATE tasks SET status = ? WHERE id = ? AND assigned_to = ?");
                $stmt->execute([$newStatus, $taskId, $userId]);
                
                // If task is being marked as in-progress from pending, notify the manager
                if ($newStatus === 'in-progress' && $currentTask['status'] === 'pending') {
                    // Get task details for the notification
                    $taskStmt = $conn->prepare("
                        SELECT t.task_number, t.title, t.assigned_by, 
                               CONCAT(u.first_name, ' ', u.last_name) as employee_name 
                        FROM tasks t 
                        JOIN users u ON t.assigned_to = u.id 
                        WHERE t.id = ?
                    ");
                    $taskStmt->execute([$taskId]);
                    $taskDetails = $taskStmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($taskDetails) {
                        // Send notification to manager
                        $notificationTitle = "Task Status Updated";
                        $notificationMessage = sprintf(
                            "Employee %s has started working on task #%s: '%s'. The task is now in progress.",
                            $taskDetails['employee_name'],
                            $taskDetails['task_number'],
                            $taskDetails['title']
                        );
                        
                        sendNotification(
                            $taskDetails['assigned_by'],
                            $notificationTitle,
                            $notificationMessage,
                            'info'
                        );
                    }
                }
                
                // If task is being marked as completed, notify the manager
                if ($newStatus === 'completed') {
                    // Get task details and manager information
                    $taskStmt = $conn->prepare("
                        SELECT t.task_number, t.title, t.assigned_by, 
                               CONCAT(u.first_name, ' ', u.last_name) as employee_name 
                        FROM tasks t 
                        JOIN users u ON t.assigned_to = u.id 
                        WHERE t.id = ?
                    ");
                    $taskStmt->execute([$taskId]);
                    $taskDetails = $taskStmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($taskDetails) {
                        // Send notification to manager
                        $notificationTitle = "Task Completed";
                        $notificationMessage = sprintf(
                            "%s has completed task #%s: '%s'. Click here to rate the task.",
                            $taskDetails['employee_name'],
                            $taskDetails['task_number'],
                            $taskDetails['title']
                        );
                        
                        sendNotification($taskDetails['assigned_by'], $notificationTitle, $notificationMessage, 'success');
                    }
                }
                
                $message = 'Task status updated successfully!';
            }
        } catch (Exception $e) {
            error_log("Error in task status update: " . $e->getMessage());
            $error = 'Error updating task status. Please try again.';
        }
    }
}

// Get all tasks assigned to the employee
$stmt = $conn->prepare("
    SELECT t.*, 
        CONCAT(u.first_name, ' ', u.last_name) as assigned_by_name
    FROM tasks t
    LEFT JOIN users u ON t.assigned_by = u.id
    WHERE t.assigned_to = ?
    ORDER BY t.due_date DESC
");
$stmt->execute([$userId]);
$tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="bg-white shadow rounded-lg p-6">
    <h2 class="text-2xl font-bold mb-6">My Tasks</h2>
    
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
    
    <!-- Task List -->
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Task #</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Title</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Assigned By</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Due Date</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php foreach ($tasks as $task): ?>
                    <tr>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($task['task_number']); ?></div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($task['title']); ?></div>
                            <div class="text-sm text-gray-500"><?php echo htmlspecialchars($task['description']); ?></div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm text-gray-900"><?php echo htmlspecialchars($task['assigned_by_name']); ?></div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm text-gray-900"><?php echo date('M d, Y', strtotime($task['due_date'])); ?></div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
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
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            <div class="flex space-x-2">
                                <a href="view_task.php?id=<?php echo $task['id']; ?>" class="text-indigo-600 hover:text-indigo-900">View</a>
                                <?php if ($task['status'] !== 'completed'): ?>
                                    <form method="POST" class="inline">
                                        <input type="hidden" name="action" value="update_status">
                                        <input type="hidden" name="task_id" value="<?php echo $task['id']; ?>">
                                        <select name="status" onchange="this.form.submit()" class="text-sm border-gray-300 rounded-md">
                                            <option value="pending" <?php echo $task['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                            <option value="in-progress" <?php echo $task['status'] === 'in-progress' ? 'selected' : ''; ?>>In Progress</option>
                                            <option value="completed" <?php echo $task['status'] === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                            <option value="cancelled" <?php echo $task['status'] === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                        </select>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?> 