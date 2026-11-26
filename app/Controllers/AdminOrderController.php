<?php
final class AdminOrderController
{
    private static function order_delete_redirect(bool $ok, string $message): void {
    $_SESSION['admin_order_flash'] = ['ok' => $ok, 'message' => $message];
    header('Location: orders.php', true, 303);
    exit();
}

    public function deleteOrder()
    {

// admin/delete_order.php — Xóa đơn hàng bằng POST + CSRF và transaction.
require_once (dirname(__DIR__, 2) . '/admin') . '/check_admin.php';
require_once (dirname(__DIR__, 2) . '/admin') . '/../csrf.php';
header('Cache-Control: no-store');



if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    exit('Yêu cầu không hợp lệ. Hãy xóa đơn hàng từ trang quản lý.');
}
if (!CsrfService::check($_POST['csrf_token'] ?? null)) {
    http_response_code(403);
    exit('Phiên xác thực không hợp lệ. Vui lòng tải lại trang quản lý đơn hàng.');
}
$id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1, 'max_range' => 2147483647]
]);
if ($id === false || $id === null) {
    self::order_delete_redirect(false, 'ID đơn hàng không hợp lệ.');
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
require_once (dirname(__DIR__, 2) . '/admin') . '/../connect.php';
$inTransaction = false;
try {
    $conn->begin_transaction();
    $inTransaction = true;

    $stmt = $conn->prepare('SELECT id, phuong_thuc FROM donhang WHERE id = ? FOR UPDATE');
    try {
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $exists = $stmt->get_result()->fetch_assoc();
    } finally {
        $stmt->close();
    }
    if (!$exists) {
        $conn->rollback();
        $inTransaction = false;
        self::order_delete_redirect(false, 'Đơn hàng không tồn tại hoặc đã bị xóa.');
    }

    if ($exists['phuong_thuc']==='PAYOS') { $conn->rollback(); $inTransaction=false; self::order_delete_redirect(false,'Không xóa đơn QR để bảo toàn lịch sử giao dịch.'); }
    // Chi tiết đơn có FK RESTRICT, phải xóa trước đơn hàng.
    // Đánh giá và lịch sử voucher được xóa theo FK ON DELETE CASCADE
    // trong ttshop_final.sql và update_voucher_fix.sql.
    $stmt = $conn->prepare('DELETE FROM chitiet_donhang WHERE donhang_id = ?');
    try {
        $stmt->bind_param('i', $id);
        $stmt->execute();
    } finally {
        $stmt->close();
    }
    $stmt = $conn->prepare('DELETE FROM donhang WHERE id = ?');
    try {
        $stmt->bind_param('i', $id);
        $stmt->execute();
        if ($stmt->affected_rows !== 1) {
            throw new RuntimeException('Không xóa được đúng một đơn hàng.');
        }
    } finally {
        $stmt->close();
    }
    $conn->commit();
    $inTransaction = false;
    self::order_delete_redirect(true, 'Đã xóa đơn hàng.');
} catch (Throwable $e) {
    if ($inTransaction) {
        try { $conn->rollback(); } catch (Throwable $ignored) {}
    }
    error_log('TT SHOP delete order: ' . $e->getMessage());
    self::order_delete_redirect(false, 'Không thể xóa đơn hàng. Vui lòng thử lại.');
}

    }

    private static function os_redirect(?int $id, bool $ok, string $message): void {
    $_SESSION['admin_order_flash'] = ['ok' => $ok, 'message' => $message];
    header('Location: ' . ($id ? 'order_detail.php?id=' . $id : 'orders.php'), true, 303);
    exit;
}

    private static function os_normalize(string $status): string {
    return $status === 'Đã hủy' ? 'Đã huỷ' : $status;
}

    public function updateStatus()
    {

// admin/update_order_status.php
require_once (dirname(__DIR__, 2) . '/admin') . '/check_admin.php';
require_once (dirname(__DIR__, 2) . '/admin') . '/../csrf.php';
header('Cache-Control: no-store');




if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    exit('Chỉ chấp nhận yêu cầu POST.');
}
if (!CsrfService::check($_POST['csrf_token'] ?? null)) {
    http_response_code(403);
    exit('Phiên xác thực không hợp lệ. Vui lòng tải lại trang chi tiết đơn hàng.');
}
$id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1, 'max_range' => 2147483647]]);
if (!$id) self::os_redirect(null, false, 'ID đơn hàng không hợp lệ.');
$new = $_POST['trang_thai'] ?? null;
$expected = $_POST['expected_status'] ?? null;
$allowed = ['Chờ xử lý', 'Chờ lấy hàng', 'Đang vận chuyển', 'Đã giao', 'Đã huỷ'];
if (!is_string($new) || !is_string($expected) || !in_array(self::os_normalize($new), $allowed, true)) {
    self::os_redirect($id, false, 'Trạng thái đơn hàng không hợp lệ.');
}
$new = self::os_normalize($new);
$expected = self::os_normalize($expected);

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
require_once (dirname(__DIR__, 2) . '/admin') . '/../connect.php';
$inTransaction = false;
try {
    // Lấy chủ đơn trước khi khóa theo cùng thứ tự với luồng đặt hàng.
    $stmt = $conn->prepare('SELECT user_id FROM donhang WHERE id = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $initial = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$initial) self::os_redirect(null, false, 'Đơn hàng không tồn tại.');
    $userId = (int)$initial['user_id'];

    $conn->begin_transaction();
    $inTransaction = true;
    $stmt = $conn->prepare('SELECT id FROM users WHERE id = ? FOR UPDATE');
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $owner = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$owner) throw new DomainException('Không tìm thấy tài khoản của đơn hàng.');

    $stmt = $conn->prepare('SELECT user_id, trang_thai, ma_giam_gia, phuong_thuc, payment_status FROM donhang WHERE id = ? FOR UPDATE');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $order = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$order || (int)$order['user_id'] !== $userId) {
        throw new DomainException('Đơn hàng đã thay đổi hoặc không còn tồn tại.');
    }
    $current = self::os_normalize((string)$order['trang_thai']);
    if ($current !== $expected) {
        throw new DomainException('Trạng thái đã thay đổi. Hãy kiểm tra lại trước khi cập nhật.');
    }
    if ($current === $new) {
        $conn->commit();
        $inTransaction = false;
        self::os_redirect($id, true, 'Trạng thái đơn hàng không thay đổi.');
    }

    if ($order['phuong_thuc']==='PAYOS' && ($order['payment_status']!=='PAID' || $new==='Đã huỷ' || $current==='Đã huỷ')) {
        throw new DomainException('Đơn QR phải được xác nhận đủ tiền trước khi giao. Không hủy hoặc mở lại đơn QR bằng trạng thái vận chuyển; cần đối soát riêng.');
    }
    // Hủy đơn giải phóng lượt sử dụng theo voucher_usage().
    // Không mở lại đơn có voucher đã hủy, tránh vượt giới hạn sau khi lượt được dùng lại.
    if ($current === 'Đã huỷ') {
        $stmt = $conn->prepare('SELECT id FROM lichsu_magiamgia WHERE donhang_id = ?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $history = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (trim((string)($order['ma_giam_gia'] ?? '')) !== '' || $history) {
            throw new DomainException('Không mở lại đơn có voucher đã hủy. Vui lòng tạo đơn mới.');
        }
    }

    // Không sửa giá tiền, hạng lúc mua hoặc lịch sử voucher.
    $stmt = $conn->prepare('UPDATE donhang SET trang_thai = ? WHERE id = ?');
    $stmt->bind_param('si', $new, $id);
    $stmt->execute();
    $stmt->close();
    $conn->commit();
    $inTransaction = false;
    self::os_redirect($id, true, 'Đã cập nhật trạng thái đơn hàng.');
} catch (Throwable $e) {
    if ($inTransaction) {
        try { $conn->rollback(); } catch (Throwable $ignored) {}
    }
    if (!($e instanceof DomainException)) {
        error_log('TT SHOP update order status: ' . $e->getMessage());
    }
    self::os_redirect($id, false, $e instanceof DomainException ? $e->getMessage() : 'Không thể cập nhật trạng thái. Vui lòng thử lại.');
}

    }
}
