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
            case 'create_quiz':
                $title = trim($_POST['title']);
                $description = trim($_POST['description']);
                $course_id = $_POST['course_id'];
                $duration = $_POST['duration'];
                $start_date = $_POST['start_date'];
                $end_date = $_POST['end_date'];
                $max_score = $_POST['max_score'];
                
                if (empty($title) || empty($course_id) || empty($duration)) {
                    $error = 'Tiêu đề, khóa học và thời gian làm bài không được để trống';
                } else {
                    try {
                        $stmt = $conn->prepare("INSERT INTO quizzes (title, description, course_id, quiz_date, duration, total_marks, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
                        $status = 'published';
                        $stmt->bind_param("ssisiis", $title, $description, $course_id, $start_date, $duration, $max_score, $status);
                        if ($stmt->execute()) {
                            $message = 'Tạo bài kiểm tra thành công';
                            log_activity($conn, $current_user['id'], 'Tạo bài kiểm tra mới', "Bài kiểm tra: $title");
                        } else {
                            $error = 'Có lỗi xảy ra khi tạo bài kiểm tra';
                        }
                    } catch (Exception $e) {
                        $error = 'Có lỗi xảy ra: ' . $e->getMessage();
                    }
                }
                break;
                
            case 'update_quiz':
                $quiz_id = $_POST['quiz_id'];
                $title = trim($_POST['title']);
                $description = trim($_POST['description']);
                $course_id = $_POST['course_id'];
                $duration = $_POST['duration'];
                $start_date = $_POST['start_date'];
                $end_date = $_POST['end_date'];
                $max_score = $_POST['max_score'];
                $status = $_POST['status'];
                
                if (empty($title) || empty($course_id) || empty($duration)) {
                    $error = 'Tiêu đề, khóa học và thời gian làm bài không được để trống';
                } else {
                    try {
                        $stmt = $conn->prepare("UPDATE quizzes SET title = ?, description = ?, course_id = ?, quiz_date = ?, duration = ?, total_marks = ?, status = ?, updated_at = NOW() WHERE id = ?");
                        $stmt->bind_param("ssisiisi", $title, $description, $course_id, $start_date, $duration, $max_score, $status, $quiz_id);
                        if ($stmt->execute()) {
                            $message = 'Cập nhật bài kiểm tra thành công';
                            log_activity($conn, $current_user['id'], 'Cập nhật bài kiểm tra', "Bài kiểm tra: $title");
                        } else {
                            $error = 'Có lỗi xảy ra khi cập nhật bài kiểm tra';
                        }
                    } catch (Exception $e) {
                        $error = 'Có lỗi xảy ra: ' . $e->getMessage();
                    }
                }
                break;
                
            case 'delete_quiz':
                $quiz_id = $_POST['quiz_id'];
                try {
                    $stmt = $conn->prepare("DELETE FROM quizzes WHERE id = ?");
                    $stmt->bind_param("i", $quiz_id);
                    if ($stmt->execute()) {
                        $message = 'Xóa bài kiểm tra thành công';
                        log_activity($conn, $current_user['id'], 'Xóa bài kiểm tra', "ID: $quiz_id");
                    } else {
                        $error = 'Có lỗi xảy ra khi xóa bài kiểm tra';
                    }
                } catch (Exception $e) {
                    $error = 'Có lỗi xảy ra: ' . $e->getMessage();
                }
                break;
                
            case 'submit_quiz':
                $quiz_id = $_POST['quiz_id'];
                $answers = $_POST['answers'] ?? [];
                
                try {
                    // Lấy câu hỏi và đáp án đúng để chấm điểm
                    $questions = [];
                    $stmtQ = $conn->prepare("SELECT id, points FROM quiz_questions WHERE quiz_id = ?");
                    $stmtQ->bind_param("i", $quiz_id);
                    $stmtQ->execute();
                    $resQ = $stmtQ->get_result();
                    while ($row = $resQ->fetch_assoc()) { $questions[$row['id']] = (int)$row['points']; }

                    $correctOptionByQuestion = [];
                    if (!empty($questions)) {
                        $ids = implode(',', array_map('intval', array_keys($questions)));
                        $resOpt = $conn->query("SELECT question_id, id FROM quiz_options WHERE question_id IN ($ids) AND is_correct = 1");
                        while ($r = $resOpt->fetch_assoc()) { $correctOptionByQuestion[(int)$r['question_id']] = (int)$r['id']; }
                    }

                    $score = 0.0;
                    foreach ($answers as $questionId => $optionId) {
                        $qid = (int)$questionId;
                        $oid = (int)$optionId;
                        if (isset($correctOptionByQuestion[$qid]) && $correctOptionByQuestion[$qid] === $oid) {
                            $score += isset($questions[$qid]) ? (float)$questions[$qid] : 1.0;
                        }
                    }

                    // Lưu kết quả
                    $stmt = $conn->prepare("INSERT INTO quiz_results (quiz_id, student_id, score, completed_at) VALUES (?, ?, ?, NOW())");
                    $stmt->bind_param("iid", $quiz_id, $current_user['id'], $score);
                    if ($stmt->execute()) {
                        $result_id = $conn->insert_id;
                        // Lưu các đáp án đã chọn
                        $stmtAns = $conn->prepare("INSERT INTO quiz_result_answers (result_id, question_id, option_id) VALUES (?, ?, ?)");
                        foreach ($answers as $questionId => $optionId) {
                            $qid = (int)$questionId; $oid = (int)$optionId;
                            $stmtAns->bind_param("iii", $result_id, $qid, $oid);
                            $stmtAns->execute();
                        }
                        $message = 'Nộp bài kiểm tra thành công. Điểm của bạn: ' . $score;
                        log_activity($conn, $current_user['id'], 'Nộp bài kiểm tra', "Quiz ID: $quiz_id, Score: $score");
                    } else {
                        $error = 'Có lỗi xảy ra khi nộp bài kiểm tra';
                    }
                } catch (Exception $e) {
                    $error = 'Có lỗi xảy ra: ' . $e->getMessage();
                }
                break;

            case 'add_quiz_question':
                $quiz_id = (int)$_POST['quiz_id'];
                $question_text = trim($_POST['question_text']);
                $points = (int)($_POST['points'] ?? 1);
                if ($quiz_id && $question_text !== '') {
                    $stmt = $conn->prepare("INSERT INTO quiz_questions (quiz_id, question_text, points) VALUES (?, ?, ?)");
                    $stmt->bind_param("isi", $quiz_id, $question_text, $points);
                    if ($stmt->execute()) { $message = 'Thêm câu hỏi thành công'; }
                    else { $error = 'Không thể thêm câu hỏi'; }
                } else { $error = 'Thiếu thông tin câu hỏi'; }
                break;

            case 'update_quiz_question':
                $question_id = (int)$_POST['question_id'];
                $question_text = trim($_POST['question_text']);
                $points = (int)($_POST['points'] ?? 1);
                if ($question_id && $question_text !== '') {
                    $stmt = $conn->prepare("UPDATE quiz_questions SET question_text = ?, points = ?, updated_at = NOW() WHERE id = ?");
                    $stmt->bind_param("sii", $question_text, $points, $question_id);
                    if ($stmt->execute()) { $message = 'Cập nhật câu hỏi thành công'; }
                    else { $error = 'Không thể cập nhật câu hỏi'; }
                } else { $error = 'Thiếu thông tin câu hỏi'; }
                break;

            case 'delete_quiz_question':
                $question_id = (int)$_POST['question_id'];
                $stmt = $conn->prepare("DELETE FROM quiz_questions WHERE id = ?");
                $stmt->bind_param("i", $question_id);
                if ($stmt->execute()) { $message = 'Xóa câu hỏi thành công'; }
                else { $error = 'Không thể xóa câu hỏi'; }
                break;

            case 'add_quiz_option':
                $question_id = (int)$_POST['question_id'];
                $option_text = trim($_POST['option_text']);
                $is_correct = isset($_POST['is_correct']) ? 1 : 0;
                if ($question_id && $option_text !== '') {
                    $stmt = $conn->prepare("INSERT INTO quiz_options (question_id, option_text, is_correct) VALUES (?, ?, ?)");
                    $stmt->bind_param("isi", $question_id, $option_text, $is_correct);
                    if ($stmt->execute()) { $message = 'Thêm phương án thành công'; }
                    else { $error = 'Không thể thêm phương án'; }
                } else { $error = 'Thiếu thông tin phương án'; }
                break;

            case 'update_quiz_option':
                $option_id = (int)$_POST['option_id'];
                $option_text = trim($_POST['option_text']);
                $is_correct = isset($_POST['is_correct']) ? 1 : 0;
                if ($option_id && $option_text !== '') {
                    $stmt = $conn->prepare("UPDATE quiz_options SET option_text = ?, is_correct = ? WHERE id = ?");
                    $stmt->bind_param("sii", $option_text, $is_correct, $option_id);
                    if ($stmt->execute()) { $message = 'Cập nhật phương án thành công'; }
                    else { $error = 'Không thể cập nhật phương án'; }
                } else { $error = 'Thiếu thông tin phương án'; }
                break;

            case 'delete_quiz_option':
                $option_id = (int)$_POST['option_id'];
                $stmt = $conn->prepare("DELETE FROM quiz_options WHERE id = ?");
                $stmt->bind_param("i", $option_id);
                if ($stmt->execute()) { $message = 'Xóa phương án thành công'; }
                else { $error = 'Không thể xóa phương án'; }
                break;
        }
    }
}

// Lấy danh sách khóa học
$courses = [];
try {
    $stmt = $conn->prepare("SELECT id, name FROM courses WHERE status = ? ORDER BY name");
    $active_status = STATUS_ACTIVE;
    $stmt->bind_param("s", $active_status);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $courses[] = $row;
    }
} catch (Exception $e) {
    $error = 'Có lỗi xảy ra khi tải danh sách khóa học: ' . $e->getMessage();
}

// Lấy danh sách bài kiểm tra
$quizzes = [];
$view_type = isset($_GET['view']) ? $_GET['view'] : 'all';

try {
    if ($current_user['role'] === 'teacher') {
        // Giảng viên xem bài kiểm tra của các khóa học mình dạy
        $query = "SELECT q.*, c.name as course_name, 
                  (SELECT COUNT(*) FROM quiz_results WHERE quiz_id = q.id) as submission_count
                  FROM quizzes q 
                  JOIN courses c ON q.course_id = c.id 
                  WHERE c.instructor_id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $current_user['id']);
    } else {
        // Sinh viên xem bài kiểm tra của các khóa học đã đăng ký
        $query = "SELECT q.*, c.name as course_name, 
                  (SELECT COUNT(*) FROM quiz_results WHERE quiz_id = q.id AND student_id = ?) as submitted,
                  (SELECT score FROM quiz_results WHERE quiz_id = q.id AND student_id = ? LIMIT 1) as score
                  FROM quizzes q 
                  JOIN courses c ON q.course_id = c.id 
                  JOIN course_enrollments ce ON q.course_id = ce.course_id 
                  WHERE ce.student_id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("iii", $current_user['id'], $current_user['id'], $current_user['id']);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $quizzes[] = $row;
    }
} catch (Exception $e) {
    $error = 'Có lỗi xảy ra khi tải danh sách bài kiểm tra: ' . $e->getMessage();
}

// Lấy chi tiết bài kiểm tra nếu có quiz_id
$quiz_detail = null;
if (isset($_GET['quiz_id'])) {
    $quiz_id = $_GET['quiz_id'];
    try {
        $stmt = $conn->prepare("SELECT q.*, c.name as course_name FROM quizzes q JOIN courses c ON q.course_id = c.id WHERE q.id = ?");
        $stmt->bind_param("i", $quiz_id);
        $stmt->execute();
        $quiz_detail = $stmt->get_result()->fetch_assoc();
        if ($quiz_detail) {
            // Nạp câu hỏi và phương án
            $questions = [];
            $stmt = $conn->prepare("SELECT * FROM quiz_questions WHERE quiz_id = ? ORDER BY id ASC");
            $stmt->bind_param("i", $quiz_id);
            $stmt->execute();
            $res = $stmt->get_result();
            while ($q = $res->fetch_assoc()) { $questions[$q['id']] = $q; $questions[$q['id']]['options'] = []; }
            if (!empty($questions)) {
                $ids = implode(',', array_map('intval', array_keys($questions)));
                $optRes = $conn->query("SELECT * FROM quiz_options WHERE question_id IN ($ids) ORDER BY id ASC");
                while ($o = $optRes->fetch_assoc()) {
                    $questions[(int)$o['question_id']]['options'][] = $o;
                }
            }
            $quiz_detail['questions'] = $questions;
        }
    } catch (Exception $e) {
        $error = 'Có lỗi xảy ra khi tải chi tiết bài kiểm tra: ' . $e->getMessage();
    }
}
?>

<div class="bg-white rounded-lg shadow p-6">
    <div class="flex justify-between items-center mb-6">
        <h2 class="text-2xl font-bold text-gray-800">Quản lý Bài kiểm tra</h2>
        <?php if ($current_user['role'] === 'teacher'): ?>
            <button onclick="openCreateQuizModal()" class="bg-indigo-600 text-white px-4 py-2 rounded-lg hover:bg-indigo-700">
                <i class="fas fa-plus mr-2"></i>Tạo bài kiểm tra
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
            <a href="?type=quizzes&view=all" class="<?php echo $view_type === 'all' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'; ?> whitespace-nowrap py-2 px-1 border-b-2 font-medium text-sm">
                Tất cả
            </a>
            <a href="?type=quizzes&view=active" class="<?php echo $view_type === 'active' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'; ?> whitespace-nowrap py-2 px-1 border-b-2 font-medium text-sm">
                Đang diễn ra
            </a>
            <a href="?type=quizzes&view=completed" class="<?php echo $view_type === 'completed' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'; ?> whitespace-nowrap py-2 px-1 border-b-2 font-medium text-sm">
                Đã hoàn thành
            </a>
        </nav>
    </div>

    <?php if (isset($_GET['quiz_id']) && $quiz_detail): ?>
        <!-- Chi tiết bài kiểm tra -->
        <div class="mb-6">
            <a href="?type=quizzes" class="text-indigo-600 hover:text-indigo-800 mb-4 inline-block">
                <i class="fas fa-arrow-left mr-2"></i>Quay lại danh sách
            </a>
            
            <div class="bg-gray-50 p-6 rounded-lg mb-6">
                <h1 class="text-2xl font-bold text-gray-900 mb-4"><?php echo htmlspecialchars($quiz_detail['title']); ?></h1>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <div>
                        <p class="text-sm text-gray-600">Khóa học: <?php echo htmlspecialchars($quiz_detail['course_name']); ?></p>
                        <p class="text-sm text-gray-600">Thời gian làm bài: <?php echo $quiz_detail['duration']; ?> phút</p>
                        <p class="text-sm text-gray-600">Điểm tối đa: <?php echo $quiz_detail['total_marks']; ?> điểm</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-600">Ngày kiểm tra: <?php echo format_date($quiz_detail['quiz_date']); ?></p>
                        <p class="text-sm text-gray-600">Trạng thái: 
                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                <?php echo $quiz_detail['status'] === STATUS_ACTIVE ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'; ?>">
                                <?php echo $quiz_detail['status'] === STATUS_ACTIVE ? 'Hoạt động' : 'Đang chờ'; ?>
                            </span>
                        </p>
                    </div>
                </div>
                <div class="prose max-w-none">
                    <p class="text-sm text-gray-700"><?php echo nl2br(htmlspecialchars($quiz_detail['description'])); ?></p>
                </div>
            </div>

            <?php if ($current_user['role'] === 'student'): ?>
                <!-- Form làm bài kiểm tra -->
                <div class="border border-gray-200 rounded-lg p-6">
                    <h3 class="text-lg font-semibold mb-4">Làm bài kiểm tra</h3>
                    <form method="POST" id="quizForm">
                        <input type="hidden" name="action" value="submit_quiz">
                        <input type="hidden" name="quiz_id" value="<?php echo $quiz_detail['id']; ?>">
                        
                        
                        <div class="mb-4">
                            <p class="text-sm text-gray-600">Thời gian còn lại: <span id="timer" class="font-bold"></span></p>
                        </div>
                        
                        <!-- Câu hỏi từ DB -->
                        <div class="space-y-4">
                            <?php if (!empty($quiz_detail['questions'])): ?>
                                <?php foreach ($quiz_detail['questions'] as $q): ?>
                                    <div class="border border-gray-200 rounded-lg p-4">
                                        <h4 class="font-medium mb-2">Câu hỏi: <?php echo htmlspecialchars($q['question_text']); ?><?php if ($q['points']): ?> (<?php echo (int)$q['points']; ?>đ)<?php endif; ?></h4>
                                        <div class="space-y-2">
                                            <?php foreach ($q['options'] as $opt): ?>
                                                <label class="flex items-center">
                                                    <input type="radio" name="answers[<?php echo $q['id']; ?>]" value="<?php echo $opt['id']; ?>" class="mr-2">
                                                    <span><?php echo htmlspecialchars($opt['option_text']); ?></span>
                                                </label>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p class="text-sm text-gray-500">Chưa có câu hỏi cho bài kiểm tra này</p>
                            <?php endif; ?>
                        </div>
                        
                        <div class="mt-6">
                            <button type="submit" class="bg-green-600 text-white px-4 py-2 rounded-md hover:bg-green-700">
                                Nộp bài
                            </button>
                        </div>
                    </form>
                </div>
                <script>
                // Tự nộp khi hết giờ: đã được main.js submit form, nhưng đảm bảo không mất event
                </script>
            <?php else: ?>
                <!-- Quản lý câu hỏi/phương án (dành cho giảng viên) -->
                <div class="border border-gray-200 rounded-lg p-6">
                    <h3 class="text-lg font-semibold mb-4">Câu hỏi</h3>
                    <form method="POST" class="mb-4">
                        <input type="hidden" name="action" value="add_quiz_question">
                        <input type="hidden" name="quiz_id" value="<?php echo $quiz_detail['id']; ?>">
                        <div class="grid grid-cols-1 md:grid-cols-6 gap-3">
                            <div class="md:col-span-5">
                                <input type="text" name="question_text" placeholder="Nhập câu hỏi" required class="w-full px-3 py-2 border rounded-md">
                            </div>
                            <div>
                                <input type="number" name="points" placeholder="Điểm" value="1" min="1" class="w-full px-3 py-2 border rounded-md">
                            </div>
                        </div>
                        <div class="mt-3">
                            <button class="bg-indigo-600 text-white px-3 py-2 rounded-md">Thêm câu hỏi</button>
                        </div>
                    </form>

                    <?php if (!empty($quiz_detail['questions'])): ?>
                        <?php foreach ($quiz_detail['questions'] as $q): ?>
                            <div class="border border-gray-100 rounded p-4 mb-4">
                                <div class="flex justify-between items-center">
                                    <div class="font-medium flex items-center gap-2">
                                        <?php echo htmlspecialchars($q['question_text']); ?><?php if ($q['points']): ?> (<?php echo (int)$q['points']; ?>đ)<?php endif; ?>
                                        <!-- Nút sửa câu hỏi (toggle form) -->
                                        <button type="button" onclick="this.closest('.border').querySelector('.edit-question').classList.toggle('hidden')" class="text-blue-600 text-xs">Sửa</button>
                                    </div>
                                    <form method="POST" onsubmit="return confirm('Xóa câu hỏi này?');">
                                        <input type="hidden" name="action" value="delete_quiz_question">
                                        <input type="hidden" name="question_id" value="<?php echo $q['id']; ?>">
                                        <button class="text-red-600 hover:text-red-800 text-sm">Xóa</button>
                                    </form>
                                </div>

                                <!-- Form sửa câu hỏi -->
                                <form method="POST" class="edit-question hidden mt-3">
                                    <input type="hidden" name="action" value="update_quiz_question">
                                    <input type="hidden" name="question_id" value="<?php echo $q['id']; ?>">
                                    <div class="grid grid-cols-1 md:grid-cols-6 gap-3">
                                        <div class="md:col-span-5">
                                            <input type="text" name="question_text" value="<?php echo htmlspecialchars($q['question_text']); ?>" required class="w-full px-3 py-2 border rounded-md">
                                        </div>
                                        <div>
                                            <input type="number" name="points" value="<?php echo (int)$q['points']; ?>" min="1" class="w-full px-3 py-2 border rounded-md">
                                        </div>
                                    </div>
                                    <div class="mt-2">
                                        <button class="bg-blue-600 text-white px-3 py-1.5 rounded-md text-sm">Lưu</button>
                                    </div>
                                </form>

                                <div class="mt-3">
                                    <h4 class="text-sm font-semibold mb-2">Phương án</h4>
                                    <form method="POST" class="flex gap-2 mb-3">
                                        <input type="hidden" name="action" value="add_quiz_option">
                                        <input type="hidden" name="question_id" value="<?php echo $q['id']; ?>">
                                        <input type="text" name="option_text" placeholder="Nhập phương án" required class="flex-1 px-3 py-2 border rounded-md">
                                        <label class="flex items-center gap-2 text-sm">
                                            <input type="checkbox" name="is_correct"> Đúng
                                        </label>
                                        <button class="bg-green-600 text-white px-3 py-2 rounded-md">Thêm</button>
                                    </form>
                                    <ul class="space-y-2">
                                        <?php foreach ($q['options'] as $opt): ?>
                                            <li class="bg-gray-50 p-2 rounded">
                                                <form method="POST" class="flex items-center justify-between gap-2">
                                                    <div class="flex items-center gap-2 flex-1">
                                                        <input type="hidden" name="option_id" value="<?php echo $opt['id']; ?>">
                                                        <input type="hidden" name="action" value="update_quiz_option">
                                                        <input type="text" name="option_text" value="<?php echo htmlspecialchars($opt['option_text']); ?>" class="flex-1 px-2 py-1 border rounded text-sm">
                                                        <label class="flex items-center gap-1 text-xs">
                                                            <input type="checkbox" name="is_correct" <?php echo $opt['is_correct'] ? 'checked' : ''; ?>> Đúng
                                                        </label>
                                                    </div>
                                                    <div class="flex items-center gap-2">
                                                        <button class="bg-blue-600 text-white px-2 py-1 rounded text-xs">Lưu</button>
                                                        <form method="POST" onsubmit="return confirm('Xóa phương án này?');">
                                                            <input type="hidden" name="action" value="delete_quiz_option">
                                                            <input type="hidden" name="option_id" value="<?php echo $opt['id']; ?>">
                                                            <button class="text-red-600 hover:text-red-800 text-xs">Xóa</button>
                                                        </form>
                                                    </div>
                                                </form>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <!-- Danh sách bài kiểm tra -->
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tiêu đề</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Khóa học</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Thời gian</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Điểm tối đa</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Trạng thái</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Hành động</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php if (!empty($quizzes)): ?>
                        <?php foreach ($quizzes as $quiz): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($quiz['title']); ?></div>
                                    <div class="text-sm text-gray-500"><?php echo htmlspecialchars(substr($quiz['description'], 0, 50)) . (strlen($quiz['description']) > 50 ? '...' : ''); ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php echo htmlspecialchars($quiz['course_name']); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php echo $quiz['duration']; ?> phút
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php echo $quiz['total_marks']; ?> điểm
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                     <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                         <?php echo $quiz['status'] === 'published' ? 'bg-green-100 text-green-800' : ($quiz['status'] === 'closed' ? 'bg-red-100 text-red-800' : 'bg-yellow-100 text-yellow-800'); ?>">
                                         <?php echo $quiz['status'] === 'published' ? 'Hoạt động' : ($quiz['status'] === 'closed' ? 'Đã đóng' : 'Nháp'); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <button onclick="viewQuiz(<?php echo htmlspecialchars(json_encode($quiz)); ?>)" 
                                            class="text-indigo-600 hover:text-indigo-900 mr-3">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <?php if ($current_user['role'] === 'teacher'): ?>
                                    <button onclick="openEditQuizModal(<?php echo htmlspecialchars(json_encode($quiz)); ?>)" 
                                                class="text-blue-600 hover:text-blue-900 mr-3">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button onclick="deleteQuiz(<?php echo $quiz['id']; ?>)" 
                                                class="text-red-600 hover:text-red-900">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    <?php else: ?>
                                        <?php if ($quiz['submitted']): ?>
                                            <span class="text-green-600 text-sm">Đã làm (<?php echo $quiz['score']; ?> điểm)</span>
                                        <?php else: ?>
                                            <button onclick="startQuiz(<?php echo $quiz['id']; ?>)" 
                                                    class="text-green-600 hover:text-green-900">
                                                <i class="fas fa-play mr-1"></i>Làm bài
                                            </button>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="px-6 py-4 text-center text-sm text-gray-500">
                                Chưa có bài kiểm tra nào
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- Modal tạo bài kiểm tra -->
<div id="createQuizModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden">
    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
        <div class="mt-3">
            <h3 class="text-lg font-medium text-gray-900 mb-4">Tạo bài kiểm tra mới</h3>
            <form method="POST">
                <input type="hidden" name="action" value="create_quiz">
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
                    <label class="block text-sm font-medium text-gray-700 mb-2">Thời gian làm bài (phút)</label>
                    <input type="number" name="duration" value="30" min="1" max="180" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500">
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Thời gian bắt đầu</label>
                    <input type="datetime-local" name="start_date" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500">
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Điểm tối đa</label>
                    <input type="number" name="max_score" value="10" min="1" max="100" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500">
                </div>
                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="closeCreateQuizModal()" class="px-4 py-2 text-gray-600 border border-gray-300 rounded-md hover:bg-gray-50">
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

<!-- Modal sửa bài kiểm tra -->
<div id="editQuizModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden">
    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
        <div class="mt-3">
            <h3 class="text-lg font-medium text-gray-900 mb-4">Sửa bài kiểm tra</h3>
            <form method="POST">
                <input type="hidden" name="action" value="update_quiz">
                <input type="hidden" name="quiz_id" id="edit_quiz_id">
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
                    <label class="block text-sm font-medium text-gray-700 mb-2">Thời gian làm bài (phút)</label>
                    <input type="number" name="duration" id="edit_duration" min="1" max="180" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500">
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Thời gian bắt đầu</label>
                    <input type="datetime-local" name="start_date" id="edit_start_date" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500">
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Điểm tối đa</label>
                    <input type="number" name="max_score" id="edit_max_score" min="1" max="100" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500">
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Trạng thái</label>
                    <select name="status" id="edit_status" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        <option value="draft">Nháp</option>
                        <option value="published">Hoạt động</option>
                        <option value="closed">Đã đóng</option>
                    </select>
                </div>
                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="closeEditQuizModal()" class="px-4 py-2 text-gray-600 border border-gray-300 rounded-md hover:bg-gray-50">
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

<!-- Forms -->
<form id="deleteQuizForm" method="POST" style="display: none;">
    <input type="hidden" name="action" value="delete_quiz">
    <input type="hidden" name="quiz_id" id="delete_quiz_id">
</form>

<script>
// Provide QUIZ_DURATION for main.js to start timer
window.QUIZ_DURATION = <?php echo isset($quiz_detail) ? (int)$quiz_detail['duration'] : 'undefined'; ?>;
</script>