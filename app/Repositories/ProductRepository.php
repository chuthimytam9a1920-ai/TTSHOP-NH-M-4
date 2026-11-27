<?php
final class ProductRepository extends DatabaseService
{
    public function featured($categoryId)
    {
        //Tìm kiếm sản phẩm theo category_id và sắp xếp theo id giảm dần, giới hạn 8 sản phẩm
        $sql = "SELECT * FROM products
            WHERE category_id = '$categoryId'
            ORDER BY id DESC
            LIMIT 8";
        return mysqli_query($this->connection, $sql);
    }

    public function findForCart(int $id, bool $category): ?array
    {
        // Tìm kiếm sản phẩm theo id trong bảng products hoặc category_products dựa trên giá trị của biến $category
        $table = $category ? 'category_products' : 'products';
        $stmt = mysqli_prepare($this->connection, "SELECT * FROM $table WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "i", $id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $product = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);
        return $product;
    }
}
