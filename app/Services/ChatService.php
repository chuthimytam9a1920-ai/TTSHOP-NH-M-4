<?php
/** TT SHOP - extracted business logic. */
final class ChatService extends DatabaseService
{
    public static function session(): void {
    if (session_status() === PHP_SESSION_NONE) session_start();
}

    public static function identity(): array {
    self::session();
    $uid = (int)($_SESSION['user_id'] ?? 0);
    if (($_SESSION['is_login'] ?? false) === true && $uid > 0) {
        return ['key' => 'u:' . $uid, 'user_id' => $uid];
    }
    if (empty($_SESSION['ttchat_guest_secret'])) {
        $_SESSION['ttchat_guest_secret'] = bin2hex(random_bytes(32));
    }
    return ['key' => 'g:' . hash('sha256', $_SESSION['ttchat_guest_secret']), 'user_id' => null];
}

    public static function token(string $scope): string {
    self::session();
    $bucket = str_starts_with($scope, 'a:') ? 'admin' : 'customer';
    if (($_SESSION['ttchat_csrf'][$bucket]['scope'] ?? null) !== $scope) {
        $_SESSION['ttchat_csrf'][$bucket] = ['scope' => $scope, 'token' => bin2hex(random_bytes(32))];
    }
    return $_SESSION['ttchat_csrf'][$bucket]['token'];
}

    public static function csrf(string $scope, $token): bool {
    return is_string($token) && hash_equals(self::token($scope), $token);
}

    public static function escape($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

    public function query( string $sql, string $types = '', array $params = []) {
        $conn = $this->connection;
    $stmt = $conn->prepare($sql);
    try {
        if ($types !== '') $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result === false ? $stmt->affected_rows : $result;
    } finally { $stmt->close(); }
}

    public function conversation( array $identity, bool $create = false): ?array {
        $conn = $this->connection;
    $key = $identity['key'];
    $uid = $identity['user_id'];
    if ($create) {
        if ($uid !== null) {
            // INSERT ... SELECT: khong tao hoi thoai cho tai khoan da bi xoa.
            $this->query( 'INSERT INTO chat_conversations (user_id, owner_key) SELECT id, ? FROM users WHERE id = ? ON DUPLICATE KEY UPDATE id = chat_conversations.id', 'si', [$key, $uid]);
        } else {
            $this->query( "INSERT INTO chat_conversations (guest_name, owner_key) VALUES ('Khách', ?) ON DUPLICATE KEY UPDATE id = chat_conversations.id", 's', [$key]);
        }
    }
    $row = $this->query( 'SELECT id, user_id FROM chat_conversations WHERE owner_key = ? AND user_id <=> ? LIMIT 1', 'si', [$key, $uid])->fetch_assoc();
    return $row ?: null;
}

    public function customerName( array $identity): string {
        $conn = $this->connection;
    if ($identity['user_id'] === null) return 'Khách';
    $row = $this->query( "SELECT LEFT(TRIM(CONCAT_WS(' ', ho, ten)), 120) AS name FROM users WHERE id = ?", 'i', [$identity['user_id']])->fetch_assoc();
    if (!$row) throw new DomainException('Tài khoản không còn tồn tại.');
    return $row['name'] !== '' ? $row['name'] : 'Khách hàng';
}

    public static function message($value): ?string {
    if (!is_string($value)) return null;
    $value = trim($value);
    return preg_match('/^.{1,2000}$/usD', $value) === 1 ? $value : null;
}

    public static function botReply(string $message): string {
    if (preg_match('/giao hàng|ship/iu', $message)) return 'Shop giao hàng toàn quốc từ 2 - 5 ngày tuỳ khu vực 🚚';
    if (preg_match('/đổi trả|hoàn tiền/iu', $message)) return 'Shop hỗ trợ đổi trả trong 7 ngày nếu sản phẩm bị lỗi từ nhà sản xuất 🔄';
    if (preg_match('/kiểm tra đơn|đơn hàng/iu', $message)) return 'Bạn vào Tài khoản → Danh sách đơn hàng để kiểm tra trạng thái đơn nhé 📦';
    if (preg_match('/sản phẩm|bán gì/iu', $message)) return 'TT SHOP bán văn phòng phẩm, dụng cụ học tập, balo, sổ tay và máy tính cầm tay 🛍️';
    return 'TT SHOP đã nhận tin nhắn của bạn ❤️. Nhân viên sẽ hỗ trợ bạn sớm nhất.';
}

    public function insert( int $id, string $name, string $role, string $message): void {
        $conn = $this->connection;
    $this->query( 'INSERT INTO chat_messages (conversation_id, customer_name, sender_type, message) VALUES (?, ?, ?, ?)', 'isss', [$id, $name, $role, $message]);
}

    public function messages( int $id): mysqli_result {
        $conn = $this->connection;
    return $this->query( 'SELECT id, sender_type, message, created_at FROM chat_messages WHERE conversation_id = ? ORDER BY id ASC', 'i', [$id]);
}

    public static function json(array $data, int $status = 200): void {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    header('X-Content-Type-Options: nosniff');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}
}
