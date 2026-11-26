<?php
require_once __DIR__ . '/app/bootstrap.php';
//Chuyển giá trị sang chuỗi và mã hóa các ký tự HTML đặc biệt
function gh_escape($v) {
    return CartPageController::gh_escape($v);
}
//Chuyển đổi số thành định dạng tiền tệ Việt Nam
function gh_money($v) {
    return CartPageController::gh_money($v);
}
//Xóa mã giảm giá khỏi giỏ hàng
function gh_clear_voucher() {
    CartPageController::gh_clear_voucher();
}
//Thiết lập HTTP status code và header
function gh_json($data, $status = 200) {
    CartPageController::gh_json($data, $status);
}
//Kiểm tra tính hợp lệ của số lượng và mã sản phẩm
function gh_refresh_cart(mysqli $conn): int {
    return CartPageController::gh_refresh_cart($conn);
}
//làm mới giỏ hàng, tính phí vận chuyển, lấy hạng thành viên, và áp dụng mã giảm giá nếu có
function gh_snapshot(
    mysqli $conn,
    bool $logged,
    int $userId
): array {
    return CartPageController::gh_snapshot($conn, $logged, $userId);
}
extract((new CartPageController())->show(), EXTR_OVERWRITE);
?>

<!DOCTYPE html>
<html lang="vi">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1">

    <title>Giỏ hàng - TT SHOP</title>

    <link rel="stylesheet"
          href="./style.css?v=<?= time() ?>">

    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    <link rel="stylesheet"
          href="./stylegiohang.css?v=<?= time() ?>">

    <style>

        .gh-member {
            margin: 24px 0 12px;
            padding: 16px;
            background: #f1fbf6;
            border: 1px solid #b9dfca;
            border-radius: 12px;
            color: #008f4c;
        }

        .gh-vouchers {
            display: grid;
            gap: 10px;
            margin: 14px 0;
        }

        .gh-voucher {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 13px;
            border: 1px dashed #a4cbb5;
            border-radius: 9px;
        }

        .gh-voucher small {
            display: block;
            color: #666;
            margin-top: 5px;
        }

        .gh-voucher button,
        #voucher-remove {
            border: 1px solid #008f4c;
            background: #fff;
            color: #008f4c;
            border-radius: 7px;
            padding: 9px 12px;
            cursor: pointer;
            font-weight: bold;
        }

        .gh-voucher button:disabled,
        .coupon-box button:disabled,
        .checkout-btn:disabled {
            opacity: .5;
            cursor: not-allowed;
        }

        .gh-msg {
            margin: 12px 0;
            min-height: 20px;
            color: #a12020;
        }

        .gh-msg.success {
            color: #008f4c;
        }

        .gh-total-row {
            display: flex;
            justify-content: space-between;
            gap: 10px;
            margin: 12px 0;
        }

        .gh-savings {
            color: #008f4c;
        }

        .gh-breakdown {
            border-bottom: 1px solid #ddd;
            padding-bottom: 14px;
            margin-bottom: 18px;
        }

        .coupon-box {
            flex-wrap: wrap;
        }

        .coupon-box input {
            max-width: 100%;
            box-sizing: border-box;
        }

        .gh-voucher button {
            flex-shrink: 0;
        }

    </style>

</head>

<body>

<?php include 'header.php'; ?>

<div class="cart-wrapper">

    <h1 class="cart-title">GIỎ HÀNG</h1>

    <?php if (!$cart): ?>

        <div class="empty-cart">

            <p>Chưa có sản phẩm nào trong giỏ hàng.</p>

            <a href="index.php">
                ← Tiếp tục mua hàng
            </a>

        </div>

    <?php else: ?>

    <div class="cart-layout">

        <div class="cart-left">

            <!-- DANH SÁCH SẢN PHẨM -->

            <div id="cart-list">

                <?php foreach ($cart as $id => $item):

                    $name = $item['name'] ?? '';
                    $img = $item['img'] ?? '';
                    $price = (int)($item['price'] ?? 0);
                    $old = (int)($item['old_price'] ?? 0);
                    $qty = max(1, (int)($item['qty'] ?? 1));

                    $percent = $old > $price && $old > 0

                        ? (int)floor(
                            ($old - $price) * 100 / $old
                        )

                        : 0;

                ?>

                <div class="cart-item"
                     data-id="<?= gh_escape($id) ?>">

                    <a href="#"
                       class="remove-btn"
                       aria-label="Xóa sản phẩm">
                        ×
                    </a>

                    <img src="<?= gh_escape($img) ?>"
                         alt="<?= gh_escape($name) ?>">

                    <div class="cart-name">

                        <h3><?= gh_escape($name) ?></h3>

                        <p class="stock">✔ Còn hàng</p>

                    </div>

                    <div class="cart-price">
                        <?= gh_money($price) ?>
                    </div>

                    <div class="old-price">

                        <?= $old > $price
                            ? gh_money($old)
                            : '' ?>

                    </div>

                    <div class="discount">

                        <?= $percent > 0
                            ? '-' . $percent . '%'
                            : '' ?>

                    </div>

                    <div class="qty-box"
                         data-id="<?= gh_escape($id) ?>">

                        <button type="button"
                                class="cart-minus">
                            -
                        </button>

                        <span class="cart-qty">
                            <?= $qty ?>
                        </span>

                        <button type="button"
                                class="cart-plus">
                            +
                        </button>

                    </div>

                    <div class="cart-total-item">
                        <?= gh_money($price * $qty) ?>
                    </div>

                </div>

                <?php endforeach; ?>

            </div>


            <!-- CHƯƠNG TRÌNH THÀNH VIÊN -->

            <div class="gh-member">

                <strong>

                    <i class="fas fa-award"></i>

                    Chương trình khách hàng thân thiết

                </strong>

                <p id="member-info"></p>

                <?php if (!$is_logged_in): ?>

                    <a href="dangky.php">
                        Đăng nhập để sử dụng ưu đãi thành viên
                    </a>

                <?php endif; ?>

            </div>


            <!-- NHẬP MÃ GIẢM GIÁ -->

            <div class="coupon-box">

                <input

                    id="voucher-code"

                    type="text"

                    maxlength="50"

                    placeholder="🎫 Nhập mã giảm giá..."

                    <?= !$is_logged_in ? 'disabled' : '' ?>

                >

                <button

                    id="voucher-apply"

                    type="button"

                    <?= !$is_logged_in ? 'disabled' : '' ?>

                >

                    Áp dụng

                </button>

                <button

                    id="voucher-remove"

                    type="button"

                    hidden

                >

                    Gỡ mã

                </button>

            </div>

            <p id="voucher-message"
               class="gh-msg"
               role="status"
               aria-live="polite">

                <?= gh_escape($state['message']) ?>

            </p>


            <!-- DANH SÁCH VOUCHER -->

            <?php if ($is_logged_in): ?>

                <h3>Mã giảm giá thành viên</h3>

                <div id="voucher-list"
                     class="gh-vouchers">
                </div>

            <?php endif; ?>

            <a href="index.php"
               class="continue-buy">

                ← Tiếp tục mua hàng

            </a>

        </div>


        <!-- TÓM TẮT ĐƠN HÀNG -->

        <div class="cart-summary">

            <h2>

                Tổng tiền

                <span class="summary-total">

                    <?= $state['ok']
                        ? gh_money($state['total'])
                        : '—' ?>

                </span>

            </h2>

            <div class="gh-breakdown">

                <div class="gh-total-row">

                    <span>Tạm tính</span>

                    <strong id="gh-subtotal">—</strong>

                </div>

                <div class="gh-total-row gh-savings">

                    <span>Giảm giá thành viên</span>

                    <strong id="gh-discount">—</strong>

                </div>

                <div class="gh-total-row">

                    <span>Phí vận chuyển (dự kiến)</span>

                    <strong id="gh-shipping">—</strong>

                </div>

                <div class="gh-total-row gh-savings">

                    <span>Tiết kiệm</span>

                    <strong id="gh-saving">—</strong>

                </div>

            </div>


            <!-- XUẤT HÓA ĐƠN CÔNG TY -->

            <div class="invoice-check">

                <label style="
                    display:flex;
                    align-items:center;
                    gap:8px;
                    cursor:pointer;
                    margin:0;
                ">

                    <input type="checkbox"
                           id="chk-invoice"
                           style="width:auto;margin:0">

                    Xuất hoá đơn công ty

                </label>

            </div>

            <div id="invoice-fields"
                 style="display:none">

                <label>Tên đơn vị mua hàng:</label>

                <input type="text"
                       name="ten_cong_ty"
                       placeholder="Công Ty Trách Nhiệm Hữu Hạn ABCXYZ">

                <label>Mã số thuế:</label>

                <input type="text"
                       name="ma_so_thue"
                       placeholder="123456789">

                <label>Địa chỉ công ty:</label>

                <input type="text"
                       name="dia_chi_cong_ty"
                       placeholder="123 Nguyễn Huệ, phường 6, Quận 8, Thành phố Hồ Chí Minh">

                <label>Email công ty:</label>

                <input type="email"
                       name="email_cong_ty"
                       placeholder="ctytnhhabcxyz123@gmail.com">

            </div>

            <p class="note-red">

                *Lưu ý: Nhập rõ ràng và đầy đủ thông tin hóa đơn,
                không viết tắt phường/xã, quận/huyện,
                tỉnh/thành phố, tên công ty.

            </p>

            <label>Ghi chú đơn hàng:</label>

            <textarea name="ghi_chu"></textarea>

            <button

                class="checkout-btn"

                type="button"

                <?= !$state['ok'] ? 'disabled' : '' ?>

            >

                Tiến hành đặt hàng

            </button>

        </div>

    </div>

    <?php endif; ?>

</div>

<?php include 'footer.php'; ?>


<script>

document.addEventListener('DOMContentLoaded', () => {

    const logged = <?= $is_logged_in ? 'true' : 'false' ?>;

    const csrf = <?= json_encode($token) ?>;

    let state = <?= json_encode(
        $state,
        JSON_HEX_TAG
        | JSON_HEX_AMP
        | JSON_HEX_APOS
        | JSON_HEX_QUOT
        | JSON_INVALID_UTF8_SUBSTITUTE
    ) ?>;

    let busy = false;

    const $ = id => document.getElementById(id);

    const money = n =>
        Number(n).toLocaleString('vi-VN') + 'đ';

    const checkout =
        document.querySelector('.checkout-btn');

    if (!checkout) return;


    // Hiển thị thông báo

    function msg(text, ok = false) {
        $('voucher-message').textContent = text || '';
        $('voucher-message')
            .classList.toggle('success', ok);
    }

    // Quản lý trạng thái các nút

    function controls() {
        document.querySelectorAll(
            '.cart-plus,.cart-minus'
        ).forEach(b => b.disabled = busy);

        $('voucher-apply').disabled =
            busy || !logged || !state.ok;

        $('voucher-code').disabled =
            busy || !logged || !state.ok;

        $('voucher-remove').disabled =
            busy || !state.ok;

        checkout.disabled =
            busy || !state.ok;

        document.querySelectorAll(
            '#voucher-list button'
        ).forEach(b => {

            b.disabled =
                busy
                || !state.ok
                || b.dataset.allowed !== '1';
        });
    }


    // Gửi dữ liệu AJAX

    async function post(url, action, extra = {}) {

        const body = new URLSearchParams({

            csrf_token: csrf,

            action,

            ...extra

        });

        const res = await fetch(url, {

            method: 'POST',

            credentials: 'same-origin',

            body,

            cache: 'no-store'

        });

        let data;

        try {

            data = await res.json();

        } catch (e) {

            throw new Error(
                'Phản hồi máy chủ không hợp lệ.'
            );
        }

        if (!res.ok && data.ok !== false) {

            throw new Error(
                'Không thể xử lý yêu cầu.'
            );
        }

        return data;
    }


    // Cập nhật toàn bộ giao diện giỏ hàng

    function render(s) {

        state = s;

        if (!s.ok) {

            msg(s.message);

            controls();

            return;
        }

        if (!s.items.length) {

            location.reload();

            return;
        }

        const rows = new Map(

            [...document.querySelectorAll('.cart-item')]

                .map(r => [r.dataset.id, r])

        );

        if (
            s.items.some(
                i => !rows.has(String(i.id))
            )
        ) {

            location.reload();

            return;
        }

        // Cập nhật sản phẩm

        s.items.forEach(i => {

            const r = rows.get(String(i.id));

            rows.delete(String(i.id));

            r.querySelector('img').src = i.img;

            r.querySelector('img').alt = i.name;

            r.querySelector('h3').textContent = i.name;

            r.querySelector('.cart-price')
                .textContent = money(i.price);

            r.querySelector('.old-price')
                .textContent = i.old_price > i.price

                    ? money(i.old_price)

                    : '';

            const pct = i.old_price > i.price

                ? Math.floor(
                    (i.old_price - i.price)
                    * 100
                    / i.old_price
                )

                : 0;

            r.querySelector('.discount')
                .textContent = pct > 0

                    ? '-' + pct + '%'

                    : '';

            r.querySelector('.cart-qty')
                .textContent = i.qty;

            r.querySelector('.cart-total-item')
                .textContent = money(i.price * i.qty);

        });

        rows.forEach(r => r.remove());

        // Cập nhật số lượng trên header

        if ($('cart-count')) {

            $('cart-count').textContent = s.count;
        }

        // Cập nhật tổng tiền

        document.querySelector('.summary-total')
            .textContent = money(s.total);

        $('gh-subtotal').textContent =
            money(s.subtotal);

        $('gh-discount').textContent =
            '−' + money(s.discount);

        $('gh-shipping').textContent =
            s.shipping === 0

                ? 'Miễn phí'

                : money(s.shipping);

        $('gh-saving').textContent =
            money(s.discount + s.shipping_discount);

        // Hiển thị hạng thành viên

        $('member-info').textContent = s.member

            ? 'Hạng ' + s.member.tier

                + ' · ' + s.member.orders

                + ' đơn đã giao · Chi tiêu '

                + money(s.member.spend)

            : 'Bạn chưa đăng nhập.';

        $('voucher-code').value = s.code || '';

        $('voucher-remove').hidden = !s.code;


        // Hiển thị danh sách voucher

        const list = $('voucher-list');

        if (list) {

            list.replaceChildren();

            if (!s.vouchers.length) {

                list.textContent =
                    'Hiện chưa có mã giảm giá.';
            }

            s.vouchers.forEach(v => {

                const row = document.createElement('div');

                row.className = 'gh-voucher';

                const info = document.createElement('div');

                const title =
                    document.createElement('strong');

                title.textContent =

                    v.code

                    + ' — Giảm '

                    + v.rate

                    + '%'

                    + (v.free ? ' + Freeship' : '');

                info.append(title);

                const desc =
                    document.createElement('small');

                desc.textContent =

                    'Hạng '

                    + v.tier

                    + ' · Đơn từ '

                    + money(v.min)

                    + ' · Tối đa '

                    + money(v.cap);

                info.append(desc);

                // Hiển thị lý do không đủ điều kiện

                if (!v.ok) {

                    const reason =
                        document.createElement('small');

                    reason.textContent = v.message;

                    info.append(reason);
                }

                const b =
                    document.createElement('button');

                b.type = 'button';

                b.dataset.allowed =

                    v.ok && s.code !== v.code

                        ? '1'

                        : '0';

                b.textContent =

                    s.code === v.code

                        ? 'Đã áp dụng'

                        : 'Áp dụng';

                b.addEventListener('click', () => {

                    voucher('apply', v.code);

                });

                row.append(info, b);

                list.append(row);

            });
        }

        controls();
    }


    // Ngăn gửi nhiều yêu cầu đồng thời

    async function run(task) {

        if (busy) return;

        busy = true;

        controls();

        try {

            await task();

        } catch (e) {

            state.ok = false;

            msg(
                e.message
                || 'Lỗi kết nối. Vui lòng tải lại trang.'
            );

        } finally {

            busy = false;

            controls();
        }
    }


    // Đọc trạng thái mới nhất từ server

    async function snapshot() {

        const s = await post(
            'giohang.php',
            'snapshot'
        );

        render(s);

        return s;
    }


    // Áp dụng hoặc gỡ mã giảm giá

    function voucher(action, code = '') {

        return run(async () => {

            const result = await post(

                'ap_dung_magiamgia.php',

                action,

                {code}

            );

            const s = await snapshot();

            if (s.ok) {

                msg(

                    result.ok === true && s.message

                        ? s.message

                        : result.message,

                    result.ok === true && !s.message

                );
            }

        });
    }


    // Nút áp dụng mã

    $('voucher-apply').addEventListener(
        'click',
        () => {

            voucher(
                'apply',
                $('voucher-code').value
            );
        }
    );


    // Nút gỡ mã

    $('voucher-remove').addEventListener(
        'click',
        () => voucher('remove')
    );


    // Cho phép nhấn Enter để áp dụng

    $('voucher-code').addEventListener(
        'keydown',
        e => {

            if (e.key === 'Enter') {

                e.preventDefault();

                voucher('apply', e.target.value);
            }
        }
    );


    // Tăng giảm và xóa sản phẩm

    $('cart-list').addEventListener('click', e => {

        const btn = e.target.closest(
            '.cart-plus,.cart-minus,.remove-btn'
        );

        if (!btn) return;

        e.preventDefault();

        const row = btn.closest('.cart-item');

        const action = btn.classList.contains('remove-btn')

            ? 'remove'

            : btn.classList.contains('cart-plus')

                ? 'plus'

                : 'minus';

        if (

            action === 'remove'

            && !confirm(
                'Bạn có chắc chắn muốn xoá sản phẩm này khỏi giỏ hàng không?'
            )

        ) return;

        run(async () => {

            const s = await post(

                'giohang.php',

                action,

                {id: row.dataset.id}

            );

            render(s);

            msg(s.message);

            if (!s.ok) {

                alert(s.message);

                location.reload();
            }

        });

    });


    // Ẩn hiện thông tin hóa đơn

    $('chk-invoice').addEventListener(
        'change',
        function () {

            $('invoice-fields').style.display =

                this.checked

                    ? 'block'

                    : 'none';

            if (!this.checked) {

                $('invoice-fields')

                    .querySelectorAll('input')

                    .forEach(i => i.value = '');
            }
        }
    );


    // Tiến hành đặt hàng

    checkout.addEventListener('click', () => {

        if (!logged) {

            alert(
                'Bạn cần đăng nhập hoặc đăng ký tài khoản để đặt hàng.'
            );

            location.href = 'dangky.php';

            return;
        }

        // Kiểm tra lần cuối trước khi thanh toán

        run(async () => {

            const s = await snapshot();

            if (s.ok && s.items.length) {

                if (s.message) {

                    msg(s.message);

                    alert(s.message);

                    return;
                }

                location.href = 'thanhtoan.php';
            }
        });

    });


    // Hiển thị dữ liệu khi tải trang

    render(state);

    msg(state.message);

});

</script>

</body>

</html>
