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
            case 'create_assignment':
                $title = trim($_POST['title']);
                $description = trim($_POST['description']);
                $course_id = $_POST['course_id'];
                $due_date = $_POST['due_date'];
                $max_score = $_POST['max_score'];
                
                if (empty($title) || empty($course_id) || empty($due_date)) {
                    $error = 'Tiêu đề, khóa học và hạn nộp không được để trống';
                } else {
                    try {
                        $stmt = $conn->prepare("INSERT INTO assignments (title, description, course_id, due_date, total_points, status, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
                        $status = 'published';
                        $stmt->bind_param("ssisis", $title, $description, $course_id, $due_date, $max_score, $status);
                        if ($stmt->execute()) {
                            $message = 'Tạo bài tập thành công';
                            log_activity($conn, $current_user['id'], 'Tạo bài tập mới', "Bài tập: $title");
                        } else {
                            $error = 'Có lỗi xảy ra khi tạo bài tập';
                        }
                    } catch (Exception $e) {
                        $error = 'Có lỗi xảy ra: ' . $e->getMessage();
                    }
                }
                break;
                
            case 'update_assignment':
                $assignment_id = $_POST['assignment_id'];
                $title = trim($_POST['title']);
                $description = trim($_POST['description']);
                $course_id = $_POST['course_id'];
                $due_date = $_POST['due_date'];
                $max_score = $_POST['max_score'];
                $status = $_POST['status'];
                
                if (empty($title) || empty($course_id) || empty($due_date)) {
                    $error = 'Tiêu đề, khóa học và hạn nộp không được để trống';
                } else {
                    try {
                        $stmt = $conn->prepare("UPDATE assignments SET title = ?, description = ?, course_id = ?, due_date = ?, total_points = ?, status = ?, updated_at = NOW() WHERE id = ?");
                        $stmt->bind_param("ssisisi", $title, $description, $course_id, $due_date, $max_score, $status, $assignment_id);
                        if ($stmt->execute()) {
                            $message = 'Cập nhật bài tập thành công';
                            log_activity($conn, $current_user['id'], 'Cập nhật bài tập', "Bài tập: $title");
                        } else {
                            $error = 'Có lỗi xảy ra khi cập nhật bài tập';
                        }
                    } catch (Exception $e) {
                        $error = 'Có lỗi xảy ra: ' . $e->getMessage();
                    }
                }
                break;
                
            case 'delete_assignment':
                $assignment_id = $_POST['assignment_id'];
                try {
                    $stmt = $conn->prepare("DELETE FROM assignments WHERE id = ?");
                    $stmt->bind_param("i", $assignment_id);
                    if ($stmt->execute()) {
                        $message = 'Xóa bài tập thành công';
                        log_activity($conn, $current_user['id'], 'Xóa bài tập', "ID: $assignment_id");
                    } else {
                        $error = 'Có lỗi xảy ra khi xóa bài tập';
                    }
                } catch (Exception $e) {
                    $error = 'Có lỗi xảy ra: ' . $e->getMessage();
                }
                break;
                
            case 'submit_assignment':
                $assignment_id = $_POST['assignment_id'];
                $content = trim($_POST['content']);
                $file_path = '';
                
                if (empty($content)) {
                    $error = 'Nội dung bài nộp không được để trống';
                } else {
                    // Xử lý upload file nếu có
                    if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
                        $upload_dir = 'uploads/assignments/';
                        if (!is_dir($upload_dir)) {
                            mkdir($upload_dir, 0777, true);
                        }
                        
                        $file_extension = pathinfo($_FILES['attachment']['name'], PATHINFO_EXTENSION);
                        $file_name = uniqid() . '.' . $file_extension;
                        $file_path = $upload_dir . $file_name;
                        
                        if (!move_uploaded_file($_FILES['attachment']['tmp_name'], $file_path)) {
                            $error = 'Có lỗi xảy ra khi upload file';
                            break;
                        }
                    }
                    
                    try {
                        $stmt = $conn->prepare("INSERT INTO assignment_submissions (assignment_id, student_id, content, file_path, submitted_at) VALUES (?, ?, ?, ?, NOW())");
                        $stmt->bind_param("iiss", $assignment_id, $current_user['id'], $content, $file_path);
                        if ($stmt->execute()) {
                            $message = 'Nộp bài thành công';
                            log_activity($conn, $current_user['id'], 'Nộp bài tập', "Assignment ID: $assignment_id");
                        } else {
                            $error = 'Có lỗi xảy ra khi nộp bài';
                        }
                    } catch (Exception $e) {
                        $error = 'Có lỗi xảy ra: ' . $e->getMessage();
                    }
                }
                break;
        }
    }
}

// Lấy danh sách khóa học (lọc theo giảng viên nếu là teacher)
$courses = [];
try {
    $active_status = STATUS_ACTIVE;
    if ($current_user['role'] === 'teacher') {
        $stmt = $conn->prepare("SELECT id, name FROM courses WHERE status = ? AND instructor_id = ? ORDER BY name");
        $stmt->bind_param("si", $active_status, $current_user['id']);
    } else {
        $stmt = $conn->prepare("SELECT id, name FROM courses WHERE status = ? ORDER BY name");
        $stmt->bind_param("s", $active_status);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $courses[] = $row;
    }
} catch (Exception $e) {
    $error = 'Có lỗi xảy ra khi tải danh sách khóa học: ' . $e->getMessage();
}

// Lấy danh sách bài tập
$assignments = [];
$view_type = isset($_GET['view']) ? $_GET['view'] : 'all';

try {
    if ($current_user['role'] === 'teacher') {
        // Giảng viên xem bài tập của các khóa học mình dạy
        $query = "SELECT a.*, c.name as course_name, 
                  (SELECT COUNT(*) FROM assignment_submissions WHERE assignment_id = a.id) as submission_count
                  FROM assignments a 
                  JOIN courses c ON a.course_id = c.id 
                  WHERE c.instructor_id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $current_user['id']);
    } elseif ($current_user['role'] === 'admin') {
        // Admin xem tất cả bài tập
        $query = "SELECT a.*, c.name as course_name, 
                  (SELECT COUNT(*) FROM assignment_submissions WHERE assignment_id = a.id) as submission_count
                  FROM assignments a 
                  JOIN courses c ON a.course_id = c.id";
        $stmt = $conn->prepare($query);
    } else {
        // Sinh viên xem bài tập của các khóa học đã đăng ký
        $query = "SELECT a.*, c.name as course_name, 
                  (SELECT COUNT(*) FROM assignment_submissions WHERE assignment_id = a.id AND student_id = ?) as submitted
                  FROM assignments a 
                  JOIN courses c ON a.course_id = c.id 
                  JOIN course_enrollments ce ON a.course_id = ce.course_id 
                  WHERE ce.student_id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ii", $current_user['id'], $current_user['id']);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $assignments[] = $row;
    }
} catch (Exception $e) {
    $error = 'Có lỗi xảy ra khi tải danh sách bài tập: ' . $e->getMessage();
}
?>

<div class="bg-white rounded-lg shadow p-6">
    <div class="flex justify-between items-center mb-6">
        <h2 class="text-2xl font-bold text-gray-800">Quản lý Bài tập</h2>
        <?php if ($current_user['role'] === 'teacher'): ?>
            <button onclick="openCreateAssignmentModal()" class="bg-indigo-600 text-white px-4 py-2 rounded-lg hover:bg-indigo-700">
                <i class="fas fa-plus mr-2"></i>Tạo bài tập
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

    <!-- Tab navigation -->
    <div class="border-b border-gray-200 mb-6">
        <nav class="-mb-px flex space-x-8">
            <a href="?type=assignments&view=all" class="<?php echo $view_type === 'all' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'; ?> whitespace-nowrap py-2 px-1 border-b-2 font-medium text-sm">
                Tất cả
            </a>
            <a href="?type=assignments&view=pending" class="<?php echo $view_type === 'pending' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'; ?> whitespace-nowrap py-2 px-1 border-b-2 font-medium text-sm">
                Đang chờ
            </a>
            <a href="?type=assignments&view=completed" class="<?php echo $view_type === 'completed' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'; ?> whitespace-nowrap py-2 px-1 border-b-2 font-medium text-sm">
                Đã hoàn thành
            </a>
        </nav>
    </div>

    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tiêu đề</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Khóa học</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Hạn nộp</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Điểm tối đa</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Trạng thái</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Hành động</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php if (!empty($assignments)): ?>
                    <?php foreach ($assignments as $assignment): ?>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($assignment['title']); ?></div>
                                <div class="text-sm text-gray-500"><?php echo htmlspecialchars(substr($assignment['description'], 0, 50)) . (strlen($assignment['description']) > 50 ? '...' : ''); ?></div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?php echo htmlspecialchars($assignment['course_name']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?php echo format_date($assignment['due_date']); ?>
                            </td>
                                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php echo $assignment['total_points']; ?> điểm
                                </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                    <?php echo $assignment['status'] === STATUS_ACTIVE ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'; ?>">
                                    <?php echo $assignment['status'] === STATUS_ACTIVE ? 'Hoạt động' : 'Đang chờ'; ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <button onclick="viewAssignment(<?php echo htmlspecialchars(json_encode($assignment)); ?>)" 
                                        class="text-indigo-600 hover:text-indigo-900 mr-3">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <?php if ($current_user['role'] === 'teacher'): ?>
                                    <button onclick="openEditAssignmentModal(<?php echo htmlspecialchars(json_encode($assignment)); ?>)" 
                                            class="text-blue-600 hover:text-blue-900 mr-3">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button onclick="deleteAssignment(<?php echo $assignment['id']; ?>)" 
                                            class="text-red-600 hover:text-red-900">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                <?php else: ?>
                                    <?php if ($assignment['submitted']): ?>
                                        <span class="text-green-600 text-sm">Đã nộp</span>
                                    <?php else: ?>
                                        <button onclick="openSubmitModal(<?php echo $assignment['id']; ?>)" 
                                                class="text-green-600 hover:text-green-900">
                                            <i class="fas fa-upload mr-1"></i>Nộp bài
                                        </button>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" class="px-6 py-4 text-center text-sm text-gray-500">
                            Chưa có bài tập nào
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal tạo bài tập -->
<div id="createAssignmentModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden">
    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
        <div class="mt-3">
            <h3 class="text-lg font-medium text-gray-900 mb-4">Tạo bài tập mới</h3>
            <form method="POST">
                <input type="hidden" name="action" value="create_assignment">
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Khóa học</label>
                    <select name="course_id" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        <option value="">Chọn khóa học</option>
                        <?php foreach ($courses as $course): ?>
                            <option value="<?php echo $course['id']; ?>"><?php echo htmlspecialchars($course['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Tiêu đề</label>
                    <input type="text" name="title" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500">
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Mô tả</label>
                    <textarea name="description" rows="4" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500"></textarea>
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Hạn nộp</label>
                    <input type="datetime-local" name="due_date" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500">
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Điểm tối đa</label>
                    <input type="number" name="max_score" value="100" min="1" max="100" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500">
                </div>
                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="closeCreateAssignmentModal()" class="px-4 py-2 text-gray-600 border border-gray-300 rounded-md hover:bg-gray-50">
                        Hủy
                    </button>
                    <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700">
                        Tạo
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal sửa bài tập -->
<div id="editAssignmentModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden">
    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
        <div class="mt-3">
            <h3 class="text-lg font-medium text-gray-900 mb-4">Sửa bài tập</h3>
            <form method="POST">
                <input type="hidden" name="action" value="update_assignment">
                <input type="hidden" name="assignment_id" id="edit_assignment_id">
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Khóa học</label>
                    <select name="course_id" id="edit_course_id" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        <option value="">Chọn khóa học</option>
                        <?php foreach ($courses as $course): ?>
                            <option value="<?php echo $course['id']; ?>"><?php echo htmlspecialchars($course['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Tiêu đề</label>
                    <input type="text" name="title" id="edit_title" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500">
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Mô tả</label>
                    <textarea name="description" id="edit_description" rows="4" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500"></textarea>
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Hạn nộp</label>
                    <input type="datetime-local" name="due_date" id="edit_due_date" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500">
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Điểm tối đa</label>
                    <input type="number" name="max_score" id="edit_max_score" min="1" max="100" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500">
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Trạng thái</label>
                    <select name="status" id="edit_status" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        <option value="<?php echo STATUS_PENDING; ?>">Đang chờ</option>
                        <option value="<?php echo STATUS_ACTIVE; ?>">Hoạt động</option>
                    </select>
                </div>
                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="closeEditAssignmentModal()" class="px-4 py-2 text-gray-600 border border-gray-300 rounded-md hover:bg-gray-50">
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

<!-- Modal nộp bài -->
<div id="submitAssignmentModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden">
    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
        <div class="mt-3">
            <h3 class="text-lg font-medium text-gray-900 mb-4">Nộp bài tập</h3>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="submit_assignment">
                <input type="hidden" name="assignment_id" id="submit_assignment_id">
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Nội dung bài làm</label>
                    <textarea name="content" rows="6" required placeholder="Nhập nội dung bài làm..." class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500"></textarea>
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">File đính kèm (tùy chọn)</label>
                    <input type="file" name="attachment" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500">
                </div>
                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="closeSubmitModal()" class="px-4 py-2 text-gray-600 border border-gray-300 rounded-md hover:bg-gray-50">
                        Hủy
                    </button>
                    <button type="submit" class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700">
                        Nộp bài
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal xem chi tiết bài tập -->
<div id="viewAssignmentModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden">
    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
        <div class="mt-3">
            <h3 class="text-lg font-medium text-gray-900 mb-4" id="viewTitle"></h3>
            <div class="mb-4">
                <p class="text-sm text-gray-600 mb-2" id="viewCourse"></p>
                <p class="text-sm text-gray-500 mb-4" id="viewDueDate"></p>
                <div class="bg-gray-50 p-4 rounded-md">
                    <p class="text-sm text-gray-700" id="viewDescription"></p>
                </div>
            </div>
            <div class="flex justify-end">
                <button onclick="closeViewModal()" class="px-4 py-2 text-gray-600 border border-gray-300 rounded-md hover:bg-gray-50">
                    Đóng
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Forms -->
<form id="deleteAssignmentForm" method="POST" style="display: none;">
    <input type="hidden" name="action" value="delete_assignment">
    <input type="hidden" name="assignment_id" id="delete_assignment_id">
</form>

