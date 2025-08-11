<?php
require_once 'db.php';
require_once 'config.php';

secure_session_start();

// Kiểm tra đăng nhập và quyền admin
if (!is_logged_in() || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$current_user = get_current_user_info($conn);
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Trang quản trị Admin</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
</head>
<body class="bg-gray-100 min-h-screen">
    <div class="flex min-h-screen">
        <!-- Sidebar -->
        <div class="w-64 bg-indigo-700 text-white flex flex-col">
            <div class="p-6 text-2xl font-bold border-b border-indigo-600">
                <i class="fas fa-user-shield mr-2"></i>Admin Panel
            </div>
            <nav class="flex-1 p-4 space-y-2">
                <a href="admin.php" class="block py-2 px-4 rounded hover:bg-indigo-600"><i class="fas fa-home mr-2"></i>Dashboard</a>
                <a href="users.php" class="block py-2 px-4 rounded hover:bg-indigo-600"><i class="fas fa-users mr-2"></i>Quản lý người dùng</a>
                <a href="courses.php" class="block py-2 px-4 rounded hover:bg-indigo-600"><i class="fas fa-book mr-2"></i>Quản lý khóa học</a>
                <a href="assignments.php" class="block py-2 px-4 rounded hover:bg-indigo-600"><i class="fas fa-tasks mr-2"></i>Quản lý bài tập</a>
                <a href="quizzes.php" class="block py-2 px-4 rounded hover:bg-indigo-600"><i class="fas fa-question-circle mr-2"></i>Quản lý quiz</a>
                <a href="grades.php" class="block py-2 px-4 rounded hover:bg-indigo-600"><i class="fas fa-graduation-cap mr-2"></i>Quản lý điểm</a>
                <a href="logout.php" class="block py-2 px-4 rounded hover:bg-indigo-600"><i class="fas fa-sign-out-alt mr-2"></i>Đăng xuất</a>
            </nav>
        </div>
        <!-- Main Content -->
        <div class="flex-1 p-8">
            <h1 class="text-3xl font-bold text-indigo-700 mb-6">Chào mừng, <?php echo htmlspecialchars($current_user['full_name']); ?>!</h1>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div class="bg-white rounded-lg shadow p-6 text-center">
                    <i class="fas fa-users fa-2x text-indigo-600 mb-2"></i>
                    <div class="text-xl font-bold">
                        <?php
                        $result = $conn->query("SELECT COUNT(*) as total FROM users");
                        $row = $result->fetch_assoc();
                        echo $row['total'];
                        ?>
                    </div>
                    <div class="text-gray-600">Tổng số người dùng</div>
                </div>
                <div class="bg-white rounded-lg shadow p-6 text-center">
                    <i class="fas fa-book fa-2x text-indigo-600 mb-2"></i>
                    <div class="text-xl font-bold">
                        <?php
                        $result = $conn->query("SELECT COUNT(*) as total FROM courses");
                        $row = $result->fetch_assoc();
                        echo $row['total'];
                        ?>
                    </div>
                    <div class="text-gray-600">Tổng số khóa học</div>
                </div>
                <div class="bg-white rounded-lg shadow p-6 text-center">
                    <i class="fas fa-tasks fa-2x text-indigo-600 mb-2"></i>
                    <div class="text-xl font-bold">
                        <?php
                        $result = $conn->query("SELECT COUNT(*) as total FROM assignments");
                        $row = $result->fetch_assoc();
                        echo $row['total'];
                        ?>
                    </div>
                    <div class="text-gray-600">Tổng số bài tập</div>
                </div>
            </div>
            <div class="mt-10">
                <h2 class="text-xl font-bold mb-4 text-indigo-700">Hoạt động gần đây</h2>
                <div class="bg-white rounded-lg shadow p-4">
                    <ul class="divide-y divide-gray-200">
                        <?php
                        $result = $conn->query("SELECT * FROM activity_log ORDER BY created_at DESC LIMIT 10");
                        while ($log = $result->fetch_assoc()):
                        ?>
                        <li class="py-2">
                            <span class="font-semibold"><?php echo htmlspecialchars($log['action']); ?></span>
                            <span class="text-gray-500 text-sm">- <?php echo htmlspecialchars($log['details']); ?> (<?php echo $log['created_at']; ?>)</span>
                        </li>
                        <?php endwhile; ?>
                    </ul>
                </div>
            </div>
        </div>
    </div>
    <script src="main.js"></script>
</body>
</html>