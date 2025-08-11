<?php
require_once 'db.php';
require_once 'config.php';

secure_session_start();

// Xử lý đăng nhập
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] == 'login') {
        $email = validate_input($_POST['email']);
        $password = $_POST['password'];
        
        if (empty($email) || empty($password)) {
            $error = "Vui lòng nhập đầy đủ thông tin.";
        } else {
            // Kiểm tra số lần đăng nhập thất bại
            $attempt_key = 'login_attempts_' . md5($email);
            $attempts = isset($_SESSION[$attempt_key]) ? $_SESSION[$attempt_key] : 0;
            $lockout_time = isset($_SESSION[$attempt_key . '_time']) ? $_SESSION[$attempt_key . '_time'] : 0;
            
            if ($attempts >= LOGIN_MAX_ATTEMPTS && (time() - $lockout_time) < LOGIN_LOCKOUT_TIME) {
                $remaining_time = LOGIN_LOCKOUT_TIME - (time() - $lockout_time);
                $error = "Tài khoản đã bị khóa. Vui lòng thử lại sau " . ceil($remaining_time / 60) . " phút.";
            } else {
                // Reset attempts nếu đã hết thời gian khóa
                if ((time() - $lockout_time) >= LOGIN_LOCKOUT_TIME) {
                    $_SESSION[$attempt_key] = 0;
                }
                
                global $conn;
                $stmt = $conn->prepare("SELECT id, email, password, role, full_name, status FROM users WHERE email = ?");
                $stmt->bind_param("s", $email);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows == 1) {
                    $user = $result->fetch_assoc();
                    
                    if ($user['status'] != STATUS_ACTIVE) {
                        $error = "Tài khoản đã bị khóa. Vui lòng liên hệ quản trị viên.";
                    } elseif (password_verify($password, $user['password'])) {
                        // Đăng nhập thành công
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['user_email'] = $user['email'];
                        $_SESSION['user_role'] = $user['role'];
                        $_SESSION['user_name'] = $user['full_name'];
                        
                        // Reset login attempts
                        $_SESSION[$attempt_key] = 0;
                        
                        // Log activity
                        log_activity($conn, $user['id'], 'login', 'Đăng nhập thành công');
                        
                        // Redirect
                        header("Location: index.php");
                        exit();
                    } else {
                        // Đăng nhập thất bại
                        $_SESSION[$attempt_key] = $attempts + 1;
                        $_SESSION[$attempt_key . '_time'] = time();
                        $error = "Email hoặc mật khẩu không đúng.";
                    }
                } else {
                    $error = "Email hoặc mật khẩu không đúng.";
                }
            }
        }
    }
    
    // Xử lý đăng ký
    elseif ($_POST['action'] == 'register') {
        $full_name = trim($_POST['full_name']);
        $email = trim($_POST['email']);
        $role = $_POST['role'];
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $status = 'active';
        $created_at = date('Y-m-d H:i:s');

        // Kiểm tra email đã tồn tại chưa
        global $conn;
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
            $error = "Email đã tồn tại!";
        } else {
            $stmt = $conn->prepare("INSERT INTO users (full_name, email, password, role, status, created_at) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssss", $full_name, $email, $password, $role, $status, $created_at);
            if ($stmt->execute()) {
                $success = "Đăng ký thành công! Bạn có thể đăng nhập.";
            } else {
                $error = "Có lỗi xảy ra khi đăng ký: " . $stmt->error;
            }
        }
    }
}

// Xử lý đăng xuất
if (isset($_GET['logout'])) {
    if (is_logged_in()) {
        global $conn;
        log_activity($conn, $_SESSION['user_id'], 'logout', 'Đăng xuất');
        session_destroy();
    }
    header("Location: login.php");
    exit();
}
?>
