<?php
/** TT SHOP - extracted business logic. */
final class MembershipService extends DatabaseService
{
    public static function rules(): array
{
    return [
        'Member' => [
            'orders' => 0,
            'spend' => 0
        ],

        'Silver' => [
            'orders' => 3,
            'spend' => 500000
        ],

        'Gold' => [
            'orders' => 6,
            'spend' => 1500000
        ],

        'Platinum' => [
            'orders' => 11,
            'spend' => 3000000
        ],

        'Diamond' => [
            'orders' => 21,
            'spend' => 6000000
        ],
    ];
}

    public static function rank(string $tier): int
{
    $rank = array_search(
        $tier,
        array_keys(self::rules()),
        true
    );

    if ($rank === false) {
        throw new InvalidArgumentException(
            'Hạng thành viên không hợp lệ.'
        );
    }

    return $rank;
}

    public static function canUse(
    string $currentTier,
    string $requiredTier
): bool
{
    return self::rank($currentTier)
        >= self::rank($requiredTier);
}

    public static function calculate(
    int $orders,
    int $spend,
    int $paidRank = 0
): array
{
    $orders = max(0, $orders);
    $spend = max(0, $spend);

    $rules = self::rules();

    $tiers = array_keys($rules);

    $tier = 'Member';

    // Xếp hạng theo số đơn đã giao
    // HOẶC tổng chi tiêu tích lũy.

    foreach ($rules as $name => $rule) {

        if (
            $orders >= $rule['orders']
            ||
            $spend >= $rule['spend']
        ) {
            $tier = $name;
        }
    }

    $rank = max(self::rank($tier), min(count($tiers) - 1, max(0, $paidRank)));
    $tier = $tiers[$rank];

    // Xác định hạng tiếp theo

    $nextTier = $tiers[$rank + 1] ?? null;

    return [

        'tier' => $tier,

        'rank' => $rank,

        'orders' => $orders,

        'spend' => $spend,

        'next_tier' => $nextTier,

        'orders_needed' => $nextTier === null
            ? 0
            : max(
                0,
                $rules[$nextTier]['orders'] - $orders
            ),

        'spend_needed' => $nextTier === null
            ? 0
            : max(
                0,
                $rules[$nextTier]['spend'] - $spend
            ),
    ];
}

    public function get(
    int $userId
): array
{
        $conn = $this->connection;
    // Kiểm tra ID khách hàng

    if ($userId <= 0) {

        throw new InvalidArgumentException(
            'ID khách hàng không hợp lệ.'
        );
    }

    // Chỉ tính những đơn hàng đã giao thành công

    $sql = "
        SELECT
            COUNT(*) AS so_lan_mua,
            COALESCE(
                SUM(tong_cong),
                0
            ) AS tong_chi_tieu,
            (SELECT COALESCE(MAX(target_rank),0) FROM membership_upgrades
             WHERE user_id = ? AND status = 'PAID') AS paid_rank

        FROM donhang

        WHERE user_id = ?
        AND trang_thai = 'Đã giao'
    ";

    $stmt = $conn->prepare($sql);

    if (!$stmt) {

        throw new RuntimeException(
            'Không thể truy vấn hạng thành viên.'
        );
    }

    try {

        if (
            !$stmt->bind_param('ii', $userId, $userId)
            ||
            !$stmt->execute()
        ) {

            throw new RuntimeException(
                'Không thể đọc dữ liệu thành viên.'
            );
        }

        if (
            !$stmt->bind_result($orders, $spend, $paidRank)
            ||
            $stmt->fetch() !== true
        ) {

            throw new RuntimeException(
                'Không thể lấy kết quả thành viên.'
            );
        }

        return self::calculate(
            (int)$orders,
            (int)$spend,
            (int)$paidRank
        );

    } finally {

        $stmt->close();
    }
}
}
