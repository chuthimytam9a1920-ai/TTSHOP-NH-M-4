<?php
final class MembershipController
{
    public static function uh($v): string {return htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}

    public static function um($v): string {return number_format((int)$v,0,',','.').'đ';}

    public function upgrade(): array
    {

session_start();
require_once (dirname(__DIR__, 2)).'/csrf.php';
require_once (dirname(__DIR__, 2)).'/upgrade_helpers.php';
if(($_SESSION['is_login']??false)!==true || (int)($_SESSION['user_id']??0)<=0){header('Location: dangnhap.php');exit;}
mysqli_report(MYSQLI_REPORT_ERROR|MYSQLI_REPORT_STRICT);
require (dirname(__DIR__, 2)).'/connect.php';
$uid=(int)$_SESSION['user_id'];$token=CsrfService::token();$error='';$o=null;$member=null;$history=[];


$tiers=array_keys(MembershipService::rules());$prices=MembershipUpgradeService::prices();
try {
    $s=$conn->prepare('SELECT email FROM users WHERE id=?');$s->bind_param('i',$uid);$s->execute();$u=$s->get_result()->fetch_assoc();$s->close();
    if(!$u || (isset($_SESSION['user_email'])&&strcasecmp((string)$_SESSION['user_email'],(string)$u['email'])!==0)){header('Location: dangnhap.php');exit;}
    if($_SERVER['REQUEST_METHOD']==='POST'){
        if(!CsrfService::check($_POST['csrf_token']??null)){http_response_code(403);throw new DomainException('Phiên làm việc không hợp lệ. Vui lòng tải lại trang.');}
        $action=$_POST['action']??'';
        if($action==='create'){
            $target=filter_var($_POST['target']??null,FILTER_VALIDATE_INT);$expected=filter_var($_POST['from_rank']??null,FILTER_VALIDATE_INT);
            if($target===false||$expected===false||$expected<0||$expected>4) throw new DomainException('Yêu cầu nâng hạng không hợp lệ.');
            $o=(new MembershipUpgradeService($conn))->createUpgrade($uid,$target,$expected);
            header('Location: nang_hang.php?id='.$o['id'],true,303);exit;
        }
        $id=filter_var($_POST['id']??null,FILTER_VALIDATE_INT);
        $o=$id?(new MembershipUpgradeService($conn))->find($id,$uid):null;
        if(!$o){http_response_code(404);throw new DomainException('Không tìm thấy giao dịch của bạn.');}
        if($action==='pay'){
            $url=(new MembershipUpgradeService($conn))->checkout($o);
            if($url!==''){header('Location: '.$url,true,303);exit;}
        }elseif($action==='check'){
            $o=(new MembershipUpgradeService($conn))->sync($o);
        }else throw new DomainException('Yêu cầu không hợp lệ.');
        header('Location: nang_hang.php?id='.$o['id'],true,303);exit;
    }
}catch(DomainException $e){$error=$e->getMessage();}catch(Throwable $e){error_log('TT SHOP upgrade: '.$e->getMessage());$error='Chưa thể xử lý giao dịch. Vui lòng thử lại hoặc liên hệ hỗ trợ; không chuyển tiền lần thứ hai khi chưa đối soát.';}
try {
    $member=(new MembershipService($conn))->get($uid);
    $active=(new MembershipUpgradeService($conn))->active($uid);
    $id=filter_var($_GET['id']??null,FILTER_VALIDATE_INT);
    if($id){$o=(new MembershipUpgradeService($conn))->find($id,$uid);if(!$o){http_response_code(404);$error='Không tìm thấy giao dịch của bạn.';}}
    if(!$o)$o=$active;
    $s=$conn->prepare('SELECT * FROM membership_upgrades WHERE user_id=? ORDER BY id DESC LIMIT 20');$s->bind_param('i',$uid);$s->execute();$history=$s->get_result()->fetch_all(MYSQLI_ASSOC);$s->close();
}catch(Throwable $e){error_log('TT SHOP upgrade view: '.$e->getMessage());$error='Chưa thể tải thông tin nâng hạng. Kiểm tra migration SQL và kết nối database.'; $member=null;}
$labels=['PENDING'=>'Chờ thanh toán','PAID'=>'Đã thanh toán','CANCELLED'=>'Đã hủy','EXPIRED'=>'Hết hạn','REVIEW'=>'Cần đối soát'];
header('Cache-Control: no-store, private');

        return get_defined_vars();
    }
}
