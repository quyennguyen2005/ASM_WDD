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
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_course':
                $name = trim($_POST['name']);
                $description = trim($_POST['description']);
                $instructor_id = $current_user['id'];
                $status = 'active';

                if ($name !== '') {
                    $stmt = $conn->prepare("INSERT INTO courses (name, description, instructor_id, status, created_at) VALUES (?, ?, ?, ?, NOW())");
                    $stmt->bind_param("ssis", $name, $description, $instructor_id, $status);
                    if ($stmt->execute()) {
                        $message = "Thêm khóa học thành công!";
                    } else {
                        $error = "Lỗi khi thêm khóa học: " . $stmt->error;
                    }
                } else {
                    $error = "Tên khóa học không được để trống!";
                }
                break;
                
            case 'update_course':
                $course_id = $_POST['course_id'];
                $name = trim($_POST['name']);
                $description = trim($_POST['description']);
                $status = $_POST['status'];
                
                if (empty($name)) {
                    $error = 'Tên khóa học không được để trống';
                } else {
                    try {
                        $stmt = $conn->prepare("UPDATE courses SET name = ?, description = ?, status = ?, updated_at = NOW() WHERE id = ?");
                        $stmt->bind_param("sssi", $name, $description, $status, $course_id);
                        if ($stmt->execute()) {
                            $message = 'Cập nhật khóa học thành công';
                            log_activity($conn, $current_user['id'], 'Cập nhật khóa học', "Khóa học: $name");
                        } else {
                            $error = 'Có lỗi xảy ra khi cập nhật khóa học';
                        }
                    } catch (Exception $e) {
                        $error = 'Có lỗi xảy ra: ' . $e->getMessage();
                    }
                }
                break;
                
            case 'delete_course':
                $course_id = $_POST['course_id'];
                try {
                    $stmt = $conn->prepare("DELETE FROM courses WHERE id = ?");
                    $stmt->bind_param("i", $course_id);
                    if ($stmt->execute()) {
                        $message = 'Xóa khóa học thành công';
                        log_activity($conn, $current_user['id'], 'Xóa khóa học', "ID: $course_id");
                    } else {
                        $error = 'Có lỗi xảy ra khi xóa khóa học';
                    }
                } catch (Exception $e) {
                    $error = 'Có lỗi xảy ra: ' . $e->getMessage();
                }
                break;
                
            case 'enroll_course':
                if ($current_user['role'] === 'student') {
                    $course_id = intval($_POST['course_id']);
                    // Kiểm tra đã đăng ký chưa
                    $stmt = $conn->prepare("SELECT id FROM course_enrollments WHERE course_id = ? AND student_id = ?");
                    $stmt->bind_param("ii", $course_id, $current_user['id']);
                    $stmt->execute();
                    $stmt->store_result();
                    if ($stmt->num_rows == 0) {
                        $stmt = $conn->prepare("INSERT INTO course_enrollments (course_id, student_id) VALUES (?, ?)");
                        $stmt->bind_param("ii", $course_id, $current_user['id']);
                        if ($stmt->execute()) {
                            $message = "Đăng ký khóa học thành công!";
                        } else {
                            $error = "Lỗi khi đăng ký: " . $stmt->error;
                        }
                    } else {
                        $error = "Bạn đã đăng ký khóa học này!";
                    }
                }
                break;
        }
    }
}

// Lấy danh sách khóa học
$courses = [];
try {
    $query = "SELECT c.*, u.full_name as teacher_name, 
              (SELECT COUNT(*) FROM course_enrollments WHERE course_id = c.id) as student_count
              FROM courses c 
              LEFT JOIN users u ON c.instructor_id = u.id 
              ORDER BY c.created_at DESC";
    $result = $conn->query($query);
    while ($row = $result->fetch_assoc()) {
        $courses[] = $row;
    }
} catch (Exception $e) {
    $error = 'Có lỗi xảy ra khi tải danh sách khóa học: ' . $e->getMessage();
}

// Lấy danh sách ID các khóa học mà sinh viên đã đăng ký
$enrolled_course_ids = [];
if ($current_user['role'] === 'student') {
    $sql = "SELECT course_id FROM course_enrollments WHERE student_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $current_user['id']);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $enrolled_course_ids[] = $row['course_id'];
    }
}
?>

<div class="bg-white rounded-lg shadow p-6">
    <div class="flex justify-between items-center mb-6">
        <h2 class="text-2xl font-bold text-gray-800">Quản lý Khóa học</h2>
        <?php if ($current_user['role'] !== 'student'): ?>
        <button onclick="openAddCourseModal()" class="bg-indigo-600 text-white px-4 py-2 rounded-lg hover:bg-indigo-700">
            <i class="fas fa-plus mr-2"></i>Thêm khóa học
        </button>
        <?php endif; ?>
    </div>

    <?php if (!empty($message)): ?>
        <div class="bg-green-100 text-green-700 p-2 rounded mb-2"><?php echo $message; ?></div>
    <?php endif; ?>
    <?php if (!empty($error)): ?>
        <div class="bg-red-100 text-red-700 p-2 rounded mb-2"><?php echo $error; ?></div>
    <?php endif; ?>

    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tên khóa học</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Giảng viên</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Sinh viên</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Trạng thái</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ngày tạo</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Hành động</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php if (!empty($courses)): ?>
                    <?php foreach ($courses as $course): ?>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                <?php echo $course['id']; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($course['name']); ?></div>
                                <div class="text-sm text-gray-500"><?php echo htmlspecialchars($course['description']); ?></div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?php echo htmlspecialchars($course['teacher_name']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?php echo $course['student_count']; ?> sinh viên
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                    <?php echo $course['status'] === STATUS_ACTIVE ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                    <?php echo $course['status'] === STATUS_ACTIVE ? 'Hoạt động' : 'Không hoạt động'; ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?php echo format_date($course['created_at']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <?php if ($current_user['role'] === 'student'): ?>
                                    <?php if (!in_array($course['id'], $enrolled_course_ids)): ?>
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="action" value="enroll_course">
                                            <input type="hidden" name="course_id" value="<?php echo $course['id']; ?>">
                                            <button type="submit" class="bg-green-600 text-white px-3 py-1 rounded hover:bg-green-700">
                                                <i class="fas fa-sign-in-alt"></i> Đăng ký
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <span class="text-green-700 font-semibold"><i class="fas fa-check-circle"></i> Đã đăng ký</span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <!-- Các nút cho giáo viên/admin như cũ -->
                                    <button onclick="openEditCourseModal(<?php echo htmlspecialchars(json_encode($course)); ?>)" 
                                            class="text-indigo-600 hover:text-indigo-900 mr-3">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button onclick="deleteCourse(<?php echo $course['id']; ?>)" 
                                            class="text-red-600 hover:text-red-900">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7" class="px-6 py-4 text-center text-sm text-gray-500">
                            Chưa có khóa học nào
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal thêm khóa học -->
<div id="addCourseModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden">
    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
        <div class="mt-3">
            <h3 class="text-lg font-medium text-gray-900 mb-4">Thêm khóa học mới</h3>
            <form method="POST" action="">
                <input type="hidden" name="action" value="add_course">

                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Tên khóa học</label>
                    <input type="text" name="name" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500">
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Mô tả</label>
                    <textarea name="description" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500"></textarea>
                </div>
                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="closeAddCourseModal()" class="px-4 py-2 text-gray-600 border border-gray-300 rounded-md hover:bg-gray-50">
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

<!-- Modal sửa khóa học -->
<div id="editCourseModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden">
    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
        <div class="mt-3">
            <h3 class="text-lg font-medium text-gray-900 mb-4">Sửa khóa học</h3>
            <form method="POST">
                <input type="hidden" name="action" value="update_course">
                <input type="hidden" name="course_id" id="edit_course_id">
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Tên khóa học</label>
                    <input type="text" name="name" id="edit_course_name" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500">
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Mô tả</label>
                    <textarea name="description" id="edit_course_description" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500"></textarea>
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Trạng thái</label>
                    <select name="status" id="edit_course_status" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        <option value="<?php echo STATUS_ACTIVE; ?>">Hoạt động</option>
                        <option value="<?php echo STATUS_INACTIVE; ?>">Không hoạt động</option>
                    </select>
                </div>
                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="closeEditCourseModal()" class="px-4 py-2 text-gray-600 border border-gray-300 rounded-md hover:bg-gray-50">
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

<!-- Form xóa khóa học -->
<form id="deleteCourseForm" method="POST" style="display: none;">
    <input type="hidden" name="action" value="delete_course">
    <input type="hidden" name="course_id" id="delete_course_id">
</form>

<style>
.fixed.inset-0.hidden {
    display: none !important;
}
</style>

<script>
function openAddCourseModal() {
    document.getElementById('addCourseModal').classList.remove('hidden');
}
function closeAddCourseModal() {
    document.getElementById('addCourseModal').classList.add('hidden');
}
function openEditCourseModal(course) {
    document.getElementById('edit_course_id').value = course.id;
    document.getElementById('edit_course_name').value = course.name;
    document.getElementById('edit_course_description').value = course.description;
    document.getElementById('edit_course_status').value = course.status;
    document.getElementById('editCourseModal').classList.remove('hidden');
}
function closeEditCourseModal() {
    document.getElementById('editCourseModal').classList.add('hidden');
}
function deleteCourse(id) {
    if (confirm('Bạn có chắc muốn xóa khóa học này?')) {
        document.getElementById('delete_course_id').value = id;
        document.getElementById('deleteCourseForm').submit();
    }
}
window.onload = function() {
    document.querySelectorAll('.fixed.inset-0').forEach(function(modal) {
        if (!modal.classList.contains('hidden')) {
            modal.classList.add('hidden');
        }
    });
    // Ngoài ra, loại bỏ mọi thẻ backdrop/modal thừa nếu có
    document.querySelectorAll('.modal-backdrop').forEach(e => e.remove());
};
<?php
if ($error) echo "<script>openAddCourseModal()</script>";
?>
</script>

