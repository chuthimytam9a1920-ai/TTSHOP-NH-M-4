//Thêm sản phẩm vào giỏ hàng
<?php
require_once __DIR__ . '/app/bootstrap.php';
(new CartController())->applyVoucher();
