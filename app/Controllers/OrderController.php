<?php
//hủy đơn hàng (cancel()) 
//đánh giá đơn hàng đã giao (review())
final class OrderController
{
    private static function hd_redirect(int $userId, bool $ok, string $message): void
{
    $_SESSION['customer_cancel_flash'] = [
        'user_id' => $userId,
        'ok' => $ok,
        'message' => $message,
    ];
    header('Location: taikhoan.php?tab=donhang', true, 303);
    exit;
}
    private static function hd_positive_id($value): ?int
{
    if (!is_int($value) && !is_string($value)) {
        return null;
    }
    $id = filter_var($value, FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 1, 'max_range' => 2147483647],
    ]);
    return $id === false ? null : $id;
}
    public function cancel()
    {


    // huydonhang.php — Hủy đơn của khách hàng bằng POST, CSRF và transaction.


if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once (dirname(__DIR__, 2)) . '/csrf.php';
header('Cache-Control: no-store');
// Chỉ cho phép khách hàng đã đăng nhập thực hiện yêu cầu.
// Lấy ID người dùng và email từ session, kiểm tra tính hợp lệ.
$userId = self::hd_positive_id($_SESSION['user_id'] ?? null);
$email = $_SESSION['user_email'] ?? '';
if (($_SESSION['is_login'] ?? false) !== true || $userId === null
    || !is_string($email) || trim($email) === '') {
    header('Location: dangnhap.php', true, 303);
    exit;
}

// GET chỉ để đọc dữ liệu, tuyệt đối không hủy đơn qua URL.
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    exit('Chỉ chấp nhận yêu cầu POST. Hãy hủy đơn từ trang tài khoản.');
}
if (!CsrfService::check($_POST['csrf_token'] ?? null)) {
    http_response_code(403);
    exit('Phiên xác thực không hợp lệ. Vui lòng tải lại trang tài khoản.');
}
$orderId = self::hd_positive_id($_POST['id'] ?? null);
if ($orderId === null) {
    self::hd_redirect($userId, false, 'ID đơn hàng không hợp lệ.');
}
$inTransaction = false;
try {
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    require_once (dirname(__DIR__, 2)) . '/connect.php';
    $conn->begin_transaction();
    $inTransaction = true;
    // Khóa tài khoản trước, cùng thứ tự với đặt hàng và cập nhật trạng thái Admin.
    $stmt = $conn->prepare('SELECT email FROM users WHERE id = ? FOR UPDATE');
    try {
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $owner = $stmt->get_result()->fetch_assoc();
    } finally {
        $stmt->close();
    }
    if (!$owner || strcasecmp($email, (string)$owner['email']) !== 0) {
        throw new DomainException('Tài khoản không hợp lệ. Vui lòng đăng nhập lại.');
    }

    // Khóa đúng đơn của khách hiện tại; không tiết lộ đơn của khách khác.
    $stmt = $conn->prepare('SELECT trang_thai, phuong_thuc FROM donhang
        WHERE id = ? AND user_id = ? FOR UPDATE');
    try {
        $stmt->bind_param('ii', $orderId, $userId);
        $stmt->execute();
        $order = $stmt->get_result()->fetch_assoc();
    } finally {
        $stmt->close();
    }
    if (!$order) {
        throw new DomainException('Không tìm thấy đơn hàng có thể thao tác.');
    }
    if ($order['phuong_thuc']==='PAYOS') throw new DomainException('Hãy quản lý hủy đơn QR tại trang chi tiết thanh toán; đơn đã chuyển tiền cần liên hệ TT SHOP.');
    // Yêu cầu gửi lại không làm thay đổi đơn hoặc hoàn lượt lần thứ hai.
    $status = (string)$order['trang_thai'];
    if (in_array($status, ['Đã huỷ', 'Đã hủy'], true)) {
        $conn->commit();
        $inTransaction = false;
        self::hd_redirect($userId, true, 'Đơn hàng này đã được hủy trước đó.');
    }
    if (!in_array($status, ['Chờ xử lý', 'Chờ lấy hàng'], true)) {
        throw new DomainException('Đơn hàng không còn ở trạng thái cho phép hủy.');
    }
    // Chỉ đổi trạng thái. Giữ nguyên giá, hạng khi mua và lịch sử voucher.
    $stmt = $conn->prepare("UPDATE donhang SET trang_thai = 'Đã huỷ'
        WHERE id = ? AND user_id = ?
        AND trang_thai IN ('Chờ xử lý', 'Chờ lấy hàng')");
    try {
        $stmt->bind_param('ii', $orderId, $userId);
        $stmt->execute();
        if ($stmt->affected_rows !== 1) {
            throw new RuntimeException('Không cập nhật được đúng một đơn hàng.');
        }
    } finally {
        $stmt->close();
    }

    // voucher_usage() tự loại đơn đã hủy khỏi lượt dùng.
    // membership_get() chỉ tính đơn đã giao. Không sửa hạng lưu trong đơn cũ.
    // Không UPDATE giới hạn voucher, không DELETE lichsu_magiamgia.
    $conn->commit();
    $inTransaction = false;
    self::hd_redirect($userId, true, 'Đã hủy đơn hàng. Nếu có voucher, lượt sử dụng đã được giải phóng.');
} catch (Throwable $e) {
    if ($inTransaction) {
        try { $conn->rollback(); } catch (Throwable $ignored) {}
    }
    if (!($e instanceof DomainException)) {
        error_log('TT SHOP cancel order: ' . $e->getMessage());
    }
    self::hd_redirect($userId, false, $e instanceof DomainException
        ? $e->getMessage() : 'Không thể hủy đơn hàng. Vui lòng thử lại.');
}
    }
    public function review()
    {
// Kiểm tra và khởi chạy session nếu chưa được bật
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Kết nối tới cơ sở dữ liệu (Database)
include dirname(__DIR__, 2) . '/connect.php';

// Kiểm tra bảo mật: Nếu chưa đăng nhập, lập tức đá người dùng về trang đăng nhập
if (!isset($_SESSION['is_login']) || $_SESSION['is_login'] !== true) {
    header("Location: dangnhap.php");
    exit();
}

// Lấy thông tin ID người dùng, ID đơn hàng, số sao (1-5) và nội dung nhận xét gửi lên từ form
$user_id = (int)$_SESSION['user_id'];
$donhang_id = (int)($_POST['donhang_id'] ?? 0);
$so_sao = (int)($_POST['so_sao'] ?? 0);
$nhan_xet = trim($_POST['nhan_xet'] ?? '');

// Ràng buộc dữ liệu đầu vào: ID đơn hàng phải hợp lệ và số sao bắt buộc phải nằm trong khoảng từ 1 đến 5
if ($donhang_id <= 0 || $so_sao < 1 || $so_sao > 5) {
    die("Dữ liệu đánh giá không hợp lệ.");
}

// BƯỚC 1: Kiểm tra xem đơn hàng này có thật sự là của người dùng này và đã ở trạng thái "Đã giao" hay chưa
$sqlCheckOrder = "
    SELECT id
    FROM donhang
    WHERE id = ?
    AND user_id = ?
    AND trang_thai = 'Đã giao'
";
$stmtCheckOrder = mysqli_prepare($conn, $sqlCheckOrder);
mysqli_stmt_bind_param($stmtCheckOrder, "ii", $donhang_id, $user_id);
mysqli_stmt_execute($stmtCheckOrder);
$resultOrder = mysqli_stmt_get_result($stmtCheckOrder);
$order = mysqli_fetch_assoc($resultOrder);

// Nếu không khớp thông tin hoặc đơn hàng chưa giao xong, chặn không cho đánh giá
if (!$order) {
    die("Bạn chỉ có thể đánh giá đơn hàng đã giao.");
}

// BƯỚC 2: Kiểm tra chống spam (Đảm bảo người dùng này chưa từng đánh giá đơn hàng này trước đó)
$sqlCheckReview = "
    SELECT id
    FROM danhgia
    WHERE donhang_id = ?
    AND user_id = ?
    LIMIT 1
";
$stmtCheckReview = mysqli_prepare($conn, $sqlCheckReview);
mysqli_stmt_bind_param($stmtCheckReview, "ii", $donhang_id, $user_id);
mysqli_stmt_execute($stmtCheckReview);
$resultReview = mysqli_stmt_get_result($stmtCheckReview);

// Nếu đã tồn tại bản ghi đánh giá rồi thì dừng lại và báo lỗi
if (mysqli_num_rows($resultReview) > 0) {
    die("Bạn đã đánh giá đơn hàng này rồi.");
}

// BƯỚC 3: Lấy toàn bộ danh sách sản phẩm nằm trong đơn hàng đó từ bảng chi tiết đơn hàng
$sqlItems = "
    SELECT *
    FROM chitiet_donhang
    WHERE donhang_id = ?
";
$stmtItems = mysqli_prepare($conn, $sqlItems);
mysqli_stmt_bind_param($stmtItems, "i", $donhang_id);
mysqli_stmt_execute($stmtItems);
$resultItems = mysqli_stmt_get_result($stmtItems);

// Chạy vòng lặp lưu đánh giá: Áp dụng cùng số sao và nội dung nhận xét cho từng sản phẩm có trong đơn hàng
while ($item = mysqli_fetch_assoc($resultItems)) {
    $ten_san_pham = $item['ten_san_pham'];
    $hinh_anh = $item['hinh_anh'];
    
    // Câu lệnh SQL thêm dữ liệu vào bảng danhgia
    $sqlInsert = "
        INSERT INTO danhgia (
            donhang_id,
            user_id,
            ten_san_pham,
            hinh_anh,
            so_sao,
            nhan_xet
        )
        VALUES (?, ?, ?, ?, ?, ?)
    ";
    $stmtInsert = mysqli_prepare($conn, $sqlInsert);
    mysqli_stmt_bind_param(
        $stmtInsert,
        "iissis",
        $donhang_id,
        $user_id,
        $ten_san_pham,
        $hinh_anh,
        $so_sao,
        $nhan_xet
    );
    mysqli_stmt_execute($stmtInsert);
}

// Sau khi lưu thành công, chuyển hướng về trang chi tiết đơn hàng kèm theo thông báo thành công (review=success)
header("Location: chitietdonhang.php?id=" . $donhang_id . "&review=success");
exit();

    }
}
