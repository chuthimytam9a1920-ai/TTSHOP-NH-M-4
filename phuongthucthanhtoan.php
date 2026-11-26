<?php
require_once __DIR__ . '/app/bootstrap.php';
function checkout_escape($value): string {
    return CheckoutController::checkout_escape($value);
}
function checkout_money($value): string {
    return CheckoutController::checkout_money($value);
}
function pt_text($v): string {
    return CheckoutController::pt_text($v);
}
function pt_clear_voucher(): void {
    CheckoutController::pt_clear_voucher();
}
function pt_redirect(string $page): void {
    CheckoutController::pt_redirect($page);
}
function pt_refresh_cart(mysqli $conn): int {
    return CheckoutController::pt_refresh_cart($conn);
}
extract((new CheckoutController())->paymentMethod(), EXTR_OVERWRITE);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Phương thức thanh toán - TT SHOP</title>
    <link rel="stylesheet" href="./style.css?v=10">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
          * { box-sizing: border-box; }
        body { margin: 0; padding-top: var(--checkout-header-height, 140px); font-family: Arial, Helvetica, sans-serif; font-weight: 400; color: #333; background: #fff; }
        /* Header dùng chung, cố định khi cuộn trang. */
        body > header { position: fixed; top: 0; left: 0; width: 100%; z-index: 999999; }
        body > header .sub-menu { transition: none; }
        .checkout-page input, .checkout-page button { font-family: Arial, Helvetica, sans-serif; }
        .checkout-page { display: grid; grid-template-columns: minmax(0,58fr) minmax(0,42fr); min-height: calc(100vh - var(--checkout-header-height, 140px)); }
        .checkout-left { padding: 55px 70px; border-right: 1px solid #ddd; min-width: 0; }
        .checkout-right { padding: 45px 60px; background: #fafafa; min-width: 0; }
        .breadcrumb { display: flex; flex-wrap: wrap; align-items: center; gap: 12px; font-size: 15px; margin-bottom: 45px; }
        .step-link { color: #333; text-decoration: none; }
        .step-link:hover { color: #15985d; }
        .step-active { color: #111; font-weight: 700; }
        .arrow { color: #999; }
        .checkout-page h2 { font-size: 24px; font-weight: 700; line-height: 1.4; margin: 28px 0 12px; }
        .shipping-box, .payment-box { border: 1px solid #ddd; border-radius: 8px; overflow: hidden; margin-bottom: 25px; }
        .shipping-row, .payment-row { display: flex; align-items: center; gap: 15px; min-height: 72px; padding: 16px 22px; }
        .check-square { width: 18px; height: 18px; background: #15985d; display: grid; place-items: center; color: white; }
        .payment-row { background: #f0fff8; }
        .payment-row label { display: flex; align-items: center; gap: 15px; width: 100%; cursor: pointer; font-size: 17px; }
        .payment-row input { width: 18px; height: 18px; accent-color: #15985d; }
        .pay-icon { font-size: 28px; color: #15985d; }
        .ship-price { margin-left: auto; font-weight: 700; }
        .checkout-action { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 15px; margin-top: 25px; }
        .back-cart { color: #333; text-decoration: none; }
        .complete-btn { background: #15985d; color: #fff; border: none; padding: 22px 38px; border-radius: 7px; font-size: 20px; font-weight: 700; cursor: pointer; }
        .complete-btn:hover { background: #0d7d4b; }
        .complete-btn:disabled { opacity: .65; cursor: wait; }
        .error-box { color: #a12020; background: #fff0f0; padding: 12px; border-radius: 6px; }
        .delivery-info { border: 1px solid #ddd; border-radius: 8px; padding: 15px 20px; overflow-wrap: anywhere; line-height: 1.6; }
        .delivery-info a { color: #15985d; }
        .checkout-product-item { display: grid; grid-template-columns: 85px minmax(0,1fr) auto; gap: 15px; align-items: center; padding-bottom: 18px; margin-bottom: 18px; border-bottom: 1px solid #e5e5e5; }
        .product-img { position: relative; }
        .product-img img { width: 78px; height: 78px; object-fit: contain; border: 1px solid #ddd; }
        .qty-badge { position: absolute; top: -10px; right: 0; background: #999; color: #fff; min-width: 25px; height: 25px; padding: 4px; border-radius: 2px; text-align: center; font-weight: bold; }
        .product-name, .product-price { font-size: 16px; font-weight: 700; line-height: 1.35; }
        .product-price { text-align: right; white-space: nowrap; }
        .coupon-box { display: flex; gap: 15px; border: 1px dashed #1d61ff; padding: 18px; margin: 25px 0 15px; }
        .coupon-box input { flex: 1; min-width: 0; height: 56px; border: 1px solid #9bbcff; padding: 0 15px; font-size: 18px; }
        .coupon-box button { padding: 0 20px; background: #15985d; color: white; border: none; border-radius: 7px; font-size: 18px; font-weight: bold; }
        .coupon-box button:disabled { opacity: .6; cursor: not-allowed; }
        .coupon-note { color: #777; font-size: 14px; }
        .voucher-summary { border: 1px dashed #a4cbb5; border-radius: 8px; padding: 15px; margin: 24px 0 15px; line-height: 1.6; }
        .voucher-summary a { color: #15985d; font-weight: bold; }
        .member-box { background: #f1fbf6; border: 1px solid #b9dfca; border-radius: 8px; padding: 14px; color: #087e49; font-family: Arial, Helvetica, sans-serif; font-size: 16px; font-weight: 400; line-height: 1.6; }
        .saving { color: #15985d; }
        .warning-box { color: #805b12; background: #fff5da; padding: 12px; border-radius: 6px; }
        .checkout-page .loyal-title { font-family: Arial, Helvetica, sans-serif; font-size: 22px; font-weight: 700; line-height: 1.4; padding-top: 28px; margin-top: 25px; margin-bottom: 12px; border-top: 1px solid #ddd; color: #333; }
        .total-row { display: flex; justify-content: space-between; margin: 18px 0; font-size: 17px; gap: 12px; }
        .grand-total { border-top: 1px solid #ddd; padding-top: 25px; margin-top: 25px; display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 10px; font-size: 20px; }
        .grand-total strong { font-size: 30px; }
        .vnd { color: #999; font-size: 15px; margin-right: 8px; }
        @media (max-width: 900px) {
            .checkout-page { grid-template-columns: 1fr; }
            .checkout-left, .checkout-right { padding: 30px 20px; }
            .complete-btn { width: 100%; }
            .checkout-product-item { gap: 10px; }
                   }
    </style>
</head>
<body>
<?php include __DIR__ . '/header.php'; ?>
<script>
    // Cập nhật khoảng cách theo chiều cao thực tế của header.
    (function () {
        const header = document.querySelector('body > header');
        if (!header) return;
        function updateHeaderHeight() {
            document.body.style.setProperty('--checkout-header-height', header.offsetHeight + 'px');
        }
        updateHeaderHeight();
        window.addEventListener('resize', updateHeaderHeight);
        if ('ResizeObserver' in window) {
            const observer = new ResizeObserver(updateHeaderHeight);
            observer.observe(header);
        }
    })();
</script>
<div class="checkout-page">
    <div class="checkout-left">
        <div class="breadcrumb">
            <a href="giohang.php" class="step-link">Giỏ hàng</a>
            <span class="arrow">›</span>
            <a href="thanhtoan.php" class="step-link">Thông tin giao hàng</a>
            <span class="arrow">›</span>
            <span class="step-active">Phương thức thanh toán</span>
        </div>
        <?php if ($checkoutError !== ''): ?>
            <p class="error-box" role="alert"><?= checkout_escape($checkoutError) ?></p>
        <?php endif; ?>

        <?php if ($voucherNotice !== ''): ?>
            <p class="warning-box" role="status"><?= checkout_escape($voucherNotice) ?></p>
        <?php endif; ?>
        <h2>Thông tin giao hàng</h2>
        <div class="delivery-info">
            <strong><?= checkout_escape($checkout['ho_ten']) ?></strong><br>
            <?= checkout_escape($checkout['sdt']) ?><br>
            <?= checkout_escape($checkout['dia_chi']) ?><br>
            <a href="thanhtoan.php">Thay đổi thông tin</a>
        </div>

        <form id="paymentForm" action="xuly_dathang.php" method="POST">
            <input type="hidden" name="csrf_token" value="<?= checkout_escape($token) ?>">
            <input type="hidden" name="review_id" value="<?= checkout_escape($reviewId) ?>">
            <input type="hidden" name="review_code" value="<?= checkout_escape($code) ?>">
            <input type="hidden" name="review_total" value="<?= (int)$totals['total'] ?>">
            <h2>Phương thức vận chuyển</h2>
            <div class="shipping-box">
                <div class="shipping-row">
                    <span class="check-square">✓</span>
                    <span>Giao hàng tận nơi</span>
                    <span class="ship-price"><?= !$ready ? "—" : ($totals['shipping'] === 0 ? "Miễn phí" : checkout_money($totals['shipping'])) ?></span>
                </div>
            </div>
            <h2>Phương thức thanh toán</h2>
            <div class="payment-box">
                <div class="payment-row">
                    <label>
                        <input type="radio" name="phuong_thuc" value="COD" checked required>
                        <i class="fas fa-money-bill-wave pay-icon" aria-hidden="true"></i>
                        Thanh toán khi giao hàng (COD)
                    </label>
                </div>
                <div class="payment-row" style="border-top:1px solid #ddd;flex-direction:column;align-items:flex-start;gap:6px">
                    <label>
                        <input type="radio" name="phuong_thuc" value="PAYOS" <?= !payos_ready() || (int)$totals['total']<=0 ? 'disabled' : '' ?>>
                        <i class="fas fa-qrcode pay-icon" aria-hidden="true"></i>
                        Chuyển khoản ngân hàng (VietQR / payOS)
                    </label>
                    <small style="display:block;padding:0 0 0 33px;color:#52685c"><?= payos_ready() ? 'QR tự điền số tiền và nội dung. Đơn hàng được xác nhận khi ngân hàng ghi nhận tiền.' : 'Kênh thanh toán QR chưa được cấu hình.' ?></small>
                </div>
            </div>
            <div class="checkout-action">
                <a href="giohang.php" class="back-cart">Giỏ hàng</a>
                <button type="submit" id="completeBtn" class="complete-btn" <?= !$ready ? 'disabled' : '' ?>>Hoàn tất đơn hàng</button>
            </div>
        </form>
    </div>
    <div class="checkout-right">
        <?php foreach ($cart as $item):
            $name = $item['name'] ?? '';
            $img = $item['img'] ?? '';
            $price = (int)($item['price'] ?? 0);
            $qty = max(1, (int)($item['qty'] ?? 1));
            $thanhTien = $price * $qty;
        ?>
            <div class="checkout-product-item">
                <div class="product-img">
                    <img src="<?= checkout_escape($img) ?>" alt="<?= checkout_escape($name) ?>">
                    <span class="qty-badge"><?= $qty ?></span>
                </div>
                <div class="product-name"><?= checkout_escape($name) ?></div>
                <div class="product-price"><?= checkout_money($thanhTien) ?></div>
            </div>
        <?php endforeach; ?>
        <div class="voucher-summary">
            <strong><i class="fas fa-ticket" aria-hidden="true"></i> Mã giảm giá</strong><br>
            <?php if ($ready && $code !== ''): ?>
                <span class="saving"><?= checkout_escape($code) ?> — Đã áp dụng</span><br>
            <?php else: ?>
                <span>Chưa áp dụng mã giảm giá.</span><br>
            <?php endif; ?>
            <a href="thanhtoan.php">Chọn, thay đổi hoặc gỡ mã giảm giá</a>
        </div>
        <div class="loyal-title">Chương trình khách hàng thân thiết</div>
        <?php if ($member !== null): ?>
        <div class="member-box">
            <strong><i class="fas fa-award" aria-hidden="true"></i> Hạng <?= checkout_escape($member['tier']) ?></strong><br>
            <?= (int)$member['orders'] ?> đơn đã giao · Chi tiêu <?= checkout_money($member['spend']) ?>
        </div>
        <?php endif; ?>
        <div class="total-row"><span>Tạm tính</span><strong><?= $ready ? checkout_money($totals['subtotal']) : '—' ?></strong></div>
        <div class="total-row saving"><span>Giảm giá thành viên</span><strong><?= $ready ? '−'.checkout_money($totals['discount']) : '—' ?></strong></div>
        <div class="total-row"><span>Phí vận chuyển</span><strong><?= !$ready ? '—' : ($totals['shipping'] === 0 ? 'Miễn phí' : checkout_money($totals['shipping'])) ?></strong></div>
        <div class="total-row saving"><span>Tiết kiệm</span><strong><?= $ready ? checkout_money($totals['discount'] + $totals['shipping_discount']) : '—' ?></strong></div>
        <div class="grand-total">
            <span>Tổng cộng</span>
            <div><span class="vnd">VND</span><strong><?= $ready ? checkout_money($totals['total']) : '—' ?></strong></div>
        </div>
    </div>
</div>
<script>
    const ready = <?= $ready ? 'true' : 'false' ?>;
    const paymentForm = document.getElementById('paymentForm');
    const completeBtn = document.getElementById('completeBtn');
    paymentForm.addEventListener('submit', function (event) {
        if (!ready || completeBtn.disabled) {
            event.preventDefault();
            return;
        }
        completeBtn.disabled = true;
        completeBtn.textContent = 'Đang xử lý đơn hàng...';
    });
    window.addEventListener('pageshow', function (event) {
        if (event.persisted) { window.location.reload(); return; }
        completeBtn.disabled = !ready;
        completeBtn.textContent = 'Hoàn tất đơn hàng';
    });
</script>
</body>
</html>
