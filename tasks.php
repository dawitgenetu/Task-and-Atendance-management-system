<?php
require_once 'includes/config.php';
require_once 'includes/header.php';
require_once 'includes/functions.php';

$role = getUserRole();
if ($role !== 'manager' && $role !== 'admin') {
    header('Location: unauthorized.php');
    exit();
}

$userId = $_SESSION['user_id'];
$message = '';
$error = '';

// Function to generate unique task number
function generateTaskNumber($conn) {
    $date = date('d-m-Y');
    $stmt = $conn->query("SELECT MAX(id) as max_id FROM tasks");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $nextId = ($result['max_id'] ?? 0) + 1;
    return "TT-{$date}-{$nextId}";
}

// Handle task creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_task') {
    $title = $_POST['title'] ?? '';
    $description = $_POST['description'] ?? '';
    $assignedTo = $_POST['assigned_to'] ?? '';
    $dueDate = $_POST['due_date'] ?? '';
    $priority = $_POST['priority'] ?? 'medium';
    
    if (empty($title) || empty($assignedTo) || empty($dueDate)) {
        $error = 'Please fill in all required fields.';
    } else {
        try {
            $taskNumber = generateTaskNumber($conn);
            
            $stmt = $conn->prepare("
                INSERT INTO tasks (
                    task_number, 
                    title, 
                    description, 
                    assigned_by,
                    assigned_to,
                    due_date, 
                    priority,
                    status,
                    created_at,
                    assigned_date
                ) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', NOW(), NOW())
            ");
            
            $stmt->execute([
                $taskNumber,
                $title,
                $description,
                $userId,
                $assignedTo,
                $dueDate,
                $priority
            ]);
            
            $taskId = $conn->lastInsertId();
            
            // Get assigned employee's name for notification
            $stmt = $conn->prepare("SELECT CONCAT(first_name, ' ', last_name) as name FROM users WHERE id = ?");
            $stmt->execute([$assignedTo]);
            $assignedEmployee = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Send notification to assigned employee
            $notificationTitle = "New Task Assignment";
            $notificationMessage = sprintf(
                "You have been assigned a new task: '%s' (Task #: %s, Priority: %s, Due: %s). Please update the status to 'In Progress' within 3 days to avoid automatic cancellation. <a href='view_task.php?id=%d' class='text-blue-600 hover:text-blue-800'>Click here to view the task</a>.",
                $title,
                $taskNumber,
                ucfirst($priority),
                date('M d, Y', strtotime($dueDate)),
                $taskId
            );
            
            sendNotification($assignedTo, $notificationTitle, $notificationMessage, 'info');
            
            $message = 'Task created successfully!';
        } catch (PDOException $e) {
            error_log("Error creating task: " . $e->getMessage());
            $error = 'Error creating task. Please try again.';
        }
    }
}

// Get all employees for task assignment
$stmt = $conn->prepare("SELECT id, employee_number, first_name, last_name FROM users WHERE role_id = (SELECT id FROM roles WHERE role_name = 'employee')");
$stmt->execute();
$employees = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get tasks based on role
if ($role === 'admin') {
    $stmt = $conn->prepare("
        SELECT t.*, 
            CONCAT(u1.first_name, ' ', u1.last_name) as assigned_by_name,
            CONCAT(u2.first_name, ' ', u2.last_name) as assigned_to_name
        FROM tasks t
        JOIN users u1 ON t.assigned_by = u1.id
        JOIN users u2 ON t.assigned_to = u2.id
        ORDER BY t.due_date DESC
    ");
    $stmt->execute();
} else {
    $stmt = $conn->prepare("
        SELECT t.*, 
            CONCAT(u1.first_name, ' ', u1.last_name) as assigned_by_name,
            CONCAT(u2.first_name, ' ', u2.last_name) as assigned_to_name
        FROM tasks t
        JOIN users u1 ON t.assigned_by = u1.id
        JOIN users u2 ON t.assigned_to = u2.id
        WHERE t.assigned_by = ?
        ORDER BY t.due_date DESC
    ");
    $stmt->execute([$userId]);
}
$tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="bg-white shadow rounded-lg p-6">
    <div class="flex justify-between items-center mb-6">
        <h2 class="text-2xl font-bold">Task Management</h2>
        <button onclick="document.getElementById('createTaskModal').classList.remove('hidden')" 
                class="bg-indigo-600 text-white px-4 py-2 rounded hover:bg-indigo-700">
            Create New Task
        </button>
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

    <!-- Task List -->
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Task #</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Title</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Assigned To</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Due Date</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Priority</th>
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
                            <div class="text-sm text-gray-900"><?php echo htmlspecialchars($task['assigned_to_name']); ?></div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm text-gray-900"><?php echo date('M d, Y', strtotime($task['due_date'])); ?></div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
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
                            <div class="flex items-center space-x-2">
                                <a href="view_task.php?id=<?php echo $task['id']; ?>" 
                                   class="text-gray-600 hover:text-gray-800"
                                   title="View Task">
                                    <i class="fas fa-eye"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Create Task Modal -->
<div id="createTaskModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full">
    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
        <div class="mt-3">
            <h3 class="text-lg font-medium leading-6 text-gray-900 mb-4">Create New Task</h3>
            <form method="POST" class="space-y-4">
                <input type="hidden" name="action" value="create_task">
                
                <div>
                    <label for="title" class="block text-sm font-medium text-gray-700">Title</label>
                    <input type="text" name="title" id="title" required
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                </div>
                
                <div>
                    <label for="description" class="block text-sm font-medium text-gray-700">Description</label>
                    <textarea name="description" id="description" rows="3"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"></textarea>
                </div>
                
                <div>
                    <label for="assigned_to" class="block text-sm font-medium text-gray-700">Assign To</label>
                    <select name="assigned_to" id="assigned_to" required
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">Select Employee</option>
                        <?php foreach ($employees as $employee): ?>
                            <option value="<?php echo $employee['id']; ?>">
                                <?php echo htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name'] . ' (' . $employee['employee_number'] . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div>
                    <label for="priority" class="block text-sm font-medium text-gray-700">Priority</label>
                    <select name="priority" id="priority" required
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="high">High</option>
                        <option value="medium" selected>Medium</option>
                        <option value="low">Low</option>
                    </select>
                </div>
                
                <div>
                    <label for="due_date" class="block text-sm font-medium text-gray-700">Due Date</label>
                    <input type="date" name="due_date" id="due_date" required
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                </div>
                
                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="document.getElementById('createTaskModal').classList.add('hidden')"
                        class="bg-gray-200 text-gray-700 px-4 py-2 rounded hover:bg-gray-300">
                        Cancel
                    </button>
                    <button type="submit"
                        class="bg-indigo-600 text-white px-4 py-2 rounded hover:bg-indigo-700">
                        Create Task
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?> 