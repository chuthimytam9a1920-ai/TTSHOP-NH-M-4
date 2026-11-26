<?php
final class PaymentMethodFactory
{
    public static function forCode(string $code, mysqli $connection): PaymentMethod
    {
        switch ($code) {
            case 'COD': return new CodPaymentMethod();
            case 'PAYOS': return new PayOSPaymentMethod(new PayOSOrderGateway($connection));
            default: throw new InvalidArgumentException('Phương thức thanh toán không hợp lệ.');
        }
    }
}
