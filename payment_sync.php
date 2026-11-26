<?php
require_once __DIR__ . '/app/bootstrap.php';
if (session_status() === PHP_SESSION_NONE) session_start();
if (($_SESSION['admin_login'] ?? false) !== true) { header('Location: admin/login.php'); exit; }
(new AdminPaymentController())->sync();
