<?php
require_once 'db.php';
require_once 'config.php';

secure_session_start();

// Nếu đã đăng nhập, chuyển đến dashboard
if (is_logged_in()) {
    header("Location: index.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo SITE_NAME; ?> - Chào mừng</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .gradient-bg {
            background: #3b82f6; /* Màu xanh dương đơn giản */
        }
        .hero-pattern {
            background-image: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.1'%3E%3Ccircle cx='30' cy='30' r='2'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
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
<body class="bg-gray-50">
    <!-- Navigation -->
    <nav class="bg-white shadow-sm">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <div class="flex items-center">
                        <div class="text-orange-500 font-bold text-2xl mr-2 btec-logo">BTEC</div>
                        <div class="text-blue-600 text-sm">
                            <div>Alliance with</div>
                            <div class="flex items-center">
                                <span class="text-blue-600">FP</span><span class="text-green-600">T</span>
                                <span class="text-blue-600 ml-1">Education</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="flex items-center space-x-4">
                    <a href="login.php" class="text-gray-600 hover:text-gray-900 px-3 py-2 rounded-md text-sm font-medium">
                        <i class="fas fa-sign-in-alt mr-2"></i>Đăng nhập
                    </a>
                    <a href="register.php" class="bg-indigo-600 text-white px-4 py-2 rounded-md text-sm font-medium hover:bg-indigo-700">
                        <i class="fas fa-user-plus mr-2"></i>Đăng ký
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <div class="bg-blue-600 hero-pattern">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-24">
            <div class="text-center">
                <h1 class="text-4xl font-bold text-white mb-6">
                    BTEC - Hệ thống quản lý học tập thông minh
                </h1>
                <p class="text-xl text-white mb-8 max-w-3xl mx-auto">
                    BTEC Alliance với FPT Education giúp giảng viên và sinh viên kết nối, chia sẻ kiến thức và quản lý quá trình học tập một cách hiệu quả.
                </p>
                <div class="flex justify-center space-x-4">
                    <a href="register.php" class="bg-white text-indigo-600 px-8 py-3 rounded-lg font-semibold hover:bg-gray-100 transition duration-300">
                        <i class="fas fa-rocket mr-2"></i>Bắt đầu ngay
                    </a>
                    <a href="login.php" class="border-2 border-white text-white px-8 py-3 rounded-lg font-semibold hover:bg-white hover:text-indigo-600 transition duration-300">
                        <i class="fas fa-sign-in-alt mr-2"></i>Đăng nhập
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Features Section -->
    <div class="py-16 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-12">
                <h2 class="text-3xl font-bold text-gray-900 mb-4">Tính năng nổi bật</h2>
                <p class="text-lg text-gray-600">Khám phá những tính năng mạnh mẽ của EduManage</p>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <div class="text-center p-6">
                    <div class="bg-indigo-100 w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-book text-2xl text-indigo-600"></i>
                    </div>
                    <h3 class="text-xl font-semibold text-gray-900 mb-2">Quản lý khóa học</h3>
                    <p class="text-gray-600">Tạo và quản lý khóa học một cách dễ dàng với giao diện thân thiện</p>
                </div>
                
                <div class="text-center p-6">
                    <div class="bg-green-100 w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-tasks text-2xl text-green-600"></i>
                    </div>
                    <h3 class="text-xl font-semibold text-gray-900 mb-2">Bài tập & Đánh giá</h3>
                    <p class="text-gray-600">Giao bài tập, chấm điểm và theo dõi tiến độ học tập</p>
                </div>
                
                <div class="text-center p-6">
                    <div class="bg-blue-100 w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-comments text-2xl text-blue-600"></i>
                    </div>
                    <h3 class="text-xl font-semibold text-gray-900 mb-2">Giao tiếp</h3>
                    <p class="text-gray-600">Tin nhắn, diễn đàn và thông báo giúp kết nối mọi người</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Stats Section -->
    <div class="bg-gray-50 py-16">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-8 text-center">
                <div>
                    <div class="text-3xl font-bold text-indigo-600 mb-2">1000+</div>
                    <div class="text-gray-600">Sinh viên</div>
                </div>
                <div>
                    <div class="text-3xl font-bold text-green-600 mb-2">50+</div>
                    <div class="text-gray-600">Khóa học</div>
                </div>
                <div>
                    <div class="text-3xl font-bold text-blue-600 mb-2">100+</div>
                    <div class="text-gray-600">Giảng viên</div>
                </div>
                <div>
                    <div class="text-3xl font-bold text-purple-600 mb-2">24/7</div>
                    <div class="text-gray-600">Hỗ trợ</div>
                </div>
            </div>
        </div>
    </div>

    <!-- CTA Section -->
    <div class="bg-indigo-600 py-16">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
            <h2 class="text-3xl font-bold text-white mb-4">Sẵn sàng bắt đầu?</h2>
            <p class="text-xl text-indigo-100 mb-8">Tham gia cùng chúng tôi ngay hôm nay!</p>
            <div class="flex justify-center space-x-4">
                <a href="register.php" class="bg-white text-indigo-600 px-8 py-3 rounded-lg font-semibold hover:bg-gray-100 transition duration-300">
                    <i class="fas fa-user-plus mr-2"></i>Đăng ký miễn phí
                </a>
                <a href="login.php" class="border-2 border-white text-white px-8 py-3 rounded-lg font-semibold hover:bg-white hover:text-indigo-600 transition duration-300">
                    <i class="fas fa-sign-in-alt mr-2"></i>Đăng nhập
                </a>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="bg-gray-800 text-white py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center">
                <p>&copy; 2024 BTEC Alliance with FPT Education. Tất cả quyền được bảo lưu.</p>
                <div class="mt-4 space-x-4">
                    <a href="#" class="text-gray-300 hover:text-white">Về chúng tôi</a>
                    <a href="#" class="text-gray-300 hover:text-white">Liên hệ</a>
                    <a href="#" class="text-gray-300 hover:text-white">Hỗ trợ</a>
                </div>
            </div>
        </div>
    </footer>
</body>
</html> 