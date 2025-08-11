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
            case 'send_message':
                $recipient_id = $_POST['recipient_id'];
                $subject = trim($_POST['subject']);
                $content = trim($_POST['content']);
                
                if (empty($subject) || empty($content)) {
                    $error = 'Tiêu đề và nội dung tin nhắn không được để trống';
                } else {
                    try {
                        $stmt = $conn->prepare("INSERT INTO messages (sender_id, recipient_id, subject, content, created_at) VALUES (?, ?, ?, ?, NOW())");
                        $stmt->bind_param("iiss", $current_user['id'], $recipient_id, $subject, $content);
                        if ($stmt->execute()) {
                            $message = 'Gửi tin nhắn thành công';
                            log_activity($conn, $current_user['id'], 'Gửi tin nhắn', "Đến: $recipient_id");
                        } else {
                            $error = 'Có lỗi xảy ra khi gửi tin nhắn';
                        }
                    } catch (Exception $e) {
                        $error = 'Có lỗi xảy ra: ' . $e->getMessage();
                    }
                }
                break;
                
            case 'mark_as_read':
                $message_id = $_POST['message_id'];
                try {
                    $stmt = $conn->prepare("UPDATE messages SET is_read = 1 WHERE id = ? AND recipient_id = ?");
                    $stmt->bind_param("ii", $message_id, $current_user['id']);
                    if ($stmt->execute()) {
                        $message = 'Đã đánh dấu đã đọc';
                    } else {
                        $error = 'Có lỗi xảy ra';
                    }
                } catch (Exception $e) {
                    $error = 'Có lỗi xảy ra: ' . $e->getMessage();
                }
                break;
                
            case 'delete_message':
                $message_id = $_POST['message_id'];
                try {
                    $stmt = $conn->prepare("DELETE FROM messages WHERE id = ? AND (sender_id = ? OR recipient_id = ?)");
                    $stmt->bind_param("iii", $message_id, $current_user['id'], $current_user['id']);
                    if ($stmt->execute()) {
                        $message = 'Xóa tin nhắn thành công';
                    } else {
                        $error = 'Có lỗi xảy ra khi xóa tin nhắn';
                    }
                } catch (Exception $e) {
                    $error = 'Có lỗi xảy ra: ' . $e->getMessage();
                }
                break;
        }
    }
}

// Lấy danh sách tin nhắn
$messages = [];
$message_type = isset($_GET['type']) ? $_GET['type'] : 'inbox';

try {
    if ($message_type === 'sent') {
        $query = "SELECT m.*, u.full_name as recipient_name 
                  FROM messages m 
                  JOIN users u ON m.recipient_id = u.id 
                  WHERE m.sender_id = ? 
                  ORDER BY m.created_at DESC";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $current_user['id']);
    } else {
        $query = "SELECT m.*, u.full_name as sender_name 
                  FROM messages m 
                  JOIN users u ON m.sender_id = u.id 
                  WHERE m.recipient_id = ? 
                  ORDER BY m.created_at DESC";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $current_user['id']);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $messages[] = $row;
    }
} catch (Exception $e) {
    $error = 'Có lỗi xảy ra khi tải tin nhắn: ' . $e->getMessage();
}

// Lấy danh sách người dùng để gửi tin nhắn
$users = [];
try {
    $stmt = $conn->prepare("SELECT id, full_name, email, role FROM users WHERE id != ? AND status = ? ORDER BY full_name");
    $active_status = STATUS_ACTIVE;
    $stmt->bind_param("is", $current_user['id'], $active_status);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $users[] = $row;
    }
} catch (Exception $e) {
    $error = 'Có lỗi xảy ra khi tải danh sách người dùng: ' . $e->getMessage();
}
?>

<div class="bg-white rounded-lg shadow p-6">
    <div class="flex justify-between items-center mb-6">
        <h2 class="text-2xl font-bold text-gray-800">Tin nhắn</h2>
        <button onclick="openComposeModal()" class="bg-indigo-600 text-white px-4 py-2 rounded-lg hover:bg-indigo-700">
            <i class="fas fa-plus mr-2"></i>Soạn tin nhắn
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

    <!-- Tab navigation -->
    <div class="border-b border-gray-200 mb-6">
        <nav class="-mb-px flex space-x-8">
            <a href="?type=inbox" class="<?php echo $message_type === 'inbox' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'; ?> whitespace-nowrap py-2 px-1 border-b-2 font-medium text-sm">
                Hộp thư đến
            </a>
            <a href="?type=sent" class="<?php echo $message_type === 'sent' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'; ?> whitespace-nowrap py-2 px-1 border-b-2 font-medium text-sm">
                Tin nhắn đã gửi
            </a>
        </nav>
    </div>

    <!-- Messages list -->
    <div class="space-y-4">
        <?php if (!empty($messages)): ?>
            <?php foreach ($messages as $msg): ?>
                <div class="border border-gray-200 rounded-lg p-4 hover:bg-gray-50 <?php echo !$msg['is_read'] && $message_type === 'inbox' ? 'bg-blue-50 border-blue-200' : ''; ?>">
                    <div class="flex justify-between items-start">
                        <div class="flex-1">
                            <div class="flex items-center mb-2">
                                <h3 class="text-lg font-medium text-gray-900 mr-2">
                                    <?php echo htmlspecialchars($msg['subject']); ?>
                                </h3>
                                <?php if (!$msg['is_read'] && $message_type === 'inbox'): ?>
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                        Mới
                                    </span>
                                <?php endif; ?>
                            </div>
                            <p class="text-sm text-gray-600 mb-2">
                                <?php echo $message_type === 'inbox' ? 'Từ: ' . htmlspecialchars($msg['sender_name']) : 'Đến: ' . htmlspecialchars($msg['recipient_name']); ?>
                            </p>
                            <p class="text-sm text-gray-500 mb-2">
                                <?php echo htmlspecialchars(substr($msg['content'], 0, 100)) . (strlen($msg['content']) > 100 ? '...' : ''); ?>
                            </p>
                            <p class="text-xs text-gray-400">
                                <?php echo format_date($msg['created_at']); ?>
                            </p>
                        </div>
                        <div class="flex space-x-2">
                            <?php if (!$msg['is_read'] && $message_type === 'inbox'): ?>
                                <button onclick="markAsRead(<?php echo $msg['id']; ?>)" 
                                        class="text-blue-600 hover:text-blue-900 text-sm">
                                    <i class="fas fa-check mr-1"></i>Đánh dấu đã đọc
                                </button>
                            <?php endif; ?>
                            <button onclick="viewMessage(<?php echo htmlspecialchars(json_encode($msg)); ?>)" 
                                    class="text-indigo-600 hover:text-indigo-900 text-sm">
                                <i class="fas fa-eye mr-1"></i>Xem
                            </button>
                            <button onclick="deleteMessage(<?php echo $msg['id']; ?>)" 
                                    class="text-red-600 hover:text-red-900 text-sm">
                                <i class="fas fa-trash mr-1"></i>Xóa
                            </button>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="text-center py-8">
                <i class="fas fa-inbox text-gray-400 text-4xl mb-4"></i>
                <p class="text-gray-500">Không có tin nhắn nào</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal soạn tin nhắn -->
<div id="composeModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden">
    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
        <div class="mt-3">
            <h3 class="text-lg font-medium text-gray-900 mb-4">Soạn tin nhắn mới</h3>
            <form method="POST">
                <input type="hidden" name="action" value="send_message">
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Gửi đến</label>
                    <select name="recipient_id" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        <option value="">Chọn người nhận</option>
                        <?php foreach ($users as $user): ?>
                            <option value="<?php echo $user['id']; ?>">
                                <?php echo htmlspecialchars($user['full_name']); ?> (<?php echo USER_ROLES[$user['role']]; ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Tiêu đề</label>
                    <input type="text" name="subject" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500">
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Nội dung</label>
                    <textarea name="content" rows="5" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500"></textarea>
                </div>
                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="closeComposeModal()" class="px-4 py-2 text-gray-600 border border-gray-300 rounded-md hover:bg-gray-50">
                        Hủy
                    </button>
                    <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700">
                        Gửi
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal xem tin nhắn -->
<div id="viewMessageModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden">
    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
        <div class="mt-3">
            <h3 class="text-lg font-medium text-gray-900 mb-4" id="messageSubject"></h3>
            <div class="mb-4">
                <p class="text-sm text-gray-600 mb-2" id="messageFrom"></p>
                <p class="text-sm text-gray-400 mb-4" id="messageDate"></p>
                <div class="bg-gray-50 p-4 rounded-md">
                    <p class="text-sm text-gray-700" id="messageContent"></p>
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
<form id="markAsReadForm" method="POST" style="display: none;">
    <input type="hidden" name="action" value="mark_as_read">
    <input type="hidden" name="message_id" id="mark_read_message_id">
</form>

<form id="deleteMessageForm" method="POST" style="display: none;">
    <input type="hidden" name="action" value="delete_message">
    <input type="hidden" name="message_id" id="delete_message_id">
</form>

<script>
function openComposeModal() {
    document.getElementById('composeModal').classList.remove('hidden');
}

function closeComposeModal() {
    document.getElementById('composeModal').classList.add('hidden');
}

function viewMessage(message) {
    document.getElementById('messageSubject').textContent = message.subject;
    document.getElementById('messageFrom').textContent = 'Từ: ' + (message.sender_name || message.recipient_name);
    document.getElementById('messageDate').textContent = new Date(message.created_at).toLocaleString('vi-VN');
    document.getElementById('messageContent').textContent = message.content;
    document.getElementById('viewMessageModal').classList.remove('hidden');
}

function closeViewModal() {
    document.getElementById('viewMessageModal').classList.add('hidden');
}

function markAsRead(messageId) {
    document.getElementById('mark_read_message_id').value = messageId;
    document.getElementById('markAsReadForm').submit();
}

function deleteMessage(messageId) {
    if (confirm('Bạn có chắc chắn muốn xóa tin nhắn này?')) {
        document.getElementById('delete_message_id').value = messageId;
        document.getElementById('deleteMessageForm').submit();
    }
}
</script> 