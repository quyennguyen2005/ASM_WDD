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
            case 'update_enrollment_grade':
                $enrollment_id = $_POST['enrollment_id'];
                $grade = $_POST['grade'];
                
                if (empty($grade)) {
                    $error = 'Điểm không được để trống';
                } else {
                    try {
                        $stmt = $conn->prepare("UPDATE course_enrollments SET grade = ? WHERE id = ?");
                        $stmt->bind_param("di", $grade, $enrollment_id);
                        if ($stmt->execute()) {
                            $message = 'Cập nhật điểm thành công';
                            log_activity($conn, $current_user['id'], 'Cập nhật điểm khóa học', "Enrollment ID: $enrollment_id");
                        } else {
                            $error = 'Có lỗi xảy ra khi cập nhật điểm';
                        }
                    } catch (Exception $e) {
                        $error = 'Có lỗi xảy ra: ' . $e->getMessage();
                    }
                }
                break;
                
            case 'update_assignment_grade':
                $submission_id = $_POST['submission_id'];
                $score = $_POST['grade'];
                $feedback = trim($_POST['feedback']);
                
                if (empty($score)) {
                    $error = 'Điểm không được để trống';
                } else {
                    try {
                        $stmt = $conn->prepare("UPDATE assignment_submissions SET score = ?, feedback = ?, status = 'graded' WHERE id = ?");
                        $stmt->bind_param("dsi", $score, $feedback, $submission_id);
                        if ($stmt->execute()) {
                            $message = 'Cập nhật điểm bài tập thành công';
                            log_activity($conn, $current_user['id'], 'Cập nhật điểm bài tập', "Submission ID: $submission_id");
                        } else {
                            $error = 'Có lỗi xảy ra khi cập nhật điểm bài tập';
                        }
                    } catch (Exception $e) {
                        $error = 'Có lỗi xảy ra: ' . $e->getMessage();
                    }
                }
                break;
                
            case 'update_quiz_grade':
                $result_id = $_POST['result_id'];
                $score = $_POST['grade'];
                
                if (empty($score)) {
                    $error = 'Điểm không được để trống';
                } else {
                    try {
                        $stmt = $conn->prepare("UPDATE quiz_results SET score = ? WHERE id = ?");
                        $stmt->bind_param("di", $score, $result_id);
                        if ($stmt->execute()) {
                            $message = 'Cập nhật điểm bài kiểm tra thành công';
                            log_activity($conn, $current_user['id'], 'Cập nhật điểm bài kiểm tra', "Result ID: $result_id");
                        } else {
                            $error = 'Có lỗi xảy ra khi cập nhật điểm bài kiểm tra';
                        }
                    } catch (Exception $e) {
                        $error = 'Có lỗi xảy ra: ' . $e->getMessage();
                    }
                }
                break;
        }
    }
}

// Lấy danh sách điểm
$grades = [];
try {
    if ($current_user['role'] === 'student') {
        // Sinh viên xem điểm của mình
        $query = "SELECT 'enrollment' as type, ce.id, c.name as course_name, ce.grade as score, 
                  ce.completion_date, ce.enrollment_date as updated_at, 'Khóa học' as grade_type
                  FROM course_enrollments ce 
                  JOIN courses c ON ce.course_id = c.id 
                  WHERE ce.student_id = ? AND ce.grade IS NOT NULL
                  UNION ALL
                  SELECT 'assignment' as type, asub.id, CONCAT(c.name, ' - ', a.title) as course_name, 
                  asub.score, asub.submission_date, asub.submission_date as updated_at, 'Bài tập' as grade_type
                  FROM assignment_submissions asub 
                  JOIN assignments a ON asub.assignment_id = a.id 
                  JOIN courses c ON a.course_id = c.id 
                  WHERE asub.student_id = ? AND asub.score IS NOT NULL
                  UNION ALL
                  SELECT 'quiz' as type, qr.id, CONCAT(c.name, ' - ', q.title) as course_name, 
                  qr.score, qr.submitted_at as submission_date, qr.submitted_at as updated_at, 'Bài kiểm tra' as grade_type
                  FROM quiz_results qr 
                  JOIN quizzes q ON qr.quiz_id = q.id 
                  JOIN courses c ON q.course_id = c.id 
                  WHERE qr.student_id = ? AND qr.score IS NOT NULL
                  ORDER BY updated_at DESC";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("iii", $current_user['id'], $current_user['id'], $current_user['id']);
    } else {
        // Giảng viên xem điểm của sinh viên trong các khóa học mình dạy
        $query = "SELECT 'enrollment' as type, ce.id, CONCAT(c.name, ' - ', u.full_name) as course_name, 
                  ce.grade as score, ce.completion_date, ce.enrollment_date as updated_at, 'Khóa học' as grade_type
                  FROM course_enrollments ce 
                  JOIN courses c ON ce.course_id = c.id 
                  JOIN users u ON ce.student_id = u.id 
                  WHERE c.instructor_id = ? AND ce.grade IS NOT NULL
                  UNION ALL
                  SELECT 'assignment' as type, asub.id, CONCAT(c.name, ' - ', a.title, ' - ', u.full_name) as course_name, 
                  asub.score, asub.submission_date, asub.submission_date as updated_at, 'Bài tập' as grade_type
                  FROM assignment_submissions asub 
                  JOIN assignments a ON asub.assignment_id = a.id 
                  JOIN courses c ON a.course_id = c.id 
                  JOIN users u ON asub.student_id = u.id 
                  WHERE c.instructor_id = ? AND asub.score IS NOT NULL
                  UNION ALL
                  SELECT 'quiz' as type, qr.id, CONCAT(c.name, ' - ', q.title, ' - ', u.full_name) as course_name, 
                  qr.score, qr.submitted_at as submission_date, qr.submitted_at as updated_at, 'Bài kiểm tra' as grade_type
                  FROM quiz_results qr 
                  JOIN quizzes q ON qr.quiz_id = q.id 
                  JOIN courses c ON q.course_id = c.id 
                  JOIN users u ON qr.student_id = u.id 
                  WHERE c.instructor_id = ? AND qr.score IS NOT NULL
                  ORDER BY updated_at DESC";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("iii", $current_user['id'], $current_user['id'], $current_user['id']);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $grades[] = $row;
    }
} catch (Exception $e) {
    $error = 'Có lỗi xảy ra khi tải danh sách điểm: ' . $e->getMessage();
}

// Tính thống kê điểm
$stats = [];
try {
    if ($current_user['role'] === 'student') {
        // Thống kê điểm của sinh viên
        $query = "SELECT 
                  AVG(ce.grade) as avg_grade,
                  MAX(ce.grade) as max_grade,
                  MIN(ce.grade) as min_grade,
                  COUNT(ce.grade) as total_courses
                  FROM course_enrollments ce 
                  WHERE ce.student_id = ? AND ce.grade IS NOT NULL";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $current_user['id']);
    } else {
        // Thống kê điểm của các khóa học giảng viên dạy
        $query = "SELECT 
                  AVG(ce.grade) as avg_grade,
                  MAX(ce.grade) as max_grade,
                  MIN(ce.grade) as min_grade,
                  COUNT(ce.grade) as total_courses
                  FROM course_enrollments ce 
                  JOIN courses c ON ce.course_id = c.id 
                  WHERE c.instructor_id = ? AND ce.grade IS NOT NULL";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $current_user['id']);
    }
    
    $stmt->execute();
    $stats = $stmt->get_result()->fetch_assoc();
} catch (Exception $e) {
    $error = 'Có lỗi xảy ra khi tính thống kê: ' . $e->getMessage();
}
?>

<div class="bg-white rounded-lg shadow p-6">
    <div class="flex justify-between items-center mb-6">
        <h2 class="text-2xl font-bold text-gray-800">Quản lý Điểm số</h2>
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

    <!-- Thống kê điểm -->
    <?php if (!empty($stats) && $stats['total_courses'] > 0): ?>
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
            <div class="bg-blue-50 p-4 rounded-lg">
                <h3 class="text-lg font-semibold text-blue-800">Điểm trung bình</h3>
                <p class="text-2xl font-bold text-blue-600"><?php echo number_format($stats['avg_grade'], 2); ?></p>
            </div>
            <div class="bg-green-50 p-4 rounded-lg">
                <h3 class="text-lg font-semibold text-green-800">Điểm cao nhất</h3>
                <p class="text-2xl font-bold text-green-600"><?php echo number_format($stats['max_grade'], 2); ?></p>
            </div>
            <div class="bg-yellow-50 p-4 rounded-lg">
                <h3 class="text-lg font-semibold text-yellow-800">Điểm thấp nhất</h3>
                <p class="text-2xl font-bold text-yellow-600"><?php echo number_format($stats['min_grade'], 2); ?></p>
            </div>
            <div class="bg-purple-50 p-4 rounded-lg">
                <h3 class="text-lg font-semibold text-purple-800">Tổng số khóa học</h3>
                <p class="text-2xl font-bold text-purple-600"><?php echo $stats['total_courses']; ?></p>
            </div>
        </div>
    <?php endif; ?>

    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Loại</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Khóa học/Bài tập</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Điểm</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ngày cập nhật</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Hành động</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php if (!empty($grades)): ?>
                    <?php foreach ($grades as $grade): ?>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                    <?php echo $grade['grade_type'] === 'Khóa học' ? 'bg-blue-100 text-blue-800' : 
                                        ($grade['grade_type'] === 'Bài tập' ? 'bg-green-100 text-green-800' : 'bg-purple-100 text-purple-800'); ?>">
                                    <?php echo htmlspecialchars($grade['grade_type']); ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                <?php echo htmlspecialchars($grade['course_name']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <span class="font-semibold"><?php echo number_format($grade['score'], 2); ?></span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?php echo format_date($grade['updated_at']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <?php if ($current_user['role'] === 'teacher'): ?>
                                    <button onclick="openEditGradeModal('<?php echo $grade['type']; ?>', <?php echo $grade['id']; ?>, <?php echo $grade['score']; ?>)" 
                                            class="text-indigo-600 hover:text-indigo-900">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5" class="px-6 py-4 text-center text-sm text-gray-500">
                            Chưa có điểm nào
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal sửa điểm -->
<div id="editGradeModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden">
    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
        <div class="mt-3">
            <h3 class="text-lg font-medium text-gray-900 mb-4">Sửa điểm</h3>
            <form method="POST">
                                 <input type="hidden" name="action" id="edit_action">
                 <input type="hidden" name="enrollment_id" id="edit_enrollment_id">
                 <input type="hidden" name="submission_id" id="edit_submission_id">
                 <input type="hidden" name="result_id" id="edit_result_id">
                 <div class="mb-4">
                     <label class="block text-sm font-medium text-gray-700 mb-2">Điểm</label>
                     <input type="number" name="grade" id="edit_grade" step="0.01" min="0" max="100" required 
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500">
                 </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Nhận xét</label>
                    <textarea name="feedback" id="edit_feedback" rows="3" 
                              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500"></textarea>
                </div>
                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="closeEditGradeModal()" 
                            class="px-4 py-2 text-gray-600 border border-gray-300 rounded-md hover:bg-gray-50">
                        Hủy
                    </button>
                    <button type="submit" 
                            class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700">
                        Cập nhật
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

