<?php
require_once __DIR__ . '/app/bootstrap.php';
(new AuthController())->logout();


session_start();
session_unset();
session_destroy();

header("Location: index.php");
exit();
