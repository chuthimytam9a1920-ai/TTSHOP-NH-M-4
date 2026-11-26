<?php
final class AdminCustomerController
{
    public static function customers_ac_e($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

    public static function customers_ac_money($value): string {
    return number_format((int)$value, 0, ',', '.') . 'đ';
}

    public static function customers_ac_get(string $key): string {
    $value = $_GET[$key] ?? '';
    return is_string($value) ? trim($value) : '';
}

    public static function customers_ac_id($value): ?int {
    $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 2147483647]]);
    return $id === false || $id === null ? null : $id;
}

    public static function customers_ac_query(mysqli $conn, string $sql, string $types = '', array $params = []): array {
    $stmt = $conn->prepare($sql);
    try {
        if ($types !== '') $stmt->bind_param($types, ...$params);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    } finally {
        $stmt->close();
    }
}

    public static function customers_ac_redirect(bool $ok, string $message): void {
    $_SESSION['admin_customer_flash'] = ['ok' => $ok, 'message' => $message];
    header('Location: customers.php', true, 303);
    exit;
}

    public static function customers_goiYChamSoc(string $hang): string {
    switch ($hang) {
        case 'Diamond': return 'Chăm sóc VIP, quà tặng riêng';
        case 'Platinum': return 'Chăm sóc khách thân thiết, giới thiệu ưu đãi';
        case 'Gold': return 'Ưu tiên tư vấn, giới thiệu ưu đãi';
        case 'Silver': return 'Gửi voucher phù hợp';
        default: return 'Gửi mã giảm giá mua lại';
    }
}

    public static function customers_ac_percent(int $current, int $target): int {
    return $target <= 0 ? 100 : (int)min(100, max(0, floor($current / $target * 100)));
}

    public static function customers_ac_voucher_error(array $voucher, string $tier, int $mine): string {
    // Chưa có giỏ hàng: dùng giá trị giả định đạt đơn tối thiểu để chỉ kiểm tra
    // cấu hình, hạng, thời hạn và lượt dùng. Không cam kết đủ điều kiện thanh toán.
    $error = VoucherService::ruleError($voucher, $tier, max(1, (int)$voucher['don_toi_thieu']), $voucher['now_db']);
    if ($error !== '') return $error;
    if ($voucher['gioi_han_su_dung'] !== null && (int)$voucher['used_total'] >= (int)$voucher['gioi_han_su_dung']) {
        return 'Mã đã hết lượt toàn hệ thống.';
    }
    if ($voucher['gioi_han_moi_user'] !== null && $mine >= (int)$voucher['gioi_han_moi_user']) {
        return 'Khách đã hết lượt của mã này.';
    }
    return '';
}

    public function customers(): array
    {

// admin/customers.php — Quản lý khách hàng và chương trình thành viên TT SHOP.
require_once (dirname(__DIR__, 2) . '/admin') . '/check_admin.php';
require_once (dirname(__DIR__, 2) . '/admin') . '/../csrf.php';
require_once (dirname(__DIR__, 2) . '/admin') . '/../membership.php';
require_once (dirname(__DIR__, 2) . '/admin') . '/../voucher_helpers.php';
header('Cache-Control: no-store');
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
require_once (dirname(__DIR__, 2) . '/admin') . '/../connect.php';











$token = CsrfService::token();
$rules = MembershipService::rules();

// ZIP gốc có liên kết delete_customer.php nhưng không có file xử lý.
// Thực hiện POST ngay tại trang này, không xóa đơn hoặc lịch sử giao dịch.
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!CsrfService::check($_POST['csrf_token'] ?? null)) {
        http_response_code(403);
        exit('Phiên xác thực không hợp lệ. Vui lòng tải lại trang.');
    }
    if (($_POST['action'] ?? '') !== 'delete_customer') {
        http_response_code(400);
        exit('Thao tác không hợp lệ.');
    }
    $id = self::customers_ac_id($_POST['id'] ?? null);
    if ($id === null) self::customers_ac_redirect(false, 'ID khách hàng không hợp lệ.');
    $inTransaction = false;
    try {
        $conn->begin_transaction();
        $inTransaction = true;
        if (!self::customers_ac_query($conn, 'SELECT id FROM users WHERE id = ? FOR UPDATE', 'i', [$id])) {
            throw new DomainException('Khách hàng không tồn tại.');
        }
        // Các bảng trong ttshop_final.sql và update_voucher_fix.sql.
        // Khóa tài khoản trước, tương thích thứ tự khóa của luồng đặt hàng.
        foreach (['donhang', 'orders', 'danhgia', 'lichsu_magiamgia', 'chat_conversations'] as $table) {
            if (self::customers_ac_query($conn, "SELECT user_id FROM `$table` WHERE user_id = ? LIMIT 1 FOR UPDATE", 'i', [$id])) {
                throw new DomainException('Không thể xóa khách đã có đơn hàng, đánh giá, voucher hoặc cuộc trò chuyện.');
            }
        }
        // Địa chỉ, hồ sơ phụ và yêu cầu đặt lại mật khẩu được xóa theo FK CASCADE.
        $stmt = $conn->prepare('DELETE FROM users WHERE id = ?');
        try {
            $stmt->bind_param('i', $id);
            $stmt->execute();
            if ($stmt->affected_rows !== 1) throw new RuntimeException('Không xóa được đúng một tài khoản.');
        } finally {
            $stmt->close();
        }
        $conn->commit();
        $inTransaction = false;
        self::customers_ac_redirect(true, 'Đã xóa khách hàng.');
    } catch (Throwable $e) {
        if ($inTransaction) {
            try { $conn->rollback(); } catch (Throwable $ignored) {}
        }
        if (!($e instanceof DomainException)) error_log('TT SHOP delete customer: ' . $e->getMessage());
        self::customers_ac_redirect(false, $e instanceof DomainException ? $e->getMessage() : 'Không thể xóa khách hàng. Hãy kiểm tra dữ liệu liên quan.');
    }
}
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    header('Allow: GET, POST');
    http_response_code(405);
    exit('Phương thức không hợp lệ.');
}

$flash = $_SESSION['admin_customer_flash'] ?? null;
unset($_SESSION['admin_customer_flash']);
$keyword = self::customers_ac_get('keyword');
$rankFilter = self::customers_ac_get('rank') ?: 'all';
$errors = [];
if ($rankFilter !== 'all' && !array_key_exists($rankFilter, $rules)) {
    $errors[] = 'Hạng thành viên không hợp lệ.';
    $rankFilter = 'all';
}
$detailId = self::customers_ac_id($_GET['detail'] ?? null);
$customers = [];
$voucherList = [];
$userUsage = [];
$detailOrders = [];
$detailCustomer = null;
$loaded = false;

try {
    // Tổng hợp mỗi nguồn độc lập để không nhân số đơn và số tiền khi JOIN.
    // Công thức phân hạng do membership_calculate() xử lý, không sao chép ngưỡng SQL.
    $sql = "SELECT u.id, u.ho, u.ten, u.email, u.sdt, u.diachi, u.created_at,
                COALESCE(o.so_lan_mua, 0) AS so_lan_mua,
                (SELECT COALESCE(MAX(mu.target_rank),0) FROM membership_upgrades mu WHERE mu.user_id=u.id AND mu.status='PAID') AS paid_rank,
                COALESCE(o.tong_da_mua, 0) AS tong_da_mua,
                COALESCE(h.used_count, 0) AS used_count,
                COALESCE(h.delivered_count, 0) AS delivered_count,
                COALESCE(h.saved, 0) AS saved,
                COALESCE(h.pending_saved, 0) AS pending_saved
            FROM users u
            LEFT JOIN (
                SELECT user_id, COUNT(*) AS so_lan_mua, SUM(tong_cong) AS tong_da_mua
                FROM donhang WHERE trang_thai = 'Đã giao' GROUP BY user_id
            ) o ON o.user_id = u.id
            LEFT JOIN (
                SELECT l.user_id,
                    SUM(CASE WHEN COALESCE(d.trang_thai, '') NOT IN ('Đã huỷ','Đã hủy') THEN 1 ELSE 0 END) AS used_count,
                    SUM(CASE WHEN d.trang_thai = 'Đã giao' THEN 1 ELSE 0 END) AS delivered_count,
                    SUM(CASE WHEN d.trang_thai = 'Đã giao' THEN l.tien_giam_gia + l.tien_giam_van_chuyen ELSE 0 END) AS saved,
                    SUM(CASE WHEN COALESCE(d.trang_thai, '') NOT IN ('Đã giao','Đã huỷ','Đã hủy') THEN l.tien_giam_gia + l.tien_giam_van_chuyen ELSE 0 END) AS pending_saved
                FROM lichsu_magiamgia l JOIN donhang d ON d.id = l.donhang_id
                GROUP BY l.user_id
            ) h ON h.user_id = u.id";
    $types = '';
    $params = [];
    if ($keyword !== '') {
        $like = '%' . strtr($keyword, ['!' => '!!', '%' => '!%', '_' => '!_']) . '%';
        $sql .= " WHERE (CONCAT_WS(' ', u.ho, u.ten) LIKE ? ESCAPE '!' OR u.ho LIKE ? ESCAPE '!'
            OR u.ten LIKE ? ESCAPE '!' OR u.email LIKE ? ESCAPE '!' OR u.sdt LIKE ? ESCAPE '!'";
        $types = 'sssss';
        $params = array_fill(0, 5, $like);
        $searchId = self::customers_ac_id($keyword);
        if ($searchId !== null) {
            $sql .= ' OR u.id = ?';
            $types .= 'i';
            $params[] = $searchId;
        }
        $sql .= ')';
    }
    $sql .= ' ORDER BY u.id DESC';
    $rows = self::customers_ac_query($conn, $sql, $types, $params);
    foreach ($rows as $row) {
        $row['member'] = MembershipService::calculate((int)$row['so_lan_mua'], (int)$row['tong_da_mua'], (int)$row['paid_rank']);
        if ($rankFilter !== 'all' && $row['member']['tier'] !== $rankFilter) continue;
        $customers[] = $row;
        if ($detailId === (int)$row['id']) $detailCustomer = $row;
    }

    // Dữ liệu quyền lợi đọc trực tiếp từ SQL, cùng điều kiện voucher_helpers.php.
    // Đếm cả đơn chưa giao; loại cả hai cách viết trạng thái hủy.
    $voucherList = self::customers_ac_query($conn, "SELECT m.*, NOW() AS now_db, COALESCE(v.used_total, 0) AS used_total
        FROM magiamgia m LEFT JOIN (
            SELECT l.magiamgia_id, COUNT(*) AS used_total
            FROM lichsu_magiamgia l JOIN donhang d ON d.id = l.donhang_id
            WHERE COALESCE(d.trang_thai, '') NOT IN ('Đã huỷ','Đã hủy') GROUP BY l.magiamgia_id
        ) v ON v.magiamgia_id = m.id
        WHERE m.trang_thai = 1 ORDER BY m.hang_toi_thieu, m.id");
    $usageRows = self::customers_ac_query($conn, "SELECT l.user_id, l.magiamgia_id, COUNT(*) AS used_count
        FROM lichsu_magiamgia l JOIN donhang d ON d.id = l.donhang_id
        JOIN magiamgia m ON m.id = l.magiamgia_id
        WHERE m.trang_thai = 1 AND COALESCE(d.trang_thai, '') NOT IN ('Đã huỷ','Đã hủy')
        GROUP BY l.user_id, l.magiamgia_id");
    foreach ($usageRows as $usage) {
        $userUsage[(int)$usage['user_id']][(int)$usage['magiamgia_id']] = (int)$usage['used_count'];
    }
    if ($detailCustomer !== null) {
        $detailOrders = self::customers_ac_query($conn, "SELECT d.id, d.ma_don, d.created_at, d.trang_thai, d.tong_cong,
            d.hang_thanh_vien, d.ma_giam_gia, l.ma_code AS history_code,
            l.tien_giam_gia, l.tien_giam_van_chuyen
            FROM donhang d LEFT JOIN lichsu_magiamgia l ON l.donhang_id = d.id
            WHERE d.user_id = ? ORDER BY d.id DESC LIMIT 20", 'i', [$detailId]);
    }
    $loaded = true;
} catch (Throwable $e) {
    error_log('TT SHOP admin customers: ' . $e->getMessage());
    $errors[] = 'Không thể tải dữ liệu khách hàng. Hãy kiểm tra kết nối và SQL nâng cấp voucher.';
    $customers = [];
    $detailCustomer = null;
}
if ($loaded && $detailId !== null && $detailCustomer === null) {
    $errors[] = 'Không tìm thấy khách hàng trong bộ lọc hiện tại.';
}
$filterParams = ['rank' => $rankFilter, 'keyword' => $keyword];
$rankCounts = array_fill_keys(array_keys($rules), 0);
$totalSaved = 0;
foreach ($customers as $row) {
    $rankCounts[$row['member']['tier']]++;
    $totalSaved += (int)$row['saved'];
}

        return get_defined_vars();
    }
}
