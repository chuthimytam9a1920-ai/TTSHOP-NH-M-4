<?php
//Xóa sản phẩm khỏi giỏ hàng
require_once __DIR__ . '/app/bootstrap.php';
(new CartController())->remove();
