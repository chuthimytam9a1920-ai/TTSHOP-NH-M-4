<?php
final class CodPaymentMethod implements PaymentMethod
{
    public function validate(int $total): void
    {
        // Existing COD flow has no provider-specific validation.
    }

    public function initialize(int $orderId): void
    {
        // COD needs no external payment code; keep database defaults.
    }

    public function successUrl(int $orderId, int $userId, string $orderCode): string
    {
        return $this->resumeUrl($orderId, $orderCode);
    }

    public function resumeUrl(int $orderId, string $orderCode): string
    {
        return 'xuly_dathang.php?success='.rawurlencode($orderCode);
    }
}
