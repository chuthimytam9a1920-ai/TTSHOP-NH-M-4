<?php
final class CustomerFeedbackController
{
    public function show(): array
    {

session_start();
require (dirname(__DIR__, 2)).'/connect.php';
require (dirname(__DIR__, 2)).'/phananh_helpers.php';
header('Cache-Control: private, no-store');
$user=null; $error=''; $flash=''; $orders=[]; $items=[]; $detail=null; $replies=[]; $total=0;
$id=FeedbackService::id($_GET['id']??null); $p=max(1,min(100000,FeedbackService::id($_GET['p']??null)?:1));
$kind=FeedbackService::text($_POST['loai']??''); $subject=FeedbackService::text($_POST['tieu_de']??''); $body=FeedbackService::text($_POST['noi_dung']??''); $order=FeedbackService::text($_POST['ma_don']??'');
try {
    $user=(new FeedbackService($conn))->customer();
    if ($user) {
        $uid=(int)$user['id'];
        $flash=FeedbackService::getFlash('pa_customer_flash',$uid);
        if ($_SERVER['REQUEST_METHOD']==='POST') {
            try {
                if (!CsrfService::check($_POST['csrf_token']??null)) throw new InvalidArgumentException('Phiên gửi không hợp lệ. Hãy tải lại trang và thử lại.');
                if (!preg_match('//u',$body) || FeedbackService::len($body)<5 || FeedbackService::len($body)>2000) throw new InvalidArgumentException('Nội dung phải từ 5 đến 2.000 ký tự.');
                $action=FeedbackService::text($_POST['action']??'');
                if ($action==='create') {
                    if (!in_array($kind,FeedbackService::types(),true) || !preg_match('//u',$subject) || FeedbackService::len($subject)<3 || FeedbackService::len($subject)>150) throw new InvalidArgumentException('Chọn loại phản ánh và nhập tiêu đề từ 3 đến 150 ký tự.');
                    if ($order!=='' && !(new FeedbackService($conn))->one('SELECT id FROM donhang WHERE user_id=? AND ma_don=?','is',[$uid,$order])) throw new InvalidArgumentException('Mã đơn hàng không thuộc tài khoản của bạn.');
                    [$mime,$bytes]=FeedbackService::upload();
                     $insert=(new FeedbackService($conn))->stmt('INSERT INTO phan_anh(user_id,ma_don,loai,tieu_de,noi_dung,anh_mime,anh) VALUES (?,?,?,?,?,?,?)','issssss',[$uid,$order===''?null:$order,$kind,$subject,$body,$mime,$bytes]);
                    $newId=(int)$insert->insert_id; $insert->close();
                    FeedbackService::flash('pa_customer_flash',$uid,'Đã gửi phản ánh '.FeedbackService::code($newId).'. Bạn có thể theo dõi phản hồi tại đây.');
                    header('Location: phananh.php?id='.$newId); exit;
                }
                if ($action==='reply') {
                    $id=FeedbackService::id($_POST['id']??null);
                    $conn->begin_transaction();
                    try {
                        if (!(new FeedbackService($conn))->one('SELECT id FROM phan_anh WHERE id=? AND user_id=? FOR UPDATE','ii',[$id,$uid])) throw new InvalidArgumentException('Không tìm thấy phản ánh của bạn.');
                        (new FeedbackService($conn))->exec("INSERT INTO phan_anh_tra_loi(phan_anh_id,nguoi_gui,noi_dung) VALUES (?,'customer',?)",'is',[$id,$body]);
                        (new FeedbackService($conn))->exec("UPDATE phan_anh SET trang_thai='pending',updated_at=NOW() WHERE id=? AND user_id=?",'ii',[$id,$uid]);
                        $conn->commit();
                    } catch (Throwable $e) { $conn->rollback(); throw $e; }
                    FeedbackService::flash('pa_customer_flash',$uid,'Đã gửi nội dung bổ sung. Phản ánh được chuyển về chờ xử lý.');
                    header('Location: phananh.php?id='.$id); exit;
                }
                throw new InvalidArgumentException('Yêu cầu không hợp lệ.');
            } catch (InvalidArgumentException $e) { $error=$e->getMessage(); }
        }
        $orders=(new FeedbackService($conn))->rows('SELECT ma_don,created_at FROM donhang WHERE user_id=? ORDER BY id DESC','i',[$uid]);
        $total=(int)(new FeedbackService($conn))->one('SELECT COUNT(*) AS n FROM phan_anh WHERE user_id=?','i',[$uid])['n'];
        $p=min($p,max(1,(int)ceil($total/10)));
        $items=(new FeedbackService($conn))->rows('SELECT id,tieu_de,loai,trang_thai,updated_at FROM phan_anh WHERE user_id=? ORDER BY updated_at DESC,id DESC LIMIT 10 OFFSET ?','ii',[$uid,($p-1)*10]);
        if ($id) {
            $detail=(new FeedbackService($conn))->one('SELECT id,ma_don,loai,tieu_de,noi_dung,trang_thai,created_at,updated_at,anh IS NOT NULL AS has_image FROM phan_anh WHERE id=? AND user_id=?','ii',[$id,$uid]);
            if (!$detail) { http_response_code(404); $error='Không tìm thấy phản ánh thuộc tài khoản của bạn.'; }
            else $replies=(new FeedbackService($conn))->rows('SELECT nguoi_gui,noi_dung,created_at FROM phan_anh_tra_loi WHERE phan_anh_id=? ORDER BY id','i',[$id]);
        }
    } elseif ($_SERVER['REQUEST_METHOD']==='POST') { http_response_code(403); $error='Vui lòng đăng nhập lại trước khi gửi.'; }
} catch (Throwable $e) { $error=FeedbackService::error($e); }

        return get_defined_vars();
    }
}
