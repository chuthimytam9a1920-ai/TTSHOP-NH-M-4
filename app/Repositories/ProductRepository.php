<?php
final class ProductRepository extends DatabaseService
{
    public function featured($categoryId)
    {
        // Original featured query; current caller uses fixed category IDs.
        $sql = "SELECT * FROM products
            WHERE category_id = '$categoryId'
            ORDER BY id DESC
            LIMIT 8";
        return mysqli_query($this->connection, $sql);
    }

    public function findForCart(int $id, bool $category): ?array
    {
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
