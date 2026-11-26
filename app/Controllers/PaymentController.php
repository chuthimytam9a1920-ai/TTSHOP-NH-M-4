<?php
final class PaymentController
{
    public function status()
    {

if(session_status()===PHP_SESSION_NONE) session_start();
header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store, private');
require_once (dirname(__DIR__, 2)).'/payment_helpers.php';
if (($_SESSION['is_login']??false)!==true || (int)($_SESSION['user_id']??0)<=0) { http_response_code(401); echo '{}'; exit; }
$uid=(int)$_SESSION['user_id']; session_write_close();
$id=filter_var($_GET['id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1,'max_range'=>2147483647]]);
if(!$id){http_response_code(404);echo '{}';exit;}
try {
    mysqli_report(MYSQLI_REPORT_ERROR|MYSQLI_REPORT_STRICT);require (dirname(__DIR__, 2)).'/connect.php';
    $o=(new PayOSService($conn))->order($id,$uid);
    if(!$o||$o['phuong_thuc']!=='PAYOS'){http_response_code(404);echo '{}';exit;}
    if(in_array($o['payment_status'],['PENDING','REVIEW'],true)) $o=(new PayOSService($conn))->sync($o);
    echo json_encode(['status'=>$o['payment_status'],'received'=>(int)$o['payment_amount_received']]);
} catch(Throwable $e){error_log('TT SHOP payOS poll: '.$e->getMessage());http_response_code(503);echo '{}';}

    }

    private static function webhook_reply(int $status): void { http_response_code($status); echo json_encode(['ok'=>$status===200]); exit; }

    public function webhook()
    {

// Public endpoint; no session, no trust in browser redirects. Only authenticated payOS signals.
header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');
require_once (dirname(__DIR__, 2)).'/upgrade_helpers.php';

if(($_SERVER['REQUEST_METHOD']??'')!=='POST') self::webhook_reply(405);
if(!PayOSService::ready()) self::webhook_reply(503);
try {
    $raw=file_get_contents('php://input',false,null,0,65537);if(strlen($raw)>65536) self::webhook_reply(413);
    $p=json_decode($raw,true,512,JSON_THROW_ON_ERROR);$d=$p['data']??null;$sig=$p['signature']??null;
    if(!is_array($d)||!is_string($sig)||!hash_equals(PayOSService::signature($d,PayOSService::config()['checksum_key']),$sig)) self::webhook_reply(401);
    if(!isset($d['orderCode'])||!is_int($d['orderCode'])) self::webhook_reply(400);
    if(($p['code']??'')!=='00'||($p['success']??false)!==true||($d['code']??'')!=='00') self::webhook_reply(200);
    mysqli_report(MYSQLI_REPORT_ERROR|MYSQLI_REPORT_STRICT);require (dirname(__DIR__, 2)).'/connect.php';
    $code=$d['orderCode'];$s=$conn->prepare("SELECT * FROM donhang WHERE payos_order_code=? AND phuong_thuc='PAYOS'");$s->bind_param('i',$code);$s->execute();$o=$s->get_result()->fetch_assoc();$s->close();
    if(!$o) {
        if($code>=1000000000 && $code<=9999999999){
            $s=$conn->prepare('SELECT * FROM membership_upgrades WHERE order_code=?');$s->bind_param('i',$code);$s->execute();$up=$s->get_result()->fetch_assoc();$s->close();
            if($up){
                if(($d['currency']??'')!=='VND'||(!empty($up['link_id'])&&!hash_equals($up['link_id'],(string)($d['paymentLinkId']??'')))) self::webhook_reply(400);
                (new MembershipUpgradeService($conn))->sync($up);
            }
        }
        self::webhook_reply(200);
    }
    if(($d['currency']??'')!=='VND'||(!empty($o['payos_link_id'])&&!hash_equals($o['payos_link_id'],(string)($d['paymentLinkId']??'')))) self::webhook_reply(400);
    (new PayOSService($conn))->sync($o,true); // authoritative GET handles partial payments and duplicated webhooks.
    self::webhook_reply(200);
} catch(JsonException $e){self::webhook_reply(400);}catch(Throwable $e){error_log('TT SHOP payOS webhook: '.$e->getMessage());self::webhook_reply(503);}

    }

    public function syncCli()
    {

// Run every minute via Windows Task Scheduler or cron; cannot be invoked over HTTP.
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once (dirname(__DIR__, 2)).'/upgrade_helpers.php';
if(!PayOSService::ready()){fwrite(STDERR,"payOS not configured\n");exit(1);}
mysqli_report(MYSQLI_REPORT_ERROR|MYSQLI_REPORT_STRICT);require (dirname(__DIR__, 2)).'/connect.php';
// Advisory lock prevents overlapping scheduled runs.
if((int)$conn->query("SELECT GET_LOCK('ttshop_payos_sync',0)")->fetch_row()[0]!==1) exit;
$errors=0;
try {
    $rows=$conn->query("SELECT * FROM donhang WHERE phuong_thuc='PAYOS' AND payment_status IN ('PENDING','REVIEW') AND (payment_checked_at IS NULL OR payment_checked_at < NOW()-INTERVAL 45 SECOND) ORDER BY COALESCE(payment_checked_at,'2000-01-01') LIMIT 20")->fetch_all(MYSQLI_ASSOC);
    foreach($rows as $o){try{(new PayOSService($conn))->sync($o,true);}catch(Throwable $e){$errors++;error_log('TT SHOP sync order '.(int)$o['id'].': '.$e->getMessage());}usleep(200000);}
    $upgrades=$conn->query("SELECT * FROM membership_upgrades WHERE status IN ('PENDING','REVIEW') AND link_id IS NOT NULL AND (checked_at IS NULL OR checked_at < NOW()-INTERVAL 45 SECOND) ORDER BY COALESCE(checked_at,'2000-01-01') LIMIT 20")->fetch_all(MYSQLI_ASSOC);
    foreach($upgrades as $up){try{(new MembershipUpgradeService($conn))->sync($up);}catch(Throwable $e){$errors++;error_log('TT SHOP upgrade sync '.(int)$up['id'].': '.$e->getMessage());}}
} finally {$conn->query("SELECT RELEASE_LOCK('ttshop_payos_sync')");}
exit($errors?1:0);

    }
}
