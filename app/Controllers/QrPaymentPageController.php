<?php
final class QrPaymentPageController
{
    public function show(): array
    {

if (session_status() === PHP_SESSION_NONE) session_start();
require_once (dirname(__DIR__, 2)).'/csrf.php';
require_once (dirname(__DIR__, 2)).'/payment_helpers.php';
header('Cache-Control: no-store, private');
if (($_SESSION['is_login'] ?? false) !== true || (int)($_SESSION['user_id'] ?? 0)<=0) { header('Location: dangnhap.php'); exit; }
$userId=(int)$_SESSION['user_id'];
$id=filter_var($_GET['order_id'] ?? null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1,'max_range'=>2147483647]]);
if (!$id) { http_response_code(404); exit('Không tìm thấy đơn hàng.'); }
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
require (dirname(__DIR__, 2)).'/connect.php';
$o=(new PayOSService($conn))->order($id,$userId);
if (!$o || $o['phuong_thuc']!=='PAYOS') { http_response_code(404); exit('Không tìm thấy đơn hàng QR.'); }
$token=CsrfService::token(); $error='';
if ($_SERVER['REQUEST_METHOD']==='POST') {
    if (!CsrfService::check($_POST['csrf_token'] ?? null)) { http_response_code(403); exit('Phiên không hợp lệ.'); }
    try {
        if (($_POST['action'] ?? '')==='open') {
            $url=(new PayOSService($conn))->create($o);
            if ($url!=='') { header('Location: '.$url,true,303); exit; }
        } elseif (($_POST['action'] ?? '')==='cancel' && $o['payment_status']==='PENDING') {
            $o=(new PayOSService($conn))->sync($o,true);
            if ($o['payment_status']==='PENDING' && (int)$o['payment_amount_received']===0) {
                $d=PayOSService::api('POST','/v2/payment-requests/'.$o['payos_order_code'].'/cancel',['cancellationReason'=>'Khach huy don']);
                (new PayOSService($conn))->apply($id,$d);
            }
        }
        header('Location: thanhtoan_qr.php?order_id='.$id,true,303); exit;
    } catch (Throwable $e) { error_log('TT SHOP payOS page: '.$e->getMessage()); $error='Chưa thể xử lý yêu cầu. Hãy kiểm tra lại hoặc liên hệ TT SHOP; không chuyển khoản lần hai.'; }
} elseif ($_SERVER['REQUEST_METHOD']!=='GET') { http_response_code(405); exit; }
try { if (in_array($o['payment_status'],['PENDING','REVIEW'],true) && PayOSService::ready()) $o=(new PayOSService($conn))->sync($o); }
catch(Throwable $e) { error_log('TT SHOP payOS check: '.$e->getMessage()); }
$paid=$o['payment_status']==='PAID'; $pending=$o['payment_status']==='PENDING';

        return get_defined_vars();
    }
}
