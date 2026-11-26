<?php
final class CartController
{
    public function add()
    {

session_start();

include dirname(__DIR__, 2) . '/connect.php';

$isAjax = ($_SERVER['REQUEST_METHOD'] === 'POST');

if ($isAjax) {
    header('Content-Type: application/json; charset=UTF-8');
}

$input = $isAjax ? $_POST : $_GET;
$rawId = $input['id'] ?? '';
$qty = is_scalar($input['qty'] ?? 1) ? (int)($input['qty'] ?? 1) : 1;
$buyNow = ($input['buy_now'] ?? '0') === '1' ? 1 : 0;

// ID số thuộc products; ID cat_N thuộc category_products.
// Chỉ chọn tên bảng từ hai giá trị cố định, không lấy trực tiếp từ URL.
$isCategory = false;
$productId = 0;
if (is_string($rawId) && preg_match('/^(cat_)?([1-9][0-9]{0,9})$/D', $rawId, $matches)) {
    if ((float)$matches[2] <= 2147483647) {
        $isCategory = $matches[1] === 'cat_';
        $productId = (int)$matches[2];
    }
}
$product = null;
if ($productId > 0) {
    $product = (new ProductRepository($conn))->findForCart($productId, $isCategory);
}

if (!$product) {
    http_response_code(404);
    if ($isAjax) {
        echo json_encode(['status' => 'error', 'message' => 'Không tìm thấy sản phẩm.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    die('Không tìm thấy sản phẩm.');
}

// Giữ nguyên khóa cat_N trong session để không trùng với products.id.
// Tên, giá và ảnh được lấy từ database, không tin dữ liệu POST.
$id = $isCategory ? 'cat_' . $productId : (string)$productId;
$name = $product['name'];
$price = (int)$product['price'];
$old_price = (int)$product['old_price'];
$img = $isCategory ? (string)$product['image'] : 'images/' . $product['image'];

if ($qty < 1) {
    $qty = 1;
}

if ($id == '' || $name == '' || $price <= 0) {
    if ($isAjax) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Dữ liệu sản phẩm không hợp lệ'
        ]);
        exit;
    }

    die("Dữ liệu sản phẩm không hợp lệ.");
}


//////THÊM MỚI SẢN PHẨM VÀO GIỎ HÀNG


if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}
//Cập nhật và đồng bộ số lượng sản phẩm trong giỏ hàng nếu sản phẩm đã tồn tại, nếu chưa thì thêm mới vào giỏ hàng
if (isset($_SESSION['cart'][$id])) {
    $_SESSION['cart'][$id]['qty'] += $qty;
    $_SESSION['cart'][$id]['name'] = $name;
    $_SESSION['cart'][$id]['price'] = $price;
    $_SESSION['cart'][$id]['old_price'] = $old_price;
    $_SESSION['cart'][$id]['img'] = $img;
} else {
    //nếu chưa thì thêm mới vào giỏ hàng
    $_SESSION['cart'][$id] = [
        'id' => $id,
        'name' => $name,
        'price' => $price,
        'old_price' => $old_price,
        'img' => $img,
        'qty' => $qty
    ];
}

$cartCount = 0;
$cartTotal = 0;

foreach ($_SESSION['cart'] as $item) {
    $cartCount += (int)$item['qty'];
    $cartTotal += (int)$item['price'] * (int)$item['qty'];
}

if ($isAjax) {
    echo json_encode([
        'status' => 'success',
        'message' => 'Đã thêm vào giỏ hàng thành công!',
        'cart_count' => $cartCount,
        'cart_total' => number_format($cartTotal, 0, ',', '.') . 'đ',
        'redirect' => $buyNow == 1 ? 'giohang.php' : ''
    ]);
    exit;
}

if ($buyNow == 1) {
    header("Location: giohang.php");
    exit;
}

header("Location: " . ($_SERVER['HTTP_REFERER'] ?? 'index.php'));
exit;

    }

    public function legacyAdd()
    {

// Khởi chạy hoặc khôi phục phiên làm việc (session) để lưu trữ giỏ hàng
session_start();

// Lấy các thông tin của sản phẩm được gửi lên từ nút bấm/form
$id = $_POST['id'];
$name = $_POST['name'];
$price = $_POST['price'];
$img = $_POST['img'];
$qty = $_POST['qty'];

// Kiểm tra nếu trong hệ thống chưa từng có giỏ hàng, tiến hành khởi tạo một mảng trống ['cart']
if(!isset($_SESSION['cart'])){
    $_SESSION['cart'] = [];
}

// TRƯỜNG HỢP 1: Nếu sản phẩm này ĐÃ TỒN TẠI trong giỏ hàng rồi
if(isset($_SESSION['cart'][$id])){
    // Tiến hành cộng dồn số lượng mua mới vào số lượng cũ đang có
    $_SESSION['cart'][$id]['qty'] += $qty;

// TRƯỜNG HỢP 2: Nếu đây là sản phẩm MỚI (chưa có trong giỏ hàng)
}else{
    // Tạo mới một phần tử trong mảng cart với key chính là ID sản phẩm và lưu đầy đủ thông tin của nó
    $_SESSION['cart'][$id] = [
        'id' => $id,
        'name' => $name,
        'price' => $price,
        'img' => $img,
        'qty' => $qty
    ];
}

// Sau khi cập nhật giỏ hàng thành công, tự động chuyển hướng người dùng sang trang giỏ hàng (giohang.php)
header("Location: giohang.php");
exit();

    }

    public function update()
    {

// Kiểm tra và khởi chạy session nếu chưa được bật
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Khai báo định dạng phản hồi trả về là JSON (dùng cho Ajax Fetch ở font-end)
header('Content-Type: application/json; charset=utf-8');

// Nhận ID sản phẩm và hành động (plus/minus) gửi lên từ giao diện
$id = $_POST['id'] ?? '';
$action = $_POST['action'] ?? '';

$cartService = new CartService($_SESSION);
// Kiểm tra dữ liệu: Nếu thiếu ID hoặc sản phẩm không tồn tại trong giỏ hàng thì dừng lại và trả về lỗi
if (!$cartService->contains($id)) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Không tìm thấy sản phẩm trong giỏ hàng.'
    ]);
    exit();
}
//Cập nhật số lượng sản phẩm
echo json_encode($cartService->changeQuantity($id, $action));
exit();

    }
//Xóa sản phẩm khỏi giỏ hàng
    public function remove()
    {

// Kiểm tra và khởi chạy session nếu chưa được bật
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Lấy ID sản phẩm cần xóa được gửi qua đường dẫn (ví dụ: remove_cart.php?id=123)
$id = $_GET['id'] ?? '';

// Nếu có ID và sản phẩm đó thực sự tồn tại trong giỏ hàng, tiến hành xóa bỏ nó (unset)
(new CartService($_SESSION))->remove($id);

// Sau khi xóa xong, lập tức chuyển hướng người dùng quay trở lại trang giỏ hàng (giohang.php)
header("Location: giohang.php");
exit();

    }

    private static function voucher_json(int $http, array $data): void
{
    http_response_code($http);

    echo json_encode(
        $data,
        JSON_UNESCAPED_UNICODE
        | JSON_INVALID_UTF8_SUBSTITUTE
    );

    exit;
}

    private static function voucher_clear_session(): void
{
    unset(
        $_SESSION['voucher_code'],
        $_SESSION['voucher_user_id']
    );
}

    private static function voucher_refresh_cart(mysqli $conn): int
{
    $cart = $_SESSION['cart'] ?? [];

    if (!is_array($cart)) {
        throw new DomainException(
            'Giỏ hàng không hợp lệ.'
        );
    }

    $subtotal = 0;

    $updated = [];

    foreach ($cart as $key => $item) {

        if (!is_array($item)) {
            throw new DomainException(
                'Dữ liệu giỏ hàng không hợp lệ.'
            );
        }

        $id = (string)$key;

        // Kiểm tra số lượng sản phẩm

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

        // Xác định bảng chứa sản phẩm

        if (
            preg_match(
                '/^cat_([1-9][0-9]{0,9})$/D',
                $id,
                $m
            )
        ) {

            $table = 'category_products';

            $productId = (int)$m[1];

        } elseif (
            preg_match(
                '/^[1-9][0-9]{0,9}$/D',
                $id
            )
        ) {

            $table = 'products';

            $productId = (int)$id;

        } else {

            throw new DomainException(
                'Mã sản phẩm trong giỏ hàng không hợp lệ.'
            );
        }

        if ($productId > 2147483647) {
            throw new DomainException(
                'Mã sản phẩm không hợp lệ.'
            );
        }

        // Lấy thông tin sản phẩm thực tế

        $stmt = $conn->prepare(
            "SELECT
                name,
                price,
                old_price,
                image

            FROM `$table`

            WHERE id = ?"
        );

        try {

            $stmt->bind_param('i', $productId);

            $stmt->execute();

            $product = $stmt
                ->get_result()
                ->fetch_assoc();

        } finally {

            $stmt->close();
        }

        // Kiểm tra sản phẩm còn tồn tại

        if (
            !$product
            || (int)$product['price'] <= 0
        ) {

            throw new DomainException(
                'Có sản phẩm không còn bán hoặc giá không hợp lệ. Vui lòng cập nhật giỏ hàng.'
            );
        }

        $price = (int)$product['price'];

        // Kiểm tra giới hạn giá trị đơn hàng

        if (
            $price > intdiv(
                2147483647 - 30000 - $subtotal,
                $qty
            )
        ) {

            throw new DomainException(
                'Tổng giá trị đơn hàng vượt giới hạn.'
            );
        }

        $subtotal += $price * $qty;

        // Cập nhật thông tin giỏ hàng từ database

        $item['id'] = $id;

        $item['qty'] = $qty;

        $item['name'] = $product['name'];

        $item['price'] = $price;

        $item['old_price'] =
            (int)$product['old_price'];

        $item['img'] = $table === 'products'
            ? 'images/' . $product['image']
            : $product['image'];

        $updated[$key] = $item;
    }

    $_SESSION['cart'] = $updated;

    return $subtotal;
}

    private static function voucher_response(
    array $result,
    ?string $code,
    int $http = 200
): void
{
    self::voucher_json($http, [

        'status' => $result['ok']
            ? 'success'
            : 'error',

        'ok' => $result['ok'],

        'message' => $result['message'],

        'applied' => $code !== null,

        'code' => $code,

        'tier' => $result['tier'],

        'subtotal' => $result['subtotal'],

        'discount' => $result['discount'],

        'shipping_discount' =>
            $result['shipping_discount'],

        'shipping' => $result['shipping'],

        'total' => $result['total']
    ]);
}

    public function applyVoucher()
    {

// ap_dung_magiamgia.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once (dirname(__DIR__, 2)) . '/csrf.php';
require_once (dirname(__DIR__, 2)) . '/voucher_helpers.php';


// 1. Hàm trả kết quả JSON




// 2. Xóa mã giảm giá khỏi SESSION




// 3. Lấy lại giá sản phẩm từ database




// 4. Chuẩn hóa dữ liệu trả về AJAX




// 5. Kiểm tra phương thức gửi yêu cầu

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {

    header('Allow: POST');

    self::voucher_json(405, [

        'status' => 'error',

        'ok' => false,

        'message' => 'Phương thức yêu cầu không hợp lệ.'
    ]);
}


// 6. Kiểm tra đăng nhập

$userId = (int)($_SESSION['user_id'] ?? 0);

if (
    ($_SESSION['is_login'] ?? false) !== true
    || $userId <= 0
) {

    self::voucher_json(401, [

        'status' => 'error',

        'ok' => false,

        'message' =>
            'Bạn cần đăng nhập để sử dụng mã giảm giá.'
    ]);
}


// 7. Kiểm tra bảo mật CSRF

if (!CsrfService::check($_POST['csrf_token'] ?? null)) {

    self::voucher_json(403, [

        'status' => 'error',

        'ok' => false,

        'message' =>
            'Phiên xác thực không hợp lệ. Vui lòng tải lại trang.'
    ]);
}


// 8. Kiểm tra hành động của khách hàng

$action = $_POST['action'] ?? '';

if (
    !in_array(
        $action,
        ['apply', 'remove', 'status'],
        true
    )
) {

    self::voucher_json(400, [

        'status' => 'error',

        'ok' => false,

        'message' => 'Thao tác không hợp lệ.'
    ]);
}


// 9. Kiểm tra dữ liệu mã giảm giá

$inputCode = $_POST['code'] ?? '';

if (
    $action === 'apply'
    && (
        !is_string($inputCode)
        || strlen($inputCode) > 100
    )
) {

    self::voucher_json(400, [

        'status' => 'error',

        'ok' => false,

        'message' => 'Mã giảm giá không hợp lệ.'
    ]);
}


// 10. Bắt đầu xử lý mã giảm giá

try {

    mysqli_report(
        MYSQLI_REPORT_ERROR
        | MYSQLI_REPORT_STRICT
    );

    // Đảm bảo lỗi kết nối trả về JSON

    ob_start(
        static function (string $body): string {

            return json_encode([

                'status' => 'error',

                'ok' => false,

                'message' =>
                    'Không thể kết nối database.'

            ], JSON_UNESCAPED_UNICODE);
        }
    );

    try {

        require (dirname(__DIR__, 2)) . '/connect.php';

    } finally {

        ob_end_clean();
    }


    // 11. Kiểm tra tài khoản trong database

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

    $email = $_SESSION['user_email'] ?? '';

    if (
        !$account
        || !is_string($email)
        || $email === ''
        || strcasecmp(
            $email,
            $account['email']
        ) !== 0
    ) {

        self::voucher_clear_session();

        self::voucher_json(401, [

            'status' => 'error',

            'ok' => false,

            'message' =>
                'Tài khoản không hợp lệ. Vui lòng đăng nhập lại.'
        ]);
    }


    // 12. Không dùng mã của tài khoản khác

    if (
        (int)($_SESSION['voucher_user_id'] ?? 0)
        !== $userId
    ) {

        self::voucher_clear_session();
    }


    // 13. Xóa mã nếu khách hàng chọn gỡ mã

    if ($action === 'remove') {

        self::voucher_clear_session();
    }


    // 14. Tính lại tổng tiền giỏ hàng

    $subtotal = self::voucher_refresh_cart($conn);

    $shipping = empty($_SESSION['cart'])
        ? 0
        : 30000;


    // 15. Kiểm tra giỏ hàng trống

    if (empty($_SESSION['cart'])) {

        self::voucher_clear_session();

        $result = (new VoucherService($conn))->validate(

            $userId,

            '',

            0,

            0
        );

        $result['ok'] = $action !== 'apply';

        $result['message'] = 'Giỏ hàng đang trống.';

        self::voucher_response(

            $result,

            null,

            $result['ok'] ? 200 : 422
        );
    }


    // 16. Xử lý gỡ mã giảm giá

    if ($action === 'remove') {

        $result = (new VoucherService($conn))->validate(

            $userId,

            '',

            $subtotal,

            $shipping
        );

        $result['message'] = 'Đã gỡ mã giảm giá.';

        self::voucher_response($result, null);
    }


    // 17. Lấy mã cần kiểm tra

    $code = $action === 'apply'

        ? VoucherService::normalize($inputCode)

        : (string)($_SESSION['voucher_code'] ?? '');


    // 18. Kiểm tra điều kiện sử dụng voucher

    $result = (new VoucherService($conn))->validate(

        $userId,

        $code,

        $subtotal,

        $shipping
    );


    // Kiểm tra trường hợp không nhập mã

    if (
        $action === 'apply'
        && $code === ''
    ) {

        $result['ok'] = false;

        $result['message'] =
            'Vui lòng nhập mã giảm giá.';
    }


    // 19. Nếu mã không hợp lệ

    if (!$result['ok']) {

        self::voucher_clear_session();

        self::voucher_response(

            $result,

            null,

            422
        );
    }


    // 20. Nếu mã giảm giá hợp lệ

    if ($result['voucher'] !== null) {

        $code = $result['voucher']['ma_code'];

        // Lưu mã và chủ tài khoản vào SESSION
        $_SESSION['voucher_code'] = $code;
        $_SESSION['voucher_user_id'] = $userId;

        self::voucher_response(

            $result,

            $code
        );
    }


    // 21. Không sử dụng mã giảm giá

    self::voucher_clear_session();

    self::voucher_response(

        $result,

        null
    );


} catch (DomainException $e) {

    self::voucher_clear_session();

    self::voucher_json(422, [

        'status' => 'error',

        'ok' => false,

        'applied' => false,

        'code' => null,

        'message' => $e->getMessage()
    ]);


} catch (Throwable $e) {

    // Ghi lỗi hệ thống vào nhật ký PHP

    error_log(
        'TT SHOP voucher: ' . $e->getMessage()
    );

    self::voucher_json(500, [

        'status' => 'error',

        'ok' => false,

        'message' =>
            'Không thể xử lý mã giảm giá. Vui lòng thử lại sau.'
    ]);
}

    }
}
