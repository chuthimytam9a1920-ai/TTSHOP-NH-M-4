<?php
//Đồng bộ trạng thái thanh toán
require_once __DIR__ . '/app/bootstrap.php';
(new PaymentController())->syncCli();
