# TT SHOP — bản tái cấu trúc PHP OOP

Nguồn: TTSHOP-main (13).zip và đối chiếu ttshop_final (10).sql.

## Cài đặt trên XAMPP (Windows)

1. Sao lưu thư mục hiện tại và cơ sở dữ liệu `ttshop_final` bằng phpMyAdmin Export hoặc mysqldump. Không xóa hoặc nhập đè cơ sở dữ liệu đang hoạt động.
2. Giải nén bản này. Có thể kiểm tra song song trong `C:\xampp\htdocs\TTSHOP-OOP` (đổi tên thư mục `TTSHOP-main` sau giải nén). Để giữ nguyên URL chính thức, sau khi kiểm thử hãy thay thư mục gốc bằng bản mới, giữ bản cũ ở nơi sao lưu.
3. Sao chép hai file cấu hình riêng `config_mail.php` và `config_payos.php` từ bản cũ nếu đã dùng; các file này không có trong ZIP đầu vào do gitignore. Không đưa thông tin xác thực lên GitHub. Kiểm tra URL callback/return/cancel trong cấu hình payOS nếu chạy thư mục thử nghiệm khác tên.
4. `connect.php` vẫn dùng host 127.0.0.1, root, mật khẩu rỗng, port 3306 và database `ttshop_final` như bản gốc. Nếu máy đã có cấu hình khác, sửa các biến kết nối tại đây. Không chạy file SQL lại nếu dữ liệu đang tồn tại. File SQL gốc không bị chỉnh sửa.
5. Bật Apache/MySQL, mở `http://localhost/TTSHOP-OOP/` (hoặc thư mục tương ứng) và `http://localhost/TTSHOP-OOP/admin/`. Dùng môi trường thử nghiệm riêng hoặc DB sao chép nếu kiểm thử nghiệp vụ ghi dữ liệu.

## Kiến trúc

- `app/bootstrap.php`: autoload lớp nội bộ, không cần Composer.
- `app/Core/Database.php`: thiết lập kết nối MySQLi, charset và timezone.
- `app/Core/DatabaseService.php`: lớp trừu tượng nhận kết nối qua constructor. Các service truy vấn sử dụng cùng handle để giữ transaction gốc.
- `app/Services/`: 10 lớp nghiệp vụ (thành viên, nâng hạng, voucher, payOS, xác thực dữ liệu, CSRF, chat, phản ánh, email OTP, giỏ hàng).
- `app/Repositories/ProductRepository.php`: truy vấn sản phẩm nổi bật và tra cứu sản phẩm khi thêm giỏ.
- `app/Controllers/`: các lớp điều phối hành động và dữ liệu trang khách hàng/Admin. Trang PHP gốc vẫn là entry point/view, giữ URL, form, HTML/CSS/JS.
- Các helper tên cũ là adapter tương thích; nghiệp vụ đã chuyển sang phương thức lớp. Các trang có thể gọi trực tiếp lớp. Một số hàm render và truy vấn xen kẽ HTML vẫn thuộc view cũ; bản này không tuyên bố MVC thuần hoàn toàn. Cơ chế `extract()` dùng để chuyển dữ liệu của controller về view mà không đổi biến và markup hiện hữu. Không thay cấu trúc database.
- Sửa lỗi đường dẫn có sẵn: `admin/orders.php` gọi `admin/payment_sync.php` nhưng bản gốc chỉ chứa `payment_sync.php` ở root với include tương đối từ thư mục admin. Bản này có endpoint admin đúng đường dẫn và giữ alias root, vẫn kiểm tra phiên admin.
- `oop_migration_manifest.json`: danh sách route và lớp đã chuyển đổi.

## Kiểm tra đã thực hiện

- Kiểm tra cú pháp PHP (CLI 8.4; chưa chạy trên XAMPP PHP 8.0.30).
- 219 trường hợp so sánh kết quả hàm với bản gốc: tính hạng, giảm giá, chuẩn hóa dữ liệu, chat, chữ ký payOS, phản ánh, CSRF. Kết quả trùng nhau khi bật output buffering.
- Kiểm tra phần HTML tĩnh theo token của 90 file PHP (trừ helper phản ánh, đã đối chiếu HTML render qua regression); không có khác biệt. Tài nguyên không phải PHP và thư viện PHPMailer gốc không thay đổi.
- Kiểm tra tăng/giảm/xóa số lượng giỏ hàng bằng CartService và một số trang không cần DB, redirect khách chưa đăng nhập. Ba endpoint không còn xuất dòng trống trước header như bản gốc.
- Chưa thể chạy MySQL/MariaDB hoặc mysqli trong môi trường tạo gói; chưa kiểm chứng trực tiếp ghi DB, SMTP thật, payOS thật, giao dịch QR/webhook, dashboard dữ liệu hoặc kiểm thử trình duyệt toàn bộ. Không xem kết quả kiểm tra cú pháp là bằng chứng website đã vận hành hoàn toàn.

## Kiểm thử cần làm trên bản sao DB

Đăng ký, đăng nhập, xác thực OTP/đặt lại mật khẩu; xem/tìm kiếm/lọc/phân trang sản phẩm; thêm/sửa/xóa giỏ; voucher đúng/sai hạng/hết lượt; đặt hàng COD và QR; trạng thái và hủy đơn; xem lịch sử, đánh giá, địa chỉ, chat, phản ánh; nâng hạng đúng giá và xác nhận thanh toán một lần; admin đăng nhập, CRUD sản phẩm, quản lý khách, voucher, đơn hàng, báo cáo, dashboard nâng hạng và CSV. Kiểm tra dữ liệu cũ còn nguyên. Dùng payOS test hoặc giao dịch thử phù hợp, không thanh toán thật chỉ để kiểm tra cú pháp.

Đây là refactor tương thích, không phải kiểm toán bảo mật đầy đủ. Những giới hạn bảo mật của mã gốc ngoài lỗi đường dẫn nêu trên không được chủ động thay đổi để tránh làm lệch hành vi. Không xóa DB hoặc ghi đè bản đang chạy trước khi kiểm thử.
