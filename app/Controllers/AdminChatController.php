<?php
final class AdminChatController
{
    public static function chat_ttchat_admin_id($value): int {
    if (!is_string($value) && !is_int($value)) return 0;
    $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 2147483647]]);
    return $id === false ? 0 : $id;
}

    public static function chat_ttchat_admin_label(array $row): string {
    if ($row['user_id'] !== null) return ($row['customer_name'] ?: 'Khách hàng') . ' (ID KH ' . $row['user_id'] . ')';
    if (str_starts_with($row['owner_key'], 'u:')) return 'Tài khoản đã xóa (Chat #' . $row['id'] . ')';
    return 'Khách #' . $row['id'];
}

    public static function chat_ttchat_admin_list(array $rows, int $selectedId): string {
    $html = '';
    foreach ($rows as $row) {
        $id = (int)$row['id'];
        $html .= '<a class="customer-item' . ($id === $selectedId ? ' active' : '') . '" href="chat.php?conversation_id=' . $id . '">'
            . '<div class="customer-name">' . ChatService::escape(self::chat_ttchat_admin_label($row)) . '</div>'
            . '<div class="customer-time">' . ChatService::escape(date('d/m/Y H:i', strtotime($row['last_time']))) . '</div></a>';
    }
    return $html ?: '<div class="empty-chat">Chưa có khách nào nhắn tin.</div>';
}

    public static function chat_ttchat_admin_messages(mysqli_result $messages, bool $legacy = false): string {
    $html = '';
    while ($msg = $messages->fetch_assoc()) {
        $role = $msg['sender_type'];
        $label = $legacy ? ('Tên cũ: ' . ($msg['customer_name'] ?: '(trống)') . ' – ' . $role . ' (chưa xác minh)') :
            ($role === 'bot' ? 'Trợ lý tự động' : ($role === 'admin' ? 'Nhân viên TT SHOP' : 'Khách hàng'));
        $html .= '<div class="msg ' . ($role === 'user' ? 'user-msg' : 'admin-msg') . '" style="overflow-wrap:anywhere">'
            . '<div class="time">' . ChatService::escape($label) . '</div>' . nl2br(ChatService::escape($msg['message']))
            . '<div class="time">' . ChatService::escape(date('d/m/Y H:i', strtotime($msg['created_at']))) . '</div></div>';
    }
    return $html ?: '<div class="empty-chat">Chưa có tin nhắn.</div>';
}

    public function chat(): array
    {

require_once (dirname(__DIR__, 2) . '/admin') . '/../chat_helpers.php';
ChatService::session();
$poll = ($_GET['poll'] ?? '') === '1';
$adminId = (int)($_SESSION['admin_id'] ?? 0);
if (($_SESSION['admin_login'] ?? false) !== true || $adminId <= 0) {
    if ($poll) ChatService::json(['ok' => false], 403);
    header('Location: login.php'); exit;
}
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
$page = 'chat';
$adminScope = 'a:' . $adminId;
$token = ChatService::token($adminScope);
$archive = ($_GET['archive'] ?? '') === '1';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';




try {
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    require (dirname(__DIR__, 2) . '/admin') . '/../connect.php';
    if (!(new ChatService($conn))->query( 'SELECT id FROM admins WHERE id = ?', 'i', [$adminId])->fetch_assoc()) {
        if ($poll) ChatService::json(['ok' => false], 403);
        http_response_code(403); exit('Tài khoản Admin không hợp lệ.');
    }
    if ($poll && ($method !== 'GET' || !ChatService::csrf($adminScope, $_SERVER['HTTP_X_CHAT_CSRF'] ?? null))) {
        ChatService::json(['ok' => false], 403);
    }
    if ($method === 'POST') {
        if (!ChatService::csrf($adminScope, $_POST['csrf_token'] ?? null)) {
            http_response_code(403); exit('Phiên xác thực không hợp lệ. Vui lòng tải lại trang.');
        }
        $id = self::chat_ttchat_admin_id($_POST['conversation_id'] ?? null);
        $message = ChatService::message($_POST['message'] ?? null);
        if (!$id || $message === null) { http_response_code(422); exit('Tin nhắn hoặc mã cuộc trò chuyện không hợp lệ.'); }
        // Chi tra loi hoi thoai moi co owner_key; khong tra loi kho lich su cu.
        $row = (new ChatService($conn))->query( 'SELECT c.id, c.user_id, c.owner_key, m.customer_name FROM chat_conversations c JOIN chat_messages m ON m.conversation_id = c.id WHERE c.id = ? AND c.owner_key IS NOT NULL ORDER BY m.id DESC LIMIT 1', 'i', [$id])->fetch_assoc();
        if (!$row || ($row['user_id'] === null && str_starts_with($row['owner_key'], 'u:'))) {
            http_response_code(404); exit('Cuộc trò chuyện không tồn tại hoặc không còn hoạt động.');
        }
        (new ChatService($conn))->insert( $id, $row['customer_name'], 'admin', $message);
        header('Location: chat.php?conversation_id=' . $id, true, 303); exit;
    }
    if ($method !== 'GET') { header('Allow: GET, POST'); http_response_code(405); exit; }
    $rows = (new ChatService($conn))->query( "SELECT c.id, c.user_id, c.owner_key, COALESCE(NULLIF(TRIM(CONCAT_WS(' ', u.ho, u.ten)), ''), m.customer_name) AS customer_name, m.created_at AS last_time FROM chat_conversations c JOIN (SELECT conversation_id, MAX(id) AS last_id FROM chat_messages WHERE conversation_id IS NOT NULL GROUP BY conversation_id) latest ON latest.conversation_id = c.id JOIN chat_messages m ON m.id = latest.last_id LEFT JOIN users u ON u.id = c.user_id WHERE c.owner_key IS NOT NULL ORDER BY m.id DESC")->fetch_all(MYSQLI_ASSOC);
    $selectedId = self::chat_ttchat_admin_id($_GET['conversation_id'] ?? null);
    if (!$selectedId && !$poll && !$archive && !isset($_GET['conversation_id']) && $rows) $selectedId = (int)$rows[0]['id'];
    $selected = null;
    foreach ($rows as $row) if ((int)$row['id'] === $selectedId) { $selected = $row; break; }
    $listHtml = self::chat_ttchat_admin_list($rows, $archive ? 0 : $selectedId);
    $messagesHtml = $selected ? self::chat_ttchat_admin_messages((new ChatService($conn))->messages( $selectedId)) : '';
    if ($poll) ChatService::json(['ok' => true, 'list_html' => $listHtml, 'messages_html' => $messagesHtml]);
    $archiveHtml = $archive ? self::chat_ttchat_admin_messages((new ChatService($conn))->query( 'SELECT customer_name, sender_type, message, created_at FROM chat_messages WHERE conversation_id IS NULL ORDER BY id ASC'), true) : '';
} catch (Throwable $e) {
    error_log('TT SHOP admin chat: ' . $e->getMessage());
    if ($poll) ChatService::json(['ok' => false], 503);
    http_response_code(503); exit('Không thể mở chat. Kiểm tra SQL nâng cấp và thử lại.');
}

        return get_defined_vars();
    }
}
