<?php
final class PayOSOrderGateway extends DatabaseService implements PayOSOrderGatewayInterface
{
    public function isReady(): bool
    {
        return PayOSService::ready();
    }

    public function allocate(int $orderId): void
    {
        // Same SQL, random code range and duplicate retry as the original checkout.
        // The caller must commit this code before contacting the provider.
        $s = $this->connection->prepare("UPDATE donhang SET payment_status='PENDING',payos_order_code=? WHERE id=?");
        try {
            for ($attempt=0; $attempt<5; $attempt++) {
                $payosCode=random_int(100000000,999999999); $s->bind_param('ii',$payosCode,$orderId);
                try { $s->execute(); break; } catch (mysqli_sql_exception $ex) { if ($ex->getCode()!==1062 || $attempt===4) throw $ex; }
            }
        } finally { $s->close(); }
    }

    public function createLink(int $orderId, int $userId): string
    {
        $service = new PayOSService($this->connection);
        return $service->create($service->order($orderId,$userId));
    }
}
