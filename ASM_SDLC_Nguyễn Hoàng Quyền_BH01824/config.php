<?php
// Cấu hình hệ thống
define('SITE_NAME', 'BTEC - Hệ thống quản lý học tập');
define('SITE_URL', 'http://localhost/ASM%20SDLC/');
define('ADMIN_EMAIL', 'admin@btec.edu.vn');

// Cấu hình thời gian
date_default_timezone_set('Asia/Ho_Chi_Minh');

// Cấu hình upload
define('UPLOAD_PATH', 'uploads/');
define('MAX_FILE_SIZE', 10 * 1024 * 1024); // 10MB
define('ALLOWED_EXTENSIONS', ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'jpg', 'jpeg', 'png', 'mp4', 'avi']);

// Cấu hình phân trang
define('ITEMS_PER_PAGE', 10);

// Cấu hình session
ini_set('session.gc_maxlifetime', 3600); // 1 giờ
ini_set('session.cookie_lifetime', 3600);

// Cấu hình bảo mật
define('PASSWORD_MIN_LENGTH', 8);
define('LOGIN_MAX_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_TIME', 900); // 15 phút

// Cấu hình thông báo
define('NOTIFICATION_TYPES', [
    'assignment_due' => 'Bài tập sắp đến hạn',
    'grade_posted' => 'Điểm đã được đăng',
    'new_message' => 'Tin nhắn mới',
    'course_update' => 'Cập nhật khóa học',
    'forum_reply' => 'Trả lời diễn đàn'
]);

// Cấu hình vai trò người dùng
define('USER_ROLES', [
    'admin' => 'Quản trị viên',
    'teacher' => 'Giảng viên',
    'student' => 'Sinh viên'
]);

// Cấu hình trạng thái
define('STATUS_ACTIVE', 'active');
define('STATUS_INACTIVE', 'inactive');
define('STATUS_PENDING', 'pending');
define('STATUS_COMPLETED', 'completed');

// Hàm helper để format ngày tháng
function format_date($date, $format = 'd/m/Y H:i') {
    return date($format, strtotime($date));
}

// Hàm helper để format file size
function format_file_size($bytes) {
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } else {
        return $bytes . ' bytes';
    }
}

// Hàm helper để validate email
function is_valid_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

// Hàm helper để tạo slug
function create_slug($string) {
    $string = strtolower($string);
    $string = preg_replace('/[^a-z0-9\s-]/', '', $string);
    $string = preg_replace('/[\s-]+/', '-', $string);
    return trim($string, '-');
}

// Hàm helper để tạo mã ngẫu nhiên
function generate_random_code($length = 8) {
    $characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $code = '';
    for ($i = 0; $i < $length; $i++) {
        $code .= $characters[rand(0, strlen($characters) - 1)];
    }
    return $code;
}

// Hàm helper để log hoạt động
function log_activity($conn, $user_id, $action, $details = '') {
    try {
        // Kiểm tra xem bảng activity_logs có tồn tại không
        $result = $conn->query("SHOW TABLES LIKE 'activity_logs'");
        if ($result->num_rows > 0) {
            $stmt = $conn->prepare("INSERT INTO activity_logs (user_id, action, details, created_at) VALUES (?, ?, ?, NOW())");
            $stmt->bind_param("iss", $user_id, $action, $details);
            return $stmt->execute();
        } else {
            // Nếu bảng không tồn tại, chỉ log ra error log
            error_log("Activity log: User $user_id - $action - $details");
            return true;
        }
    } catch (Exception $e) {
        error_log("Error logging activity: " . $e->getMessage());
        return false;
    }
}
?>