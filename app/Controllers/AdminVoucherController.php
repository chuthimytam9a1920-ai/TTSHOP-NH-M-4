<?php
final class AdminVoucherController
{
    public static function vouchers_av_e($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

    public static function vouchers_av_money($value): string {
    return number_format((int)$value, 0, ',', '.') . 'đ';
}

    public static function vouchers_av_text(array $input, string $key): string {
    $value = $input[$key] ?? '';
    if (!is_string($value)) throw new DomainException('Dữ liệu không hợp lệ.');
    return trim($value);
}

    public static function vouchers_av_int(array $input, string $key, string $label, int $min, int $max, bool $nullable = false): ?int {
    $raw = self::vouchers_av_text($input, $key);
    if ($nullable && $raw === '') return null;
    $value = filter_var($raw, FILTER_VALIDATE_INT, ['options' => ['min_range' => $min, 'max_range' => $max]]);
    if ($value === false) throw new DomainException($label . ' phải là số nguyên từ ' . $min . ' đến ' . $max . '.');
    return $value;
}

    public static function vouchers_av_date(array $input, string $key, string $label): ?string {
    $raw = self::vouchers_av_text($input, $key);
    if ($raw === '') return null;
    $date = DateTimeImmutable::createFromFormat('!Y-m-d\\TH:i', $raw, new DateTimeZone('Asia/Ho_Chi_Minh'));
    if (!$date || $date->format('Y-m-d\\TH:i') !== $raw || (int)$date->format('Y') < 1000) {
        throw new DomainException($label . ' không hợp lệ.');
    }
    return $date->format('Y-m-d H:i:s');
}

    public static function vouchers_av_datetime_input($date): string {
    return $date === null || $date === '' ? '' : str_replace(' ', 'T', substr($date, 0, 16));
}

    public static function vouchers_av_read_form(array $post): array {
    $code = strtoupper(self::vouchers_av_text($post, 'ma_code'));
    if (!preg_match('/^[A-Z0-9_-]{1,50}$/D', $code)) {
        throw new DomainException('Mã chỉ gồm A-Z, 0-9, dấu gạch ngang hoặc gạch dưới, tối đa 50 ký tự.');
    }
    $name = self::vouchers_av_text($post, 'ten_ma');
    if (!preg_match('/^.{1,150}$/usD', $name)) throw new DomainException('Tên chương trình phải từ 1 đến 150 ký tự.');
    $tier = self::vouchers_av_text($post, 'hang_toi_thieu');
    if (!array_key_exists($tier, MembershipService::rules())) throw new DomainException('Hạng thành viên không hợp lệ.');
    $rate = self::vouchers_av_int($post, 'phan_tram_giam', 'Phần trăm giảm', 1, 100);
    $min = self::vouchers_av_int($post, 'don_toi_thieu', 'Đơn tối thiểu', 0, 2147483647);
    $cap = self::vouchers_av_int($post, 'giam_toi_da', 'Giảm tối đa', 1, 2147483647);
    $free = self::vouchers_av_int($post, 'mien_phi_van_chuyen', 'Miễn phí vận chuyển', 0, 1);
    $limit = self::vouchers_av_int($post, 'gioi_han_su_dung', 'Giới hạn toàn hệ thống', 1, 2147483647, true);
    $perUser = self::vouchers_av_int($post, 'gioi_han_moi_user', 'Giới hạn mỗi khách', 1, 2147483647, true);
    $start = self::vouchers_av_date($post, 'ngay_bat_dau', 'Ngày bắt đầu');
    $end = self::vouchers_av_date($post, 'ngay_ket_thuc', 'Ngày kết thúc');
    if ($start !== null && $end !== null && $end <= $start) throw new DomainException('Ngày kết thúc phải sau ngày bắt đầu.');
    $active = self::vouchers_av_int($post, 'trang_thai', 'Trạng thái', 0, 1);
    return [$code, $name, $tier, $rate, $min, $cap, $free, $limit, $perUser, $start, $end, $active];
}

    public static function vouchers_av_query(mysqli $conn, string $sql, string $types = '', array $params = []): array {
    $stmt = $conn->prepare($sql);
    try {
        if ($types !== '') $stmt->bind_param($types, ...$params);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    } finally { $stmt->close(); }
}

    public static function vouchers_av_flash(string $message): void {
    $_SESSION['admin_voucher_flash'] = $message;
    header('Location: vouchers.php', true, 303);
    exit;
}

    public static function vouchers_av_status(array $v): array {
    if ((int)$v['trang_thai'] !== 1) return ['Vô hiệu hóa', 'off'];
    if ($v['ngay_bat_dau'] !== null && $v['now_db'] < $v['ngay_bat_dau']) return ['Chưa bắt đầu', 'wait'];
    if ($v['ngay_ket_thuc'] !== null && $v['now_db'] >= $v['ngay_ket_thuc']) return ['Hết hạn', 'off'];
    if ($v['gioi_han_su_dung'] !== null && (int)$v['used_count'] >= (int)$v['gioi_han_su_dung']) return ['Hết lượt', 'wait'];
    return ['Đang hoạt động', 'on'];
}

    public static function vouchers_av_history_url(int $id, int $page): string {
    return 'vouchers.php?' . http_build_query(['history_id'=>$id,'p'=>$page]) . '#history';
}

    public function vouchers(): array
    {

// admin/vouchers.php - Quản lý ưu đãi thành viên TT SHOP.
require_once (dirname(__DIR__, 2) . '/admin') . '/check_admin.php';
require_once (dirname(__DIR__, 2) . '/admin') . '/../csrf.php';
require_once (dirname(__DIR__, 2) . '/admin') . '/../membership.php';
header('Cache-Control: no-store');
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
require_once (dirname(__DIR__, 2) . '/admin') . '/../connect.php';












$token = CsrfService::token();
$flash = $_SESSION['admin_voucher_flash'] ?? '';
unset($_SESSION['admin_voucher_flash']);
$error = '';
$loadError = '';
$oldForm = null;
$editId = filter_var($_GET['edit'] ?? 0, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 2147483647]]) ?: 0;

// Mọi thao tác ghi đều sử dụng POST + CSRF, không thay đổi lịch sử đơn cũ.
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!CsrfService::check($_POST['csrf_token'] ?? null)) {
        http_response_code(403);
        exit('Phiên xác thực không hợp lệ. Vui lòng quay lại và tải lại trang.');
    }
    try {
        $action = self::vouchers_av_text($_POST, 'action');
        if ($action === 'save') {
            $editId = self::vouchers_av_int($_POST, 'id', 'ID mã', 0, 2147483647);
            $oldForm = $_POST;
            $values = self::vouchers_av_read_form($_POST);
            // Đổi cấu hình bằng một lệnh UPDATE nguyên tử; khóa hàng của checkout vẫn có hiệu lực.
            if ($editId > 0) {
                if (!self::vouchers_av_query($conn, 'SELECT id FROM magiamgia WHERE id = ?', 'i', [$editId])) {
                    throw new DomainException('Mã giảm giá không còn tồn tại.');
                }
                $sql = 'UPDATE magiamgia SET ma_code=?, ten_ma=?, hang_toi_thieu=?, phan_tram_giam=?, don_toi_thieu=?, giam_toi_da=?, mien_phi_van_chuyen=?, gioi_han_su_dung=?, gioi_han_moi_user=?, ngay_bat_dau=?, ngay_ket_thuc=?, trang_thai=? WHERE id=?';
                $values[] = $editId;
                $types = 'sssiiiiiissii';
            } else {
                $sql = 'INSERT INTO magiamgia (ma_code,ten_ma,hang_toi_thieu,phan_tram_giam,don_toi_thieu,giam_toi_da,mien_phi_van_chuyen,gioi_han_su_dung,gioi_han_moi_user,ngay_bat_dau,ngay_ket_thuc,trang_thai) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)';
                $types = 'sssiiiiiissi';
            }
            $stmt = $conn->prepare($sql);
            try {
                $stmt->bind_param($types, ...$values);
                $stmt->execute();
            } finally { $stmt->close(); }
            self::vouchers_av_flash($editId ? 'Đã cập nhật mã giảm giá.' : 'Đã tạo mã giảm giá mới.');
        } elseif ($action === 'toggle') {
            $id = self::vouchers_av_int($_POST, 'id', 'ID mã', 1, 2147483647);
            $active = self::vouchers_av_int($_POST, 'trang_thai', 'Trạng thái', 0, 1);
            $stmt = $conn->prepare('UPDATE magiamgia SET trang_thai=? WHERE id=?');
            try {
                $stmt->bind_param('ii', $active, $id);
                $stmt->execute();
            } finally { $stmt->close(); }
            self::vouchers_av_flash($active ? 'Đã bật mã. Điều kiện thời hạn và lượt dùng vẫn được kiểm tra.' : 'Đã vô hiệu hóa mã giảm giá.');
        } else {
            throw new DomainException('Thao tác không hợp lệ.');
        }
    } catch (DomainException $e) {
        $error = $e->getMessage();
    } catch (mysqli_sql_exception $e) {
        $error = (int)$e->getCode() === 1062 ? 'Mã giảm giá đã tồn tại. Hãy dùng mã khác.' : 'Không lưu được dữ liệu. Vui lòng thử lại.';
        error_log('TT SHOP admin vouchers: ' . $e->getMessage());
    }
}

$defaults = ['ma_code'=>'','ten_ma'=>'','hang_toi_thieu'=>'Member','phan_tram_giam'=>3,'don_toi_thieu'=>100000,'giam_toi_da'=>30000,'mien_phi_van_chuyen'=>0,'gioi_han_su_dung'=>null,'gioi_han_moi_user'=>null,'ngay_bat_dau'=>null,'ngay_ket_thuc'=>null,'trang_thai'=>1];
$form = $defaults;
$vouchers = $history = [];
$historyId = filter_var($_GET['history_id'] ?? 0, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1,'max_range'=>2147483647]]) ?: 0;
$historyPage = filter_var($_GET['p'] ?? 1, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1,'max_range'=>2147483647]]) ?: 1;
$historyPages = 1;
$stats = ['active'=>0,'used'=>0,'delivered'=>0,'saved'=>0,'pending'=>0];
try {
    // Lượt hợp lệ trừ đơn hủy, tương thích voucher_helpers.php.
    $vouchers = self::vouchers_av_query($conn, "SELECT m.*, NOW() AS now_db,
        COALESCE(s.used_count,0) AS used_count,
        COALESCE(s.delivered_count,0) AS delivered_count,
        COALESCE(s.cancelled_count,0) AS cancelled_count,
        COALESCE(s.saved,0) AS saved,
        COALESCE(s.pending,0) AS pending
        FROM magiamgia m LEFT JOIN (
            SELECT l.magiamgia_id,
                SUM(CASE WHEN COALESCE(d.trang_thai,'') NOT IN ('Đã huỷ','Đã hủy') THEN 1 ELSE 0 END) AS used_count,
                SUM(CASE WHEN d.trang_thai='Đã giao' THEN 1 ELSE 0 END) AS delivered_count,
                SUM(CASE WHEN d.trang_thai IN ('Đã huỷ','Đã hủy') THEN 1 ELSE 0 END) AS cancelled_count,
                SUM(CASE WHEN d.trang_thai='Đã giao' THEN l.tien_giam_gia+l.tien_giam_van_chuyen ELSE 0 END) AS saved,
                SUM(CASE WHEN COALESCE(d.trang_thai,'') NOT IN ('Đã giao','Đã huỷ','Đã hủy') THEN l.tien_giam_gia+l.tien_giam_van_chuyen ELSE 0 END) AS pending
            FROM lichsu_magiamgia l JOIN donhang d ON d.id=l.donhang_id GROUP BY l.magiamgia_id
        ) s ON s.magiamgia_id=m.id ORDER BY m.id DESC");
    foreach ($vouchers as $v) {
                $stats['active'] += self::vouchers_av_status($v)[1] === 'on' ? 1 : 0;
        $stats['used'] += (int)$v['used_count'];
        $stats['delivered'] += (int)$v['delivered_count'];
        $stats['saved'] += (int)$v['saved'];
        $stats['pending'] += (int)$v['pending'];
        if ((int)$v['id'] === $editId) $form = array_merge($defaults, $v);
    }
    if ($editId && !isset($form['id'])) {
        $error = $error ?: 'Không tìm thấy mã giảm giá cần sửa.';
        $editId = 0;
    }
    $form['ngay_bat_dau'] = self::vouchers_av_datetime_input($form['ngay_bat_dau']);
    $form['ngay_ket_thuc'] = self::vouchers_av_datetime_input($form['ngay_ket_thuc']);
    if ($oldForm !== null) {
        foreach ($defaults as $key => $unused) {
            if (isset($oldForm[$key]) && is_string($oldForm[$key])) $form[$key] = $oldForm[$key];
        }
    }
    $where = $historyId ? ' WHERE l.magiamgia_id = ?' : '';
    $params = $historyId ? [$historyId] : [];
    $types = $historyId ? 'i' : '';
    $count = self::vouchers_av_query($conn, 'SELECT COUNT(*) AS n FROM lichsu_magiamgia l JOIN donhang d ON d.id=l.donhang_id' . $where, $types, $params);
    $historyPages = max(1, (int)ceil((int)$count[0]['n'] / 20));
    $historyPage = min($historyPage, $historyPages);
    $offset = ($historyPage - 1) * 20;
    $history = self::vouchers_av_query($conn, "SELECT l.*,d.ma_don,d.ho_ten,d.hang_thanh_vien,d.trang_thai,d.tong_cong
        FROM lichsu_magiamgia l JOIN donhang d ON d.id=l.donhang_id" . $where . ' ORDER BY l.id DESC LIMIT 20 OFFSET ?', $types . 'i', array_merge($params, [$offset]));
} catch (Throwable $e) {
    $loadError = 'Không thể tải dữ liệu voucher. Hãy kiểm tra kết nối và hai bảng magiamgia, lichsu_magiamgia.';
    error_log('TT SHOP admin voucher list: ' . $e->getMessage());
}


        return get_defined_vars();
    }
}
