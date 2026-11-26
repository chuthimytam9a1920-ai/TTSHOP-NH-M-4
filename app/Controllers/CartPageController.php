<?php
final class CartPageController
{
    public static function gh_escape($v) {
    return htmlspecialchars(
        (string)$v, ENT_QUOTES, 'UTF-8'
    );
}
    public static function gh_money($v) {
    return number_format((int)$v, 0, ',', '.') . 'đ';
}
    public static function gh_clear_voucher() {
    unset(
        $_SESSION['voucher_code'],
        $_SESSION['voucher_user_id']
    );
}
    public static function gh_json($data, $status = 200) {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(
        $data,
        JSON_UNESCAPED_UNICODE
        | JSON_INVALID_UTF8_SUBSTITUTE
    );
    exit;
}
    public static function gh_refresh_cart(mysqli $conn): int {
    $updated = [];
    $subtotal = 0;
    foreach (($_SESSION['cart'] ?? []) as $key => $item) {
        $id = (string)$key;
        if (!is_array($item)) {
            throw new DomainException(
                'Dữ liệu giỏ hàng không hợp lệ.'
            );
        }
        $qty = filter_var(
            $item['qty'] ?? null,
            FILTER_VALIDATE_INT,
            [
                'options' => [
                    'min_range' => 1,
                    'max_range' => 2147483647
                ]
            ]
        );
        if ($qty === false) {
            throw new DomainException(
                'Số lượng sản phẩm không hợp lệ.'
            );
        }
        // Xác định bảng sản phẩm
        if (preg_match(
            '/^cat_([1-9][0-9]{0,9})$/D',
            $id,
            $m
        )) {
            $table = 'category_products';
            $pid = (int)$m[1];
        } elseif (preg_match(
            '/^[1-9][0-9]{0,9}$/D',
            $id
        )) {
            $table = 'products';
            $pid = (int)$id;
        } else {
            throw new DomainException(
                'Mã sản phẩm không hợp lệ. Hãy xóa sản phẩm lỗi.'
            );
        }
        if ($pid > 2147483647) {
            throw new DomainException(
                'Mã sản phẩm không hợp lệ.'
            );
        }
        $stmt = $conn->prepare(
            "SELECT name, price, old_price, image
             FROM `$table`
             WHERE id = ?"
        );
        try {
            $stmt->bind_param('i', $pid);
            $stmt->execute();
            $p = $stmt->get_result()->fetch_assoc();
        } finally {
            $stmt->close();
        }
        if (!$p || (int)$p['price'] <= 0) {
            throw new DomainException(
                'Có sản phẩm không còn bán hoặc giá không hợp lệ. Hãy xóa sản phẩm đó.'
            );
        }

        $price = (int)$p['price'];
        if ($price > intdiv(
            2147483647 - 30000 - $subtotal,
            $qty
        )) {
            throw new DomainException(
                'Tổng tiền vượt giới hạn cho phép.'
            );
        }
        $subtotal += $price * $qty;
        $item['id'] = $id;
        $item['qty'] = $qty;
        $item['name'] = $p['name'];
        $item['price'] = $price;
        $item['old_price'] = (int)$p['old_price'];
        $item['img'] = $table === 'products'
            ? 'images/' . $p['image']
            : $p['image'];
        $updated[$key] = $item;
    }
    $_SESSION['cart'] = $updated;
    return $subtotal;
}

    public static function gh_snapshot(
    mysqli $conn,
    bool $logged,
    int $userId
): array {
    $subtotal = self::gh_refresh_cart($conn);
    $cart = $_SESSION['cart'] ?? [];
    $shipping = $cart ? 30000 : 0;
    $member = null;
    $list = [];
    $code = '';
    $message = '';
    $totals = VoucherService::calculate(
        $subtotal,
        $shipping
    );
    if ($logged) {

        // Kiểm tra tài khoản hiện tại

        $stmt = $conn->prepare(
            'SELECT email FROM users WHERE id = ?'
        );
        try {
            $stmt->bind_param('i', $userId);
            $stmt->execute();
            $account = $stmt
                ->get_result()
                ->fetch_assoc();
        } finally {
            $stmt->close();
        }
        if (
            !$account
            || empty($_SESSION['user_email'])
            || strcasecmp(
                (string)$_SESSION['user_email'],
                (string)$account['email']
            ) !== 0
        ) {
            self::gh_clear_voucher();
            throw new DomainException(
                'Tài khoản không hợp lệ. Hãy đăng nhập lại.'
            );
        }

        // Lấy hạng thành viên

        $member = (new MembershipService($conn))->get(
            $userId
        );

        // Kiểm tra chủ sở hữu voucher

        if (
            !$cart
            || (int)($_SESSION['voucher_user_id'] ?? 0)
                !== $userId
        ) {
            self::gh_clear_voucher();
        }
        $code = (string)($_SESSION['voucher_code'] ?? '');

        // Kiểm tra mã đang sử dụng

        if ($code !== '') {
            $v = (new VoucherService($conn))->validate(
                $userId,
                $code,
                $subtotal,
                $shipping
            );
            if (!$v['ok'] || !$v['voucher']) {
                $message = $v['message'];
                self::gh_clear_voucher();
                $code = '';
            } else {
                $totals = VoucherService::calculate(
                    $subtotal,
                    $shipping,
                    $v['voucher']
                );
            }
        }

        // Danh sách voucher

        if ($cart) {
            foreach (
                (new VoucherService($conn))->listVouchers(
                    $userId,
                    $subtotal,
                    $shipping
                ) as $v
            ) {
                $r = $v['voucher'];
                $list[] = [
                    'code' => $r['ma_code'],
                    'name' => $r['ten_ma'],
                    'tier' => $r['hang_toi_thieu'],
                    'rate' => (int)$r['phan_tram_giam'],
                    'min' => (int)$r['don_toi_thieu'],
                    'cap' => (int)$r['giam_toi_da'],
                    'free' =>
                        (int)$r['mien_phi_van_chuyen'] === 1,

                    'ok' => $v['ok'],
                    'message' => $v['ok']
                        ? ''
                        : $v['message']
                ];
            }
        }
    } else {
        self::gh_clear_voucher();
    }
    $items = [];
    $count = 0;
    foreach ($cart as $id => $item) {
        $items[] = array_merge(
            $item,
            ['id' => (string)$id]
        );
        $count += (int)$item['qty'];
    }
    return array_merge(
        $totals,
        [
            'ok' => true,
            'items' => $items,
            'count' => $count,
            'member' => $member,
            'vouchers' => $list,
            'code' => $code,
            'message' => $message
        ]
    );
}
    public function show(): array
    {
if (session_status() === PHP_SESSION_NONE) session_start();
require_once (dirname(__DIR__, 2)) . '/csrf.php';
require_once (dirname(__DIR__, 2)) . '/voucher_helpers.php';
header('Cache-Control: no-store');

// Kiểm tra đăng nhập và CSRF

$is_logged_in = ($_SESSION['is_login'] ?? false) === true;
$userId = (int)($_SESSION['user_id'] ?? 0);
$token = CsrfService::token();
$isAjax = ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST';
if ($isAjax) {
    if (!CsrfService::check($_POST['csrf_token'] ?? null)) {
        self::gh_json([
            'ok' => false,
            'message' => 'Phiên xác thực hết hạn. Hãy tải lại trang.'
        ], 403);
    }
    $action = $_POST['action'] ?? '';
    if (!in_array(
        $action,
        ['plus', 'minus', 'remove', 'snapshot'],
        true
    )) {
        self::gh_json([
            'ok' => false,
            'message' => 'Thao tác không hợp lệ.'
        ], 400);
    }
}


// Đọc lại giá sản phẩm từ database




// Lấy trạng thái giỏ hàng và mã giảm giá




// Trạng thái mặc định

$state = [
    'ok' => false,
    'member' => null,
    'code' => '',
    'vouchers' => [],
    'message' => '',
    'subtotal' => 0,
    'discount' => 0,
    'shipping' => 0,
    'shipping_discount' => 0,
    'total' => 0
];


// Xử lý cập nhật giỏ hàng

try {
    mysqli_report(
        MYSQLI_REPORT_ERROR
        | MYSQLI_REPORT_STRICT
    );
    require (dirname(__DIR__, 2)) . '/connect.php';
    if ($isAjax && $action !== 'snapshot') {
        $id = $_POST['id'] ?? '';
        if (
            !is_string($id)
            || !isset($_SESSION['cart'][$id])
        ) {
            self::gh_json([
                'ok' => false,
                'message' => 'Không tìm thấy sản phẩm.'
            ], 404);
        }
        if ($action === 'remove') {
            unset($_SESSION['cart'][$id]);
        } else {
            $qty = (int)(
                $_SESSION['cart'][$id]['qty'] ?? 1
            );
            if (
                $action === 'plus'
                && $qty >= 2147483647
            ) {
                throw new DomainException(
                    'Số lượng vượt giới hạn.'
                );
            }
            $qty += $action === 'plus' ? 1 : -1;
            if ($qty <= 0) {
                unset($_SESSION['cart'][$id]);
            } else {
                $_SESSION['cart'][$id]['qty'] = $qty;
            }
        }
    }

    // Xóa mã nếu giỏ hàng trống

    if (empty($_SESSION['cart'])) {
        self::gh_clear_voucher();
    }
    $state = self::gh_snapshot(
        $conn,
        $is_logged_in,
        $userId
    );
    if ($isAjax) {
        self::gh_json($state);
    }
} catch (Throwable $e) {
    $message = $e instanceof DomainException
        ? $e->getMessage()
        : 'Không thể tải giỏ hàng. Vui lòng thử lại.';
    if (!($e instanceof DomainException)) {
        error_log(
            'TT SHOP giohang: ' . $e->getMessage()
        );
    }
    if ($isAjax) {
        self::gh_json([
            'ok' => false,
            'message' => $message
        ], $e instanceof DomainException ? 422 : 500);
    }
    $state['message'] = $message;
}
$cart = $_SESSION['cart'] ?? [];
        return get_defined_vars();
    }
}
