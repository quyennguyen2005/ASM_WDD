<?php
require_once 'db.php';
require_once 'config.php';

secure_session_start();

// Kiểm tra đăng nhập
if (!is_logged_in()) {
    header("Location: login.php");
    exit();
}

$current_user = get_current_user_info($conn);
if (!$current_user) {
    header("Location: login.php");
    exit();
}

// Xử lý cập nhật thông tin
$message = '';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name']);
    $birthday = trim($_POST['birthday']);
    $phone = trim($_POST['phone']);
    $email = trim($_POST['email']);
    $address = trim($_POST['address']);

    if (empty($full_name) || empty($birthday) || empty($phone) || empty($email) || empty($address)) {
        $error = "Vui lòng nhập đầy đủ thông tin!";
    } else {
        $stmt = $conn->prepare("UPDATE users SET full_name=?, birthday=?, phone=?, email=?, address=? WHERE id=?");
        $stmt->bind_param("sssssi", $full_name, $birthday, $phone, $email, $address, $current_user['id']);
        if ($stmt->execute()) {
            $message = "Cập nhật thông tin thành công!";
            $current_user = get_current_user_info($conn);
        } else {
            $error = "Có lỗi xảy ra khi cập nhật: " . $stmt->error;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Thông tin tài khoản</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <style>
        body {
            background: #18191a;
            color: #fff;
            font-family: 'Segoe UI', Arial, sans-serif;
        }
        .profile-container {
            background: #23272f;
            border-radius: 12px;
            box-shadow: 0 4px 24px rgba(80,112,255,0.08);
            padding: 2.5rem 2rem;
            max-width: 600px;
            margin: 2rem auto;
            display: flex;
            gap: 2.5rem;
            border: 1px solid #333;
            flex-direction: column;
        }
        .profile-header {
            display: flex;
            align-items: center;
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }
        .profile-avatar {
            width: 90px;
            height: 90px;
            border-radius: 50%;
            border: 4px solid #6366f1;
            object-fit: cover;
            background: #222;
        }
        .profile-info {
            flex: 1;
        }
        .profile-name {
            font-size: 1.5rem;
            font-weight: bold;
            color: #fff;
            margin-bottom: 0.25rem;
        }
        .profile-role {
            color: #6366f1;
            font-size: 1rem;
            margin-bottom: 0.5rem;
        }
        .profile-message {
            margin-bottom: 1rem;
            padding: 0.75rem 1rem;
            border-radius: 0.5rem;
            font-size: 1rem;
            text-align: center;
        }
        .profile-message.success {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #34d399;
        }
        .profile-message.error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #f87171;
        }
        .profile-form label {
            color: #f3f4f6;
            font-weight: 500;
            margin-bottom: 0.25rem;
            display: block;
        }
        .profile-form input {
            width: 100%;
            border: 1px solid #d1d5db;
            border-radius: 0.5rem;
            padding: 0.5rem 0.75rem;
            margin-bottom: 1rem;
            font-size: 1rem;
            background: #23272f;
            color: #fff;
            transition: border-color 0.2s;
        }
        .profile-form input:focus {
            border-color: #6366f1;
            outline: none;
            background: #18191a;
        }
        .profile-form button {
            width: 100%;
            background: #6366f1;
            color: #fff;
            font-weight: 600;
            padding: 0.75rem;
            border-radius: 0.5rem;
            border: none;
            font-size: 1rem;
            transition: background 0.2s;
            margin-top: 0.5rem;
            cursor: pointer;
        }
        .profile-form button:hover {
            background: #4338ca;
        }
    </style>
</head>
<body>
    <div class="profile-container">
        <div class="profile-header">
            <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($current_user['full_name']); ?>&background=23272f&color=6366f1&rounded=true" alt="User" class="profile-avatar">
            <div class="profile-info">
                <div class="profile-name"><?php echo htmlspecialchars($current_user['full_name']); ?></div>
                <div class="profile-role"><i class="fa-solid fa-user-graduate"></i> <?php echo ucfirst($current_user['role']); ?></div>
            </div>
        </div>
        <?php if ($message): ?>
            <div class="profile-message success"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="profile-message error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <form method="POST" class="profile-form">
            <button type="button" onclick="window.location.href='index.php'" style="margin-bottom:1rem;background:#333;color:#fff;border:none;padding:0.6rem 1.2rem;border-radius:0.5rem;cursor:pointer;">
                <i class="fa-solid fa-arrow-left"></i> Trở về
            </button>
            <label>Họ tên</label>
            <input type="text" name="full_name" value="<?php echo htmlspecialchars($current_user['full_name'] ?? ''); ?>" required>
            <label>Năm sinh</label>
            <input type="date" name="birthday" value="<?php echo htmlspecialchars($current_user['birthday'] ?? ''); ?>" required>
            <label>Số điện thoại</label>
            <input type="text" name="phone" value="<?php echo htmlspecialchars($current_user['phone'] ?? ''); ?>" required>
            <label>Email</label>
            <input type="email" name="email" value="<?php echo htmlspecialchars($current_user['email'] ?? ''); ?>" required>
            <label>Địa chỉ</label>
            <input type="text" name="address" value="<?php echo htmlspecialchars($current_user['address'] ?? ''); ?>" required>
            <button type="submit"><i class="fa-solid fa-save"></i> Cập nhật</button>
        </form>
    </div>
</body>
</html>