<?php
date_default_timezone_set('Asia/Ho_Chi_Minh');

$servername = "127.0.0.1";
$username = "root";
$password = "";
$dbname = "ttshop_final";
$port = 3306;
$systemErrorMessage = defined('TT_SHOP_PASSWORD_RESET_FLOW')
    ? 'Hệ thống tạm thời không thể xử lý yêu cầu.'
    : 'Hệ thống đang bận, vui lòng thử lại sau.';

require_once __DIR__ . '/app/bootstrap.php';
$conn = (new Database())->connect($servername, $username, $password, $dbname, $port, $systemErrorMessage);
