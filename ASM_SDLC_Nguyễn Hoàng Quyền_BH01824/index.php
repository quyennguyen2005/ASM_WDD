<?php
require_once 'db.php';
require_once 'config.php';

secure_session_start();

// Kiểm tra đăng nhập
if (!is_logged_in()) {
    // Nếu chưa đăng nhập, chuyển đến trang chào mừng
    header("Location: welcome.php");
    exit();
}

$current_user = get_current_user_info($conn);
if (!$current_user) {
    header("Location: login.php");
    exit();
}

// Lấy thống kê dashboard
$stats = [];
try {
    // Tổng số khóa học
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM courses WHERE status = ?");
    $active_status = STATUS_ACTIVE;
    $stmt->bind_param("s", $active_status);
    $stmt->execute();
    $result = $stmt->get_result();
    $stats['courses'] = $result->fetch_assoc()['total'];

    // Tổng số sinh viên
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM users WHERE role = 'student' AND status = ?");
    $stmt->bind_param("s", $active_status);
    $stmt->execute();
    $result = $stmt->get_result();
    $stats['students'] = $result->fetch_assoc()['total'];

    // Bài tập đang chờ
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM assignments WHERE status = ?");
    $pending_status = STATUS_PENDING;
    $stmt->bind_param("s", $pending_status);
    $stmt->execute();
    $result = $stmt->get_result();
    $stats['assignments'] = $result->fetch_assoc()['total'];

    // Tin nhắn chưa đọc
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM messages WHERE recipient_id = ? AND is_read = 0");
    $stmt->bind_param("i", $current_user['id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $stats['messages'] = $result->fetch_assoc()['total'];

} catch (Exception $e) {
    error_log("Error getting dashboard stats: " . $e->getMessage());
    $stats = ['courses' => 0, 'students' => 0, 'assignments' => 0, 'messages' => 0];
}

// Lấy hoạt động gần đây
$recent_activities = [];
try {
    $stmt = $conn->prepare("SELECT * FROM activity_logs ORDER BY created_at DESC LIMIT 10");
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $recent_activities[] = $row;
    }
} catch (Exception $e) {
    error_log("Error getting recent activities: " . $e->getMessage());
}

// Lấy deadline sắp tới
$upcoming_deadlines = [];
try {
    $stmt = $conn->prepare("
        SELECT a.*, c.name as course_name 
        FROM assignments a 
        JOIN courses c ON a.course_id = c.id 
        WHERE a.due_date >= CURDATE() 
        ORDER BY a.due_date ASC 
        LIMIT 5
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $upcoming_deadlines[] = $row;
    }
} catch (Exception $e) {
    error_log("Error getting upcoming deadlines: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo SITE_NAME; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .sidebar {
            transition: all 0.3s ease;
        }
        .sidebar.collapsed {
            width: 70px;
        }
        .sidebar.collapsed .sidebar-text {
            display: none;
        }
        .main-content {
            transition: all 0.3s ease;
        }
        .sidebar.collapsed + .main-content {
            margin-left: 70px;
        }
        .chart-container {
            position: relative;
            height: 300px;
        }
        .course-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
        .notification-badge {
            position: absolute;
            top: -5px;
            right: -5px;
        }
        .message-item:hover {
            background-color: #f3f4f6;
        }
        .tab-content {
            display: none;
        }
        .tab-content.active {
            display: block;
        }
        .btec-logo {
            position: relative;
        }
        .btec-logo::before {
            content: '';
            position: absolute;
            left: -8px;
            top: 50%;
            transform: translateY(-50%);
            width: 20px;
            height: 20px;
            background: radial-gradient(circle, #f97316 0%, #f97316 30%, transparent 70%);
            border-radius: 50%;
        }
        .btec-logo::after {
            content: '';
            position: absolute;
            left: -4px;
            top: 50%;
            transform: translateY(-50%);
            width: 12px;
            height: 12px;
            background: radial-gradient(circle, #f97316 0%, #f97316 40%, transparent 80%);
            border-radius: 50%;
        }
    </style>
</head>
<body class="bg-gray-100 font-sans">
    <div class="flex h-screen overflow-hidden">
        <!-- Sidebar -->
        <div class="sidebar bg-indigo-800 text-white w-64 flex-shrink-0 flex flex-col">
            <div class="p-4 flex items-center justify-between border-b border-indigo-700">
                <div class="flex items-center">
                    <div class="flex items-center">
                        <div class="text-orange-500 font-bold text-xl mr-2 btec-logo">BTEC</div>
                        <div class="text-blue-300 text-xs sidebar-text">
                            <div>Alliance with</div>
                            <div class="flex items-center">
                                <span class="text-blue-300">FP</span><span class="text-green-300">T</span>
                                <span class="text-blue-300 ml-1">Education</span>
                            </div>
                        </div>
                    </div>
                </div>
                <button id="toggleSidebar" class="text-white focus:outline-none">
                    <i class="fas fa-bars"></i>
                </button>
            </div>
            <div class="flex-grow overflow-y-auto">
                <nav class="p-4">
                    <div class="mb-6">
                        <p class="sidebar-text text-indigo-300 uppercase text-xs font-semibold mb-2">Chính</p>
                        <ul>
                            <li class="mb-1">
                                <a href="#" class="flex items-center p-2 text-white rounded hover:bg-indigo-700 active-tab" data-tab="dashboard">
                                    <i class="fas fa-tachometer-alt mr-3"></i>
                                    <span class="sidebar-text">Dashboard</span>
                                </a>
                            </li>
                            <li class="mb-1">
                                <a href="#" class="flex items-center p-2 text-white rounded hover:bg-indigo-700" data-tab="courses">
                                    <i class="fas fa-book mr-3"></i>
                                    <span class="sidebar-text">Khóa học</span>
                                </a>
                            </li>
                            <li class="mb-1">
                                <a href="#" class="flex items-center p-2 text-white rounded hover:bg-indigo-700" data-tab="students">
                                    <i class="fas fa-users mr-3"></i>
                                    <span class="sidebar-text">Sinh viên</span>
                                </a>
                            </li>
                        </ul>
                    </div>
                    <div class="mb-6">
                        <p class="sidebar-text text-indigo-300 uppercase text-xs font-semibold mb-2">Giao tiếp</p>
                        <ul>
                            <li class="mb-1">
                                <a href="#" class="flex items-center p-2 text-white rounded hover:bg-indigo-700" data-tab="messages">
                                    <i class="fas fa-envelope mr-3"></i>
                                    <span class="sidebar-text">Tin nhắn</span>
                                    <?php if ($stats['messages'] > 0): ?>
                                        <span class="ml-auto bg-red-500 text-white text-xs font-bold px-2 py-1 rounded-full notification-badge"><?php echo $stats['messages']; ?></span>
                                    <?php endif; ?>
                                </a>
                            </li>
                            <li class="mb-1">
                                <a href="#" class="flex items-center p-2 text-white rounded hover:bg-indigo-700" data-tab="forum">
                                    <i class="fas fa-comments mr-3"></i>
                                    <span class="sidebar-text">Diễn đàn</span>
                                </a>
                            </li>
                        </ul>
                    </div>
                    <div class="mb-6">
                        <p class="sidebar-text text-indigo-300 uppercase text-xs font-semibold mb-2">Đánh giá</p>
                        <ul>
                            <li class="mb-1">
                                <a href="#" class="flex items-center p-2 text-white rounded hover:bg-indigo-700" data-tab="assignments">
                                    <i class="fas fa-tasks mr-3"></i>
                                    <span class="sidebar-text">Bài tập</span>
                                </a>
                            </li>
                            <li class="mb-1">
                                <a href="#" class="flex items-center p-2 text-white rounded hover:bg-indigo-700" data-tab="quizzes">
                                    <i class="fas fa-question-circle mr-3"></i>
                                    <span class="sidebar-text">Bài kiểm tra</span>
                                </a>
                            </li>
                            <li class="mb-1">
                                <a href="#" class="flex items-center p-2 text-white rounded hover:bg-indigo-700" data-tab="grades">
                                    <i class="fas fa-chart-bar mr-3"></i>
                                    <span class="sidebar-text">Điểm số</span>
                                </a>
                            </li>
                        </ul>
                    </div>
                </nav>
            </div>
            <div class="p-4 border-t border-indigo-700">
                <div class="flex items-center">
                    <a href="profile.php" class="flex items-center hover:bg-indigo-600 rounded p-2 transition">
                        <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($current_user['full_name']); ?>&background=ffffff&color=cccccc&rounded=true" alt="User" class="w-10 h-10 rounded-full mr-3">
                        <div>
                            <div class="font-bold text-white"><?php echo htmlspecialchars($current_user['full_name']); ?></div>
                            <div class="text-indigo-200 text-sm"><?php echo ucfirst($current_user['role']); ?></div>
                        </div>
                    </a>
                </div>
                <div class="mt-3 sidebar-text">
                    <a href="auth.php?logout=1" class="flex items-center text-indigo-300 hover:text-white text-sm">
                        <i class="fas fa-sign-out-alt mr-2"></i>
                        <span>Đăng xuất</span>
                    </a>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="main-content flex-1 overflow-auto bg-gray-100 ml-64">
            <!-- Top Navigation -->
            <header class="bg-white shadow-sm">
                <div class="flex items-center justify-between p-4">
                    <h1 class="text-2xl font-bold text-gray-800" id="pageTitle">Dashboard</h1>
                    <div class="flex items-center space-x-4">
                        <div class="relative">
                            <input type="text" placeholder="Tìm kiếm..." class="pl-10 pr-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
                            <i class="fas fa-search absolute left-3 top-3 text-gray-400"></i>
                        </div>
                        <button class="text-gray-600 hover:text-gray-900 relative">
                            <i class="fas fa-bell text-xl"></i>
                            <?php if ($stats['messages'] > 0): ?>
                                <span class="absolute top-2 right-2 h-3 w-3 bg-red-500 rounded-full"></span>
                            <?php endif; ?>
                        </button>
                    </div>
                </div>
            </header>

            <!-- Dashboard Content -->
            <div class="p-6">
                <!-- Dashboard Tab -->
                <div class="tab-content active" id="dashboard-tab">
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
                        <div class="bg-white rounded-lg shadow p-6">
                            <div class="flex items-center">
                                <div class="p-3 rounded-full bg-indigo-100 text-indigo-600 mr-4">
                                    <i class="fas fa-book text-xl"></i>
                                </div>
                                <div>
                                    <p class="text-gray-500">Khóa học đang hoạt động</p>
                                    <h3 class="text-2xl font-bold"><?php echo $stats['courses']; ?></h3>
                                </div>
                            </div>
                        </div>
                        <div class="bg-white rounded-lg shadow p-6">
                            <div class="flex items-center">
                                <div class="p-3 rounded-full bg-green-100 text-green-600 mr-4">
                                    <i class="fas fa-users text-xl"></i>
                                </div>
                                <div>
                                    <p class="text-gray-500">Tổng sinh viên</p>
                                    <h3 class="text-2xl font-bold"><?php echo $stats['students']; ?></h3>
                                </div>
                            </div>
                        </div>
                        <div class="bg-white rounded-lg shadow p-6">
                            <div class="flex items-center">
                                <div class="p-3 rounded-full bg-blue-100 text-blue-600 mr-4">
                                    <i class="fas fa-tasks text-xl"></i>
                                </div>
                                <div>
                                    <p class="text-gray-500">Bài tập đang chờ</p>
                                    <h3 class="text-2xl font-bold"><?php echo $stats['assignments']; ?></h3>
                                </div>
                            </div>
                        </div>
                        <div class="bg-white rounded-lg shadow p-6">
                            <div class="flex items-center">
                                <div class="p-3 rounded-full bg-yellow-100 text-yellow-600 mr-4">
                                    <i class="fas fa-comments text-xl"></i>
                                </div>
                                <div>
                                    <p class="text-gray-500">Tin nhắn chưa đọc</p>
                                    <h3 class="text-2xl font-bold"><?php echo $stats['messages']; ?></h3>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
                        <div class="bg-white rounded-lg shadow p-6 lg:col-span-2">
                            <div class="flex justify-between items-center mb-4">
                                <h2 class="text-lg font-semibold">Thống kê khóa học</h2>
                                <select class="border rounded px-3 py-1 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                                    <option>7 ngày qua</option>
                                    <option>30 ngày qua</option>
                                    <option>90 ngày qua</option>
                                </select>
                            </div>
                            <div class="chart-container">
                                <canvas id="courseChart"></canvas>
                            </div>
                        </div>
                        <div class="bg-white rounded-lg shadow p-6">
                            <h2 class="text-lg font-semibold mb-4">Hoạt động gần đây</h2>
                            <div class="space-y-4">
                                <?php if (!empty($recent_activities)): ?>
                                    <?php foreach (array_slice($recent_activities, 0, 4) as $activity): ?>
                                        <div class="flex items-start">
                                            <div class="bg-indigo-100 p-2 rounded-full mr-3">
                                                <i class="fas fa-activity text-indigo-600"></i>
                                            </div>
                                            <div>
                                                <p class="text-sm font-medium"><?php echo htmlspecialchars($activity['action']); ?></p>
                                                <p class="text-xs text-gray-500"><?php echo format_date($activity['created_at']); ?></p>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <p class="text-sm text-gray-500">Chưa có hoạt động nào</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white rounded-lg shadow p-6 mb-6">
                        <div class="flex justify-between items-center mb-4">
                            <h2 class="text-lg font-semibold">Deadline sắp tới</h2>
                            <button class="text-indigo-600 hover:text-indigo-800 text-sm font-medium">Xem tất cả</button>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Khóa học</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Bài tập</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Hạn nộp</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Trạng thái</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Hành động</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php if (!empty($upcoming_deadlines)): ?>
                                        <?php foreach ($upcoming_deadlines as $deadline): ?>
                                            <tr>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?php echo htmlspecialchars($deadline['course_name']); ?></td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($deadline['title']); ?></td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo format_date($deadline['due_date']); ?></td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800">Đang chờ</span>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                    <a href="#" class="text-indigo-600 hover:text-indigo-900">Xem</a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="5" class="px-6 py-4 text-center text-sm text-gray-500">Không có deadline nào sắp tới</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Courses Tab -->
                <div class="tab-content" id="courses-tab">
                    <?php include 'courses.php'; ?>
                </div>

                <!-- Students Tab -->
                <div class="tab-content" id="students-tab">
                    <?php include 'students.php'; ?>
                </div>

                <!-- Messages Tab -->
                <div class="tab-content" id="messages-tab">
                    <?php include 'messages.php'; ?>
                </div>

                <!-- Forum Tab -->
                <div class="tab-content" id="forum-tab">
                    <?php include 'forum.php'; ?>
                </div>

                <!-- Assignments Tab -->
                <div class="tab-content" id="assignments-tab">
                    <?php include 'assignments.php'; ?>
                </div>

                <!-- Quizzes Tab -->
                <div class="tab-content" id="quizzes-tab">
                    <?php include 'quizzes.php'; ?>
                </div>

                <!-- Grades Tab -->
                <div class="tab-content" id="grades-tab">
                    <?php include 'grades.php'; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="main.js"></script>
</body>
</html>