<?php
// Cấu hình database
$servername = "localhost";
$username = "root";
$password = ""; 
$dbname = "sdlcdb";

// Tạo kết nối với xử lý lỗi tốt hơn
try {
    $conn = new mysqli($servername, $username, $password, $dbname);
    
    // Kiểm tra kết nối
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }
    
    // Đặt charset để hỗ trợ tiếng Việt
    $conn->set_charset("utf8mb4");
    
} catch (Exception $e) {
    error_log("Database connection error: " . $e->getMessage());
    die("Không thể kết nối đến cơ sở dữ liệu. Vui lòng thử lại sau.");
}

// Hàm helper để escape SQL injection
function escape_string($conn, $string) {
    return $conn->real_escape_string($string);
}

// Hàm helper để validate input
function validate_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

// Hàm helper để tạo session an toàn
function secure_session_start() {
    if (session_status() == PHP_SESSION_NONE) {
        ini_set('session.cookie_httponly', 1);
        ini_set('session.use_only_cookies', 1);
        ini_set('session.cookie_secure', isset($_SERVER['HTTPS']));
        session_start();
    }
}

// Hàm helper để kiểm tra đăng nhập
function is_logged_in() {
    secure_session_start();
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

// Hàm helper để lấy thông tin user hiện tại
function get_current_user_info($conn) {
    if (!is_logged_in()) {
        return null;
    }
    
    $user_id = $_SESSION['user_id'];
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    return $result->fetch_assoc();
}
?>