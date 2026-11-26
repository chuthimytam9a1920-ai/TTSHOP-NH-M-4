<?php
final class AdminCatalogController
{
    public static function products_tinhGiamGia($oldPrice, $price) {
    $oldPrice = (int)$oldPrice;
    $price = (int)$price;

    if ($oldPrice > 0 && $price > 0 && $oldPrice > $price) {
        return round((($oldPrice - $price) / $oldPrice) * 100);
    }

    return 0;
}

    public function products(): array
    {

include dirname(__DIR__, 2) . '/admin' . '/check_admin.php';
include dirname(__DIR__, 2) . '/admin' . '/../connect.php';

$keyword = trim($_GET['keyword'] ?? '');
$filter = $_GET['filter'] ?? 'all';

$where = [];

if ($keyword !== '') {
    $safeKeyword = mysqli_real_escape_string($conn, $keyword);

    if (is_numeric($keyword)) {
        $where[] = "(id = " . (int)$keyword . " OR name LIKE '%$safeKeyword%')";
    } else {
        $where[] = "name LIKE '%$safeKeyword%'";
    }
}

if ($filter === 'hot') {
    $where[] = "sold_count > 500";
}

elseif ($filter === 'new') {
    $where[] = "is_new = 1";
}

elseif ($filter === 'flash') {
    $where[] = "is_flash_sale = 1";
}

elseif ($filter !== 'all') {
    $safeFilter = mysqli_real_escape_string($conn, $filter);
    $where[] = "category_id = '$safeFilter'";
}
$whereSql = "";
if (!empty($where)) {
    $whereSql = "WHERE " . implode(" AND ", $where);
}

$products = mysqli_query($conn, "SELECT * FROM products $whereSql ORDER BY id DESC");



        return get_defined_vars();
    }

    public static function categoryProducts_tinhGiamGia($oldPrice, $price) {
    $oldPrice = (int)$oldPrice;
    $price = (int)$price;

    if ($oldPrice > 0 && $price > 0 && $oldPrice > $price) {
        return round((($oldPrice - $price) / $oldPrice) * 100);
    }

    return 0;
}

    public function categoryProducts(): array
    {

include dirname(__DIR__, 2) . '/admin' . '/check_admin.php';
include dirname(__DIR__, 2) . '/admin' . '/../connect.php';

$keyword = trim($_GET['keyword'] ?? '');
$filter = $_GET['filter'] ?? 'all';

$categoryList = [
    'vo-oly' => 'Vở ô ly',
    'vo-caro' => 'Vở caro',
    'vo-ke-ngang' => 'Vở kẻ ngang',
    'so-loxo' => 'Sổ lò xo',
    'so-da' => 'Sổ da',
    'but-bi' => 'Bút bi',
    'but-chi' => 'Bút chì',
    'but-muc' => 'Bút mực',
    'but-xoa' => 'Bút xoá',
    'but-quang' => 'Bút dạ quang',
    'thuoc' => 'Thước',
    'note' => 'Giấy note',
    'phan' => 'Phấn',
    'compa' => 'Compa',
    'hop-but' => 'Hộp bút',
    'giay-in' => 'Giấy in',
    'kep-giay' => 'Kẹp giấy',
    'dao-roc' => 'Dao rọc giấy',
    'bia-cong' => 'Bìa còng',
    'keo' => 'Kéo văn phòng',
    'balo-hs' => 'Balo học sinh',
    'balo-vp' => 'Balo văn phòng',
    'casio' => 'Casio',
    'mt-vp' => 'Máy tính văn phòng'
];

$where = [];

if ($keyword !== '') {
    $safeKeyword = mysqli_real_escape_string($conn, $keyword);

    if (is_numeric($keyword)) {
        $where[] = "(id = " . (int)$keyword . " OR name LIKE '%$safeKeyword%')";
    } else {
        $where[] = "name LIKE '%$safeKeyword%'";
    }
}

if ($filter === 'hot') {
    $where[] = "sold_count > 500";
} elseif ($filter === 'new') {
    $where[] = "is_new = 1";
} elseif ($filter !== 'all') {
    $safeFilter = mysqli_real_escape_string($conn, $filter);
    $where[] = "type_slug = '$safeFilter'";
}

$whereSql = "";
if (!empty($where)) {
    $whereSql = "WHERE " . implode(" AND ", $where);
}

$result = mysqli_query($conn, "SELECT * FROM category_products $whereSql ORDER BY id DESC");



        return get_defined_vars();
    }

    public function addProduct(): array
    {

include dirname(__DIR__, 2) . '/admin' . '/check_admin.php';
include dirname(__DIR__, 2) . '/admin' . '/../connect.php';

$error = "";

$cats = [
    'top-sale' => 'TOP SALE',
    'vpp' => 'Văn phòng phẩm',
    'vo-ghi' => 'Vở ghi - sổ tay',
    'but-viet' => 'Bút viết',
    'dung-cu' => 'Dụng cụ học tập',
    'balo' => 'Ba lô - Cặp sách',
    'may-tinh' => 'Máy tính cầm tay'
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $category_id = trim($_POST['category_id'] ?? '');
    $price = (int)($_POST['price'] ?? 0);
    $old_price = (int)($_POST['old_price'] ?? 0);
    $sold_count = (int)($_POST['sold_count'] ?? 0);
    $star_count = (int)($_POST['star_count'] ?? 5);
    $review_count = (int)($_POST['review_count'] ?? 0);
    $mo_ta_chi_tiet = trim($_POST['mo_ta_chi_tiet'] ?? '');

    $is_flash_sale = isset($_POST['is_flash_sale']) ? 1 : 0;
    $is_new = isset($_POST['is_new']) ? 1 : 0;

    $imageName = "";

    if (!empty($_FILES['image']['name'])) {
        $imageName = time() . "_" . basename($_FILES['image']['name']);
        $target = "../images/" . $imageName;

        if (!move_uploaded_file($_FILES['image']['tmp_name'], $target)) {
            $error = "Lỗi tải ảnh lên.";
        }
    }

    if ($name === '' || $category_id === '' || $price <= 0 || $imageName === '') {
        $error = "Vui lòng nhập đầy đủ thông tin.";
    }

    if ($error === "") {
        $sql = "INSERT INTO products 
                (name, category_id, price, old_price, image, sold_count, star_count, review_count, is_flash_sale, is_new, mo_ta_chi_tiet)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = mysqli_prepare($conn, $sql);

        mysqli_stmt_bind_param(
            $stmt,
            "ssiisiiiiis",
            $name,
            $category_id,
            $price,
            $old_price,
            $imageName,
            $sold_count,
            $star_count,
            $review_count,
            $is_flash_sale,
            $is_new,
            $mo_ta_chi_tiet
        );

        if (mysqli_stmt_execute($stmt)) {
            header("Location: products.php");
            exit();
        } else {
            $error = "Lỗi thêm sản phẩm: " . mysqli_error($conn);
        }
    }
}

        return get_defined_vars();
    }

    public function editProduct(): array
    {

include dirname(__DIR__, 2) . '/admin' . '/check_admin.php';
include dirname(__DIR__, 2) . '/admin' . '/../connect.php';

$id = (int)($_GET['id'] ?? 0);

$stmt = mysqli_prepare($conn, "SELECT * FROM products WHERE id = ?");
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);
$product = mysqli_fetch_assoc($result);

if (!$product) {
    die("Không tìm thấy sản phẩm.");
}

$error = "";

$cats = [
    'top-sale' => 'TOP SALE',
    'vpp' => 'Văn phòng phẩm',
    'vo-ghi' => 'Vở ghi - sổ tay',
    'but-viet' => 'Bút viết',
    'dung-cu' => 'Dụng cụ học tập',
    'balo' => 'Ba lô - Cặp sách',
    'may-tinh' => 'Máy tính cầm tay'
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $category_id = trim($_POST['category_id'] ?? '');
    $price = (int)($_POST['price'] ?? 0);
    $old_price = (int)($_POST['old_price'] ?? 0);
    $sold_count = (int)($_POST['sold_count'] ?? 0);
    $star_count = (int)($_POST['star_count'] ?? 5);
    $review_count = (int)($_POST['review_count'] ?? 0);
    $mo_ta_chi_tiet = trim($_POST['mo_ta_chi_tiet'] ?? '');

    $is_flash_sale = isset($_POST['is_flash_sale']) ? 1 : 0;
    $is_new = isset($_POST['is_new']) ? 1 : 0;

    $imageName = $product['image'];

    if (!empty($_FILES['image']['name'])) {
        $imageName = time() . "_" . basename($_FILES['image']['name']);
        $target = "../images/" . $imageName;

        if (!move_uploaded_file($_FILES['image']['tmp_name'], $target)) {
            $error = "Lỗi tải ảnh lên.";
        }
    }

    if ($error === "") {
        $sql = "UPDATE products SET 
                    name = ?, 
                    category_id = ?, 
                    price = ?, 
                    old_price = ?, 
                    image = ?, 
                    sold_count = ?, 
                    star_count = ?, 
                    review_count = ?, 
                    is_flash_sale = ?, 
                    is_new = ?,
                    mo_ta_chi_tiet = ?
                WHERE id = ?";

        $stmt = mysqli_prepare($conn, $sql);

        mysqli_stmt_bind_param(
            $stmt,
            "ssiisiiiiisi",
            $name,
            $category_id,
            $price,
            $old_price,
            $imageName,
            $sold_count,
            $star_count,
            $review_count,
            $is_flash_sale,
            $is_new,
            $mo_ta_chi_tiet,
            $id
        );

        if (mysqli_stmt_execute($stmt)) {
            header("Location: products.php");
            exit();
        } else {
            $error = "Lỗi cập nhật sản phẩm: " . mysqli_error($conn);
        }
    }
}

        return get_defined_vars();
    }

    public function addCategoryProduct(): array
    {

include dirname(__DIR__, 2) . '/admin' . '/check_admin.php';
include dirname(__DIR__, 2) . '/admin' . '/../connect.php';

$categories = [
    'vo-oly' => 'Vở ô ly',
    'vo-caro' => 'Vở caro',
    'vo-ke-ngang' => 'Vở kẻ ngang',
    'so-loxo' => 'Sổ lò xo',
    'so-da' => 'Sổ da',
    'but-bi' => 'Bút bi',
    'but-chi' => 'Bút chì',
    'but-muc' => 'Bút mực',
    'but-xoa' => 'Bút xoá',
    'but-quang' => 'Bút dạ quang',
    'thuoc' => 'Thước',
    'note' => 'Giấy note',
    'phan' => 'Phấn',
    'compa' => 'Compa',
    'hop-but' => 'Hộp bút',
    'giay-in' => 'Giấy in',
    'kep-giay' => 'Kẹp giấy',
    'dao-roc' => 'Dao rọc giấy',
    'bia-cong' => 'Bìa còng',
    'keo' => 'Kéo văn phòng',
    'balo-hs' => 'Balo học sinh',
    'balo-vp' => 'Balo văn phòng',
    'casio' => 'Casio',
    'mt-vp' => 'Máy tính văn phòng'
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $type_slug = $_POST['type_slug'] ?? '';
    $price = (int)($_POST['price'] ?? 0);
    $old_price = (int)($_POST['old_price'] ?? 0);
    $sold_count = (int)($_POST['sold_count'] ?? 0);
    $rating_count = (int)($_POST['rating_count'] ?? 0);
    $mo_ta_chi_tiet = trim($_POST['mo_ta_chi_tiet'] ?? '');
    $is_new = isset($_POST['is_new']) ? 1 : 0;

    $imagePath = "";

    if (!empty($_FILES['image']['name'])) {
        $fileName = time() . "_" . basename($_FILES['image']['name']);
        $target = "../imagescategory/" . $fileName;

        if (move_uploaded_file($_FILES['image']['tmp_name'], $target)) {
            $imagePath = "imagescategory/" . $fileName;
        }
    }

    $sql = "INSERT INTO category_products 
            (name, image, price, old_price, sold_count, rating_count, type_slug, is_new, mo_ta_chi_tiet)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $stmt = mysqli_prepare($conn, $sql);

    mysqli_stmt_bind_param(
        $stmt,
        "ssiiiisis",
        $name,
        $imagePath,
        $price,
        $old_price,
        $sold_count,
        $rating_count,
        $type_slug,
        $is_new,
        $mo_ta_chi_tiet
    );

    mysqli_stmt_execute($stmt);

    header("Location: category_products.php");
    exit();
}

        return get_defined_vars();
    }

    public function editCategoryProduct(): array
    {

include dirname(__DIR__, 2) . '/admin' . '/check_admin.php';
include dirname(__DIR__, 2) . '/admin' . '/../connect.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$categories = [
    'vo-oly' => 'Vở ô ly',
    'vo-caro' => 'Vở caro',
    'vo-ke-ngang' => 'Vở kẻ ngang',
    'so-loxo' => 'Sổ lò xo',
    'so-da' => 'Sổ da',
    'but-bi' => 'Bút bi',
    'but-chi' => 'Bút chì',
    'but-muc' => 'Bút mực',
    'but-xoa' => 'Bút xoá',
    'but-quang' => 'Bút dạ quang',
    'thuoc' => 'Thước',
    'note' => 'Giấy note',
    'phan' => 'Phấn',
    'compa' => 'Compa',
    'hop-but' => 'Hộp bút',
    'giay-in' => 'Giấy in',
    'kep-giay' => 'Kẹp giấy',
    'dao-roc' => 'Dao rọc giấy',
    'bia-cong' => 'Bìa còng',
    'keo' => 'Kéo văn phòng',
    'balo-hs' => 'Balo học sinh',
    'balo-vp' => 'Balo văn phòng',
    'casio' => 'Casio',
    'mt-vp' => 'Máy tính văn phòng'
];

$stmt = mysqli_prepare($conn, "SELECT * FROM category_products WHERE id = ?");
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$product = mysqli_fetch_assoc($result);

if (!$product) {
    header("Location: category_products.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $type_slug = $_POST['type_slug'];
    $price = (int)$_POST['price'];
    $old_price = (int)$_POST['old_price'];
    $sold_count = (int)$_POST['sold_count'];
    $rating_count = (int)$_POST['rating_count'];
    $is_new = isset($_POST['is_new']) ? 1 : 0;

    $imagePath = $product['image'];

    if (!empty($_FILES['image']['name'])) {
        $fileName = time() . "_" . basename($_FILES['image']['name']);
        $target = "../imagescategory/" . $fileName;

        if (move_uploaded_file($_FILES['image']['tmp_name'], $target)) {
            $imagePath = "imagescategory/" . $fileName;
        }
    }

    $sql = "UPDATE category_products 
            SET name = ?, image = ?, price = ?, old_price = ?, sold_count = ?, 
                rating_count = ?, type_slug = ?, is_new = ?
            WHERE id = ?";

    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param(
        $stmt,
        "ssiiiisii",
        $name,
        $imagePath,
        $price,
        $old_price,
        $sold_count,
        $rating_count,
        $type_slug,
        $is_new,
        $id
    );

    mysqli_stmt_execute($stmt);

    header("Location: category_products.php");
    exit();
}

        return get_defined_vars();
    }
}
