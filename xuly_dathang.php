<?php
require_once __DIR__ . '/app/bootstrap.php';
function dh_text($v): string {
    return CheckoutController::dh_text($v);
}
function dh_escape($v): string {
    return CheckoutController::dh_escape($v);
}
function dh_money($v): string {
    return CheckoutController::dh_money($v);
}
function dh_redirect(string $url): void {
    CheckoutController::dh_redirect($url);
}
function dh_fail(string $message, string $page = 'phuongthucthanhtoan.php'): void {
    CheckoutController::dh_fail($message, $page);
}
function dh_delivery(array $info): array {
    return CheckoutController::dh_delivery($info);
}
function dh_clear_checkout(): void {
    CheckoutController::dh_clear_checkout();
}
function dh_refresh_cart(mysqli $conn, array $cart): array {
    return CheckoutController::dh_refresh_cart($conn, $cart);
}
function dh_catalog_id(mysqli $conn, int $legacy, string $name): int {
    return CheckoutController::dh_catalog_id($conn, $legacy, $name);
}
extract((new CheckoutController())->placeOrder(), EXTR_OVERWRITE);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Đặt hàng thành công - TT SHOP</title>
    <link rel="stylesheet" href="./style.css?v=10">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        body.tt-success-page {
            margin: 0;
            padding-top: var(--tt-header-height, 140px);
            background: #f3f7f5;
            color: #23352d;
            font-family: Arial, Helvetica, sans-serif;
            min-height: 100vh;
        }
        body.tt-success-page > header {
            position: fixed; top: 0; left: 0;
            width: 100%; z-index: 999999;
        }
        body.tt-success-page > header .sub-menu { transition: none; }
        .tt-success-page .tt-receipt-main {
            display: flex; align-items: center; justify-content: center;
            min-height: calc(100vh - var(--tt-header-height, 140px));
            padding: 36px 20px;
        }
        .tt-success-page .tt-receipt-card {
            width: 100%; max-width: 660px;
            background: #fff; border: 1px solid #dce9e2;
            border-radius: 20px; padding: 36px 42px;
            box-shadow: 0 12px 38px rgba(30, 78, 54, .09);
            text-align: center;
        }
        .tt-success-page .tt-receipt-icon {
            width: 84px; height: 84px; margin: 0 auto 20px;
            border-radius: 50%; background: #e7f7ee;
            color: #15985d; display: grid; place-items: center;
        }
        .tt-success-page .tt-receipt-card h1 {
            font-family: Arial, Helvetica, sans-serif;
            color: #08794f; font-size: 30px; font-weight: 700;
            line-height: 1.3; margin: 0 0 12px;
        }
        .tt-success-page .tt-receipt-intro {
            color: #65766d; font-size: 16px; line-height: 1.65;
            margin: 0 auto 24px;
        }
        .tt-success-page .tt-receipt-details {
            padding: 18px 20px; margin-bottom: 24px;
            background: #f7faf8; border: 1px solid #e2ebe5;
            border-radius: 12px; text-align: left;
        }
        .tt-success-page .tt-receipt-row {
            display: flex; justify-content: space-between; align-items: baseline;
            gap: 12px 20px; padding: 10px 0;
            font-size: 15px; line-height: 1.5;
        }
        .tt-success-page .tt-receipt-row dt { color: #596b61; flex-shrink: 0; }
        .tt-success-page .tt-receipt-row dd {
            margin: 0; text-align: right; min-width: 0; overflow-wrap: anywhere;
            font-weight: 700; color: #273b30;
        }
        .tt-success-page .tt-receipt-row .tt-receipt-code,
        .tt-success-page .tt-receipt-row .tt-receipt-saving { color: #08794f; }
        .tt-success-page .tt-receipt-total {
            border-top: 1px solid #dbe7de; margin-top: 8px; padding-top: 16px;
        }
        .tt-success-page .tt-receipt-total dt { font-size: 17px; font-weight: 700; color: #23352d; }
        .tt-success-page .tt-receipt-total dd { font-size: 25px; color: #008b50; }
        .tt-success-page .tt-receipt-actions {
            display: flex; flex-wrap: wrap; justify-content: center; gap: 12px;
        }
        .tt-success-page .tt-receipt-btn {
            display: inline-flex; justify-content: center; align-items: center;
            min-height: 46px; padding: 12px 24px; border-radius: 8px;
            border: 1px solid #15985d; text-decoration: none;
            font-family: Arial, Helvetica, sans-serif; font-size: 16px;
            font-weight: 700; line-height: 1.4; cursor: pointer;
        }
        .tt-success-page .tt-receipt-primary { color: #fff; background: #15985d; }
        .tt-success-page .tt-receipt-primary:hover { background: #0c794c; border-color: #0c794c; }
        .tt-success-page .tt-receipt-secondary { color: #08794f; background: #fff; }
        .tt-success-page .tt-receipt-secondary:hover { background: #eef8f2; }
        .tt-success-page .tt-receipt-btn:focus-visible { outline: 3px solid #79cfa2; outline-offset: 3px; }
        .tt-success-page .tt-receipt-note { margin-top: 18px; color: #65766d; font-size: 13px; line-height: 1.5; }
        @media (max-width: 600px) {
            .tt-success-page .tt-receipt-main { padding: 24px 12px; }
            .tt-success-page .tt-receipt-card { padding: 26px 18px; border-radius: 14px; }
            .tt-success-page .tt-receipt-card h1 { font-size: 25px; }
            .tt-success-page .tt-receipt-details { padding: 12px; }
            .tt-success-page .tt-receipt-row { gap: 10px; font-size: 14px; }
            .tt-success-page .tt-receipt-row dt { flex-shrink: 1; }
            .tt-success-page .tt-receipt-total dd { font-size: 22px; }
            .tt-success-page .tt-receipt-btn { width: 100%; }
        }
    </style>
</head>
<body class="tt-success-page">
<?php include __DIR__ . '/header.php'; ?>
<main class="tt-receipt-main">
    <section class="tt-receipt-card" aria-labelledby="tt-receipt-heading">
        <div class="tt-receipt-icon" aria-hidden="true">
            <svg width="46" height="46" viewBox="0 0 48 48" fill="none">
                <path d="M11 24L20 33L37 15" stroke="currentColor" stroke-width="5" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
        </div>
        <h1 id="tt-receipt-heading">Đặt hàng thành công!</h1>
        <p class="tt-receipt-intro">Cảm ơn quý khách đã mua sắm tại TT SHOP.<br>Đơn hàng của quý khách đã được ghi nhận.</p>
        <dl class="tt-receipt-details">
            <div class="tt-receipt-row">
                <dt>Mã đơn hàng</dt>
                <dd class="tt-receipt-code"><?= dh_escape($ma_don) ?></dd>
            </div>
            <div class="tt-receipt-row">
                <dt>Phương thức thanh toán</dt>
                <dd>COD</dd>
            </div>
            <?php if (!empty($receipt['ma_giam_gia'])): ?>
                <div class="tt-receipt-row">
                    <dt>Mã giảm giá</dt>
                    <dd class="tt-receipt-code"><?= dh_escape($receipt['ma_giam_gia']) ?></dd>
                </div>
                <div class="tt-receipt-row">
                    <dt>Giảm giá sản phẩm</dt>
                    <dd class="tt-receipt-saving">−<?= dh_money($receipt['tien_giam_gia']) ?></dd>
                </div>
            <?php endif; ?>
            <div class="tt-receipt-row tt-receipt-total">
                <dt>Tổng thanh toán</dt>
                <dd><?= dh_money($receipt['tong_cong']) ?></dd>
            </div>
        </dl>
        <div class="tt-receipt-actions">
            <a class="tt-receipt-btn tt-receipt-secondary" href="index.php">Tiếp tục mua sắm</a>
            <a class="tt-receipt-btn tt-receipt-primary" href="taikhoan.php">Đồng ý</a>
        </div>
        <p class="tt-receipt-note">Quý khách có thể theo dõi trạng thái đơn hàng trong tài khoản.</p>
    </section>
</main>

<script>
(function () {
    const header = document.querySelector('body > header');
    if (!header) return;
    function updateHeaderHeight() {
        document.body.style.setProperty('--tt-header-height', header.offsetHeight + 'px');
    }
    updateHeaderHeight();
    window.addEventListener('resize', updateHeaderHeight);
    window.addEventListener('load', updateHeaderHeight);
    if ('ResizeObserver' in window) {
        new ResizeObserver(updateHeaderHeight).observe(header);
    }
})();
</script>
</body>
</html>
