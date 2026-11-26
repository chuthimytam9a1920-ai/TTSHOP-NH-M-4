<?php
require_once __DIR__ . '/app/bootstrap.php';
//Gọi đến controller giỏ hàng
(new OrderController())->review();
