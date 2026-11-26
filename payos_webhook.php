<?php
//Xử lý webhook từ PayOS
require_once __DIR__ . '/app/bootstrap.php';
(new PaymentController())->webhook();
