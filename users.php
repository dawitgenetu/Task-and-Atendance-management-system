<?php
require_once 'config/database.php';
require_once 'includes/header.php';

$role = getUserRole();
if ($role !== 'admin') {
    header('Location: unauthorized.php');
    exit();
}

$conn = getDBConnection();
$message = '';
$error = '';

// Handle user actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'create') {
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';
        $email = $_POST['email'] ?? '';
        $firstName = $_POST['first_name'] ?? '';
        $lastName = $_POST['last_name'] ?? '';
        $roleId = $_POST['role_id'] ?? '';
        $employeeNumber = $_POST['employee_number'] ?? '';
        
        if (!empty($username) && !empty($password) && !empty($email) && !empty($firstName) && !empty($lastName) && !empty($roleId) && !empty($employeeNumber)) {
            try {
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("INSERT INTO users (username, password, email, first_name, last_name, role_id, employee_number) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$username, $hashedPassword, $email, $firstName, $lastName, $roleId, $employeeNumber]);
                $message = 'User created successfully!';
            } catch (Exception $e) {
                $error = 'Error creating user. Please try again.';
            }
        } else {
            $error = 'Please fill in all required fields.';
        }
    } elseif ($_POST['action'] === 'update') {
        $userId = $_POST['user_id'] ?? '';
        $email = $_POST['email'] ?? '';
        $firstName = $_POST['first_name'] ?? '';
        $lastName = $_POST['last_name'] ?? '';
        $roleId = $_POST['role_id'] ?? '';
        $employeeNumber = $_POST['employee_number'] ?? '';
        
        if (!empty($userId) && !empty($email) && !empty($firstName) && !empty($lastName) && !empty($roleId) && !empty($employeeNumber)) {
            try {
                $stmt = $conn->prepare("UPDATE users SET email = ?, first_name = ?, last_name = ?, role_id = ?, employee_number = ? WHERE id = ?");
                $stmt->execute([$email, $firstName, $lastName, $roleId, $employeeNumber, $userId]);
                
                // Update password if provided
                if (!empty($_POST['password'])) {
                    $hashedPassword = password_hash($_POST['password'], PASSWORD_DEFAULT);
                    $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
                    $stmt->execute([$hashedPassword, $userId]);
                }
                
                $message = 'User updated successfully!';
            } catch (Exception $e) {
                $error = 'Error updating user. Please try again.';
            }
        } else {
            $error = 'Please fill in all required fields.';
        }
    } elseif ($_POST['action'] === 'delete') {
        $userId = $_POST['user_id'] ?? '';
        
        if (!empty($userId)) {
            // Prevent admin from deleting their own account
            if ($userId == $_SESSION['user_id']) {
                $error = 'You cannot delete your own account.';
            } else {
                try {
                    // Start transaction
                    $conn->beginTransaction();
                    
                    // Helper function to check if table exists
                    $tableExists = function($tableName) use ($conn) {
                        $stmt = $conn->query("SHOW TABLES LIKE '$tableName'");
                        return $stmt->rowCount() > 0;
                    };
                    
                    // Delete related records first
                    // Delete task ratings if table exists
                    if ($tableExists('task_ratings')) {
                        $stmt = $conn->prepare("DELETE FROM task_ratings WHERE employee_id = ? OR manager_id = ?");
                        $stmt->execute([$userId, $userId]);
                    }
                    
                    // Delete task comments if table exists
                    if ($tableExists('task_comments')) {
                        $stmt = $conn->prepare("DELETE FROM task_comments WHERE user_id = ?");
                        $stmt->execute([$userId]);
                    }
                    
                    // Delete task files if table exists
                    if ($tableExists('task_files')) {
                        $stmt = $conn->prepare("DELETE FROM task_files WHERE user_id = ?");
                        $stmt->execute([$userId]);
                    }
                    
                    // Delete files if table exists
                    if ($tableExists('files')) {
                        $stmt = $conn->prepare("DELETE FROM files WHERE employee_id = ?");
                        $stmt->execute([$userId]);
                    }
                    
                    // Delete attendance records if table exists
                    if ($tableExists('attendance')) {
                        $stmt = $conn->prepare("DELETE FROM attendance WHERE employee_id = ?");
                        $stmt->execute([$userId]);
                    }
                    
                    // Delete tasks if table exists
                    if ($tableExists('tasks')) {
                        $stmt = $conn->prepare("DELETE FROM tasks WHERE assigned_by = ? OR assigned_to = ?");
                        $stmt->execute([$userId, $userId]);
                    }
                    
                    // Finally, delete the user
                    $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
                    $stmt->execute([$userId]);
                    
                    // Commit transaction
                    $conn->commit();
                    $message = 'User deleted successfully!';
                } catch (Exception $e) {
                    // Rollback transaction on error
                    $conn->rollBack();
                    $error = 'Error deleting user: ' . $e->getMessage();
                    error_log("Error deleting user: " . $e->getMessage());
                }
            }
        }
    }
}

// Get all roles
$stmt = $conn->prepare("SELECT * FROM roles");
$stmt->execute();
$roles = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all users with their roles
$stmt = $conn->prepare("
    SELECT u.*, r.role_name 
    FROM users u 
    JOIN roles r ON u.role_id = r.id 
    ORDER BY u.created_at DESC
");
$stmt->execute();
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="bg-white shadow rounded-lg p-6">
    <div class="flex justify-between items-center mb-6">
        <h2 class="text-2xl font-bold">User Management</h2>
        <button onclick="document.getElementById('createUserModal').classList.remove('hidden')" 
                class="bg-indigo-600 text-white px-4 py-2 rounded hover:bg-indigo-700">
            Create New User
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
    
    <!-- User List -->
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Employee</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Username</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Role</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Created</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php foreach ($users as $user): ?>
                    <tr>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm font-medium text-gray-900">
                                <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>
                            </div>
                            <div class="text-sm text-gray-500">
                                <?php echo htmlspecialchars($user['employee_number']); ?>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm text-gray-900"><?php echo htmlspecialchars($user['username']); ?></div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm text-gray-900"><?php echo htmlspecialchars($user['email']); ?></div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                <?php echo ucfirst($user['role_name']); ?>
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            <?php echo date('M d, Y', strtotime($user['created_at'])); ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                            <button onclick="openEditModal(<?php echo htmlspecialchars(json_encode($user)); ?>)"
                                    class="text-indigo-600 hover:text-indigo-900 mr-3">
                                <i class="fas fa-edit"></i> Edit
                            </button>
                            <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                <button onclick="openDeleteModal(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>')"
                                        class="text-red-600 hover:text-red-900">
                                    <i class="fas fa-trash"></i> Delete
                                </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Create User Modal -->
<div id="createUserModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full">
    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
        <div class="mt-3">
            <h3 class="text-lg font-medium leading-6 text-gray-900 mb-4">Create New User</h3>
            <form method="POST" class="space-y-4">
                <input type="hidden" name="action" value="create">
                
                <div>
                    <label for="employee_number" class="block text-sm font-medium text-gray-700">Employee Number</label>
                    <input type="text" name="employee_number" id="employee_number" required
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                </div>
                
                <div>
                    <label for="username" class="block text-sm font-medium text-gray-700">Username</label>
                    <input type="text" name="username" id="username" required
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                </div>
                
                <div>
                    <label for="password" class="block text-sm font-medium text-gray-700">Password</label>
                    <input type="password" name="password" id="password" required
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                </div>
                
                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700">Email</label>
                    <input type="email" name="email" id="email" required
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                </div>
                
                <div>
                    <label for="first_name" class="block text-sm font-medium text-gray-700">First Name</label>
                    <input type="text" name="first_name" id="first_name" required
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                </div>
                
                <div>
                    <label for="last_name" class="block text-sm font-medium text-gray-700">Last Name</label>
                    <input type="text" name="last_name" id="last_name" required
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                </div>
                
                <div>
                    <label for="role_id" class="block text-sm font-medium text-gray-700">Role</label>
                    <select name="role_id" id="role_id" required
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">Select Role</option>
                        <?php foreach ($roles as $role): ?>
                            <option value="<?php echo $role['id']; ?>">
                                <?php echo ucfirst($role['role_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="document.getElementById('createUserModal').classList.add('hidden')"
                        class="bg-gray-200 text-gray-700 px-4 py-2 rounded hover:bg-gray-300">
                        Cancel
                    </button>
                    <button type="submit"
                        class="bg-indigo-600 text-white px-4 py-2 rounded hover:bg-indigo-700">
                        Create User
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit User Modal -->
<div id="editUserModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full">
    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
        <div class="mt-3">
            <h3 class="text-lg font-medium leading-6 text-gray-900 mb-4">Edit User</h3>
            <form method="POST" class="space-y-4">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="user_id" id="edit_user_id">
                
                <div>
                    <label for="edit_employee_number" class="block text-sm font-medium text-gray-700">Employee Number</label>
                    <input type="text" name="employee_number" id="edit_employee_number" required
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                </div>
                
                <div>
                    <label for="edit_username" class="block text-sm font-medium text-gray-700">Username</label>
                    <input type="text" id="edit_username" disabled
                        class="mt-1 block w-full rounded-md border-gray-300 bg-gray-100 shadow-sm">
                </div>
                
                <div>
                    <label for="edit_password" class="block text-sm font-medium text-gray-700">New Password (leave blank to keep current)</label>
                    <input type="password" name="password" id="edit_password"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                </div>
                
                <div>
                    <label for="edit_email" class="block text-sm font-medium text-gray-700">Email</label>
                    <input type="email" name="email" id="edit_email" required
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                </div>
                
                <div>
                    <label for="edit_first_name" class="block text-sm font-medium text-gray-700">First Name</label>
                    <input type="text" name="first_name" id="edit_first_name" required
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                </div>
                
                <div>
                    <label for="edit_last_name" class="block text-sm font-medium text-gray-700">Last Name</label>
                    <input type="text" name="last_name" id="edit_last_name" required
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                </div>
                
                <div>
                    <label for="edit_role_id" class="block text-sm font-medium text-gray-700">Role</label>
                    <select name="role_id" id="edit_role_id" required
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <?php foreach ($roles as $role): ?>
                            <option value="<?php echo $role['id']; ?>">
                                <?php echo ucfirst($role['role_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="document.getElementById('editUserModal').classList.add('hidden')"
                        class="bg-gray-200 text-gray-700 px-4 py-2 rounded hover:bg-gray-300">
                        Cancel
                    </button>
                    <button type="submit"
                        class="bg-indigo-600 text-white px-4 py-2 rounded hover:bg-indigo-700">
                        Update User
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete User Modal -->
<div id="deleteUserModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full">
    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
        <div class="mt-3">
            <h3 class="text-lg font-medium leading-6 text-gray-900 mb-4">Delete User</h3>
            <p class="text-sm text-gray-500 mb-4">Are you sure you want to delete this user? This action cannot be undone.</p>
            <form method="POST" class="space-y-4">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="user_id" id="delete_user_id">
                
                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="document.getElementById('deleteUserModal').classList.add('hidden')"
                        class="bg-gray-200 text-gray-700 px-4 py-2 rounded hover:bg-gray-300">
                        Cancel
                    </button>
                    <button type="submit"
                        class="bg-red-600 text-white px-4 py-2 rounded hover:bg-red-700">
                        Delete User
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openEditModal(user) {
    document.getElementById('edit_user_id').value = user.id;
    document.getElementById('edit_employee_number').value = user.employee_number;
    document.getElementById('edit_username').value = user.username;
    document.getElementById('edit_email').value = user.email;
    document.getElementById('edit_first_name').value = user.first_name;
    document.getElementById('edit_last_name').value = user.last_name;
    document.getElementById('edit_role_id').value = user.role_id;
    document.getElementById('editUserModal').classList.remove('hidden');
}

function openDeleteModal(userId, userName) {
    document.getElementById('delete_user_id').value = userId;
    document.getElementById('deleteUserModal').classList.remove('hidden');
}
</script>

<?php require_once 'includes/footer.php'; ?> 