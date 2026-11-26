<?php
final class CatalogController
{
    public static function category_buildQuery($extra = []) {
    $query = $_GET;

    foreach ($extra as $key => $value) {
        $query[$key] = $value;
    }

    return http_build_query($query);
}

    public static function category_tinhGiamGia($oldPrice, $price) {
    $oldPrice = (int)$oldPrice;
    $price = (int)$price;

    if ($oldPrice > 0 && $price > 0 && $oldPrice > $price) {
        return round((($oldPrice - $price) / $oldPrice) * 100);
    }

    return 0;
}

    public function category(): array
    {

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include dirname(__DIR__, 2) . '/connect.php';

$type = $_GET['type'] ?? 'vo-oly';

$categoryNames = [
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

$title = $categoryNames[$type] ?? 'Danh mục sản phẩm';

$categoryBanners = [
    'vo-oly' => 'imagescategory/vs.png',
    'vo-caro' => 'imagescategory/vs.png',
    'vo-ke-ngang' => 'imagescategory/vs.png',
    'so-loxo' => 'imagescategory/vs.png',
    'so-da' => 'imagescategory/vs.png',

    'but-bi' => 'imagescategory/bu.png',
    'but-chi' => 'imagescategory/bu.png',
    'but-muc' => 'imagescategory/bu.png',
    'but-xoa' => 'imagescategory/bu.png',
    'but-quang' => 'imagescategory/bu.png',

    'thuoc' => 'imagescategory/đư.png',
    'note' => 'imagescategory/đư.png',
    'phan' => 'imagescategory/đư.png',
    'compa' => 'imagescategory/đư.png',
    'hop-but' => 'imagescategory/đư.png',

    'giay-in' => 'imagescategory/ba.png',
    'kep-giay' => 'imagescategory/ba.png',
    'dao-roc' => 'imagescategory/ba.png',
    'bia-cong' => 'imagescategory/ba.png',
    'keo' => 'imagescategory/ba.png',

    'balo-hs'=>'imagescategory/olyy.png',
    'balo-vp' =>'imagescategory/olyy.png',

    'casio'=>'imagescategory/maytinhh.png',
    'mt-vp' =>'imagescategory/maytinhh.png'
];

$banner = $categoryBanners[$type] ?? 'images/olyy.png';

$search = trim($_GET['search'] ?? '');
$filter = $_GET['filter'] ?? [];
$priceFilter = $_GET['price'] ?? [];
$sort = $_GET['sort'] ?? '';

if (!is_array($filter)) $filter = [];
if (!is_array($priceFilter)) $priceFilter = [];

$where = [];
$params = [];
$types = "";

if ($search !== '') {
    $where[] = "name LIKE ?";
    $params[] = "%" . $search . "%";
    $types .= "s";
    $title = "Kết quả tìm kiếm: " . $search;
} else {
    $where[] = "type_slug = ?";
    $params[] = $type;
    $types .= "s";
}

if (!empty($filter)) {
    $filterSql = [];

    if (in_array('new', $filter)) {
        $filterSql[] = "is_new = 1";
    }

    if (in_array('hot', $filter)) {
        $filterSql[] = "sold_count > 500";
    }

    if (in_array('sale', $filter)) {
        $filterSql[] = "old_price > price";
    }

    if (!empty($filterSql)) {
        $where[] = "(" . implode(" OR ", $filterSql) . ")";
    }
}

if (!empty($priceFilter)) {
    $priceSql = [];

    if (in_array('under50', $priceFilter)) {
        $priceSql[] = "price < 50000";
    }

    if (in_array('50to100', $priceFilter)) {
        $priceSql[] = "price >= 50000 AND price <= 100000";
    }

    if (in_array('100to500', $priceFilter)) {
        $priceSql[] = "price > 100000 AND price <= 500000";
    }

    if (in_array('500to800', $priceFilter)) {
        $priceSql[] = "price > 500000 AND price <= 800000";
    }

    if (in_array('over800', $priceFilter)) {
        $priceSql[] = "price > 800000";
    }

    if (!empty($priceSql)) {
        $where[] = "(" . implode(" OR ", $priceSql) . ")";
    }
}

$whereSql = implode(" AND ", $where);

$orderSql = "ORDER BY id DESC";

if ($sort === 'asc') {
    $orderSql = "ORDER BY price ASC";
}

if ($sort === 'desc') {
    $orderSql = "ORDER BY price DESC";
}

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;

$limit = 4;
$start = ($page - 1) * $limit;

$sqlCount = "SELECT COUNT(*) AS total FROM category_products WHERE $whereSql";
$stmtCount = mysqli_prepare($conn, $sqlCount);

if (!empty($params)) {
    mysqli_stmt_bind_param($stmtCount, $types, ...$params);
}

mysqli_stmt_execute($stmtCount);
$countResult = mysqli_stmt_get_result($stmtCount);
$countRow = mysqli_fetch_assoc($countResult);

$total = $countRow['total'] ?? 0;
$totalPages = ceil($total / $limit);

$sql = "SELECT * FROM category_products WHERE $whereSql $orderSql LIMIT ?, ?";

$paramsProduct = $params;
$paramsProduct[] = $start;
$paramsProduct[] = $limit;

$typesProduct = $types . "ii";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, $typesProduct, ...$paramsProduct);
mysqli_stmt_execute($stmt);

$resultProducts = mysqli_stmt_get_result($stmt);





        return get_defined_vars();
    }

    public function productDetail(): array
    {

include dirname(__DIR__, 2) . '/connect.php';

$rawId = $_GET['id'] ?? '';
// ID số thuộc products; ID cat_N thuộc category_products.
// Chỉ chọn tên bảng từ hai giá trị cố định, không lấy trực tiếp từ URL.
$isCategory = false;
$productId = 0;
if (is_string($rawId) && preg_match('/^(cat_)?([1-9][0-9]{0,9})$/D', $rawId, $matches)) {
    if ((float)$matches[2] <= 2147483647) {
        $isCategory = $matches[1] === 'cat_';
        $productId = (int)$matches[2];
    }
}
$product = null;
if ($productId > 0) {
    $table = $isCategory ? 'category_products' : 'products';
    $stmt = mysqli_prepare($conn, "SELECT * FROM $table WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "i", $productId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $product = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
}

if (!$product) {
    http_response_code(404);
    die("Không tìm thấy sản phẩm.");
}

$cartId = $isCategory ? 'cat_' . $productId : (string)$productId;
$imagePath = $isCategory ? (string)$product['image'] : 'images/' . $product['image'];
$backUrl = $isCategory ? 'category.php?type=' . rawurlencode($product['type_slug']) : 'index.php';
$backLabel = $isCategory ? 'Quay lại danh mục' : 'Quay lại trang chủ';

        return get_defined_vars();
    }
}
