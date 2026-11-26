<?php
final class CartService
{
    private array $session;

    public function __construct(array &$session)
    {
        $this->session = &$session;
    }

    public function contains($id): bool
    {
        return $id !== '' && isset($this->session['cart'][$id]);
    }

    public function remove($id): void
    {
        if ($this->contains($id)) unset($this->session['cart'][$id]);
    }

    public function changeQuantity($id, $action): array
    {
// Xử lý hành động "plus": Tăng số lượng sản phẩm hiện tại lên 1 đơn vị
if ($action === 'plus') {
    $this->session['cart'][$id]['qty'] = (int)($this->session['cart'][$id]['qty'] ?? 1) + 1;
}

// Xử lý hành động "minus": Giảm số lượng đi 1. Nếu số lượng bằng hoặc nhỏ hơn 0 thì xóa hẳn sản phẩm khỏi giỏ hàng (unset)
if ($action === 'minus') {
    $this->session['cart'][$id]['qty'] = (int)($this->session['cart'][$id]['qty'] ?? 1) - 1;
    if ($this->session['cart'][$id]['qty'] <= 0) {
        unset($this->session['cart'][$id]); 
    }
}

// Khởi tạo các biến để tính toán lại số liệu mới sau khi tăng/giảm
$total = 0;
$cart_count = 0;
$item_total = "0đ";
$qty = 0;

// Tính riêng "Thành tiền" (Giá x Số lượng) của sản phẩm vừa chỉnh sửa (nếu sản phẩm đó chưa bị xóa)
if (isset($this->session['cart'][$id])) {
    $price = (int)($this->session['cart'][$id]['price'] ?? 0);
    $qty = (int)($this->session['cart'][$id]['qty'] ?? 1);
    $item_total = number_format($price * $qty, 0, ',', '.') . 'đ';
}

// Vòng lặp duyệt qua toàn bộ giỏ hàng để tính lại: Tổng tiền mới ($total) và Tổng số lượng tất cả sản phẩm ($cart_count)
foreach ($this->session['cart'] as $item) {
    $price = (int)($item['price'] ?? 0);
    $itemQty = (int)($item['qty'] ?? 1);
    $total += $price * $itemQty;
    $cart_count += $itemQty;
}

        return [
            'status' => 'success',
            'qty' => $qty,
            'item_total' => $item_total,
            'total' => number_format($total, 0, ',', '.') . 'đ',
            'cart_count' => $cart_count,
        ];
    }
}
