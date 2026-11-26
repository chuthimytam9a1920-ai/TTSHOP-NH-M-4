<?php
final class PayOSPaymentMethod implements PaymentMethod
{
    private PayOSOrderGatewayInterface $gateway;

    public function __construct(PayOSOrderGatewayInterface $gateway)
    {
        $this->gateway = $gateway;
    }

    public function validate(int $total): void
    {
        if (!$this->gateway->isReady() || $total <= 0) {
            throw new DomainException('Thanh toán QR chưa sẵn sàng hoặc tổng tiền không hợp lệ. Vui lòng chọn COD.');
        }
    }

    public function initialize(int $orderId): void
    {
        $this->gateway->allocate($orderId);
    }

    public function successUrl(int $orderId, int $userId, string $orderCode): string
    {
        // Called only AFTER the existing transaction commits.
        // A provider failure must not recreate the order.
        try {
            $url = $this->gateway->createLink($orderId, $userId);
            if ($url !== '') return $url;
        } catch (Throwable $e) { error_log('TT SHOP payOS create: '.$e->getMessage()); }
        return $this->resumeUrl($orderId, $orderCode);
    }

    public function resumeUrl(int $orderId, string $orderCode): string
    {
        // Replayed requests return to the saved order, without creating a new link.
        return 'thanhtoan_qr.php?order_id='.$orderId;
    }
}
