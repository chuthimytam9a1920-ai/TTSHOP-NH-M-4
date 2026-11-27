
<?php
require_once __DIR__ . '/app/bootstrap.php';

extract((new CustomerOrderPageController())->success(), EXTR_OVERWRITE);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Đặt hàng thành công</title>

    <style>
        body {
            margin: 0;
            font-family: Arial, Helvetica, sans-serif;
            background: #ffffff;
            height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .success-box {
            text-align: center;
            width: 560px;
            padding: 45px;
        }

        .check-icon {
            width: 130px;
            height: 130px;
            border-radius: 50%;
            border: 5px solid #dff4d8;
            margin: 0 auto 28px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #7fd56f;
            font-size: 82px;
            font-weight: bold;
        }

        .success-box h1 {
            font-size: 42px;
            color: #555;
            margin: 0 0 18px;
        }

        .success-box p {
            font-size: 23px;
            color: #666;
            line-height: 1.55;
            margin: 8px 0;
        }

        .order-code {
            color: #15985d;
            font-weight: bold;
        }

        .ok-btn {
            margin-top: 32px;
            width: 130px;
            height: 58px;
            border: none;
            border-radius: 10px;
            background: #2458ff;
            color: #fff;
            font-size: 24px;
            font-weight: 800;
            cursor: pointer;
        }

        .ok-btn:hover {
            background: #0c42f2;
        }
    </style>
</head>

<body>

<div class="success-box">

    <div class="check-icon">✓</div>

    <h1>Thông báo</h1>

    <p>Đã đặt đơn hàng thành công!</p>

    <p>Cảm ơn quý khách đã mua hàng tại TT SHOP ❤️</p>

    <p>
        Mã đơn hàng:
        <span class="order-code"><?php echo htmlspecialchars($ma_don); ?></span>
    </p>

    <button class="ok-btn" onclick="window.location.href='taikhoan.php'">
        OK
    </button>

</div>

</body>
</html>