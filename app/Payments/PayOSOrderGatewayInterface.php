<?php
/** Isolates provider/DB operations from the payment strategy. */
interface PayOSOrderGatewayInterface
{
    public function isReady(): bool;
    public function allocate(int $orderId): void;
    public function createLink(int $orderId, int $userId): string;
}
