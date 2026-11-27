
<?php
// Xử lý áp dụng và gỡ mã giảm giá
require_once __DIR__ . '/app/bootstrap.php';
(new CartController())->applyVoucher();
