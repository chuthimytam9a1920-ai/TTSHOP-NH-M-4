<?php
final class CustomerAccountPageController
{
    public static function account_tk_h($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

    public static function account_tk_money($value): string {
    return number_format((int)$value, 0, ',', '.') . 'đ';
}

    public static function account_tk_date($value): string {
    $time = $value ? strtotime((string)$value) : false;
    return $time === false ? '—' : date('d/m/Y H:i', $time);
}

    public function account(): array
    {

require_once (dirname(__DIR__, 2)) . '/payment_helpers.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include dirname(__DIR__, 2) . '/connect.php';
require_once (dirname(__DIR__, 2)) . '/csrf.php';
require_once (dirname(__DIR__, 2)) . '/voucher_helpers.php';
$csrfToken = CsrfService::token();
$accountFieldErrors = $_SESSION['account_field_errors'] ?? array();
unset($_SESSION['account_field_errors']);

/* Kiểm tra đăng nhập */
if (!isset($_SESSION['is_login']) || $_SESSION['is_login'] !== true) {
    header("Location: dangnhap.php");
    exit();
}

$user_id = (int)$_SESSION['user_id'];

/* Đếm địa chỉ */
$sqlCount = "SELECT COUNT(*) AS total FROM diachi_khachhang WHERE user_id = ?";
$stmtCount = mysqli_prepare($conn, $sqlCount);
mysqli_stmt_bind_param($stmtCount, "i", $user_id);
mysqli_stmt_execute($stmtCount);

$resultCount = mysqli_stmt_get_result($stmtCount);
$rowCount = mysqli_fetch_assoc($resultCount);
$soDiaChi = ($rowCount['total'] ?? 0) + 1;

/* Lấy thông tin user */
$sqlUser = "SELECT * FROM users WHERE id = ?";
$stmtUser = mysqli_prepare($conn, $sqlUser);
mysqli_stmt_bind_param($stmtUser, "i", $user_id);
mysqli_stmt_execute($stmtUser);

$resultUser = mysqli_stmt_get_result($stmtUser);
$user = mysqli_fetch_assoc($resultUser);

/* Không sử dụng phiên đăng nhập cũ nếu ID đã thuộc tài khoản khác. */
if (!$user || (isset($_SESSION['user_email']) &&
    strcasecmp((string)$_SESSION['user_email'], (string)$user['email']) !== 0)) {
    unset($_SESSION['is_login'], $_SESSION['user_id'], $_SESSION['user_email'],
          $_SESSION['voucher_code'], $_SESSION['voucher_user_id'],
          $_SESSION['checkout_info'], $_SESSION['checkout_review']);
    header('Location: dangnhap.php');
    exit();
}

/* Thông báo hủy đơn chỉ hiển thị cho đúng tài khoản. */
$cancelFlash = $_SESSION['customer_cancel_flash'] ?? null;
unset($_SESSION['customer_cancel_flash']);
if (!is_array($cancelFlash) || ($cancelFlash['user_id'] ?? null) !== $user_id) {
    $cancelFlash = null;
}

/* Lưu session */
if ($user) {
    $_SESSION['user_ho']        = $user['ho'];
    $_SESSION['user_name']      = $user['ten'];
    $_SESSION['user_email']     = $user['email'];
    $_SESSION['user_sdt']       = $user['sdt'];
    $_SESSION['user_ngaysinh']  = $user['ngaysinh'];
    $_SESSION['user_diachi']    = $user['diachi'];
}

/* Lấy dữ liệu từ session */
$ho        = $_SESSION['user_ho'] ?? '';
$ten       = $_SESSION['user_name'] ?? '';
$email     = $_SESSION['user_email'] ?? '';
$sdt       = $_SESSION['user_sdt'] ?? '';
$ngaysinh  = $_SESSION['user_ngaysinh'] ?? '';
$diachi    = $_SESSION['user_diachi'] ?? '';

/* Lấy danh sách địa chỉ */
$addressList = [];

$sqlAddress = "
    SELECT * 
    FROM diachi_khachhang 
    WHERE user_id = ?
    ORDER BY mac_dinh DESC, id DESC
";

$stmtAddress = mysqli_prepare($conn, $sqlAddress);
mysqli_stmt_bind_param($stmtAddress, "i", $user_id);
mysqli_stmt_execute($stmtAddress);

$resultAddress = mysqli_stmt_get_result($stmtAddress);

if ($resultAddress) {
    while ($row = mysqli_fetch_assoc($resultAddress)) {
        $addressList[] = $row;
    }
}

/* Họ tên đầy đủ */
$fullName = trim($ho . ' ' . $ten);
$fullName = mb_convert_case($fullName, MB_CASE_TITLE, "UTF-8");

/* Tính phần trăm hoàn thiện tài khoản */
$percent = 0;
$profileComplete = true;

foreach ([[$ho, 15], [$ten, 15], [$email, 15], [$sdt, 15], [$ngaysinh, 20], [$diachi, 20]] as [$value, $weight]) {
    if (trim((string)$value) !== '') {
        $percent += $weight;
    } else {
        $profileComplete = false;
    }
}

if ($profileComplete) {
    $percent = 100;
    $profileText   = "Tuyệt vời! Hồ sơ của bạn đã đầy đủ";
    $profileMascot = "./imagescategory/mascot-happy.png";
} else {
    $profileText   = "Ôi không, bạn chưa cập nhật đủ thông tin!";
    $profileMascot = "./imagescategory/Screenshot 2026-05-07 143553.png";
}

/* Tạo avatar */
$first_letter_ho  = mb_substr(trim($ho), 0, 1, "UTF-8");
$first_letter_ten = mb_substr(trim($ten), 0, 1, "UTF-8");

$avatar_text = mb_strtoupper($first_letter_ho . $first_letter_ten, "UTF-8");

if (empty($avatar_text) && !empty($ten)) {
    $avatar_text = mb_strtoupper(mb_substr(trim($ten), 0, 1, "UTF-8"), "UTF-8");
}

/* Đếm sản phẩm trong giỏ hàng */
$cartCount = 0;

if (isset($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $item) {
        $cartCount += (int)$item['qty'];
    }
}

/* Đếm đơn hàng */
$orderCount = 0;

$sqlOrderCount = "SELECT COUNT(*) AS total FROM donhang WHERE user_id = ?";
$stmtOrderCount = mysqli_prepare($conn, $sqlOrderCount);
mysqli_stmt_bind_param($stmtOrderCount, "i", $user_id);
mysqli_stmt_execute($stmtOrderCount);

$resultOrderCount = mysqli_stmt_get_result($stmtOrderCount);
$rowOrderCount = mysqli_fetch_assoc($resultOrderCount);
$orderCount = $rowOrderCount['total'] ?? 0;

/* Lọc trạng thái đơn hàng */
$trangThaiHopLe = [
    'Chờ xử lý',
    'Chờ lấy hàng',
    'Đang vận chuyển',
    'Đã giao',
    'Đã huỷ'
];

$locTrangThai = $_GET['trang_thai'] ?? '';

if ($locTrangThai === 'Đã hủy') {
    $locTrangThai = 'Đã huỷ';
}
if (!is_string($locTrangThai) || !in_array($locTrangThai, $trangThaiHopLe, true)) {
    $locTrangThai = '';
}

/* Nếu đang lọc đơn hàng thì tự mở tab đơn hàng */
$requestedTab = $_GET['tab'] ?? '';
$currentTab = $locTrangThai !== '' || $requestedTab === 'donhang'
    ? 'donhang' : ($requestedTab === 'thanhvien' ? 'thanhvien' : 'tongquan');

/* Lấy danh sách đơn hàng */
$orderList = [];

if ($locTrangThai === 'Đã huỷ') {
    $sqlOrders = "SELECT * FROM donhang WHERE user_id = ?
        AND trang_thai IN ('Đã huỷ', 'Đã hủy') ORDER BY id DESC";
    $stmtOrders = mysqli_prepare($conn, $sqlOrders);
    mysqli_stmt_bind_param($stmtOrders, "i", $user_id);
} elseif ($locTrangThai != '') {
    $sqlOrders = "
        SELECT *
        FROM donhang
        WHERE user_id = ?
        AND trang_thai = ?
        ORDER BY id DESC
    ";

    $stmtOrders = mysqli_prepare($conn, $sqlOrders);
    mysqli_stmt_bind_param($stmtOrders, "is", $user_id, $locTrangThai);
} else {
    $sqlOrders = "
        SELECT *
        FROM donhang
        WHERE user_id = ?
        ORDER BY id DESC
    ";

    $stmtOrders = mysqli_prepare($conn, $sqlOrders);
    mysqli_stmt_bind_param($stmtOrders, "i", $user_id);
}

mysqli_stmt_execute($stmtOrders);
$resultOrders = mysqli_stmt_get_result($stmtOrders);

if ($resultOrders) {
    while ($row = mysqli_fetch_assoc($resultOrders)) {
        $orderList[] = $row;
    }
}
/* Chương trình thành viên: dùng cùng hàm với giỏ hàng và thanh toán. */




$member = null;
$memberError = '';
$voucherError = '';
$historyError = '';
$wallet = [];
$benefits = array_fill_keys(array_keys(MembershipService::rules()), []);
$history = [];
$historyCount = 0;
$historyPage = filter_var($_GET['voucher_page'] ?? 1, FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1, 'max_range' => 1000000]]) ?: 1;
$historySize = 10;
$historyPages = 1;
$saved = 0;
$pendingSaved = 0;
$deliveredVoucherOrders = 0;
$progressOrders = 0;
$progressSpend = 0;
$rules = MembershipService::rules();

try {
    $member = (new MembershipService($conn))->get( $user_id);
    if ($member['next_tier'] === null) {
        $progressOrders = $progressSpend = 100;
    } else {
        $nextRule = $rules[$member['next_tier']];
        $progressOrders = min(100, (int)floor(100 * $member['orders'] / $nextRule['orders']));
        $progressSpend = min(100, (int)floor(100 * $member['spend'] / $nextRule['spend']));
    }
} catch (Throwable $e) {
    error_log('TT SHOP membership account: ' . $e->getMessage());
    $memberError = 'Chưa thể tải thông tin thành viên. Vui lòng thử lại sau.';
}

/* Kho voucher không giả định giỏ hàng đã đủ tiền.
   Dùng mức tối thiểu của mỗi mã để kiểm tra hạng, thời hạn và lượt còn lại;
   khi thanh toán, hệ thống sẽ kiểm tra giá trị giỏ hàng thực tế. */
if ($member !== null) {
    try {
        $result = $conn->query("SELECT m.*, NOW() AS now_db FROM magiamgia m
                                WHERE trang_thai = 1 ORDER BY hang_toi_thieu, id");
        if (!$result) throw new RuntimeException('Không thể đọc kho voucher.');
        while ($v = $result->fetch_assoc()) {
            $testSubtotal = max(1, (int)$v['don_toi_thieu']);
            $check = (new VoucherService($conn))->evaluate( $v, $user_id, $member['tier'], $testSubtotal, 0);
            $wallet[] = ['voucher' => $v, 'ok' => $check['ok'], 'message' => $check['message']];
            // Quyền lợi theo từng hạng, lấy từ chương trình còn hiệu lực trong SQL.
            $required = $v['hang_toi_thieu'];
            if (isset($benefits[$required]) &&
                VoucherService::ruleError($v, $required, $testSubtotal, $v['now_db']) === '') {
                $benefits[$required][] = $v['ma_code'] . ' – Giảm ' . (int)$v['phan_tram_giam'] . '%'
                    . ((int)$v['mien_phi_van_chuyen'] === 1 ? ' + Freeship' : '')
                    . ', đơn từ ' . self::account_tk_money($v['don_toi_thieu'])
                    . ', tối đa ' . self::account_tk_money($v['giam_toi_da']);
            }
        }
        $result->free();
    } catch (Throwable $e) {
        error_log('TT SHOP voucher wallet: ' . $e->getMessage());
        $wallet = [];
        $benefits = array_fill_keys(array_keys($rules), []);
        $voucherError = 'Chưa thể tải kho mã giảm giá. Vui lòng thử lại sau.';
    }
}

/* Lịch sử voucher: chỉ đọc đơn của tài khoản đang đăng nhập.
   Tiết kiệm thực tế = giảm sản phẩm + giảm vận chuyển của đơn Đã giao.
   Đơn đã hủy vẫn hiển thị lịch sử nhưng không cộng vào tổng tiết kiệm. */
try {
    $stmt = $conn->prepare("SELECT COUNT(*) AS total,
        COALESCE(SUM(CASE WHEN d.trang_thai = 'Đã giao'
            THEN l.tien_giam_gia + l.tien_giam_van_chuyen ELSE 0 END), 0) AS saved,
        COALESCE(SUM(CASE WHEN d.trang_thai IN ('Chờ xử lý','Chờ lấy hàng','Đang vận chuyển')
            THEN l.tien_giam_gia + l.tien_giam_van_chuyen ELSE 0 END), 0) AS pending_saved,
        COALESCE(SUM(CASE WHEN d.trang_thai = 'Đã giao' THEN 1 ELSE 0 END), 0) AS delivered_count
        FROM lichsu_magiamgia l JOIN donhang d ON d.id = l.donhang_id
        WHERE l.user_id = ? AND d.user_id = ?");
    if (!$stmt) throw new RuntimeException('Không thể đọc tổng tiết kiệm.');
    $stmt->bind_param('ii', $user_id, $user_id);
    if (!$stmt->execute()) throw new RuntimeException('Không thể đọc tổng tiết kiệm.');
    $stats = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $historyCount = (int)$stats['total'];
    $saved = (int)$stats['saved'];
    $pendingSaved = (int)$stats['pending_saved'];
    $deliveredVoucherOrders = (int)$stats['delivered_count'];
    $historyPages = max(1, (int)ceil($historyCount / $historySize));
    $historyPage = min($historyPage, $historyPages);
    $offset = ($historyPage - 1) * $historySize;
    $stmt = $conn->prepare("SELECT l.ma_code, l.tien_giam_gia, l.tien_giam_van_chuyen,
        l.created_at, d.id AS donhang_id, d.ma_don, d.trang_thai, d.hang_thanh_vien
        FROM lichsu_magiamgia l JOIN donhang d ON d.id = l.donhang_id
        WHERE l.user_id = ? AND d.user_id = ?
        ORDER BY l.created_at DESC, l.id DESC LIMIT ? OFFSET ?");
    if (!$stmt) throw new RuntimeException('Không thể đọc lịch sử voucher.');
    $stmt->bind_param('iiii', $user_id, $user_id, $historySize, $offset);
    if (!$stmt->execute()) throw new RuntimeException('Không thể đọc lịch sử voucher.');
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) $history[] = $row;
    $stmt->close();
} catch (Throwable $e) {
    error_log('TT SHOP voucher history: ' . $e->getMessage());
    $historyError = 'Chưa thể tải lịch sử và tổng tiết kiệm. Vui lòng thử lại sau.';
}


        return get_defined_vars();
    }

    public function editAddress(): array
    {

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once (dirname(__DIR__, 2)) . '/connect.php';
require_once (dirname(__DIR__, 2)) . '/validate_helpers.php';
require_once (dirname(__DIR__, 2)) . '/csrf.php';

if (!isset($_SESSION['is_login']) || $_SESSION['is_login'] !== true) {
    header('Location: dangnhap.php');
    exit();
}

$userId = (int)($_SESSION['user_id'] ?? 0);
$requestId = $_SERVER['REQUEST_METHOD'] === 'POST'
    ? ($_POST['id'] ?? null)
    : ($_GET['id'] ?? null);
$addressId = is_scalar($requestId)
    ? filter_var((string)$requestId, FILTER_VALIDATE_INT)
    : false;

if (!$addressId || $userId <= 0) {
    $_SESSION['account_field_errors'] = array('address_action' => ValidationService::validationMessage('system'));
    header('Location: taikhoan.php');
    exit();
}

try {
    $selectSql = 'SELECT id, ho_ten, so_dien_thoai, cong_ty, dia_chi, quoc_gia, ma_zip, mac_dinh
        FROM diachi_khachhang WHERE id = ? AND user_id = ? LIMIT 1';
    $selectStatement = $conn->prepare($selectSql);
    $selectStatement->bind_param('ii', $addressId, $userId);
    $selectStatement->execute();
    $address = $selectStatement->get_result()->fetch_assoc();
    $selectStatement->close();
} catch (Throwable $exception) {
    error_log(sprintf('%s:%d: %s', (dirname(__DIR__, 2) . '/suadiachi.php'), __LINE__, $exception->getMessage()));
    $_SESSION['account_field_errors'] = array('address_action' => ValidationService::validationMessage('system'));
    header('Location: taikhoan.php');
    exit();
}

if (!$address) {
    $_SESSION['account_field_errors'] = array('address_action' => ValidationService::validationMessage('system'));
    header('Location: taikhoan.php');
    exit();
}

$errors = array();
$values = $address;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $values = array(
        'ho_ten' => trim($_POST['ho_ten'] ?? ''),
        'so_dien_thoai' => ValidationService::normalizeVnPhone($_POST['sdt'] ?? ''),
        'cong_ty' => trim($_POST['cong_ty'] ?? ''),
        'dia_chi' => trim($_POST['dia_chi'] ?? ''),
        'quoc_gia' => trim($_POST['quoc_gia'] ?? ''),
        'ma_zip' => trim($_POST['ma_zip'] ?? ''),
        'mac_dinh' => isset($_POST['mac_dinh']) ? 1 : 0
    );

    if (!CsrfService::check($_POST['csrf_token'] ?? null)) {
        $errors['form'] = ValidationService::validationMessage('system');
    }
    if (!ValidationService::validatePersonName($values['ho_ten'])) {
        $errors['ho_ten'] = ValidationService::validationMessage('recipient_required');
    }
    if (!ValidationService::isValidVnPhone($values['so_dien_thoai'])) {
        $errors['sdt'] = ValidationService::phoneValidationMessage();
    }
    if ($values['dia_chi'] === '') {
        $errors['dia_chi'] = ValidationService::validationMessage('address_required');
    }
    if (!in_array($values['quoc_gia'], array('Vietnam', 'Japan', 'Korea'), true)) {
        $errors['quoc_gia'] = ValidationService::validationMessage('locations_required');
    }

    if (!$errors) {
        $transactionStarted = false;
        try {
            $conn->begin_transaction();
            $transactionStarted = true;

            if ($values['mac_dinh'] === 1) {
                $resetDefault = $conn->prepare(
                    'UPDATE diachi_khachhang SET mac_dinh = 0 WHERE user_id = ? AND id <> ?'
                );
                $resetDefault->bind_param('ii', $userId, $addressId);
                $resetDefault->execute();
                $resetDefault->close();
            }

            $updateSql = 'UPDATE diachi_khachhang
                SET ho_ten = ?, so_dien_thoai = ?, cong_ty = ?, dia_chi = ?,
                    quoc_gia = ?, ma_zip = ?, mac_dinh = ?
                WHERE id = ? AND user_id = ?';
            $update = $conn->prepare($updateSql);
            $hoTen = $values['ho_ten'];
            $phone = $values['so_dien_thoai'];
            $company = $values['cong_ty'];
            $addressText = $values['dia_chi'];
            $country = $values['quoc_gia'];
            $zipCode = $values['ma_zip'];
            $isDefault = (int)$values['mac_dinh'];
            $update->bind_param(
                'ssssssiii',
                $hoTen,
                $phone,
                $company,
                $addressText,
                $country,
                $zipCode,
                $isDefault,
                $addressId,
                $userId
            );
            $update->execute();
            $update->close();

            $conn->commit();
            $transactionStarted = false;
            header('Location: taikhoan.php');
            exit();
        } catch (Throwable $exception) {
            if ($transactionStarted) {
                $conn->rollback();
            }
            error_log(sprintf('%s:%d: %s', (dirname(__DIR__, 2) . '/suadiachi.php'), __LINE__, $exception->getMessage()));
            $errors['form'] = ValidationService::validationMessage('system');
        }
    }
}

$csrfToken = CsrfService::token();

        return get_defined_vars();
    }
}
