<?php
require_once 'config/database.php';
require_once 'includes/session.php';

// Check if user is logged in
if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}

$role = getUserRole();
$userId = $_SESSION['user_id'];
$conn = getDBConnection();

// Check if employee has marked attendance today
if ($role === 'employee') {
    $stmt = $conn->prepare("SELECT * FROM attendance WHERE employee_id = ? AND date = CURDATE()");
    $stmt->execute([$userId]);
    $attendance = $stmt->fetch(PDO::FETCH_ASSOC);

    // If attendance not marked and not on mark_attendance.php, redirect to mark attendance
    $currentPage = basename($_SERVER['PHP_SELF']);
    if (!$attendance && $currentPage !== 'mark_attendance.php') {
        header('Location: mark_attendance.php');
        exit();
    }
}

// Get user details
$stmt = $conn->prepare("SELECT first_name, last_name FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Get unread notifications
$stmt = $conn->prepare("
    SELECT * FROM notifications 
    WHERE user_id = ? AND is_read = 0 
    ORDER BY created_at DESC
");
$stmt->execute([$userId]);
$notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Only start output after all potential redirects
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Work & Attendance Management System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* Custom scrollbar for sidebar */
        .sidebar-scroll::-webkit-scrollbar {
            width: 6px;
        }
        .sidebar-scroll::-webkit-scrollbar-track {
            background: #1f2937;
        }
        .sidebar-scroll::-webkit-scrollbar-thumb {
            background: #4b5563;
            border-radius: 3px;
        }
        .sidebar-scroll::-webkit-scrollbar-thumb:hover {
            background: #6b7280;
        }

        /* Notification styles */
        .notification-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background-color: #ef4444;
            color: white;
            border-radius: 9999px;
            padding: 0.25rem 0.5rem;
            font-size: 0.75rem;
            font-weight: 600;
        }
        .notification-dropdown {
            max-height: 400px;
            overflow-y: auto;
        }
        .notification-item {
            transition: background-color 0.2s;
        }
        .notification-item:hover {
            background-color: #f3f4f6;
        }
        .notification-item.unread {
            background-color: #fef2f2;
        }
    </style>
</head>
<body class="bg-gray-100">
    <!-- Top Navigation -->
    <div class="fixed top-0 right-0 left-0 bg-white shadow-sm z-20">
        <div class="flex items-center justify-between h-16 px-4 ml-64 transition-all duration-300 ease-in-out" id="topNav">
            <div class="flex items-center">
                <h1 class="text-xl font-semibold text-gray-800">Amhara Media Corporation</h1>
            </div>
            <div class="flex items-center space-x-4">
                <!-- Notifications -->
                <div class="relative">
                    <?php
                    // Get unread notifications count
                    $stmt = $conn->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = FALSE");
                    $stmt->execute([$userId]);
                    $unreadCount = $stmt->fetchColumn();
                    ?>
                    <button id="notificationButton" class="relative p-2 text-gray-700 hover:text-gray-900 focus:outline-none">
                        <i class="fas fa-bell fa-lg"></i>
                        <?php if ($unreadCount > 0): ?>
                            <span class="notification-badge"><?php echo $unreadCount; ?></span>
                        <?php endif; ?>
                    </button>
                    <div id="notificationDropdown" class="hidden absolute right-0 mt-2 w-80 bg-white rounded-lg shadow-lg z-50">
                        <div class="p-4 border-b flex justify-between items-center">
                            <h3 class="text-lg font-semibold text-gray-900">Notifications</h3>
                            <?php
                            // Check if there are any unread notifications
                            $unreadStmt = $conn->prepare("
                                SELECT COUNT(*) as unread_count 
                                FROM notifications 
                                WHERE user_id = ? AND is_read = 0
                            ");
                            $unreadStmt->execute([$userId]);
                            $unreadCount = $unreadStmt->fetch(PDO::FETCH_ASSOC)['unread_count'];
                            
                            if ($unreadCount > 0): ?>
                                <form method="POST" action="mark_notifications_read.php" class="inline">
                                    <button type="submit" id="headerMarkAllRead" class="text-sm text-indigo-600 hover:text-indigo-900">
                                        Mark All as Read
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                        <div class="notification-dropdown">
                            <?php
                            $stmt = $conn->prepare("
                                SELECT n.*, t.id as task_id 
                                FROM notifications n 
                                LEFT JOIN tasks t ON n.message LIKE CONCAT('%', t.title, '%') AND t.title IS NOT NULL AND t.title != ''
                                WHERE n.user_id = ? 
                                ORDER BY n.created_at DESC 
                                LIMIT 10
                            ");
                            $stmt->execute([$userId]);
                            $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

                            if (empty($notifications)): ?>
                                <div class="p-4 text-center text-gray-500">
                                    No notifications
                                </div>
                            <?php else:
                                foreach ($notifications as $notification): 
                                    // Determine the link for the notification
                                    $link = '#';
                                    if (!empty($notification['task_id'])) {
                                        $link = 'view_task.php?id=' . $notification['task_id'];
                                    } elseif (stripos($notification['title'], 'attendance') !== false) {
                                        $link = 'attendance.php';
                                    }
                                    ?>
                                    <a href="<?php echo $link; ?>" 
                                       class="block notification-item p-4 border-b <?php echo $notification['is_read'] ? '' : 'unread'; ?>" 
                                       data-notification-id="<?php echo $notification['id']; ?>">
                                        <div class="flex items-start">
                                            <div class="flex-shrink-0">
                                                <?php
                                                $iconClass = match($notification['type']) {
                                                    'success' => 'fas fa-check-circle text-green-500',
                                                    'warning' => 'fas fa-exclamation-triangle text-yellow-500',
                                                    'error' => 'fas fa-times-circle text-red-500',
                                                    default => 'fas fa-info-circle text-blue-500'
                                                };
                                                ?>
                                                <i class="<?php echo $iconClass; ?> fa-lg"></i>
                                            </div>
                                            <div class="ml-3 flex-1">
                                                <p class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($notification['title']); ?></p>
                                                <p class="text-sm text-gray-500"><?php echo htmlspecialchars($notification['message']); ?></p>
                                                <p class="text-xs text-gray-400 mt-1">
                                                    <?php echo date('M d, Y H:i', strtotime($notification['created_at'])); ?>
                                                </p>
                                            </div>
                                        </div>
                                    </a>
                                <?php endforeach;
                            endif; ?>
                        </div>
                        <div class="p-2 border-t">
                            <a href="notifications.php" class="block text-center text-sm text-red-600 hover:text-red-700">
                                View all notifications
                            </a>
                        </div>
                    </div>
                </div>

                <div class="relative">
                    <button id="userMenuButton" class="flex items-center text-gray-700 hover:text-gray-900 focus:outline-none">
                        <i class="fas fa-user-circle fa-lg mr-2 text-gray-400"></i>
                        <span class="mr-2"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></span>
                        <span class="text-sm text-gray-500">(<?php echo ucfirst($role); ?>)</span>
                        <i class="fas fa-chevron-down ml-2 text-sm"></i>
                    </button>
                    <div id="userMenu" class="hidden absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-lg z-50">
                        <div class="py-1">
                            <a href="profile.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                <i class="fas fa-user mr-2"></i> Profile
                            </a>
                            <a href="logout.php" class="block px-4 py-2 text-sm text-red-600 hover:bg-gray-100">
                                <i class="fas fa-sign-out-alt mr-2"></i> Logout
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Sidebar -->
    <?php require_once 'includes/sidebar.php'; ?>

    <!-- Main Content -->
    <main class="pt-16 ml-64 transition-all duration-300 ease-in-out" id="mainContent">
        <div class="container mx-auto px-4 py-6">

    <!-- Global Notification Update Script -->
    <script>
        function updateNotificationBadgeCount(count) {
            const badge = document.querySelector('.notification-badge');
            if (badge) {
                if (count > 0) {
                    badge.textContent = count;
                    badge.style.display = 'flex';
                } else {
                    badge.style.display = 'none';
                }
            } else if (count > 0) {
                const notificationButton = document.getElementById('notificationButton');
                if (notificationButton) {
                    const newBadge = document.createElement('span');
                    newBadge.className = 'notification-badge';
                    newBadge.textContent = count;
                    notificationButton.appendChild(newBadge);
                }
            }
        }
    </script>

    <!-- Notification Dropdown Script -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const notificationButton = document.getElementById('notificationButton');
        const notificationDropdown = document.getElementById('notificationDropdown');
        let isDropdownVisible = false;

        // Toggle notification dropdown
        notificationButton.addEventListener('click', function(e) {
            e.stopPropagation();
            isDropdownVisible = !isDropdownVisible;
            notificationDropdown.classList.toggle('hidden', !isDropdownVisible);
        });

        // Close dropdown when clicking outside
        document.addEventListener('click', function(e) {
            if (isDropdownVisible && !notificationDropdown.contains(e.target) && !notificationButton.contains(e.target)) {
                isDropdownVisible = false;
                notificationDropdown.classList.add('hidden');
            }
        });

        // Mark notifications as read when clicked in dropdown
        document.querySelectorAll('#notificationDropdown .notification-item').forEach(item => {
            item.addEventListener('click', function(e) {
                e.preventDefault(); // Prevent navigation to handle fetch first
                const notificationId = this.dataset.notificationId;
                const link = this.getAttribute('href');

                fetch('mark_notification_read.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ notification_id: notificationId })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        this.classList.remove('unread');
                        updateNotificationBadgeCount(data.unreadCount);
                    }
                })
                .finally(() => {
                    if (link && link !== '#') {
                        window.location.href = link;
                    }
                });
            });
        });
        
        // Mark all as read from header dropdown
        const headerMarkAllReadBtn = document.getElementById('headerMarkAllRead');
        if (headerMarkAllReadBtn) {
            headerMarkAllReadBtn.addEventListener('click', function(e) {
                e.preventDefault();
                fetch('mark_all_notifications_read.php', { method: 'POST' })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            document.querySelectorAll('#notificationDropdown .notification-item').forEach(item => {
                                item.classList.remove('unread');
                            });
                            updateNotificationBadgeCount(data.unreadCount);
                            this.style.display = 'none'; // Hide the button
                        }
                    });
            });
        }
    });
    </script>

    <!-- User Menu Script -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const userMenuButton = document.getElementById('userMenuButton');
        const userMenu = document.getElementById('userMenu');
        let isUserMenuVisible = false;

        // Toggle user menu
        userMenuButton.addEventListener('click', function(e) {
            e.stopPropagation();
            isUserMenuVisible = !isUserMenuVisible;
            userMenu.classList.toggle('hidden', !isUserMenuVisible);
        });

        // Close menu when clicking outside
        document.addEventListener('click', function(e) {
            if (isUserMenuVisible && !userMenu.contains(e.target) && !userMenuButton.contains(e.target)) {
                isUserMenuVisible = false;
                userMenu.classList.add('hidden');
            }
        });
    });
    </script> 