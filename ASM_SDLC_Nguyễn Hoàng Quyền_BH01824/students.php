<?php
require_once 'db.php';
require_once 'config.php';

secure_session_start();

// Kiểm tra đăng nhập
if (!is_logged_in()) {
    header("Location: welcome.php");
    exit();
}

$current_user = get_current_user_info($conn);
if (!$current_user) {
    header("Location: login.php");
    exit();
}

// Xử lý các action
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($current_user['role'] === 'student') {
        $error = 'Bạn không có quyền thực hiện thao tác này!';
    } else if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_student':
                $full_name = trim($_POST['full_name']);
                $email = trim($_POST['email']);
                $phone = trim($_POST['phone']);
                
                if (empty($full_name) || empty($email)) {
                    $error = 'Họ tên và email không được để trống';
                } elseif (!is_valid_email($email)) {
                    $error = 'Email không hợp lệ';
                } else {
                    try {
                        // Tạo mật khẩu mặc định
                        $password = password_hash('123456', PASSWORD_DEFAULT);
                        
                        $stmt = $conn->prepare("INSERT INTO users (full_name, email, password, phone, role, status, created_at) VALUES (?, ?, ?, ?, 'student', ?, NOW())");
                        $status = STATUS_ACTIVE;
                        $stmt->bind_param("sssss", $full_name, $email, $password, $phone, $status);
                        if ($stmt->execute()) {
                            $message = 'Thêm sinh viên thành công';
                            log_activity($conn, $current_user['id'], 'Thêm sinh viên mới', "Sinh viên: $full_name");
                        } else {
                            $error = 'Có lỗi xảy ra khi thêm sinh viên';
                        }
                    } catch (Exception $e) {
                        $error = 'Có lỗi xảy ra: ' . $e->getMessage();
                    }
                }
                break;
                
            case 'update_student':
                $user_id = $_POST['user_id'];
                $full_name = trim($_POST['full_name']);
                $email = trim($_POST['email']);
                $phone = trim($_POST['phone']);
                $status = $_POST['status'];
                
                if (empty($full_name) || empty($email)) {
                    $error = 'Họ tên và email không được để trống';
                } elseif (!is_valid_email($email)) {
                    $error = 'Email không hợp lệ';
                } else {
                    try {
                        $stmt = $conn->prepare("UPDATE users SET full_name = ?, email = ?, phone = ?, status = ?, updated_at = NOW() WHERE id = ?");
                        $stmt->bind_param("ssssi", $full_name, $email, $phone, $status, $user_id);
                        if ($stmt->execute()) {
                            $message = 'Cập nhật sinh viên thành công';
                            log_activity($conn, $current_user['id'], 'Cập nhật thông tin sinh viên', "Sinh viên: $full_name");
                        } else {
                            $error = 'Có lỗi xảy ra khi cập nhật sinh viên';
                        }
                    } catch (Exception $e) {
                        $error = 'Có lỗi xảy ra: ' . $e->getMessage();
                    }
                }
                break;
                
            case 'delete_student':
                $user_id = $_POST['user_id'];
                try {
                    $stmt = $conn->prepare("DELETE FROM users WHERE id = ? AND role = 'student'");
                    $stmt->bind_param("i", $user_id);
                    if ($stmt->execute()) {
                        $message = 'Xóa sinh viên thành công';
                        log_activity($conn, $current_user['id'], 'Xóa sinh viên', "ID: $user_id");
                    } else {
                        $error = 'Có lỗi xảy ra khi xóa sinh viên';
                    }
                } catch (Exception $e) {
                    $error = 'Có lỗi xảy ra: ' . $e->getMessage();
                }
                break;
        }
    }
}

// Lấy danh sách sinh viên
$students = [];
try {
    $query = "SELECT u.*, 
              (SELECT COUNT(*) FROM course_enrollments WHERE student_id = u.id) as enrolled_courses,
              (SELECT COUNT(*) FROM assignment_submissions WHERE student_id = u.id AND status = 'graded') as completed_assignments
              FROM users u 
              WHERE u.role = 'student' 
              ORDER BY u.created_at DESC";
    $result = $conn->query($query);
    while ($row = $result->fetch_assoc()) {
        $students[] = $row;
    }
} catch (Exception $e) {
    $error = 'Có lỗi xảy ra khi tải danh sách sinh viên: ' . $e->getMessage();
}
?>

<div class="bg-white rounded-lg shadow p-6">
    <div class="flex justify-between items-center mb-6">
        <h2 class="text-2xl font-bold text-gray-800">Quản lý Sinh viên</h2>
        <?php if ($current_user['role'] !== 'student'): ?>
        <button onclick="openAddStudentModal()" class="bg-indigo-600 text-white px-4 py-2 rounded-lg hover:bg-indigo-700">
            <i class="fas fa-plus mr-2"></i>Thêm sinh viên
        </button>
        <?php endif; ?>
    </div>

    <?php if ($message): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
            <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>

    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Họ tên</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Số điện thoại</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Khóa học đăng ký</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Bài tập hoàn thành</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Trạng thái</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ngày tạo</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Hành động</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php if (!empty($students)): ?>
                    <?php foreach ($students as $student): ?>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                <?php echo $student['id']; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                <?php echo htmlspecialchars($student['full_name']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?php echo htmlspecialchars($student['email']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?php echo htmlspecialchars($student['phone']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?php echo $student['enrolled_courses']; ?> khóa học
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?php echo $student['completed_assignments']; ?> bài tập
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                    <?php echo $student['status'] === STATUS_ACTIVE ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                    <?php echo $student['status'] === STATUS_ACTIVE ? 'Hoạt động' : 'Không hoạt động'; ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?php echo format_date($student['created_at']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <?php if ($current_user['role'] !== 'student'): ?>
                                <button onclick="openEditStudentModal(<?php echo htmlspecialchars(json_encode($student)); ?>)" 
                                        class="text-indigo-600 hover:text-indigo-900 mr-3">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button onclick="deleteStudent(<?php echo $student['id']; ?>)" 
                                        class="text-red-600 hover:text-red-900">
                                    <i class="fas fa-trash"></i>
                                </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="9" class="px-6 py-4 text-center text-sm text-gray-500">
                            Chưa có sinh viên nào
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal thêm sinh viên -->
<div id="addStudentModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden">
    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
        <div class="mt-3">
            <h3 class="text-lg font-medium text-gray-900 mb-4">Thêm sinh viên mới</h3>
            <form method="POST">
                <input type="hidden" name="action" value="add_student">

                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Họ tên</label>
                    <input type="text" name="full_name" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500">
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Email</label>
                    <input type="email" name="email" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500">
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Số điện thoại</label>
                    <input type="text" name="phone" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500">
                </div>
                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="closeAddStudentModal()" class="px-4 py-2 text-gray-600 border border-gray-300 rounded-md hover:bg-gray-50">
                        Hủy
                    </button>
                    <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700">
                        Thêm
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal sửa sinh viên -->
<div id="editStudentModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden">
    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
        <div class="mt-3">
            <h3 class="text-lg font-medium text-gray-900 mb-4">Sửa thông tin sinh viên</h3>
            <form method="POST">
                <input type="hidden" name="action" value="update_student">
                <input type="hidden" name="user_id" id="edit_user_id">

                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Họ tên</label>
                    <input type="text" name="full_name" id="edit_full_name" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500">
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Email</label>
                    <input type="email" name="email" id="edit_email" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500">
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Số điện thoại</label>
                    <input type="text" name="phone" id="edit_phone" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500">
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Trạng thái</label>
                    <select name="status" id="edit_status" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        <option value="<?php echo STATUS_ACTIVE; ?>">Hoạt động</option>
                        <option value="<?php echo STATUS_INACTIVE; ?>">Không hoạt động</option>
                    </select>
                </div>
                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="closeEditStudentModal()" class="px-4 py-2 text-gray-600 border border-gray-300 rounded-md hover:bg-gray-50">
                        Hủy
                    </button>
                    <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700">
                        Cập nhật
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Form xóa sinh viên -->
<form id="deleteStudentForm" method="POST" style="display: none;">
    <input type="hidden" name="action" value="delete_student">
    <input type="hidden" name="user_id" id="delete_user_id">
</form>

<script>
function openAddStudentModal() {
    document.getElementById('addStudentModal').classList.remove('hidden');
}
function closeAddStudentModal() {
    document.getElementById('addStudentModal').classList.add('hidden');
}
function openEditStudentModal(student) {
    document.getElementById('edit_user_id').value = student.id;
    document.getElementById('edit_full_name').value = student.full_name;
    document.getElementById('edit_email').value = student.email;
    document.getElementById('edit_phone').value = student.phone;
    document.getElementById('edit_status').value = student.status;
    document.getElementById('editStudentModal').classList.remove('hidden');
}
function closeEditStudentModal() {
    document.getElementById('editStudentModal').classList.add('hidden');
}
function deleteStudent(userId) {
    if (confirm('Bạn có chắc chắn muốn xóa sinh viên này?')) {
        document.getElementById('delete_user_id').value = userId;
        document.getElementById('deleteStudentForm').submit();
    }
}
</script>