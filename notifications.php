<?php
require_once 'config/database.php';
require_once 'includes/session.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}

$conn = getDBConnection();
$stmt = $conn->prepare("
    SELECT n.*, t.id AS task_id
    FROM notifications n
    LEFT JOIN tasks t ON n.message LIKE CONCAT('%', t.title, '%') AND t.title IS NOT NULL AND t.title != ''
    WHERE n.user_id = ? 
    ORDER BY n.created_at DESC
");
$stmt->execute([$_SESSION['user_id']]);
$notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

require_once 'includes/header.php';
?>

<div class="bg-white rounded-lg shadow-lg p-6">
    <div class="flex justify-between items-center mb-6">
        <h2 class="text-2xl font-bold text-gray-900">All Notifications</h2>
        <div class="space-x-4">
            <button id="deleteAll" class="bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700 transition-colors duration-200">
                <i class="fas fa-trash-alt mr-2"></i>Delete All
            </button>
        <button id="markAllRead" class="bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700 transition-colors duration-200">
                <i class="fas fa-check-double mr-2"></i>Mark All as Read
        </button>
        </div>
    </div>

    <div class="space-y-4">
        <?php if (empty($notifications)): ?>
            <div class="text-center text-gray-500 py-8">
                No notifications found
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
                <a href="<?php echo $link; ?>" class="notification-item block p-4 border rounded-lg <?php echo $notification['is_read'] ? '' : 'bg-red-50'; ?>" 
                     data-notification-id="<?php echo $notification['id']; ?>">
                    <div class="flex items-start justify-between">
                        <div class="flex items-start flex-1">
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
                        <button class="delete-notification ml-4 text-red-600 hover:text-red-800 focus:outline-none" 
                                data-notification-id="<?php echo $notification['id']; ?>">
                            <i class="fas fa-trash-alt"></i>
                        </button>
                    </div>
                </a>
            <?php endforeach;
        endif; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Delete all notifications
    document.getElementById('deleteAll').addEventListener('click', function() {
        if (!confirm('Are you sure you want to delete all notifications? This action cannot be undone.')) {
            return;
        }

        fetch('delete_all_notifications.php', {
            method: 'POST'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Remove all notifications from the UI
                const container = document.querySelector('.space-y-4');
                container.innerHTML = `
                    <div class="text-center text-gray-500 py-8">
                        No notifications found
                    </div>
                `;
                
                // Show success message
                const successMessage = document.createElement('div');
                successMessage.className = 'fixed top-4 right-4 bg-green-500 text-white px-6 py-3 rounded shadow-lg';
                successMessage.textContent = `Successfully deleted ${data.count} notifications!`;
                document.body.appendChild(successMessage);
                setTimeout(() => successMessage.remove(), 3000);
                updateNotificationBadgeCount(data.unreadCount);
            } else {
                alert('Failed to delete notifications. Please try again.');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Failed to delete notifications. Please try again.');
        });
    });

    // Mark all as read
    document.getElementById('markAllRead').addEventListener('click', function() {
        fetch('mark_all_notifications_read.php', {
            method: 'POST'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Update UI instead of reloading
                document.querySelectorAll('.notification-item').forEach(item => {
                    item.classList.remove('bg-red-50');
                });
                const successMessage = document.createElement('div');
                successMessage.className = 'fixed top-4 right-4 bg-green-500 text-white px-6 py-3 rounded shadow-lg';
                successMessage.textContent = 'All notifications marked as read!';
                document.body.appendChild(successMessage);
                setTimeout(() => successMessage.remove(), 3000);
                updateNotificationBadgeCount(data.unreadCount);
            }
        });
    });

    // Mark individual notification as read and navigate
    document.querySelectorAll('.notification-item').forEach(item => {
        item.addEventListener('click', function(e) {
            // Don't trigger if delete button was clicked
            if (e.target.closest('.delete-notification')) {
                return;
            }
            
            const link = this.getAttribute('href');
            if (!link || link === '#') {
                e.preventDefault(); // Prevent navigation for non-linked notifications
            } else {
                e.preventDefault(); // Prevent default link behavior to mark as read first
            }
            
            const notificationId = this.dataset.notificationId;

            fetch('mark_notification_read.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ notification_id: notificationId })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    this.classList.remove('bg-red-50');
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

    // Delete notification
    document.querySelectorAll('.delete-notification').forEach(button => {
        button.addEventListener('click', function(e) {
            e.stopPropagation(); // Prevent triggering the notification item click
            
            if (!confirm('Are you sure you want to delete this notification?')) {
                return;
            }

            const notificationId = this.dataset.notificationId;
            fetch('delete_notification.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ notification_id: notificationId })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Remove the notification item from the UI
                    const notificationItem = this.closest('.notification-item');
                    notificationItem.remove();
                    
                    // Show success message
                    const successMessage = document.createElement('div');
                    successMessage.className = 'fixed top-4 right-4 bg-green-500 text-white px-6 py-3 rounded shadow-lg';
                    successMessage.textContent = 'Notification deleted successfully!';
                    document.body.appendChild(successMessage);
                    setTimeout(() => successMessage.remove(), 3000);
                    updateNotificationBadgeCount(data.unreadCount);

                    // If no notifications left, show the "No notifications" message
                    if (document.querySelectorAll('.notification-item').length === 0) {
                        const container = document.querySelector('.space-y-4');
                        container.innerHTML = `
                            <div class="text-center text-gray-500 py-8">
                                No notifications found
                            </div>
                        `;
                    }
                } else {
                    alert('Failed to delete notification. Please try again.');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Failed to delete notification. Please try again.');
            });
        });
    });
});
</script>

<?php require_once 'includes/footer.php'; ?> 