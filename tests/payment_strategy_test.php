<?php
// CLI-only tests with a fake gateway: no DB or network calls.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__.'/../app/bootstrap.php';

final class FakePayOSOrderGateway implements PayOSOrderGatewayInterface
{
    public bool $ready = true;
    public string $link = 'https://pay.payos.vn/web/test';
    public bool $failLink = false;
    public bool $failAllocate = false;
    public array $calls = [];
    public function isReady(): bool { $this->calls[] = 'ready'; return $this->ready; }
    public function allocate(int $orderId): void {
        $this->calls[] = ['allocate', $orderId];
        if ($this->failAllocate) throw new RuntimeException('mock allocation failure');
    }
    public function createLink(int $orderId, int $userId): string {
        $this->calls[] = ['link', $orderId, $userId];
        if ($this->failLink) throw new RuntimeException('mock provider failure');
        return $this->link;
    }
}

$count = 0;
function checkSame($expected, $actual, string $name): void {
    global $count;
    if ($expected !== $actual) throw new RuntimeException('FAIL: '.$name.' expected '.var_export($expected,true).' got '.var_export($actual,true));
    $count++; echo 'PASS: '.$name.PHP_EOL;
}
function expectFailure(string $type, string $message, callable $action, string $name): void {
    try { $action(); } catch (Throwable $e) {
        checkSame([$type,$message],[get_class($e),$e->getMessage()],$name); return;
    }
    throw new RuntimeException('FAIL: '.$name.' (no exception)');
}
function perform(PaymentMethod $method, int $total): string {
    $method->validate($total);
    $method->initialize(17);
    return $method->successUrl(17,9,'TT 123');
}
$cod = new CodPaymentMethod();
$gateway = new FakePayOSOrderGateway();
$qr = new PayOSPaymentMethod($gateway);
checkSame(true,$cod instanceof PaymentMethod,'COD contract');
checkSame(true,$qr instanceof PaymentMethod,'payOS contract');
checkSame('xuly_dathang.php?success=TT%20123',perform($cod,1000),'COD normal flow');
checkSame('xuly_dathang.php?success=TT%20123',perform($cod,0),'COD zero total unchanged');
checkSame('xuly_dathang.php?success=TT%2B1',$cod->resumeUrl(17,'TT+1'),'COD replay uses encoded code');
checkSame('https://pay.payos.vn/web/test',perform($qr,1000),'payOS success link');
checkSame(['ready',['allocate',17],['link',17,9]],$gateway->calls,'payOS call order and arguments');
$gateway->calls=[];
checkSame('thanhtoan_qr.php?order_id=17',$qr->resumeUrl(17,'TT 123'),'payOS replay QR');
checkSame([],$gateway->calls,'payOS replay does not contact provider');
$gateway->link='';
checkSame('thanhtoan_qr.php?order_id=17',$qr->successUrl(17,9,'TT 123'),'empty link fallback');
$gateway->failLink=true;
checkSame('thanhtoan_qr.php?order_id=17',$qr->successUrl(17,9,'TT 123'),'provider failure fallback (expected error log)');
$gateway->failAllocate=true;
expectFailure(RuntimeException::class,'mock allocation failure',fn()=>$qr->initialize(17),'allocation failure propagates for rollback');
$validation='Thanh toán QR chưa sẵn sàng hoặc tổng tiền không hợp lệ. Vui lòng chọn COD.';
expectFailure(DomainException::class,$validation,fn()=>$qr->validate(0),'payOS zero rejected');
expectFailure(DomainException::class,$validation,fn()=>$qr->validate(-1),'payOS negative rejected');
$gateway->ready=false;
expectFailure(DomainException::class,$validation,fn()=>$qr->validate(1000),'payOS unavailable rejected');
checkSame(true,is_subclass_of(PayOSOrderGateway::class,DatabaseService::class),'gateway inherits shared DB class');
checkSame(true,(new ReflectionClass(PayOSPaymentMethod::class))->getProperty('gateway')->isPrivate(),'gateway encapsulated');
checkSame(true,(new ReflectionClass(DatabaseService::class))->isAbstract(),'database abstraction');
echo 'Passed '.$count.' checks.'.PHP_EOL;
