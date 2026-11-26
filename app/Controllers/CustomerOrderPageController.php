//Chuẩn bị dữ liệu cho các trang đơn hàng phía khách hàng trong TT SHOP: 
//danh sách đơn hàng, xem chi tiết đơn hàng, tra cứu đơn hàng và lưu đơn hàng khi đặt hàng thành công.
<?php
final class CustomerOrderPageController
{
    public function orders(): array
    {

require_once (dirname(__DIR__, 2)) . '/payment_helpers.php';
// Kiểm tra và khởi chạy session nếu chưa được bật
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Kiểm tra bảo mật: Nếu khách chưa đăng nhập, lập tức đá về trang đăng nhập
if (($_SESSION['is_login'] ?? false) !== true || (int)($_SESSION['user_id'] ?? 0) <= 0) {
    header("Location: dangnhap.php");
    exit();
}

require_once (dirname(__DIR__, 2)) . "/connect.php";

// Lấy ID của người dùng đang đăng nhập (ép kiểu về số nguyên để an toàn dữ liệu)
$user_id = (int)$_SESSION['user_id'];

// Chuẩn bị câu lệnh SQL lấy toàn bộ đơn hàng của user này, sắp xếp đơn hàng mới nhất lên đầu (ORDER BY id DESC)
$sql = "
    SELECT id, ma_don, created_at, tong_cong, phuong_thuc, payment_status, trang_thai
    FROM donhang
    WHERE user_id = ?
    ORDER BY id DESC
";

// Thực thi câu lệnh SQL sử dụng Prepared Statement nhằm chống lỗi bảo mật SQL Injection
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);

// Lấy bộ kết quả trả về từ cơ sở dữ liệu lưu vào biến $result để sẵn sàng lặp hiển thị ra giao diện HTML
$result = mysqli_stmt_get_result($stmt);

        return get_defined_vars();
    }

    public function detail(): array
    {

require_once (dirname(__DIR__, 2)) . '/payment_helpers.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include dirname(__DIR__, 2) . '/connect.php';

if (!isset($_SESSION['is_login']) || $_SESSION['is_login'] !== true) {
    header("Location: dangnhap.php");
    exit();
}


//               HIỂN THỊ CHI TIẾT ĐƠN HÀNG CỦA KHÁCH HÀNG


$user_id = (int)$_SESSION['user_id'];
$order_id = (int)($_GET['id'] ?? 0);

$sqlOrder = "
    SELECT *
    FROM donhang
    WHERE id = ?
    AND user_id = ?
";

$stmtOrder = mysqli_prepare($conn, $sqlOrder);
mysqli_stmt_bind_param($stmtOrder, "ii", $order_id, $user_id);
mysqli_stmt_execute($stmtOrder);

$resultOrder = mysqli_stmt_get_result($stmtOrder);
$order = mysqli_fetch_assoc($resultOrder);

if (!$order) {
    die("Không tìm thấy đơn hàng.");
}


//            HIỂN THỊ CHI TIẾT SẢN PHẨM TRONG ĐƠN HÀNG VÀ ĐÁNH GIÁ CỦA KHÁCH HÀNG


$sqlItems = "
    SELECT *
    FROM chitiet_donhang
    WHERE donhang_id = ?
";

$stmtItems = mysqli_prepare($conn, $sqlItems);
mysqli_stmt_bind_param($stmtItems, "i", $order_id);
mysqli_stmt_execute($stmtItems);

$resultItems = mysqli_stmt_get_result($stmtItems);

$sqlReview = "
    SELECT *
    FROM danhgia
    WHERE donhang_id = ?
    AND user_id = ?
    LIMIT 1
";

$stmtReview = mysqli_prepare($conn, $sqlReview);
mysqli_stmt_bind_param($stmtReview, "ii", $order_id, $user_id);
mysqli_stmt_execute($stmtReview);

$resultReview = mysqli_stmt_get_result($stmtReview);
$review = mysqli_fetch_assoc($resultReview);

        return get_defined_vars();
    }

    public function lookup(): array
    {

require_once (dirname(__DIR__, 2)) . '/payment_helpers.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Thông tin đơn hàng chỉ được trả về cho tài khoản sở hữu đơn.
header('Cache-Control: no-store, private, max-age=0');
header('Pragma: no-cache');
$lookupUserId = filter_var($_SESSION['user_id'] ?? null, FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1]
]);
if (($_SESSION['is_login'] ?? false) !== true || $lookupUserId === false) {
    $_SESSION['login_error'] = 'Vui lòng đăng nhập để kiểm tra đơn hàng của bạn.';
    header('Location: dangnhap.php', true, 303);
    exit();
}

require_once (dirname(__DIR__, 2)) . '/connect.php';

$checkType = $_POST['check_type'] ?? 'phone';
$rawKeyword = $_POST['keyword'] ?? '';
$keyword = is_string($rawKeyword) ? trim($rawKeyword) : '';
$orders = [];
$lookupError = '';
$isPost = ($_SERVER['REQUEST_METHOD'] === 'POST');

//             TRA CỨU ĐƠN HÀNG THEO SỐ ĐIỆN THOẠI HOẶC EMAIL CỦA KHÁCH HÀNG

if (!isset($_SESSION['order_lookup_csrf']) || !is_string($_SESSION['order_lookup_csrf'])) {
    $_SESSION['order_lookup_csrf'] = bin2hex(random_bytes(32));
}
$lookupCsrf = $_SESSION['order_lookup_csrf'];

if ($isPost) {
    $token = $_POST['csrf_token'] ?? null;
    if (!is_string($token) || !hash_equals($lookupCsrf, $token)) {
        http_response_code(403);
        $lookupError = 'Phiên kiểm tra không hợp lệ. Vui lòng tải lại trang và thử lại.';
    } elseif (!in_array($checkType, ['phone', 'email'], true) || !is_string($rawKeyword)) {
        $lookupError = 'Thông tin kiểm tra không hợp lệ.';
    } elseif ($keyword === '') {
        $lookupError = 'Vui lòng nhập thông tin kiểm tra.';
    } elseif ($checkType === 'email' && (strlen($keyword) > 255 || !filter_var($keyword, FILTER_VALIDATE_EMAIL))) {
        $lookupError = 'Vui lòng nhập email hợp lệ.';
    } elseif ($checkType === 'phone' && (strlen($keyword) > 30 || !preg_match('/\A[0-9+(). -]{1,30}\z/', $keyword))) {
        $lookupError = 'Vui lòng nhập số điện thoại hợp lệ.';
    } else {
        // Không nhận user_id từ GET/POST. Cả hai truy vấn bắt buộc có user_id từ session.
        $sql = $checkType === 'email'
            ? 'SELECT id, ma_don, ho_ten, sdt, email, dia_chi, phuong_xa, quan_huyen, tinh_thanh, phuong_thuc, payment_status, tong_cong, trang_thai, created_at FROM donhang WHERE user_id = ? AND email = ? ORDER BY id DESC'
            : 'SELECT id, ma_don, ho_ten, sdt, email, dia_chi, phuong_xa, quan_huyen, tinh_thanh, phuong_thuc, payment_status, tong_cong, trang_thai, created_at FROM donhang WHERE user_id = ? AND sdt = ? ORDER BY id DESC';
        try {
            if ($checkType === 'email') {
                $keyword = strtolower($keyword);
            }
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, 'is', $lookupUserId, $keyword);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            while ($row = mysqli_fetch_assoc($result)) {
                $orders[] = $row;
            }
            mysqli_stmt_close($stmt);

            if ($orders) {
                // Kiểm tra quyền sở hữu cả khi đọc chi tiết sản phẩm của đơn.
                $stmtItems = mysqli_prepare($conn, 'SELECT ct.hinh_anh, ct.ten_san_pham, ct.so_luong, ct.thanh_tien FROM chitiet_donhang ct INNER JOIN donhang dh ON dh.id = ct.donhang_id WHERE dh.user_id = ? AND dh.id = ? ORDER BY ct.id');
                foreach ($orders as &$order) {
                    $lookupOrderId = (int)$order['id'];
                    mysqli_stmt_bind_param($stmtItems, 'ii', $lookupUserId, $lookupOrderId);
                    mysqli_stmt_execute($stmtItems);
                    $itemsResult = mysqli_stmt_get_result($stmtItems);
                    $order['items'] = [];
                    while ($item = mysqli_fetch_assoc($itemsResult)) {
                        $order['items'][] = $item;
                    }
                    mysqli_free_result($itemsResult);
                }
                unset($order);
                mysqli_stmt_close($stmtItems);
            }
        } catch (Throwable $exception) {
            unset($order);
            $orders = [];
            $lookupError = 'Hệ thống đang bận. Vui lòng thử lại sau.';
            error_log('TT SHOP order lookup: ' . $exception->getMessage());
            http_response_code(503);
        }
    }
}
if (!in_array($checkType, ['phone', 'email'], true)) {
    $checkType = 'phone';
}

        return get_defined_vars();
    }

    public function success(): array
    {

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include dirname(__DIR__, 2) . '/connect.php';

if (!isset($_SESSION['is_login']) || $_SESSION['is_login'] !== true) {
    header("Location: dangky.php");
    exit();
}


//            KIỂM TRA GIỎ HÀNG VÀ THÔNG TIN THANH TOÁN TRƯỚC KHI LƯU ĐƠN HÀNG


$cart = $_SESSION['cart'] ?? [];
$checkout = $_SESSION['checkout_info'] ?? [];

if (empty($cart) || empty($checkout)) {
    header("Location: giohang.php");
    exit();
}

$user_id = $_SESSION['user_id'] ?? 0;

$phuong_thuc = $_POST['phuong_thuc'] ?? 'COD';
$phi_van_chuyen = 30000;

$ho_ten     = $checkout['ho_ten'] ?? '';
$sdt        = $checkout['sdt'] ?? '';
$dia_chi    = $checkout['dia_chi'] ?? '';
$tinh_thanh = $checkout['tinh_thanh'] ?? '';
$quan_huyen = $checkout['quan_huyen'] ?? '';
$phuong_xa  = $checkout['phuong_xa'] ?? '';

$tam_tinh = 0;

foreach ($cart as $item) {
    $price = (int)($item['price'] ?? 0);
    $qty   = (int)($item['qty'] ?? 1);

    if ($qty < 1) {
        $qty = 1;
    }

    $tam_tinh += $price * $qty;
}

$tong_cong = $tam_tinh + $phi_van_chuyen;
$ma_don = "TT" . date("YmdHis") . rand(100, 999);
$trang_thai = "Chờ xử lý";

mysqli_begin_transaction($conn);

try {
    $sqlOrder = "INSERT INTO donhang (
        user_id,
        ma_don,
        ho_ten,
        sdt,
        dia_chi,
        tinh_thanh,
        quan_huyen,
        phuong_xa,
        phuong_thuc,
        tam_tinh,
        phi_van_chuyen,
        tong_cong,
        trang_thai
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $stmtOrder = mysqli_prepare($conn, $sqlOrder);

    if (!$stmtOrder) {
        throw new Exception("Lỗi prepare donhang: " . mysqli_error($conn));
    }

    mysqli_stmt_bind_param(
        $stmtOrder,
        "issssssssiiis",
        $user_id,
        $ma_don,
        $ho_ten,
        $sdt,
        $dia_chi,
        $tinh_thanh,
        $quan_huyen,
        $phuong_xa,
        $phuong_thuc,
        $tam_tinh,
        $phi_van_chuyen,
        $tong_cong,
        $trang_thai
    );

    if (!mysqli_stmt_execute($stmtOrder)) {
        throw new Exception("Lỗi lưu đơn hàng: " . mysqli_stmt_error($stmtOrder));
    }

    $donhang_id = mysqli_insert_id($conn);

    $sqlDetail = "INSERT INTO chitiet_donhang (
        donhang_id,
        product_id,
        ten_san_pham,
        hinh_anh,
        gia,
        so_luong,
        thanh_tien
    ) VALUES (?, ?, ?, ?, ?, ?, ?)";

    $stmtDetail = mysqli_prepare($conn, $sqlDetail);

    if (!$stmtDetail) {
        throw new Exception("Lỗi prepare chitiet_donhang: " . mysqli_error($conn));
    }

    foreach ($cart as $item) {
        $product_id   = (int)($item['id'] ?? 0);
        $ten_san_pham = $item['name'] ?? '';
        $hinh_anh     = $item['img'] ?? '';
        $gia          = (int)($item['price'] ?? 0);
        $so_luong     = (int)($item['qty'] ?? 1);

        if ($so_luong < 1) {
            $so_luong = 1;
        }

        $thanh_tien = $gia * $so_luong;

        mysqli_stmt_bind_param(
            $stmtDetail,
            "iissiii",
            $donhang_id,
            $product_id,
            $ten_san_pham,
            $hinh_anh,
            $gia,
            $so_luong,
            $thanh_tien
        );

        if (!mysqli_stmt_execute($stmtDetail)) {
            throw new Exception("Lỗi lưu chi tiết đơn hàng: " . mysqli_stmt_error($stmtDetail));
        }
    }

    mysqli_commit($conn);

    unset($_SESSION['cart']);
    unset($_SESSION['checkout_info']);

} catch (Exception $e) {
    mysqli_rollback($conn);
    die("Đặt hàng thất bại: " . $e->getMessage());
}

        return get_defined_vars();
    }
}
