<?php
//xử lý luồng thanh toán và đặt hàn
final class CheckoutController
{
    public static function dh_text($v): string {
    return is_string($v) ? trim($v) : '';
}

    public static function dh_escape($v): string {
    return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

    public static function dh_money($v): string {
    return number_format((int)$v, 0, ',', '.') . 'đ';
}

    public static function dh_redirect(string $url): void {
    header('Location: ' . $url, true, 303);
    exit;
}

    public static function dh_fail(string $message, string $page = 'phuongthucthanhtoan.php'): void {
    $_SESSION['checkout_error'] = $message;
    $_SESSION['checkout_old'] = $_SESSION['checkout_info'] ?? [];
    unset($_SESSION['checkout_review']);
    self::dh_redirect($page);
}

    public static function dh_delivery(array $info): array {
    foreach (['ho_ten','sdt','dia_chi','tinh_thanh','quan_huyen','phuong_xa'] as $field) {
        $info[$field] = self::dh_text($info[$field] ?? '');
    }
    $info['sdt'] = ValidationService::normalizeVnPhone($info['sdt']);
    return $info;
}

    public static function dh_clear_checkout(): void {
    unset($_SESSION['cart'], $_SESSION['checkout_info'], $_SESSION['checkout_review'],
        $_SESSION['checkout_old'], $_SESSION['checkout_error'],
        $_SESSION['voucher_code'], $_SESSION['voucher_user_id']);
}

    public static function dh_refresh_cart(mysqli $conn, array $cart): array {
    if (!$cart) throw new DomainException('Giỏ hàng đang trống.');
    ksort($cart, SORT_STRING);
    $items = []; $reviewCart = []; $updated = []; $subtotal = 0;
    foreach ($cart as $key => $item) {
        $id = (string)$key;
        if (!is_array($item)) throw new DomainException('Sản phẩm không hợp lệ.');
        $qty = filter_var($item['qty'] ?? null, FILTER_VALIDATE_INT,
            ['options'=>['min_range'=>1,'max_range'=>2147483647]]);
        if ($qty === false) throw new DomainException('Số lượng sản phẩm không hợp lệ.');
        if (preg_match('/^cat_([1-9][0-9]{0,9})$/D', $id, $m)) {
            $table = 'category_products'; $pid = (int)$m[1];
        } elseif (preg_match('/^[1-9][0-9]{0,9}$/D', $id)) {
            $table = 'products'; $pid = (int)$id;
        } else throw new DomainException('Mã sản phẩm không hợp lệ.');
        if ($pid > 2147483647) throw new DomainException('Mã sản phẩm không hợp lệ.');
        // Tên bảng chỉ lấy từ hai giá trị cố định ở trên.
        $stmt = $conn->prepare("SELECT name, price, old_price, image FROM `$table` WHERE id = ? FOR UPDATE");
        try {
            $stmt->bind_param('i', $pid); $stmt->execute();
            $p = $stmt->get_result()->fetch_assoc();
        } finally { $stmt->close(); }
        if (!$p || (int)$p['price'] <= 0) {
                       throw new DomainException('Có sản phẩm không còn bán hoặc giá không hợp lệ. Vui lòng cập nhật giỏ hàng.');
        }
        // Các bảng đơn hàng gốc lưu giá nguyên đồng; nhất quán với bước 7.
        $price = (int)$p['price'];
        if ($price > intdiv(2147483647 - 30000 - $subtotal, $qty)) {
            throw new DomainException('Tổng giá trị đơn hàng vượt giới hạn.');
        }
        $line = $price * $qty; $subtotal += $line;
        $name = (string)$p['name'];
        $image = $table === 'products' ? 'images/'.$p['image'] : (string)$p['image'];
        // Giữ quy ước dữ liệu cũ: product_id = 0 với sản phẩm danh mục.
        // catalog_id sẽ phân biệt chúng với sản phẩm từ bảng products.
        $items[] = ['legacy_id'=>$table === 'products' ? $pid : 0,
            'name'=>$name,'image'=>$image,'price'=>$price,'qty'=>$qty,'line'=>$line];
        $reviewCart[$id] = ['qty'=>$qty,'price'=>$price];
        $item['id']=$id; $item['name']=$name; $item['img']=$image;
        $item['price']=$price; $item['old_price']=(int)$p['old_price']; $item['qty']=$qty;
        $updated[$key]=$item;
    }
       ksort($reviewCart, SORT_STRING);
    $_SESSION['cart'] = $updated;
    return ['items'=>$items,'cart'=>$reviewCart,'subtotal'=>$subtotal];
}

    public static function dh_catalog_id(mysqli $conn, int $legacy, string $name): int {
    $stmt = $conn->prepare('INSERT INTO ordered_product_catalog (legacy_product_id, ten_san_pham)
        VALUES (?, ?) ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)');
    try {
        $stmt->bind_param('is', $legacy, $name); $stmt->execute();
        $id = (int)$conn->insert_id;
    } finally { $stmt->close(); }
    if ($id <= 0) throw new RuntimeException('Không xác định được catalog_id.');
    return $id;
}

    public function placeOrder(): array
    {

// TT SHOP - Bước 8: xác nhận, tính lại và lưu đơn hàng COD.
if (session_status() === PHP_SESSION_NONE) session_start();
require_once (dirname(__DIR__, 2)) . '/csrf.php';
require_once (dirname(__DIR__, 2)) . '/validate_helpers.php';
require_once (dirname(__DIR__, 2)) . '/voucher_helpers.php';
require_once (dirname(__DIR__, 2)) . '/payment_helpers.php';
header('Cache-Control: no-store');








// Đọc lại giá và giữ khóa sản phẩm đến khi hoàn tất transaction.


// Chèn hoặc lấy ID danh mục đã đặt. Dùng UNIQUE(legacy_product_id, ten_san_pham)
// có sẵn trong ttshop_final.sql để tránh tạo trùng catalog.


$userId = (int)($_SESSION['user_id'] ?? 0);
if (($_SESSION['is_login'] ?? false) !== true || $userId <= 0) self::dh_redirect('dangky.php');
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// GET chỉ hiển thị biên nhận của chính tài khoản, tuyệt đối không tạo đơn.
if ($method === 'GET') {
    $receipt = $_SESSION['checkout_receipt'] ?? [];
    if (!is_array($receipt) || (int)($receipt['user_id'] ?? 0) !== $userId
        || empty($receipt['ma_don']) || (int)($receipt['created_at'] ?? 0) < time()-86400
        || !isset($_GET['success']) || !is_string($_GET['success'])
        || !hash_equals((string)$receipt['ma_don'], $_GET['success'])
        || strcasecmp(self::dh_text($_SESSION['user_email'] ?? ''), (string)($receipt['email'] ?? '')) !== 0) {
        self::dh_redirect(empty($_SESSION['cart']) ? 'giohang.php' : 'phuongthucthanhtoan.php');
    }
} elseif ($method === 'POST') {
    if (!CsrfService::check($_POST['csrf_token'] ?? null)) {
        http_response_code(403);
        exit('Phiên xác thực không hợp lệ. Vui lòng tải lại trang thanh toán.');
    }
    $chosen = $_POST['phuong_thuc'] ?? '';
    if (!in_array($chosen, ['COD','PAYOS'], true)) {
        self::dh_fail(ValidationService::validationMessage('payment_required'));
    }
    $reviewId = $_POST['review_id'] ?? '';
    if (!is_string($reviewId) || !preg_match('/^[a-f0-9]{64}$/D', $reviewId)) {
        self::dh_fail('Phiên xác nhận đơn hàng không hợp lệ. Vui lòng kiểm tra lại.');
    }
    // Khóa chống đặt trùng tách khỏi mã đơn hàng hiển thị.
    // Giữ công thức cũ để nhận diện cả các đơn đã được chuyển đổi.
    $requestKey = 'TT'.substr(hash('sha256', $userId.':'.$reviewId), 0, 48);
    // Mã tạm chỉ tồn tại trong transaction, không hiển thị cho khách hàng.
    $ma_don = $requestKey;
        $review = $_SESSION['checkout_review'] ?? [];
    $matchesReview = is_array($review) && (int)($review['user_id'] ?? 0) === $userId
        && is_string($review['id'] ?? null) && hash_equals($review['id'], $reviewId);
    $conn = null; $inTransaction = false;
    $errorPage = 'phuongthucthanhtoan.php';
    try {
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        require (dirname(__DIR__, 2)) . '/connect.php';
        // Đọc dữ liệu đã commit mới nhất, và giữ khóa tới cuối transaction.
        $conn->query('SET TRANSACTION ISOLATION LEVEL READ COMMITTED');
        $conn->begin_transaction(); $inTransaction = true;

        // Khóa hàng tài khoản để hai yêu cầu của cùng khách không tạo trùng đơn.
        $stmt = $conn->prepare('SELECT email FROM users WHERE id = ? FOR UPDATE');
        try {
            $stmt->bind_param('i', $userId); $stmt->execute();
            $account = $stmt->get_result()->fetch_assoc();
        } finally { $stmt->close(); }
        $email = self::dh_text($_SESSION['user_email'] ?? '');
        if (!$account || $email === '' || strcasecmp($email, (string)$account['email']) !== 0) {
            $errorPage = 'dangky.php';
            throw new DomainException('Tài khoản không hợp lệ. Vui lòng đăng nhập lại.');
        }
        $email = (string)$account['email'];
        
        // Nếu request đã được lưu trước đó, trả lại biên nhận thay vì INSERT lần nữa.
        $stmt = $conn->prepare('SELECT id, ma_don, ma_giam_gia, tien_giam_gia, tong_cong, phuong_thuc
            FROM donhang WHERE user_id = ? AND checkout_request_key = ? LIMIT 1');
        try {
            $stmt->bind_param('is', $userId, $requestKey); $stmt->execute();
            $existing = $stmt->get_result()->fetch_assoc();
        } finally { $stmt->close(); }
        if ($existing) {
            $ma_don = (string)$existing['ma_don'];
            $conn->commit(); $inTransaction = false;
            // Không xóa giỏ hàng mới nếu đây là request cũ được gửi lại.
            if ($matchesReview) self::dh_clear_checkout();
            $_SESSION['checkout_receipt'] = array_merge($existing,
                ['user_id'=>$userId,'email'=>$email,'created_at'=>time()]);
            // Preserve legacy fallback: only exact PAYOS uses QR.
            $strategy = PaymentMethodFactory::forCode(($existing['phuong_thuc'] ?? '') === 'PAYOS' ? 'PAYOS' : 'COD', $conn);
            self::dh_redirect($strategy->resumeUrl((int)$existing['id'], $ma_don));
        }

        if (!$matchesReview || (int)($review['created_at'] ?? 0) < time()-1800) {
            throw new DomainException('Phiên xác nhận đã thay đổi hoặc hết hạn. Vui lòng kiểm tra lại đơn hàng.');
        }
        $cart = $_SESSION['cart'] ?? [];
        if (!is_array($cart) || !$cart) {
            $errorPage = 'giohang.php'; throw new DomainException('Giỏ hàng đang trống.');
        }
        $checkout = $_SESSION['checkout_info'] ?? [];
        if (!is_array($checkout) || !$checkout) {
            $errorPage = 'thanhtoan.php'; throw new DomainException('Vui lòng nhập thông tin giao hàng.');
        }
        $checkout = self::dh_delivery($checkout);
        if ($checkout['ho_ten'] === '' || !ValidationService::isValidVnPhone($checkout['sdt']) || $checkout['dia_chi'] === '') {
            $errorPage = 'thanhtoan.php';
            throw new DomainException($checkout['ho_ten'] === '' ? ValidationService::validationMessage('recipient_required')
                : (!ValidationService::isValidVnPhone($checkout['sdt']) ? ValidationService::phoneValidationMessage() : ValidationService::validationMessage('address_required')));
        }
        if (($review['delivery'] ?? null) !== $checkout) {
            throw new DomainException('Thông tin giao hàng đã thay đổi. Vui lòng xác nhận lại.');
        }
        $code = self::dh_text($_SESSION['voucher_code'] ?? '');
        if ($code !== '' && (int)($_SESSION['voucher_user_id'] ?? 0) !== $userId) {
            unset($_SESSION['voucher_code'], $_SESSION['voucher_user_id']);
            throw new DomainException('Mã giảm giá không thuộc tài khoản hiện tại. Vui lòng kiểm tra lại.');
        }
        if ($code !== ($review['code'] ?? null) || ($_POST['review_code'] ?? null) !== $code
            || ($_POST['review_total'] ?? null) !== (string)($review['total'] ?? '')) {
            throw new DomainException('Mã giảm giá hoặc tổng tiền đã thay đổi. Vui lòng xác nhận lại.');
        }
               // Giá và số lượng phải khớp với bản review ở bước 7.
        $fresh = self::dh_refresh_cart($conn, $cart);
        if ($fresh['cart'] !== ($review['cart'] ?? null)) {
            throw new DomainException('Giá hoặc số lượng sản phẩm đã thay đổi. Vui lòng kiểm tra lại đơn hàng.');
        }
        // true: khóa voucher và đọc lượt sử dụng bên trong transaction.
        $v = (new VoucherService($conn))->validate( $userId, $code, $fresh['subtotal'], 30000, true);
        if (!$v['ok']) throw new DomainException($v['message'].' Vui lòng kiểm tra lại đơn hàng.');
        foreach (['subtotal','discount','shipping_discount','shipping','total'] as $field) {
            if (!isset($review[$field]) || (int)$review[$field] !== (int)$v[$field]) {
                throw new DomainException('Ưu đãi hoặc tổng thanh toán đã thay đổi. Vui lòng xác nhận lại.');
            }
        }
        if (($review['tier'] ?? null) !== $v['tier'] || ($v['voucher']['ma_code'] ?? '') !== $code) {
            throw new DomainException('Hạng thành viên hoặc mã giảm giá đã thay đổi. Vui lòng xác nhận lại.');
        }

        $ho = $checkout['ho_ten']; $sdt = $checkout['sdt']; $diachi = $checkout['dia_chi'];
        $tinh = $checkout['tinh_thanh']; $huyen = $checkout['quan_huyen']; $xa = $checkout['phuong_xa'];
        $payment = $chosen; $status = 'Chờ xử lý';
        $strategy = PaymentMethodFactory::forCode($payment, $conn);
        $strategy->validate((int)$v['total']);
        $subtotal = (int)$v['subtotal']; $discount = (int)$v['discount'];
        $shipping = (int)$v['shipping']; $total = (int)$v['total'];
        $tier = $v['tier']; $savedCode = $code === '' ? null : $code;
        
        // Lưu đơn hàng, bao gồm các cột đã thêm từ update_voucher_fix.sql.
        $stmt = $conn->prepare('INSERT INTO donhang
            (user_id, ma_don, ho_ten, sdt, email, dia_chi, tinh_thanh, quan_huyen,
             phuong_xa, phuong_thuc, tam_tinh, phi_van_chuyen, tong_cong, trang_thai,
             ma_giam_gia, tien_giam_gia, hang_thanh_vien, checkout_request_key)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        try {
            $stmt->bind_param('isssssssssiiississ', $userId, $ma_don, $ho, $sdt, $email,
                $diachi, $tinh, $huyen, $xa, $payment, $subtotal, $shipping, $total,
                $status, $savedCode, $discount, $tier, $requestKey);
            $stmt->execute(); $orderId = (int)$conn->insert_id;
        } finally { $stmt->close(); }
        if ($orderId <= 0) throw new RuntimeException('Không xác định được ID đơn hàng.');
        // Provider-specific initialization stays inside the existing transaction.
        $strategy->initialize($orderId);

        // TT + thời điểm tạo đơn + ID đơn (ít nhất 4 chữ số, không cắt ID lớn).
        // Dùng created_at của database; 4 chữ số phân biệt với mã cũ 3 số ngẫu nhiên.
        $stmt = $conn->prepare("UPDATE donhang SET ma_don = CONCAT('TT',
            DATE_FORMAT(created_at, '%Y%m%d%H%i%s'),
            LPAD(CAST(id AS CHAR), GREATEST(4, CHAR_LENGTH(CAST(id AS CHAR))), '0'))
            WHERE id = ?");
        try {
            $stmt->bind_param('i', $orderId); $stmt->execute();
            if ($stmt->affected_rows !== 1) throw new RuntimeException('Không thể tạo mã đơn hàng.');
        } finally { $stmt->close(); }
        $stmt = $conn->prepare('SELECT ma_don FROM donhang WHERE id = ?');
        try {
            $stmt->bind_param('i', $orderId); $stmt->execute();
            $savedOrder = $stmt->get_result()->fetch_assoc();
            if (!$savedOrder) throw new RuntimeException('Không tìm thấy mã đơn hàng.');
            $ma_don = (string)$savedOrder['ma_don'];
        } finally { $stmt->close(); }


        // Lưu chi tiết từng sản phẩm và catalog_id đúng khóa ngoại.
        $stmt = $conn->prepare('INSERT INTO chitiet_donhang
            (donhang_id, product_id, ten_san_pham, hinh_anh, gia, so_luong, thanh_tien, catalog_id)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
        try {
            foreach ($fresh['items'] as $item) {
                $legacy = $item['legacy_id']; $name = $item['name']; $image = $item['image'];
                $price = $item['price']; $qty = $item['qty']; $line = $item['line'];
                $catalogId = self::dh_catalog_id($conn, $legacy, $name);
                $stmt->bind_param('iissiiii', $orderId, $legacy, $name, $image,
                    $price, $qty, $line, $catalogId);
                $stmt->execute();
            }
        } finally { $stmt->close(); }
       // Ghi nhận một lượt sử dụng voucher chỉ khi đơn hàng được lưu thành công.
        if ($v['voucher'] !== null) {
            $voucherId = (int)$v['voucher']['id'];
            $shipDiscount = (int)$v['shipping_discount'];
            $stmt = $conn->prepare('INSERT INTO lichsu_magiamgia
                (magiamgia_id, user_id, donhang_id, ma_code, tien_giam_gia, tien_giam_van_chuyen)
                VALUES (?, ?, ?, ?, ?, ?)');
            try {
                $stmt->bind_param('iiisii', $voucherId, $userId, $orderId, $savedCode, $discount, $shipDiscount);
                $stmt->execute();
            } finally { $stmt->close(); }
        }

        // Chỉ xóa giỏ hàng sau khi tất cả truy vấn đã COMMIT thành công.
        $conn->commit(); $inTransaction = false;
        $_SESSION['checkout_receipt'] = ['id'=>$orderId,'ma_don'=>$ma_don,
            'ma_giam_gia'=>$savedCode,'tien_giam_gia'=>$discount,'tong_cong'=>$total,
            'user_id'=>$userId,'email'=>$email,'created_at'=>time()];
        self::dh_clear_checkout();
        // Polymorphic dispatch after commit; preserves POST/Redirect/GET.
        self::dh_redirect($strategy->successUrl($orderId, $userId, $ma_don));
    } catch (Throwable $e) {
        if ($inTransaction && $conn instanceof mysqli) {
            try { $conn->rollback(); } catch (Throwable $rollbackError) {
                error_log('TT SHOP rollback: '.$rollbackError->getMessage());
            }
        }
        if (!($e instanceof DomainException)) error_log('TT SHOP xuly_dathang: '.$e->getMessage());
        self::dh_fail($e instanceof DomainException ? $e->getMessage()
            : 'Không thể lưu đơn hàng. Vui lòng kiểm tra lịch sử đơn hàng và thử lại.', $errorPage);
    }
} else {
    header('Allow: GET, POST'); http_response_code(405); exit;
}
$ma_don = (string)$receipt['ma_don'];

        return get_defined_vars();
    }

    public static function tt_e($v): string {
    return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

    public static function tt_money($v): string {
    return number_format((int)$v, 0, ',', '.') . 'đ';
}

    public static function tt_text($v): string {
    return is_string($v) ? trim($v) : '';
}

    public static function tt_clear_voucher(): void {
    unset($_SESSION['voucher_code'], $_SESSION['voucher_user_id']);
}

    public static function tt_refresh_cart(mysqli $conn): int {
    $cart = $_SESSION['cart'] ?? [];
    if (!is_array($cart)) throw new DomainException('Giỏ hàng không hợp lệ.');
    $updated = []; $subtotal = 0;
    foreach ($cart as $key => $item) {
        $id = (string)$key;
        if (!is_array($item)) throw new DomainException('Sản phẩm không hợp lệ.');
        $qty = filter_var($item['qty'] ?? null, FILTER_VALIDATE_INT,
            ['options' => ['min_range'=>1, 'max_range'=>2147483647]]);
        if ($qty === false) throw new DomainException('Số lượng không hợp lệ.');
        if (preg_match('/^cat_([1-9][0-9]{0,9})$/D', $id, $m)) {
            $table = 'category_products'; $pid = (int)$m[1];
        } elseif (preg_match('/^[1-9][0-9]{0,9}$/D', $id)) {
            $table = 'products'; $pid = (int)$id;
        } else throw new DomainException('Mã sản phẩm không hợp lệ.');
        if ($pid > 2147483647) throw new DomainException('Mã sản phẩm không hợp lệ.');
        $stmt = $conn->prepare("SELECT name, price, old_price, image FROM `$table` WHERE id = ?");
        try {
            $stmt->bind_param('i', $pid); $stmt->execute();
            $p = $stmt->get_result()->fetch_assoc();
        } finally { $stmt->close(); }
        if (!$p || (int)$p['price'] <= 0) {
            throw new DomainException('Sản phẩm không còn bán hoặc giá không hợp lệ. Hãy cập nhật giỏ hàng.');
        }
        $price = (int)$p['price'];
        if ($price > intdiv(2147483647 - 30000 - $subtotal, $qty)) {
            throw new DomainException('Tổng giá trị đơn hàng vượt giới hạn.');
        }
                $subtotal += $price * $qty;
        $item['id'] = $id; $item['qty'] = $qty;
        $item['name'] = $p['name']; $item['price'] = $price;
        $item['old_price'] = (int)$p['old_price'];
        $item['img'] = $table === 'products' ? 'images/'.$p['image'] : $p['image'];
        $updated[$key] = $item;
    }
    $_SESSION['cart'] = $updated;
    return $subtotal;
}

    public static function tt_snapshot(mysqli $conn, int $userId): array {
    $subtotal = self::tt_refresh_cart($conn);
    $cart = $_SESSION['cart'];
    $shipping = $cart ? 30000 : 0;
    $member = (new MembershipService($conn))->get( $userId);
    if (!$cart || (int)($_SESSION['voucher_user_id'] ?? 0) !== $userId) self::tt_clear_voucher();
    $code = self::tt_text($_SESSION['voucher_code'] ?? '');
    $result = (new VoucherService($conn))->validate( $userId, $code, $subtotal, $shipping);
    $message = '';
    if (!$result['ok']) {
        $message = $result['message']; self::tt_clear_voucher(); $code = '';
    }
    $totals = VoucherService::calculate($subtotal, $shipping, $result['ok'] ? $result['voucher'] : null);
    $list = [];
    if ($cart) foreach ((new VoucherService($conn))->listVouchers( $userId, $subtotal, $shipping) as $v) {
        $r = $v['voucher'];
        $list[] = ['code'=>$r['ma_code'], 'tier'=>$r['hang_toi_thieu'],
            'rate'=>(int)$r['phan_tram_giam'], 'min'=>(int)$r['don_toi_thieu'],
            'cap'=>(int)$r['giam_toi_da'], 'free'=>(int)$r['mien_phi_van_chuyen']===1,
            'ok'=>$v['ok'], 'message'=>$v['ok'] ? '' : $v['message']];
    }
    return array_merge($totals, ['ok'=>true, 'items'=>array_values($cart),
        'member'=>$member, 'vouchers'=>$list, 'code'=>$code, 'message'=>$message]);
}

    public function checkout(): array
    {

// thanhtoan.php - Thông tin giao hàng và voucher thành viên
if (session_status() === PHP_SESSION_NONE) session_start();
require_once (dirname(__DIR__, 2)) . '/csrf.php';
require_once (dirname(__DIR__, 2)) . '/validate_helpers.php';
require_once (dirname(__DIR__, 2)) . '/voucher_helpers.php';
header('Cache-Control: no-store');






$userId = (int)($_SESSION['user_id'] ?? 0);
if (($_SESSION['is_login'] ?? false) !== true || $userId <= 0) {
    header('Location: dangky.php'); exit;
}
if (empty($_SESSION['cart'])) {
    self::tt_clear_voucher(); header('Location: giohang.php'); exit;
}
$token = CsrfService::token();
$isPost = ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST';
if ($isPost && !CsrfService::check($_POST['csrf_token'] ?? null)) {
    http_response_code(403); exit('Phiên xác thực không hợp lệ. Vui lòng quay lại và tải lại trang.');
}
// Tính lại giá từ hai bảng sản phẩm như giohang.php.


// Cùng cấu trúc kết quả với AJAX snapshot của giohang.php.


$error = self::tt_text($_SESSION['checkout_error'] ?? '');
$old = $_SESSION['checkout_old'] ?? $_SESSION['checkout_info'] ?? [];
if (!is_array($old)) $old = [];
unset($_SESSION['checkout_error'], $_SESSION['checkout_old']);
$user = [];
$delivery = [];
$state = ['ok'=>false,'items'=>[],'member'=>null,'vouchers'=>[],'code'=>'','message'=>'',
    'subtotal'=>0,'discount'=>0,'shipping'=>0,'shipping_discount'=>0,'total'=>0];
try {
       mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    require (dirname(__DIR__, 2)) . '/connect.php';
    $stmt = $conn->prepare('SELECT ho, ten, email, sdt, diachi FROM users WHERE id = ?');
    try {
        $stmt->bind_param('i', $userId); $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc() ?: [];
    } finally { $stmt->close(); }
    if (!$user || empty($_SESSION['user_email']) ||
        strcasecmp((string)$_SESSION['user_email'], (string)$user['email']) !== 0) {
        self::tt_clear_voucher();
        unset($_SESSION['checkout_info']);
        throw new DomainException('Tài khoản không hợp lệ. Vui lòng đăng xuất và đăng nhập lại.');
    }
    $fullName = trim(($user['ho'] ?? '').' '.($user['ten'] ?? ''));
    $delivery = [
        'ho_ten'=>self::tt_text($old['ho_ten'] ?? $fullName),
        'sdt'=>self::tt_text($old['sdt'] ?? $user['sdt'] ?? ''),
        'dia_chi'=>self::tt_text($old['dia_chi'] ?? $user['diachi'] ?? '')
    ];
    if ($isPost) {
        foreach ($delivery as $key=>$value) $delivery[$key] = self::tt_text($_POST[$key] ?? '');
        $delivery['sdt'] = ValidationService::normalizeVnPhone($delivery['sdt']);
    }
    $state = self::tt_snapshot($conn, $userId);
    if ($isPost) {
        $error = '';
        if ($delivery['ho_ten'] === '') $error = ValidationService::validationMessage('recipient_required');
        elseif (!ValidationService::isValidVnPhone($delivery['sdt'])) $error = ValidationService::phoneValidationMessage();
        elseif ($delivery['dia_chi'] === '') $error = ValidationService::validationMessage('address_required');
        elseif (!$state['items']) $error = 'Giỏ hàng đang trống.';
        elseif ($state['message'] !== '') $error = $state['message'].' Hãy kiểm tra lại tổng tiền.';
        elseif (self::tt_text($_POST['review_code'] ?? '') !== $state['code'] ||
            self::tt_text($_POST['review_total'] ?? '') !== (string)$state['total']) {
            $error = 'Ưu đãi hoặc tổng tiền đã thay đổi. Vui lòng kiểm tra lại trước khi tiếp tục.';
        }
        if ($error === '') {
            // Trang phương thức thanh toán đã hỗ trợ đọc checkout_info từ SESSION.
            $_SESSION['checkout_info'] = array_merge($delivery,
                ['tinh_thanh'=>'','quan_huyen'=>'','phuong_xa'=>'']);
            header('Location: phuongthucthanhtoan.php', true, 303); exit;
        }
        // Không cho dùng lại thông tin giao hàng cũ sau POST thất bại.
        unset($_SESSION['checkout_info']);
    }
} catch (Throwable $e) {
    $state['ok'] = false;
    $error = $e instanceof DomainException ? $e->getMessage() : 'Không thể tải thông tin thanh toán. Vui lòng thử lại.';
    if (!($e instanceof DomainException)) error_log('TT SHOP thanhtoan: '.$e->getMessage());
}
$fullName = trim(($user['ho'] ?? '').' '.($user['ten'] ?? ''));

        return get_defined_vars();
    }

    public static function checkout_escape($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

    public static function checkout_money($value): string {
    return number_format((int)$value, 0, ',', '.') . 'đ';
}

    public static function pt_text($v): string { return is_string($v) ? trim($v) : ''; }

    public static function pt_clear_voucher(): void {
    unset($_SESSION['voucher_code'], $_SESSION['voucher_user_id']);
}

    public static function pt_redirect(string $page): void {
    header('Location: '.$page, true, 303); exit;
}

    public static function pt_refresh_cart(mysqli $conn): int {
    $cart = $_SESSION['cart'] ?? [];
    if (!is_array($cart)) throw new DomainException('Giỏ hàng không hợp lệ.');
    $updated = []; $subtotal = 0;
    foreach ($cart as $key => $item) {
        $id = (string)$key;
        if (!is_array($item)) throw new DomainException('Sản phẩm không hợp lệ.');
        $qty = filter_var($item['qty'] ?? null, FILTER_VALIDATE_INT,
            ['options' => ['min_range'=>1, 'max_range'=>2147483647]]);
        if ($qty === false) throw new DomainException('Số lượng không hợp lệ.');
        if (preg_match('/^cat_([1-9][0-9]{0,9})$/D', $id, $m)) {
            $table = 'category_products'; $pid = (int)$m[1];
        } elseif (preg_match('/^[1-9][0-9]{0,9}$/D', $id)) {
            $table = 'products'; $pid = (int)$id;
        } else throw new DomainException('Mã sản phẩm không hợp lệ.');
        if ($pid > 2147483647) throw new DomainException('Mã sản phẩm không hợp lệ.');
        $stmt = $conn->prepare("SELECT name, price, old_price, image FROM `$table` WHERE id = ?");
        try {
            $stmt->bind_param('i', $pid); $stmt->execute();
            $p = $stmt->get_result()->fetch_assoc();
        } finally { $stmt->close(); }
        if (!$p || (int)$p['price'] <= 0) {
            throw new DomainException('Sản phẩm không còn bán hoặc giá không hợp lệ. Hãy cập nhật giỏ hàng.');
        }
        $price = (int)$p['price'];
        if ($price > intdiv(2147483647 - 30000 - $subtotal, $qty)) {
            throw new DomainException('Tổng giá trị đơn hàng vượt giới hạn.');
        }
        $subtotal += $price * $qty;
        $item['id'] = $id; $item['qty'] = $qty;
        $item['name'] = $p['name']; $item['price'] = $price;
        $item['old_price'] = (int)$p['old_price'];
        $item['img'] = $table === 'products' ? 'images/'.$p['image'] : $p['image'];
        $updated[$key] = $item;
    }
    $_SESSION['cart'] = $updated;
    return $subtotal;
}

    public function paymentMethod(): array
    {

// phuongthucthanhtoan.php - TT SHOP: bước 7, xác nhận thanh toán.
if (session_status() === PHP_SESSION_NONE) session_start();
require_once (dirname(__DIR__, 2)) . '/csrf.php';
require_once (dirname(__DIR__, 2)) . '/validate_helpers.php';
require_once (dirname(__DIR__, 2)) . '/voucher_helpers.php';
require_once (dirname(__DIR__, 2)) . '/payment_helpers.php';
header('Cache-Control: no-store');







$userId = (int)($_SESSION['user_id'] ?? 0);
if (($_SESSION['is_login'] ?? false) !== true || $userId <= 0) {
    self::pt_clear_voucher(); unset($_SESSION['checkout_review']);
    self::pt_redirect('dangky.php');
}
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
// Trang thanhtoan.php mới chỉ chuyển sang đây bằng GET sau khi kiểm tra CSRF.
// Không nhận lại thông tin giao hàng qua POST trực tiếp.
if ($method === 'POST') {
    if (!CsrfService::check($_POST['csrf_token'] ?? null)) {
        http_response_code(403); exit('Phiên xác thực không hợp lệ. Vui lòng tải lại trang.');
    }
    self::pt_redirect('thanhtoan.php');
}
if (!in_array($method, ['GET', 'HEAD'], true)) {
    header('Allow: GET, HEAD'); http_response_code(405); exit;
}
if (empty($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
    self::pt_clear_voucher(); unset($_SESSION['checkout_review']);
    self::pt_redirect('giohang.php');
}
$checkout = $_SESSION['checkout_info'] ?? [];
if (!is_array($checkout) || !$checkout) {
    unset($_SESSION['checkout_review']); self::pt_redirect('thanhtoan.php');
}
foreach (['ho_ten','sdt','dia_chi','tinh_thanh','quan_huyen','phuong_xa'] as $field) {
    $checkout[$field] = self::pt_text($checkout[$field] ?? '');
}
$checkout['sdt'] = ValidationService::normalizeVnPhone($checkout['sdt']);
$error = '';
if ($checkout['ho_ten'] === '') $error = ValidationService::validationMessage('recipient_required');
elseif (!ValidationService::isValidVnPhone($checkout['sdt'])) $error = ValidationService::phoneValidationMessage();
elseif ($checkout['dia_chi'] === '') $error = ValidationService::validationMessage('address_required');
if ($error !== '') {
    $_SESSION['checkout_error'] = $error;
    $_SESSION['checkout_old'] = $checkout;
    unset($_SESSION['checkout_info'], $_SESSION['checkout_review']);
    self::pt_redirect('thanhtoan.php');
}
$_SESSION['checkout_info'] = $checkout;
$token = CsrfService::token();
$checkoutError = self::pt_text($_SESSION['checkout_error'] ?? '');
unset($_SESSION['checkout_error'], $_SESSION['checkout_review']);

// Lấy giá trực tiếp từ database, hỗ trợ ID thường và cat_ID.



$cart = [];
$member = null;
$code = '';
$voucherNotice = '';
$reviewId = '';
$ready = false;
$totals = VoucherService::calculate(0, 0);
$shippingBase = 30000;
try {
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    require (dirname(__DIR__, 2)) . '/connect.php';
    $stmt = $conn->prepare('SELECT email FROM users WHERE id = ?');
    try {
        $stmt->bind_param('i', $userId); $stmt->execute();
        $account = $stmt->get_result()->fetch_assoc();
    } finally { $stmt->close(); }
    $email = self::pt_text($_SESSION['user_email'] ?? '');
    if (!$account || $email === '' || strcasecmp($email, (string)$account['email']) !== 0) {
        self::pt_clear_voucher(); unset($_SESSION['checkout_info']);
        throw new DomainException('Tài khoản không hợp lệ. Vui lòng đăng xuất và đăng nhập lại.');
    }
    $subtotal = self::pt_refresh_cart($conn);
    $cart = $_SESSION['cart'];
    if (!$cart) { self::pt_clear_voucher(); self::pt_redirect('giohang.php'); }
    $member = (new MembershipService($conn))->get( $userId);
    if ((int)($_SESSION['voucher_user_id'] ?? 0) !== $userId) self::pt_clear_voucher();
    $code = self::pt_text($_SESSION['voucher_code'] ?? '');
    $result = (new VoucherService($conn))->validate( $userId, $code, $subtotal, $shippingBase);
    if (!$result['ok']) {
        $voucherNotice = $result['message'].' Mã đã được gỡ. Vui lòng kiểm tra lại tổng tiền.';
        self::pt_clear_voucher(); $code = '';
        $totals = VoucherService::calculate($subtotal, $shippingBase);
    } else {
        $totals = VoucherService::calculate($subtotal, $shippingBase, $result['voucher']);
        if ($result['voucher'] !== null) {
            $code = $result['voucher']['ma_code'];
            $_SESSION['voucher_code'] = $code;
            $_SESSION['voucher_user_id'] = $userId;
        } else { $code = ''; self::pt_clear_voucher(); }
    }
    // Lưu bản xác nhận phía máy chủ; xuly_dathang.php bước 8 phải đối chiếu
    // lại trong transaction, không dùng số tiền POST làm giá trị thanh toán.
    $reviewId = bin2hex(random_bytes(32));
    $reviewCart = [];
    foreach ($cart as $id => $item) {
        $reviewCart[(string)$id] = ['qty'=>(int)$item['qty'], 'price'=>(int)$item['price']];
    }
    ksort($reviewCart, SORT_STRING);
    $_SESSION['checkout_review'] = [
        'id'=>$reviewId, 'user_id'=>$userId, 'code'=>$code,
        'tier'=>$member['tier'], 'cart'=>$reviewCart, 'delivery'=>$checkout,
        'subtotal'=>$totals['subtotal'], 'discount'=>$totals['discount'],
        'shipping_discount'=>$totals['shipping_discount'], 'shipping'=>$totals['shipping'],
        'total'=>$totals['total'], 'created_at'=>time()
    ];
    $ready = true;
} catch (Throwable $e) {
    unset($_SESSION['checkout_review']);
    $checkoutError = $e instanceof DomainException ? $e->getMessage()
        : 'Không thể tải thông tin thanh toán. Vui lòng thử lại.';
    if (!($e instanceof DomainException)) error_log('TT SHOP phuongthucthanhtoan: '.$e->getMessage());
}

        return get_defined_vars();
    }
}
