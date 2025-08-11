<?php
require_once 'auth.php';

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
    <title><?php echo SITE_NAME; ?> - Đăng ký</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-50 min-h-screen flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-md w-full space-y-8">
        <div class="text-center">
            <div class="flex items-center justify-center mb-6">
                <div class="flex items-center">
                    <div class="text-orange-500 font-bold text-3xl mr-3">BTEC</div>
                    <div class="text-blue-600 text-sm">
                        <div>Alliance with</div>
                        <div class="flex items-center">
                            <span class="text-blue-600">FP</span><span class="text-green-600">T</span>
                            <span class="text-blue-600 ml-1">Education</span>
                        </div>
                    </div>
                </div>
            </div>
            <h2 class="text-3xl font-bold text-gray-900">Đăng ký tài khoản</h2>
            <p class="mt-2 text-sm text-gray-600">Tạo tài khoản mới để bắt đầu</p>
        </div>
        
        <div class="bg-white py-8 px-6 shadow-lg rounded-lg">
            <?php if (isset($error)): ?>
                <div class="mb-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
                    <i class="fas fa-exclamation-triangle mr-2"></i><?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($success)): ?>
                <div class="mb-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded">
                    <i class="fas fa-check-circle mr-2"></i><?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="" class="space-y-6">
                <input type="hidden" name="action" value="register">
                
                <div>
                    <label for="full_name" class="block text-sm font-medium text-gray-700">Họ và tên</label>
                    <div class="mt-1 relative">
                        <input type="text" id="full_name" name="full_name" required 
                               class="appearance-none relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 rounded-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 focus:z-10 sm:text-sm"
                               placeholder="Nhập họ và tên đầy đủ">
                        <div class="absolute inset-y-0 right-0 pr-3 flex items-center">
                            <i class="fas fa-user text-gray-400"></i>
                        </div>
                    </div>
                </div>
                
                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700">Email</label>
                    <div class="mt-1 relative">
                        <input type="email" id="email" name="email" required 
                               class="appearance-none relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 rounded-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 focus:z-10 sm:text-sm"
                               placeholder="Nhập email của bạn">
                        <div class="absolute inset-y-0 right-0 pr-3 flex items-center">
                            <i class="fas fa-envelope text-gray-400"></i>
                        </div>
                    </div>
                </div>
                
                <div>
                    <label for="role" class="block text-sm font-medium text-gray-700">Vai trò</label>
                    <div class="mt-1 relative">
                        <select id="role" name="role" required 
                                class="appearance-none relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 rounded-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 focus:z-10 sm:text-sm">
                            <option value="">Chọn vai trò</option>
                            <option value="student">Sinh viên</option>
                            <option value="teacher">Giảng viên</option>
                        </select>
                        <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                            <i class="fas fa-user-tag text-gray-400"></i>
                        </div>
                    </div>
                </div>
                
                <div>
                    <label for="password" class="block text-sm font-medium text-gray-700">Mật khẩu</label>
                    <div class="mt-1 relative">
                        <input type="password" id="password" name="password" required 
                               class="appearance-none relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 rounded-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 focus:z-10 sm:text-sm"
                               placeholder="Nhập mật khẩu (tối thiểu 8 ký tự)">
                        <div class="absolute inset-y-0 right-0 pr-3 flex items-center">
                            <i class="fas fa-lock text-gray-400"></i>
                        </div>
                    </div>
                    <div id="password-strength" class="mt-1 text-xs"></div>
                </div>
                
                <div>
                    <label for="confirm_password" class="block text-sm font-medium text-gray-700">Xác nhận mật khẩu</label>
                    <div class="mt-1 relative">
                        <input type="password" id="confirm_password" name="confirm_password" required 
                               class="appearance-none relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 rounded-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 focus:z-10 sm:text-sm"
                               placeholder="Nhập lại mật khẩu">
                        <div class="absolute inset-y-0 right-0 pr-3 flex items-center">
                            <i class="fas fa-lock text-gray-400"></i>
                        </div>
                    </div>
                </div>
                
                <div class="flex items-center">
                    <input id="agree_terms" name="agree_terms" type="checkbox" required
                           class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded">
                    <label for="agree_terms" class="ml-2 block text-sm text-gray-900">
                        Tôi đồng ý với <a href="#" class="text-indigo-600 hover:text-indigo-500">điều khoản sử dụng</a>
                    </label>
                </div>
                
                <div>
                    <button type="submit" 
                            class="group relative w-full flex justify-center py-2 px-4 border border-transparent text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                        <span class="absolute left-0 inset-y-0 flex items-center pl-3">
                            <i class="fas fa-user-plus text-indigo-500 group-hover:text-indigo-400"></i>
                        </span>
                        Đăng ký
                    </button>
                </div>
                
                <div class="text-center">
                    <p class="text-sm text-gray-600">
                        Đã có tài khoản? 
                        <a href="login.php" class="font-medium text-indigo-600 hover:text-indigo-500">
                            Đăng nhập ngay
                        </a>
                    </p>
                </div>
            </form>
        </div>
        
        <div class="text-center">
            <a href="welcome.php" class="text-sm text-gray-600 hover:text-gray-900">
                <i class="fas fa-arrow-left mr-1"></i>Quay lại trang chủ
            </a>
        </div>
    </div>
    
    <script>
        // Password strength checker
        document.getElementById('password').addEventListener('input', function() {
            const password = this.value;
            const strengthDiv = document.getElementById('password-strength');
            let strength = 0;
            let message = '';
            let color = '';
            
            if (password.length >= 8) strength++;
            if (password.match(/[a-z]/)) strength++;
            if (password.match(/[A-Z]/)) strength++;
            if (password.match(/[0-9]/)) strength++;
            if (password.match(/[^a-zA-Z0-9]/)) strength++;
            
            switch (strength) {
                case 0:
                case 1:
                    message = 'Rất yếu';
                    color = 'text-red-600';
                    break;
                case 2:
                    message = 'Yếu';
                    color = 'text-orange-600';
                    break;
                case 3:
                    message = 'Trung bình';
                    color = 'text-yellow-600';
                    break;
                case 4:
                    message = 'Mạnh';
                    color = 'text-blue-600';
                    break;
                case 5:
                    message = 'Rất mạnh';
                    color = 'text-green-600';
                    break;
            }
            
            strengthDiv.innerHTML = `<span class="${color}">Độ mạnh: ${message}</span>`;
        });
        
        // Password confirmation checker
        document.getElementById('confirm_password').addEventListener('input', function() {
            const password = document.getElementById('password').value;
            const confirmPassword = this.value;
            
            if (confirmPassword && password !== confirmPassword) {
                this.style.borderColor = '#ef4444';
            } else {
                this.style.borderColor = '#d1d5db';
            }
        });
    </script>
</body>
</html>