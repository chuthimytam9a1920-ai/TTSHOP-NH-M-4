
<?php
require_once __DIR__ . '/membership.php';

// 1. Chuẩn hóa mã giảm giá
require_once __DIR__ . '/app/bootstrap.php';

function voucher_normalize(string $code): string
{
    return VoucherService::normalize($code);
}


// 2. Tìm mã giảm giá trong database

function voucher_find(
    mysqli $conn,
    string $code,
    bool $lock = false
): ?array
{
    return (new VoucherService($conn))->find($code, $lock);
}


// 3. Kiểm tra số lượt sử dụng mã giảm giá

function voucher_usage(
    mysqli $conn,
    int $voucherId,
    int $userId,
    bool $lock = false
): array
{
    return (new VoucherService($conn))->usage($voucherId, $userId, $lock);
}


// 4. Kiểm tra điều kiện sử dụng mã giảm giá

function voucher_rule_error(
    array $v,
    string $tier,
    int $subtotal,
    string $now
): string
{
    return VoucherService::ruleError($v, $tier, $subtotal, $now);
}


// 5. Tính số tiền giảm giá và tổng thanh toán

function voucher_calculate(
    int $subtotal,
    int $shipping,
    ?array $v = null
): array
{
    return VoucherService::calculate($subtotal, $shipping, $v);
}


// 6. Kiểm tra toàn bộ điều kiện của voucher

function voucher_evaluate(
    mysqli $conn,
    array $v,
    int $userId,
    string $tier,
    int $subtotal,
    int $shipping,
    bool $lock = false
): array
{
    return (new VoucherService($conn))->evaluate($v, $userId, $tier, $subtotal, $shipping, $lock);
}


// 7. Hàm chính kiểm tra mã giảm giá

function voucher_validate(
    mysqli $conn,
    int $userId,
    string $code,
    int $subtotal,
    int $shipping = 30000,
    bool $lock = false
): array
{
    return (new VoucherService($conn))->validate($userId, $code, $subtotal, $shipping, $lock);
}


// 8. Lấy danh sách voucher dành cho thành viên

function voucher_list(
    mysqli $conn,
    int $userId,
    int $subtotal,
    int $shipping = 30000
): array
{
    return (new VoucherService($conn))->listVouchers($userId, $subtotal, $shipping);
}
