<?php
require_once 'config/database.php';
require_once 'includes/header.php';

$role = getUserRole();
if ($role !== 'employee') {
    header('Location: unauthorized.php');
    exit();
}

$userId = $_SESSION['user_id'];
$conn = getDBConnection();

// Function to delete old attendance photos
function deleteOldAttendancePhotos($conn, $userId) {
    $yesterday = date('Y-m-d', strtotime('-1 day'));
    
    // Get yesterday's attendance record
    $stmt = $conn->prepare("SELECT photo_path_front, photo_path_left, photo_path_right FROM attendance WHERE employee_id = ? AND date = ?");
    $stmt->execute([$userId, $yesterday]);
    $oldAttendance = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($oldAttendance) {
        // Delete all three photos if they exist
        $photoPaths = [
            $oldAttendance['photo_path_front'],
            $oldAttendance['photo_path_left'],
            $oldAttendance['photo_path_right']
        ];
        
        foreach ($photoPaths as $path) {
            if ($path && file_exists($path)) {
                unlink($path);
            }
        }
        
        // Update the database record to remove the photo paths
        $stmt = $conn->prepare("UPDATE attendance SET photo_path_front = NULL, photo_path_left = NULL, photo_path_right = NULL WHERE employee_id = ? AND date = ?");
        $stmt->execute([$userId, $yesterday]);
    }
}

// Check if attendance is already marked for today
$stmt = $conn->prepare("SELECT * FROM attendance WHERE employee_id = ? AND date = CURDATE()");
$stmt->execute([$userId]);
$attendance = $stmt->fetch(PDO::FETCH_ASSOC);

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $today = date('Y-m-d');
    $currentTime = date('H:i:s');
    
    if ($action === 'clock_in' || $action === 'clock_out') {
        try {
            $conn->beginTransaction();
            
            // Handle video upload
            if (!isset($_FILES['attendance_video']) || empty($_FILES['attendance_video']['tmp_name'])) {
                throw new Exception('Please record a video before submitting.');
            }

            $uploadDir = 'uploads/attendance_videos/';
                if (!file_exists($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }
                
            $videoFileName = $userId . '_' . $action . '_' . date('Y-m-d_H-i-s') . '.webm';
            $videoPath = $uploadDir . $videoFileName;
            
            if (!move_uploaded_file($_FILES['attendance_video']['tmp_name'], $videoPath)) {
                throw new Exception('Failed to upload video.');
            }

            if ($action === 'clock_in') {
                // Check if already clocked in today
            $stmt = $conn->prepare("SELECT * FROM attendance WHERE employee_id = ? AND date = ?");
            $stmt->execute([$userId, $today]);
                $existingAttendance = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($existingAttendance) {
                    throw new Exception('You have already clocked in today.');
                }

                // Determine if late based on time
                $status = 'present';
                $workStartTime = '09:00:00';
                if ($currentTime > $workStartTime) {
                    $status = 'late';
                }

                // Create new attendance record
                $stmt = $conn->prepare("
                    INSERT INTO attendance (
                        employee_id, 
                        date, 
                        clock_in, 
                        status, 
                        video_path
                    ) VALUES (?, ?, NOW(), ?, ?)
                ");
                $stmt->execute([$userId, $today, $status, $videoPath]);
                
                $statusMessage = $status === 'late' ? 'You are marked as late.' : '';
                $message = 'Clock in successful! ' . $statusMessage;
            } else {
                // Clock out
                // Check if clocked in today
                $stmt = $conn->prepare("SELECT * FROM attendance WHERE employee_id = ? AND date = ?");
                $stmt->execute([$userId, $today]);
                $existingAttendance = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$existingAttendance) {
                    throw new Exception('You must clock in before clocking out.');
                }
                
                if ($existingAttendance['clock_out']) {
                    throw new Exception('You have already clocked out today.');
                }

                $stmt = $conn->prepare("
                    UPDATE attendance 
                    SET clock_out = NOW(),
                        video_path_out = ?
                    WHERE employee_id = ? AND date = ?
                ");
                $stmt->execute([$videoPath, $userId, $today]);
                $message = 'Clock out successful!';
            }

            // Create notification for managers
            $stmt = $conn->prepare("
                INSERT INTO notifications (user_id, title, message, type) 
                SELECT id, 'New Attendance Video', ?, 'info'
                FROM users 
                WHERE role_id IN (SELECT id FROM roles WHERE role_name IN ('admin', 'manager'))
            ");
            $notificationMessage = sprintf(
                "Employee %s has %s with a new video. ",
                $_SESSION['username'],
                $action
            );
            $stmt->execute([$notificationMessage]);
            
            $conn->commit();
            
            // Redirect to refresh the page
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit();
            
        } catch (Exception $e) {
            $conn->rollBack();
            $error = $e->getMessage();
        }
    }
}

// Get today's attendance record
$stmt = $conn->prepare("SELECT * FROM attendance WHERE employee_id = ? AND date = ?");
$stmt->execute([$userId, date('Y-m-d')]);
$attendance = $stmt->fetch(PDO::FETCH_ASSOC);
?>

<div class="bg-white shadow rounded-lg p-6">
    <h2 class="text-2xl font-bold mb-6">Mark Attendance</h2>
    
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
    
    <div class="bg-gray-50 p-6 rounded-lg">
        <h3 class="text-lg font-semibold mb-4">Today's Status</h3>
        <?php if ($attendance): ?>
            <div class="space-y-4">
                <p class="text-gray-700">
                    <span class="font-semibold">Status:</span> 
                    <span class="px-2 py-1 rounded-full text-sm <?php
                        switch($attendance['status']) {
                            case 'present': echo 'bg-green-100 text-green-800'; break;
                            case 'late': echo 'bg-yellow-100 text-yellow-800'; break;
                            case 'absent': echo 'bg-red-100 text-red-800'; break;
                            case 'half-day': echo 'bg-blue-100 text-blue-800'; break;
                        }
                    ?>">
                    <?php echo ucfirst($attendance['status']); ?>
                    </span>
                </p>
                <p class="text-gray-700">
                    <span class="font-semibold">Clock In:</span> 
                    <?php echo $attendance['clock_in'] ? date('h:i A', strtotime($attendance['clock_in'])) : 'Not clocked in'; ?>
                </p>
                <p class="text-gray-700">
                    <span class="font-semibold">Clock Out:</span> 
                    <?php echo $attendance['clock_out'] ? date('h:i A', strtotime($attendance['clock_out'])) : 'Not clocked out'; ?>
                </p>
                
                <?php if ($attendance['video_path']): ?>
                    <div class="mt-4">
                        <span class="font-semibold">Clock In Video:</span>
                        <div class="mt-2">
                            <video controls class="w-full max-w-2xl rounded-lg shadow-md">
                                <source src="<?php echo htmlspecialchars($attendance['video_path']); ?>" type="video/webm">
                                Your browser does not support the video tag.
                            </video>
                            </div>
                            </div>
                <?php endif; ?>

                <?php if ($attendance['video_path_out']): ?>
                    <div class="mt-4">
                        <span class="font-semibold">Clock Out Video:</span>
                        <div class="mt-2">
                            <video controls class="w-full max-w-2xl rounded-lg shadow-md">
                                <source src="<?php echo htmlspecialchars($attendance['video_path_out']); ?>" type="video/webm">
                                Your browser does not support the video tag.
                            </video>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            
            <?php if (!$attendance['clock_out']): ?>
                <div class="mt-6">
                    <button id="start-clock-out" class="bg-red-600 text-white px-6 py-3 rounded-lg hover:bg-red-700 transition-colors">
                        <i class="fas fa-sign-out-alt mr-2"></i> Clock Out
                    </button>
                </div>
            <?php else: ?>
                <div class="mt-6">
                    <p class="text-green-600 font-semibold">You have completed your attendance for today.</p>
                    <div class="mt-4 p-4 bg-gray-50 rounded-lg">
                        <p class="text-gray-600">
                            <span class="font-semibold">Clock In:</span> 
                            <?php echo date('h:i A', strtotime($attendance['clock_in'])); ?>
                        </p>
                        <p class="text-gray-600">
                            <span class="font-semibold">Clock Out:</span> 
                            <?php echo date('h:i A', strtotime($attendance['clock_out'])); ?>
                        </p>
                        <p class="text-gray-600 mt-2">
                            <span class="font-semibold">Total Hours:</span> 
                            <?php 
                                $clockIn = new DateTime($attendance['clock_in']);
                                $clockOut = new DateTime($attendance['clock_out']);
                                $interval = $clockIn->diff($clockOut);
                                echo $interval->format('%H hours %i minutes');
                            ?>
                        </p>
                    </div>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <p class="text-gray-700 mb-4">You haven't marked your attendance today.</p>
            <div class="mt-6">
                <button id="start-clock-in" class="bg-green-600 text-white px-6 py-3 rounded-lg hover:bg-green-700 transition-colors">
                    <i class="fas fa-sign-in-alt mr-2"></i> Clock In
                            </button>
                        </div>
        <?php endif; ?>
                        </div>
                    </div>

<!-- Video Recording Modal -->
<div id="video-modal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center">
    <div class="bg-white rounded-lg p-6 max-w-2xl w-full mx-4">
        <div class="relative w-64 h-64 mx-auto">
            <video id="preview-video" class="w-full h-full rounded-full object-cover" autoplay></video>
            <div id="scanning-circle" class="absolute inset-0 border-4 border-blue-500 rounded-full animate-pulse hidden"></div>
            <div id="success-circle" class="absolute inset-0 flex items-center justify-center hidden">
                <div class="w-32 h-32 rounded-full border-4 border-green-500 animate-success-circle">
                    <div class="w-full h-full flex items-center justify-center">
                        <svg class="w-16 h-16 text-green-500 animate-checkmark" viewBox="0 0 52 52">
                            <circle class="checkmark-circle" cx="26" cy="26" r="25" fill="none"/>
                            <path class="checkmark-check" fill="none" d="M14.1 27.2l7.1 7.2 16.7-16.8"/>
                        </svg>
                    </div>
                        </div>
                    </div>
            <div id="direction-indicator" class="absolute inset-0 flex items-center justify-center">
                <div class="text-4xl text-white bg-black bg-opacity-50 rounded-full p-4">
                    <i class="fas fa-arrow-up"></i>
                </div>
            </div>
        </div>
        <div class="mt-4 text-center">
            <div id="recording-status" class="text-gray-600 mb-4">Recording in progress...</div>
            <div class="flex justify-center space-x-4">
                <button id="stop-recording" class="bg-red-600 text-white px-4 py-2 rounded hover:bg-red-700">
                    Stop Recording
                </button>
            </div>
        </div>
    </div>
</div>

<style>
@keyframes success-circle {
    0% {
        transform: scale(0);
        opacity: 0;
    }
    50% {
        transform: scale(1.2);
        opacity: 1;
    }
    100% {
        transform: scale(1);
        opacity: 1;
    }
}

@keyframes checkmark {
    0% {
        stroke-dashoffset: 100;
        opacity: 0;
    }
    100% {
        stroke-dashoffset: 0;
        opacity: 1;
    }
}

.animate-success-circle {
    animation: success-circle 0.5s ease-out forwards;
}

.animate-checkmark {
    animation: checkmark 0.5s ease-out 0.3s forwards;
}

.checkmark-circle {
    stroke-dasharray: 166;
    stroke-dashoffset: 166;
    stroke-width: 2;
    stroke-miterlimit: 10;
    stroke: currentColor;
    fill: none;
}

.checkmark-check {
    stroke-dasharray: 48;
    stroke-dashoffset: 48;
    stroke-width: 2;
    stroke-miterlimit: 10;
    stroke: currentColor;
}

/* Add Font Awesome if not already included */
@import url('https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css');
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const videoModal = document.getElementById('video-modal');
    const previewVideo = document.getElementById('preview-video');
    const scanningCircle = document.getElementById('scanning-circle');
    const successCircle = document.getElementById('success-circle');
    const directionIndicator = document.getElementById('direction-indicator');
    const recordingStatus = document.getElementById('recording-status');
    const stopRecordingBtn = document.getElementById('stop-recording');
    const startClockInBtn = document.getElementById('start-clock-in');
    const startClockOutBtn = document.getElementById('start-clock-out');
    
    let mediaRecorder = null;
    let recordedChunks = [];
    let currentAction = '';
    let currentDirection = 0;
    
    const directions = [
        { icon: 'fa-arrow-up', text: 'Look Up' },
        { icon: 'fa-arrow-down', text: 'Look Down' },
        { icon: 'fa-arrow-left', text: 'Look Left' },
        { icon: 'fa-arrow-right', text: 'Look Right' }
    ];
    
    function updateDirection() {
        if (currentDirection < directions.length) {
            const direction = directions[currentDirection];
            directionIndicator.innerHTML = `
                <div class="text-4xl text-white bg-black bg-opacity-50 rounded-full p-4">
                    <i class="fas ${direction.icon}"></i>
                </div>
            `;
            recordingStatus.textContent = direction.text;
            
            // Move to next direction after 2 seconds
            setTimeout(() => {
                currentDirection++;
                if (currentDirection < directions.length) {
                    updateDirection();
                } else {
                    stopRecording();
                }
            }, 2000);
        }
    }
    
    async function startRecording(action) {
        currentAction = action;
        currentDirection = 0;
        
        try {
            const stream = await navigator.mediaDevices.getUserMedia({ 
                video: { 
                    width: { ideal: 640 },
                    height: { ideal: 640 },
                    facingMode: 'user'
                } 
            });
            
            previewVideo.srcObject = stream;
            videoModal.classList.remove('hidden');
            videoModal.classList.add('flex');
            scanningCircle.classList.remove('hidden');
            directionIndicator.classList.remove('hidden');
            
            mediaRecorder = new MediaRecorder(stream, {
                mimeType: 'video/webm;codecs=vp9'
            });
            
            mediaRecorder.ondataavailable = (event) => {
                if (event.data.size > 0) {
                    recordedChunks.push(event.data);
                }
            };
            
            mediaRecorder.onstop = () => {
                const blob = new Blob(recordedChunks, { type: 'video/webm' });
                const formData = new FormData();
                formData.append('action', currentAction);
                formData.append('attendance_video', blob, 'attendance.webm');
                
                // Submit the form
                fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                }).then(response => {
                    if (response.ok) {
                        // Update UI to show success
                        recordingStatus.textContent = 'Attendance marked successfully!';
                        stopRecordingBtn.style.display = 'none'; // Hide the button

                        // Reload page after a short delay to show updated status
                        setTimeout(() => {
                            videoModal.classList.add('hidden');
                            window.location.reload();
                        }, 2000);
                    } else {
                        throw new Error('Network response was not ok');
                    }
                }).catch(error => {
                    console.error('Error:', error);
                    alert('Failed to submit attendance. Please try again.');
                    // Reload on error to reset the UI and allow user to try again
                    window.location.reload();
                });
            };
            
            // Start recording
            mediaRecorder.start();
            updateDirection();
            
        } catch (err) {
            console.error('Error accessing camera:', err);
            alert('Error accessing camera. Please make sure you have granted camera permissions.');
        }
    }
    
    function stopRecording() {
        if (mediaRecorder && mediaRecorder.state === 'recording') {
            mediaRecorder.stop();
            scanningCircle.classList.add('hidden');
            directionIndicator.classList.add('hidden');
            successCircle.classList.remove('hidden');
            recordingStatus.textContent = 'Processing...';
            stopRecordingBtn.disabled = true;
            
            // Stop all tracks
            previewVideo.srcObject.getTracks().forEach(track => track.stop());
        }
    }
    
    startClockInBtn?.addEventListener('click', () => startRecording('clock_in'));
    startClockOutBtn?.addEventListener('click', () => startRecording('clock_out'));
    stopRecordingBtn.addEventListener('click', stopRecording);
});
</script>

<?php require_once 'includes/footer.php'; ?> 