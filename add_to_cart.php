<?php
// Thêm sản phẩm vào giỏ hàng
require_once __DIR__ . '/app/bootstrap.php';

(new CartController())->add();
