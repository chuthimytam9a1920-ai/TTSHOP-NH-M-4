<?php
final class Database
{
    public function connect(string $servername, string $username, string $password, string $dbname, int $port, string $systemErrorMessage): mysqli
    {
$conn = mysqli_connect(
    $servername,
    $username,
    $password,
    $dbname,
    $port
);

if (!$conn) {
    error_log("TT SHOP database connection failed: " . mysqli_connect_error());
    http_response_code(503);
    if (defined('TT_SHOP_JSON_REQUEST')) {
        header('Content-Type: application/json; charset=utf-8');
        die(json_encode(array('trung' => false)));
    }
    die($systemErrorMessage);
}

mysqli_set_charset($conn, "utf8mb4");

try {
    $timezoneStatement = $conn->prepare("SET time_zone = '+07:00'");
    $timezoneStatement->execute();
    $timezoneStatement->close();
} catch (Throwable $exception) {
    error_log(sprintf('%s:%d: Unable to set database time zone: %s', __FILE__, __LINE__, $exception->getMessage()));
    http_response_code(503);
    die($systemErrorMessage);
}

        return $conn;
    }
}
