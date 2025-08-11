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
            case 'create_topic':
                $title = trim($_POST['title']);
                $content = trim($_POST['content']);
                $category = trim($_POST['category']);
                
                if (empty($title) || empty($content)) {
                    $error = 'Tiêu đề và nội dung không được để trống';
                } else {
                    try {
                        $stmt = $conn->prepare("INSERT INTO forum_topics (title, content, category, author_id, created_at) VALUES (?, ?, ?, ?, NOW())");
                        $stmt->bind_param("sssi", $title, $content, $category, $current_user['id']);
                        if ($stmt->execute()) {
                            $message = 'Tạo chủ đề thành công';
                            log_activity($conn, $current_user['id'], 'Tạo chủ đề diễn đàn', "Tiêu đề: $title");
                        } else {
                            $error = 'Có lỗi xảy ra khi tạo chủ đề';
                        }
                    } catch (Exception $e) {
                        $error = 'Có lỗi xảy ra: ' . $e->getMessage();
                    }
                }
                break;
                
            case 'add_reply':
                $topic_id = $_POST['topic_id'];
                $content = trim($_POST['content']);
                
                if (empty($content)) {
                    $error = 'Nội dung trả lời không được để trống';
                } else {
                    try {
                        $stmt = $conn->prepare("INSERT INTO forum_replies (topic_id, author_id, content, created_at) VALUES (?, ?, ?, NOW())");
                        $stmt->bind_param("iis", $topic_id, $current_user['id'], $content);
                        if ($stmt->execute()) {
                            $message = 'Trả lời thành công';
                            log_activity($conn, $current_user['id'], 'Trả lời diễn đàn', "Topic ID: $topic_id");
                        } else {
                            $error = 'Có lỗi xảy ra khi trả lời';
                        }
                    } catch (Exception $e) {
                        $error = 'Có lỗi xảy ra: ' . $e->getMessage();
                    }
                }
                break;
                
            case 'delete_topic':
                $topic_id = $_POST['topic_id'];
                try {
                    $stmt = $conn->prepare("DELETE FROM forum_topics WHERE id = ? AND author_id = ?");
                    $stmt->bind_param("ii", $topic_id, $current_user['id']);
                    if ($stmt->execute()) {
                        $message = 'Xóa chủ đề thành công';
                        log_activity($conn, $current_user['id'], 'Xóa chủ đề diễn đàn', "Topic ID: $topic_id");
                    } else {
                        $error = 'Có lỗi xảy ra khi xóa chủ đề';
                    }
                } catch (Exception $e) {
                    $error = 'Có lỗi xảy ra: ' . $e->getMessage();
                }
                break;
        }
    }
}

// Lấy danh sách chủ đề
$topics = [];
$selected_category = isset($_GET['category']) ? $_GET['category'] : '';

try {
    $query = "SELECT t.*, u.full_name as author_name, 
              (SELECT COUNT(*) FROM forum_replies WHERE topic_id = t.id) as reply_count,
              (SELECT MAX(created_at) FROM forum_replies WHERE topic_id = t.id) as last_reply_date
              FROM forum_topics t 
              JOIN users u ON t.author_id = u.id";
    
    if (!empty($selected_category)) {
        $query .= " WHERE t.category = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("s", $selected_category);
    } else {
        $stmt = $conn->prepare($query);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $topics[] = $row;
    }
} catch (Exception $e) {
    $error = 'Có lỗi xảy ra khi tải danh sách chủ đề: ' . $e->getMessage();
}

// Lấy chi tiết chủ đề nếu có topic_id
$topic_detail = null;
$replies = [];
if (isset($_GET['topic_id'])) {
    $topic_id = $_GET['topic_id'];
    try {
        // Lấy thông tin chủ đề
        $stmt = $conn->prepare("SELECT t.*, u.full_name as author_name FROM forum_topics t JOIN users u ON t.author_id = u.id WHERE t.id = ?");
        $stmt->bind_param("i", $topic_id);
        $stmt->execute();
        $topic_detail = $stmt->get_result()->fetch_assoc();
        
        if ($topic_detail) {
            // Lấy danh sách trả lời
            $stmt = $conn->prepare("SELECT r.*, u.full_name as author_name FROM forum_replies r JOIN users u ON r.author_id = u.id WHERE r.topic_id = ? ORDER BY r.created_at ASC");
            $stmt->bind_param("i", $topic_id);
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $replies[] = $row;
            }
        }
    } catch (Exception $e) {
        $error = 'Có lỗi xảy ra khi tải chi tiết chủ đề: ' . $e->getMessage();
    }
}

// Danh sách danh mục
$categories = [
    'general' => 'Chung',
    'academic' => 'Học tập',
    'technical' => 'Kỹ thuật',
    'social' => 'Giao lưu',
    'announcement' => 'Thông báo'
];
?>

<div class="bg-white rounded-lg shadow p-6">
    <div class="flex justify-between items-center mb-6">
        <h2 class="text-2xl font-bold text-gray-800">Diễn đàn</h2>
        <button onclick="openCreateTopicModal()" class="bg-indigo-600 text-white px-4 py-2 rounded-lg hover:bg-indigo-700">
            <i class="fas fa-plus mr-2"></i>Tạo chủ đề mới
        </button>
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

    <?php if (isset($_GET['topic_id']) && $topic_detail): ?>
        <!-- Chi tiết chủ đề -->
        <div class="mb-6">
            <a href="?type=forum" class="text-indigo-600 hover:text-indigo-800 mb-4 inline-block">
                <i class="fas fa-arrow-left mr-2"></i>Quay lại danh sách
            </a>
            
            <div class="bg-gray-50 p-6 rounded-lg mb-6">
                <h1 class="text-2xl font-bold text-gray-900 mb-4"><?php echo htmlspecialchars($topic_detail['title']); ?></h1>
                <div class="flex items-center text-sm text-gray-500 mb-4">
                    <span>Tác giả: <?php echo htmlspecialchars($topic_detail['author_name']); ?></span>
                    <span class="mx-2">•</span>
                    <span><?php echo format_date($topic_detail['created_at']); ?></span>
                    <span class="mx-2">•</span>
                    <span>Danh mục: <?php echo $categories[$topic_detail['category']] ?? $topic_detail['category']; ?></span>
                </div>
                <div class="prose max-w-none">
                    <?php echo nl2br(htmlspecialchars($topic_detail['content'])); ?>
                </div>
            </div>

            <!-- Danh sách trả lời -->
            <div class="space-y-4">
                <h3 class="text-lg font-semibold">Trả lời (<?php echo count($replies); ?>)</h3>
                
                <?php foreach ($replies as $reply): ?>
                    <div class="border border-gray-200 rounded-lg p-4">
                        <div class="flex justify-between items-start mb-2">
                            <div class="flex items-center">
                                <span class="font-medium text-gray-900"><?php echo htmlspecialchars($reply['author_name']); ?></span>
                                <span class="text-sm text-gray-500 ml-2"><?php echo format_date($reply['created_at']); ?></span>
                            </div>
                        </div>
                        <div class="prose max-w-none">
                            <?php echo nl2br(htmlspecialchars($reply['content'])); ?>
                        </div>
                    </div>
                <?php endforeach; ?>

                <!-- Form trả lời -->
                <div class="border border-gray-200 rounded-lg p-4">
                    <h4 class="font-medium text-gray-900 mb-4">Trả lời</h4>
                    <form method="POST">
                        <input type="hidden" name="action" value="add_reply">
                        <input type="hidden" name="topic_id" value="<?php echo $topic_detail['id']; ?>">
                        <div class="mb-4">
                            <textarea name="content" rows="4" required placeholder="Nhập nội dung trả lời..." class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500"></textarea>
                        </div>
                        <button type="submit" class="bg-indigo-600 text-white px-4 py-2 rounded-md hover:bg-indigo-700">
                            Gửi trả lời
                        </button>
                    </form>
                </div>
            </div>
        </div>
    <?php else: ?>
        <!-- Danh sách chủ đề -->
        <div class="mb-6">
            <div class="flex space-x-4 mb-4">
                <a href="?type=forum" class="<?php echo empty($selected_category) ? 'bg-indigo-100 text-indigo-700' : 'bg-gray-100 text-gray-700'; ?> px-3 py-2 rounded-md text-sm font-medium">
                    Tất cả
                </a>
                <?php foreach ($categories as $key => $name): ?>
                    <a href="?type=forum&category=<?php echo $key; ?>" class="<?php echo $selected_category === $key ? 'bg-indigo-100 text-indigo-700' : 'bg-gray-100 text-gray-700'; ?> px-3 py-2 rounded-md text-sm font-medium">
                        <?php echo $name; ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="space-y-4">
            <?php if (!empty($topics)): ?>
                <?php foreach ($topics as $topic): ?>
                    <div class="border border-gray-200 rounded-lg p-4 hover:bg-gray-50">
                        <div class="flex justify-between items-start">
                            <div class="flex-1">
                                <h3 class="text-lg font-medium text-gray-900 mb-2">
                                    <a href="?type=forum&topic_id=<?php echo $topic['id']; ?>" class="hover:text-indigo-600">
                                        <?php echo htmlspecialchars($topic['title']); ?>
                                    </a>
                                </h3>
                                <div class="flex items-center text-sm text-gray-500 mb-2">
                                    <span>Tác giả: <?php echo htmlspecialchars($topic['author_name']); ?></span>
                                    <span class="mx-2">•</span>
                                    <span><?php echo format_date($topic['created_at']); ?></span>
                                    <span class="mx-2">•</span>
                                    <span><?php echo $categories[$topic['category']] ?? $topic['category']; ?></span>
                                </div>
                                <p class="text-sm text-gray-600">
                                    <?php echo htmlspecialchars(substr($topic['content'], 0, 150)) . (strlen($topic['content']) > 150 ? '...' : ''); ?>
                                </p>
                            </div>
                            <div class="text-right text-sm text-gray-500">
                                <div><?php echo $topic['reply_count']; ?> trả lời</div>
                                <?php if ($topic['last_reply_date']): ?>
                                    <div class="text-xs">Cập nhật: <?php echo format_date($topic['last_reply_date']); ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php if ($topic['author_id'] == $current_user['id']): ?>
                            <div class="mt-2">
                                <button onclick="deleteTopic(<?php echo $topic['id']; ?>)" class="text-red-600 hover:text-red-900 text-sm">
                                    <i class="fas fa-trash mr-1"></i>Xóa
                                </button>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="text-center py-8">
                    <i class="fas fa-comments text-gray-400 text-4xl mb-4"></i>
                    <p class="text-gray-500">Chưa có chủ đề nào</p>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Modal tạo chủ đề -->
<div id="createTopicModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden">
    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
        <div class="mt-3">
            <h3 class="text-lg font-medium text-gray-900 mb-4">Tạo chủ đề mới</h3>
            <form method="POST">
                <input type="hidden" name="action" value="create_topic">
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Danh mục</label>
                    <select name="category" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        <option value="">Chọn danh mục</option>
                        <?php foreach ($categories as $key => $name): ?>
                            <option value="<?php echo $key; ?>"><?php echo $name; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Tiêu đề</label>
                    <input type="text" name="title" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500">
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Nội dung</label>
                    <textarea name="content" rows="6" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500"></textarea>
                </div>
                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="closeCreateTopicModal()" class="px-4 py-2 text-gray-600 border border-gray-300 rounded-md hover:bg-gray-50">
                        Hủy
                    </button>
                    <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700">
                        Tạo chủ đề
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Form xóa chủ đề -->
<form id="deleteTopicForm" method="POST" style="display: none;">
    <input type="hidden" name="action" value="delete_topic">
    <input type="hidden" name="topic_id" id="delete_topic_id">
</form>

