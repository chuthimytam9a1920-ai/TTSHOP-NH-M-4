
<?php
// membership.php
// Hàm dùng chung cho chương trình thành viên TT SHOP.

// 1. Quy định xếp hạng thành viên

require_once __DIR__ . '/app/bootstrap.php';

function membership_rules(): array
{
    return MembershipService::rules();
}


// 2. Xác định thứ bậc thành viên

function membership_rank(string $tier): int
{
    return MembershipService::rank($tier);
}


// 3. Kiểm tra quyền sử dụng mã giảm giá

function membership_can_use(
    string $currentTier,
    string $requiredTier
): bool
{
    return MembershipService::canUse($currentTier, $requiredTier);
}


// 4. Tính hạng thành viên

function membership_calculate(
    int $orders,
    int $spend,
    int $paidRank = 0
): array
{
    return MembershipService::calculate($orders, $spend, $paidRank);
}


// 5. Lấy thông tin thành viên từ database

function membership_get(
    mysqli $conn,
    int $userId
): array
{
    return (new MembershipService($conn))->get($userId);
}
