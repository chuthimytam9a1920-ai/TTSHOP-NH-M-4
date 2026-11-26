<?php
final class ChatController
{
    public function load()
    {

require_once (dirname(__DIR__, 2)) . '/chat_helpers.php';
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    header('Allow: GET'); ChatService::json(['ok' => false], 405);
}
$identity = ChatService::identity();
// Token ngan tab cu doc nham lich su sau khi doi tai khoan.
if (!ChatService::csrf($identity['key'], $_SERVER['HTTP_X_CHAT_CSRF'] ?? null)) {
    ChatService::json(['ok' => false, 'message' => 'Phiên chat đã thay đổi. Vui lòng tải lại trang.'], 403);
}
session_write_close();
try {
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    require (dirname(__DIR__, 2)) . '/connect.php';
    $conversation = (new ChatService($conn))->conversation( $identity);
    $messages = $conversation ? (new ChatService($conn))->messages( (int)$conversation['id'])->fetch_all(MYSQLI_ASSOC) : [];
    ChatService::json(['ok' => true, 'messages' => $messages]);
} catch (Throwable $e) {
    error_log('TT SHOP chat load: ' . $e->getMessage());
    ChatService::json(['ok' => false, 'message' => 'Không thể tải tin nhắn. Vui lòng thử lại.'], 503);
}

    }

    public function send()
    {

require_once (dirname(__DIR__, 2)) . '/chat_helpers.php';
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST'); ChatService::json(['ok' => false], 405);
}
$identity = ChatService::identity();
if (!ChatService::csrf($identity['key'], $_POST['csrf_token'] ?? null)) {
    ChatService::json(['ok' => false, 'message' => 'Phiên chat đã thay đổi. Vui lòng tải lại trang.'], 403);
}
// Khong chap nhan vai tro, ten hay ID hoi thoai tu client.
foreach (['sender_type', 'customer_name', 'conversation_id', 'user_id'] as $field) {
    if (array_key_exists($field, $_POST)) ChatService::json(['ok' => false, 'message' => 'Yêu cầu không hợp lệ.'], 400);
}
$message = ChatService::message($_POST['message'] ?? null);
if ($message === null) ChatService::json(['ok' => false, 'message' => 'Tin nhắn phải từ 1 đến 2000 ký tự.'], 422);
$now = microtime(true);
if ($now - (float)($_SESSION['ttchat_last_send'] ?? 0) < 1.5) {
    ChatService::json(['ok' => false, 'message' => 'Bạn gửi quá nhanh. Vui lòng thử lại sau vài giây.'], 429);
}
$_SESSION['ttchat_last_send'] = $now;
session_write_close();
$transaction = false;
try {
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    require (dirname(__DIR__, 2)) . '/connect.php';
    $conn->begin_transaction(); $transaction = true;
    $name = (new ChatService($conn))->customerName( $identity);
    $conversation = (new ChatService($conn))->conversation( $identity, true);
    if (!$conversation) throw new DomainException('Không tìm thấy cuộc trò chuyện.');
    $id = (int)$conversation['id'];
    (new ChatService($conn))->insert( $id, $name, 'user', $message);
    (new ChatService($conn))->insert( $id, $name, 'bot', ChatService::botReply($message));
    $conn->commit(); $transaction = false;
    ChatService::json(['ok' => true]);
} catch (Throwable $e) {
    if ($transaction) { try { $conn->rollback(); } catch (Throwable $ignored) {} }
    error_log('TT SHOP chat send: ' . $e->getMessage());
    ChatService::json(['ok' => false, 'message' => 'Không thể gửi tin nhắn. Vui lòng thử lại.'], 503);
}

    }
}
