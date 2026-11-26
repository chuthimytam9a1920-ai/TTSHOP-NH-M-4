<?php
final class AdminProductController
{
    public function deleteProduct()
    {

include dirname(__DIR__, 2) . '/admin' . '/check_admin.php';
include dirname(__DIR__, 2) . '/admin' . '/../connect.php';

$id = (int)($_GET['id'] ?? 0);

if ($id > 0) {
    $stmt = mysqli_prepare($conn, "SELECT image FROM products WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);

    $result = mysqli_stmt_get_result($stmt);
    $product = mysqli_fetch_assoc($result);

    if ($product && !empty($product['image'])) {
        $imagePath = "../images/" . $product['image'];

        if (file_exists($imagePath)) {
            unlink($imagePath);
        }
    }

    $stmtDelete = mysqli_prepare($conn, "DELETE FROM products WHERE id = ?");
    mysqli_stmt_bind_param($stmtDelete, "i", $id);
    mysqli_stmt_execute($stmtDelete);
}

header("Location: products.php");
exit();

    }

    public function deleteCategoryProduct()
    {

include dirname(__DIR__, 2) . '/admin' . '/check_admin.php';
include dirname(__DIR__, 2) . '/admin' . '/../connect.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id > 0) {
    $stmt = mysqli_prepare($conn, "SELECT image FROM category_products WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);

    $result = mysqli_stmt_get_result($stmt);
    $product = mysqli_fetch_assoc($result);

    if ($product && !empty($product['image'])) {
        $imagePath = "../" . $product['image'];

        if (file_exists($imagePath)) {
            unlink($imagePath);
        }
    }

    $stmtDelete = mysqli_prepare($conn, "DELETE FROM category_products WHERE id = ?");
    mysqli_stmt_bind_param($stmtDelete, "i", $id);
    mysqli_stmt_execute($stmtDelete);
}

header("Location: category_products.php");
exit();
    }
}
