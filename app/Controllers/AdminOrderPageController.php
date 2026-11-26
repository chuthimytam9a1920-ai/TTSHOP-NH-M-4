<?php
final class AdminOrderPageController
{
    public static function orders_ao_e($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

    public static function orders_ao_money($value): string {
    return number_format((int)$value, 0, ',', '.') . 'đ';
}

    public static function orders_ao_get(string $key): string {
    $value = $_GET[$key] ?? '';
    return is_string($value) ? trim($value) : '';
}

    public static function orders_ao_valid_date(string $value): bool {
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    return $date !== false && $date->format('Y-m-d') === $value
        && (int)$date->format('Y') >= 1000;
}

    public function orders(): array
    {

require_once (dirname(__DIR__, 2) . '/admin') . '/../payment_helpers.php';
// admin/orders.php — Danh sách đơn hàng và ưu đãi thành viên.
require_once (dirname(__DIR__, 2) . '/admin') . '/check_admin.php';
require_once (dirname(__DIR__, 2) . '/admin') . '/../csrf.php';
header('Cache-Control: no-store');
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
require_once (dirname(__DIR__, 2) . '/admin') . '/../connect.php';






$token = CsrfService::token();
$flash = $_SESSION['admin_order_flash'] ?? null;
unset($_SESSION['admin_order_flash']);
$statuses = ['Chờ xử lý', 'Chờ lấy hàng', 'Đang vận chuyển', 'Đã giao', 'Đã huỷ'];
$keyword = self::orders_ao_get('keyword');
$status = self::orders_ao_get('status');
$fromDate = self::orders_ao_get('from_date');
$toDate = self::orders_ao_get('to_date');
$voucherFilter = self::orders_ao_get('voucher');
$voucherCode = self::orders_ao_get('voucher_code');
$errors = [];
$where = [];
$types = '';
$params = [];

// Giữ bộ lọc cũ, thêm tìm kiếm mã voucher và lọc có/không voucher.
if ($keyword !== '') {
    // Escape ký tự LIKE để tìm theo đúng chuỗi người dùng nhập.
    $like = '%' . strtr($keyword, ['!' => '!!', '%' => '!%', '_' => '!_']) . '%';
    $where[] = "(d.ma_don LIKE ? ESCAPE '!' OR d.ho_ten LIKE ? ESCAPE '!' OR d.sdt LIKE ? ESCAPE '!')";
    $types .= 'sss';
    array_push($params, $like, $like, $like);
}
if ($status === 'Đã huỷ' || $status === 'Đã hủy') {
    $status = 'Đã huỷ';
    $where[] = "d.trang_thai IN ('Đã huỷ', 'Đã hủy')";
} elseif ($status !== '') {
    if (in_array($status, $statuses, true)) {
        $where[] = 'd.trang_thai = ?';
        $types .= 's';
        $params[] = $status;
    } else {
        $errors[] = 'Trạng thái đơn hàng không hợp lệ.';
    }
}
foreach ([[$fromDate, '>=', 'Ngày bắt đầu'], [$toDate, '<=', 'Ngày kết thúc']] as [$date, $operator, $label]) {
    if ($date !== '') {
        if (!self::orders_ao_valid_date($date)) {
            $errors[] = $label . ' không hợp lệ.';
        } else {
            $where[] = 'DATE(d.created_at) ' . $operator . ' ?';
            $types .= 's';
            $params[] = $date;
        }
    }
}
if ($fromDate !== '' && $toDate !== '' && $fromDate > $toDate) {
    $errors[] = 'Ngày bắt đầu không được sau ngày kết thúc.';
}
if ($voucherFilter === 'yes') {
    $where[] = "NULLIF(TRIM(d.ma_giam_gia), '') IS NOT NULL";
} elseif ($voucherFilter === 'no') {
    $where[] = "NULLIF(TRIM(d.ma_giam_gia), '') IS NULL";
} elseif ($voucherFilter !== '') {
    $errors[] = 'Bộ lọc voucher không hợp lệ.';
}
if ($voucherCode !== '') {
    if (!preg_match('/^[A-Za-z0-9_-]{1,50}$/D', $voucherCode)) {
        $errors[] = 'Mã voucher không hợp lệ.';
    } else {
        $where[] = 'd.ma_giam_gia = ?';
        $types .= 's';
        $params[] = strtoupper($voucherCode);
    }
}


$orders = null;
if (!$errors) {
    // Đọc hạng và tiền đã lưu tại thời điểm mua, không tính lại từ cấu hình voucher hiện tại.
    // Gom đánh giá trước khi JOIN để mỗi đơn chỉ xuất hiện một dòng.
    $sql = "SELECT d.*,
                   COALESCE(r.so_danh_gia, 0) AS so_danh_gia,
                   r.so_sao, r.nhan_xet,
                   COALESCE(l.tien_giam_van_chuyen, 0) AS tien_giam_van_chuyen
            FROM donhang d
            LEFT JOIN (
                SELECT donhang_id, COUNT(id) AS so_danh_gia,
                       MAX(so_sao) AS so_sao, MAX(nhan_xet) AS nhan_xet
                FROM danhgia GROUP BY donhang_id
            ) r ON r.donhang_id = d.id
            LEFT JOIN lichsu_magiamgia l ON l.donhang_id = d.id";
    if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
    $sql .= ' ORDER BY d.id DESC';
    try {
        $stmt = $conn->prepare($sql);
        try {
            if ($types !== '') $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $orders = $stmt->get_result();
        } finally {
            $stmt->close();
        }
    } catch (Throwable $e) {
        error_log('TT SHOP admin orders: ' . $e->getMessage());
        $errors[] = 'Không thể tải đơn hàng. Hãy kiểm tra kết nối và SQL nâng cấp voucher.';
    }
}

        return get_defined_vars();
    }

    public static function detail_od_e($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

    public static function detail_od_money($value): string {
    return number_format((int)$value, 0, ',', '.') . 'đ';
}

    public static function detail_od_status(string $status): string {
    return $status === 'Đã hủy' ? 'Đã huỷ' : $status;
}

    public static function detail_statusClass($status) {
    switch ($status) {
        case 'Chờ xử lý':
            return 'status-pending';
        case 'Chờ lấy hàng':
            return 'status-waiting';
        case 'Đang vận chuyển':
            return 'status-shipping';
        case 'Đã giao':
            return 'status-success';
        case 'Đã hủy':
        case 'Đã huỷ':
            return 'status-cancel';
        default:
            return 'status-default';
    }
}

    public function detail(): array
    {

require_once (dirname(__DIR__, 2) . '/admin') . '/../payment_helpers.php';
require_once (dirname(__DIR__, 2) . '/admin') . '/check_admin.php';
require_once (dirname(__DIR__, 2) . '/admin') . '/../csrf.php';
header('Cache-Control: no-store');
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
require_once (dirname(__DIR__, 2) . '/admin') . '/../connect.php';




$token = CsrfService::token();

$id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1, 'max_range' => 2147483647]]);

if (!$id) {
    http_response_code(400);
    die("ID đơn hàng không hợp lệ.");
}

/* Lấy thông tin đơn hàng */
$stmt = mysqli_prepare($conn, "SELECT * FROM donhang WHERE id = ?");
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);
$order = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

if (!$order) {
    http_response_code(404);
    die("Không tìm thấy đơn hàng.");
}

/* Lấy sản phẩm trong đơn hàng */
$stmtItems = mysqli_prepare($conn, "
    SELECT * FROM chitiet_donhang 
    WHERE donhang_id = ?
");

mysqli_stmt_bind_param($stmtItems, "i", $id);
mysqli_stmt_execute($stmtItems);
$items = mysqli_stmt_get_result($stmtItems);

mysqli_stmt_close($stmtItems);

/* Lịch sử voucher: chỉ lấy dữ liệu đã ghi tại thời điểm mua. */
$stmtVoucher = $conn->prepare("SELECT id, magiamgia_id, user_id, ma_code,
    tien_giam_gia, tien_giam_van_chuyen, created_at
    FROM lichsu_magiamgia WHERE donhang_id = ?");
$stmtVoucher->bind_param('i', $id);
$stmtVoucher->execute();
$history = $stmtVoucher->get_result()->fetch_assoc();
$stmtVoucher->close();

$voucherCode = trim((string)($order['ma_giam_gia'] ?? ''));
$tier = $order['hang_thanh_vien'] ?? null;
$subtotal = (int)$order['tam_tinh'];
$discount = (int)($order['tien_giam_gia'] ?? 0);
$shipping = (int)$order['phi_van_chuyen'];
$shippingDiscount = $history ? (int)$history['tien_giam_van_chuyen'] : 0;
$total = (int)$order['tong_cong'];
$currentStatus = self::detail_od_status((string)($order['trang_thai'] ?? ''));
$cancelled = $currentStatus === 'Đã huỷ';
$hasVoucher = $voucherCode !== '' || $history !== null;
$qr = ($order['phuong_thuc']??'')==='PAYOS';
$terminal = ($cancelled && $hasVoucher) || ($qr && (($order['payment_status']??'')!=='PAID' || $cancelled));
$flash = $_SESSION['admin_order_flash'] ?? null;
unset($_SESSION['admin_order_flash']);

/* Ghép địa chỉ đầy đủ */
$diaChiParts = [
    $order['dia_chi'] ?? '',
    $order['phuong_xa'] ?? '',
    $order['quan_huyen'] ?? '',
    $order['tinh_thanh'] ?? ''
];

$diaChiParts = array_filter($diaChiParts, function ($value) {
    return trim($value) !== '';
});

$diaChiDayDu = implode(', ', $diaChiParts);

/* Danh sách trạng thái */
$trangThaiList = [
    'Chờ xử lý',
    'Chờ lấy hàng',
    'Đang vận chuyển',
    'Đã giao',
    'Đã huỷ'
];



        return get_defined_vars();
    }
}
