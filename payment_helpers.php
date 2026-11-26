<?php
// TT SHOP - payOS REST API. No credentials in source control.
require_once __DIR__ . '/app/bootstrap.php';
//Đọc và kiểm tra cấu hình payOS từ config_payos.php, trả về mảng cấu hình hoặc mảng rỗng nếu không hợp lệ.
function payos_config(): array {
    return PayOSService::config();
}
//Kiểm tra xem cấu hình payOS có hợp lệ và cURL có sẵn hay không.
function payos_ready(): bool {
    return PayOSService::ready();
}
//Tạo chữ ký HMAC-SHA256 cho dữ liệu thanh toán dựa trên khóa bí mật.
function payos_signature(array $data, string $key): string {
    return PayOSService::signature($data, $key);
}
//Gọi API payOS với phương thức HTTP, đường dẫn và dữ liệu tùy chọn, trả về dữ liệu phản hồi.
function payos_api(string $method, string $path, ?array $body = null): array {
    return PayOSService::api($method, $path, $body);
}
//Hiển thị cho trạng thái thanh toán của đơn hàng dựa trên dữ liệu đơn hàng.
function payos_label(array $o): string {
    return PayOSService::label($o);
}
//Mã hóa dữ liệu để sử dụng trong các yêu cầu payOS, đảm bảo an toàn cho các ký tự đặc biệt.
function payos_escape($v): string {
    return PayOSService::escape($v);
}
//Lấy thông tin đơn hàng từ cơ sở dữ liệu dựa trên ID và tùy chọn chủ sở hữu, trả về mảng dữ liệu hoặc null nếu không tìm thấy.
function payos_order(mysqli $db, int $id, ?int $owner = null): ?array {
    return (new PayOSService($db))->order($id, $owner);
}
//Xác thực dữ liệu đơn hàng từ payOS, đảm bảo rằng dữ liệu nhận được là hợp lệ và khớp với dữ liệu trong cơ sở dữ liệu.
function payos_validate_data(array $order, array $d, string $idKey = 'id'): string {
    return PayOSService::validateData($order, $d, $idKey);
}
//Kiểm tra xem URL có hợp lệ cho payOS hay không, đảm bảo rằng nó tuân thủ các yêu cầu bảo mật và định dạng.
function payos_url_valid(string $url): bool {
    return PayOSService::urlValid($url);
}
//Áp dụng dữ liệu thanh toán từ payOS cho đơn hàng trong cơ sở dữ liệu, trả về mảng dữ liệu đã được cập nhật.
function payos_apply(mysqli $db, int $id, array $d): array {
    return (new PayOSService($db))->apply($id, $d);
}
//Đồng bộ hóa dữ liệu đơn hàng với payOS, có thể buộc đồng bộ ngay cả khi không cần thiết.
function payos_sync(mysqli $db, array $o, bool $force = false): array {
    return (new PayOSService($db))->sync($o, $force);
}
//Tạo đơn hàng mới trên payOS dựa trên dữ liệu đơn hàng hiện tại, trả về mã liên kết hoặc ID liên kết.
function payos_create(mysqli $db, array $o): string {
    return (new PayOSService($db))->create($o);
}
