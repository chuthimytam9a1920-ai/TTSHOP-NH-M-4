<?php
/** Strategy contract for checkout. The controller owns the transaction. */
interface PaymentMethod
{
    public function validate(int $total): void;
    public function initialize(int $orderId): void;
    public function successUrl(int $orderId, int $userId, string $orderCode): string;
    public function resumeUrl(int $orderId, string $orderCode): string;
}
