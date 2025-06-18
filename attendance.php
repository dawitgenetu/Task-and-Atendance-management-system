<?php
require_once 'config/database.php';
require_once 'includes/header.php';

$role = getUserRole();
if ($role !== 'admin' && $role !== 'manager') {
    header('Location: unauthorized.php');
    exit();
}

$conn = getDBConnection();

// Handle attendance status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['attendance_id']) && isset($_POST['status'])) {
    $attendanceId = $_POST['attendance_id'];
    $status = $_POST['status'];
    
    try {
        $stmt = $conn->prepare("UPDATE attendance SET status = ? WHERE id = ?");
        $stmt->execute([$status, $attendanceId]);
        $_SESSION['success_message'] = 'Attendance status updated successfully!';
    } catch (PDOException $e) {
        $_SESSION['error_message'] = 'Error updating attendance status. Please try again.';
    }
    
    // Use JavaScript for redirect instead of header
    echo "<script>window.location.href = 'attendance.php';</script>";
    exit();
}

// Get filter parameters
$date = $_GET['date'] ?? date('Y-m-d');
$status = $_GET['status'] ?? '';
$employee = $_GET['employee'] ?? '';

// Build the query
$query = "
    SELECT 
        a.*,
        u.first_name,
        u.last_name,
        u.email,
        a.video_path,
        a.video_path_out
    FROM attendance a
    JOIN users u ON a.employee_id = u.id
    WHERE 1=1
";

$params = [];

if ($date) {
    $query .= " AND a.date = ?";
    $params[] = $date;
}

if ($status) {
    $query .= " AND a.status = ?";
    $params[] = $status;
}

if ($employee) {
    $query .= " AND (u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ?)";
    $searchTerm = "%$employee%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

$query .= " ORDER BY a.date DESC, a.clock_in DESC";

$stmt = $conn->prepare($query);
$stmt->execute($params);
$attendanceRecords = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all employees for filter
$stmt = $conn->query("SELECT id, first_name, last_name FROM users WHERE role_id IN (SELECT id FROM roles WHERE role_name = 'employee') ORDER BY first_name, last_name");
$employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="bg-white shadow rounded-lg p-6">
    <div class="flex justify-between items-center mb-6">
        <h2 class="text-2xl font-bold">Attendance Records</h2>
    </div>

    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">
            <span class="block sm:inline"><?php echo htmlspecialchars($_SESSION['success_message']); ?></span>
        </div>
        <?php unset($_SESSION['success_message']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['error_message'])): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
            <span class="block sm:inline"><?php echo htmlspecialchars($_SESSION['error_message']); ?></span>
        </div>
        <?php unset($_SESSION['error_message']); ?>
    <?php endif; ?>

    <!-- Filters -->
    <form method="GET" class="mb-6 grid grid-cols-1 md:grid-cols-4 gap-4">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Date</label>
            <input type="date" name="date" value="<?php echo htmlspecialchars($date); ?>" class="w-full rounded-md border-gray-300 shadow-sm focus:border-red-500 focus:ring-red-500">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
            <select name="status" class="w-full rounded-md border-gray-300 shadow-sm focus:border-red-500 focus:ring-red-500">
                <option value="">All</option>
                <option value="present" <?php echo $status === 'present' ? 'selected' : ''; ?>>Present</option>
                <option value="absent" <?php echo $status === 'absent' ? 'selected' : ''; ?>>Absent</option>
                <option value="late" <?php echo $status === 'late' ? 'selected' : ''; ?>>Late</option>
                <option value="half-day" <?php echo $status === 'half-day' ? 'selected' : ''; ?>>Half Day</option>
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Employee</label>
            <input type="text" name="employee" value="<?php echo htmlspecialchars($employee); ?>" placeholder="Search by name or email" class="w-full rounded-md border-gray-300 shadow-sm focus:border-red-500 focus:ring-red-500">
        </div>
        <div class="flex items-end">
            <button type="submit" class="bg-red-600 text-white px-4 py-2 rounded hover:bg-red-700">
                <i class="fas fa-search mr-2"></i> Filter
            </button>
        </div>
    </form>

    <!-- Photo View Modal -->
    <div id="videoModal" class="fixed inset-0 bg-black bg-opacity-75 hidden items-center justify-center z-50">
        <div class="bg-white rounded-lg p-6 w-full max-w-6xl mx-4">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-xl font-semibold text-gray-900">Attendance Videos</h3>
                <button onclick="closeVideoModal()" class="text-gray-500 hover:text-gray-700">
                    <i class="fas fa-times text-2xl"></i>
                </button>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div id="clockInVideoContainer" class="hidden">
                    <h4 class="text-lg font-medium text-gray-700 mb-2">Clock In Video</h4>
                    <div class="relative w-full aspect-video bg-black rounded-lg overflow-hidden">
                        <video id="clockInVideo" controls class="w-full h-full">
                            <source src="" type="video/webm">
                            Your browser does not support the video tag.
                        </video>
                    </div>
                </div>
                <div id="clockOutVideoContainer" class="hidden">
                    <h4 class="text-lg font-medium text-gray-700 mb-2">Clock Out Video</h4>
                    <div class="relative w-full aspect-video bg-black rounded-lg overflow-hidden">
                        <video id="clockOutVideo" controls class="w-full h-full">
                            <source src="" type="video/webm">
                            Your browser does not support the video tag.
                        </video>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <style>
    /* Video modal styles */
    #videoModal {
        backdrop-filter: blur(4px);
    }

    #videoModal .bg-white {
        max-height: 90vh;
        overflow-y: auto;
    }

    #videoModal video {
        object-fit: contain;
        background-color: #000;
    }

    @media (max-width: 768px) {
        #videoModal .bg-white {
            margin: 1rem;
            padding: 1rem;
        }
        
        #videoModal .grid {
            grid-template-columns: 1fr;
        }
    }
    </style>

    <script>
    function viewVideo(clockInVideo, clockOutVideo) {
        const clockInVideoElement = document.getElementById('clockInVideo');
        const clockOutVideoElement = document.getElementById('clockOutVideo');
        const clockInContainer = document.getElementById('clockInVideoContainer');
        const clockOutContainer = document.getElementById('clockOutVideoContainer');
        
        // Reset videos
        clockInVideoElement.pause();
        clockOutVideoElement.pause();
        clockInVideoElement.currentTime = 0;
        clockOutVideoElement.currentTime = 0;
        
        // Set video sources and show containers
        if (clockInVideo) {
            clockInVideoElement.querySelector('source').src = clockInVideo;
            clockInVideoElement.load();
            clockInContainer.classList.remove('hidden');
        } else {
            clockInContainer.classList.add('hidden');
        }
        
        if (clockOutVideo) {
            clockOutVideoElement.querySelector('source').src = clockOutVideo;
            clockOutVideoElement.load();
            clockOutContainer.classList.remove('hidden');
        } else {
            clockOutContainer.classList.add('hidden');
        }
        
        // Show modal
        const modal = document.getElementById('videoModal');
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        
        // Prevent body scrolling
        document.body.style.overflow = 'hidden';
    }

    function closeVideoModal() {
        const modal = document.getElementById('videoModal');
        modal.classList.add('hidden');
        modal.classList.remove('flex');
        
        // Reset videos
        const clockInVideo = document.getElementById('clockInVideo');
        const clockOutVideo = document.getElementById('clockOutVideo');
        clockInVideo.pause();
        clockOutVideo.pause();
        clockInVideo.currentTime = 0;
        clockOutVideo.currentTime = 0;
        
        // Restore body scrolling
        document.body.style.overflow = '';
    }

    // Close modal when clicking outside
    document.getElementById('videoModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeVideoModal();
        }
    });

    // Close modal with Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeVideoModal();
        }
    });
    </script>

    <!-- Attendance Records Table -->
    <div class="overflow-x-auto">
        <table class="min-w-full bg-white">
            <thead>
                <tr class="bg-gray-100">
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Employee</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Clock In</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Clock Out</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Videos</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php foreach ($attendanceRecords as $record): ?>
                    <tr>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm font-medium text-gray-900">
                                <?php echo htmlspecialchars($record['first_name'] . ' ' . $record['last_name']); ?>
                            </div>
                            <div class="text-sm text-gray-500">
                                <?php echo htmlspecialchars($record['email']); ?>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            <?php echo date('M d, Y', strtotime($record['date'])); ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            <?php echo $record['clock_in'] ? date('h:i A', strtotime($record['clock_in'])) : '-'; ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            <?php echo $record['clock_out'] ? date('h:i A', strtotime($record['clock_out'])) : '-'; ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="px-2 py-1 text-xs font-semibold rounded-full <?php
                                switch($record['status']) {
                                    case 'present': echo 'bg-green-100 text-green-800'; break;
                                    case 'late': echo 'bg-yellow-100 text-yellow-800'; break;
                                    case 'absent': echo 'bg-red-100 text-red-800'; break;
                                    case 'half-day': echo 'bg-blue-100 text-blue-800'; break;
                                }
                            ?>">
                                <?php echo ucfirst($record['status']); ?>
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            <?php if ($record['video_path'] || $record['video_path_out']): ?>
                                <button onclick="viewVideo(
                                    '<?php echo htmlspecialchars($record['video_path']); ?>',
                                    '<?php echo htmlspecialchars($record['video_path_out']); ?>'
                                )" class="text-blue-600 hover:text-blue-800">
                                    <i class="fas fa-video"></i> View Videos
                                </button>
                            <?php else: ?>
                                No Videos
                            <?php endif; ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                            <form method="POST" class="inline-block">
                                <input type="hidden" name="attendance_id" value="<?php echo $record['id']; ?>">
                                <input type="hidden" name="status" value="present">
                                <button type="submit" class="text-green-600 hover:text-green-900 mr-2">
                                    <i class="fas fa-check"></i> Present
                                </button>
                            </form>
                            <form method="POST" class="inline-block">
                                <input type="hidden" name="attendance_id" value="<?php echo $record['id']; ?>">
                                <input type="hidden" name="status" value="absent">
                                <button type="submit" class="text-red-600 hover:text-red-900">
                                    <i class="fas fa-times"></i> Absent
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?> 