<?php
final class AdminFeedbackController
{
    public function feedback(): array
    {

require (dirname(__DIR__, 2) . '/admin').'/check_admin.php';
require (dirname(__DIR__, 2) . '/admin').'/../connect.php';
require (dirname(__DIR__, 2) . '/admin').'/../phananh_helpers.php';
header('Cache-Control: private, no-store');
$page='feedback'; $error=''; $flash=''; $items=[]; $detail=null; $replies=[]; $total=0; $counts=array_fill_keys(array_keys(FeedbackService::statuses()),0);
$id=FeedbackService::id($_GET['id']??null); $p=max(1,min(100000,FeedbackService::id($_GET['p']??null)?:1));
$filter=FeedbackService::text($_GET['status']??''); if(!isset(FeedbackService::statuses()[$filter])) $filter='';
$q=FeedbackService::text($_GET['q']??''); if(!preg_match('//u',$q)||FeedbackService::len($q)>150) $q='';
$reply=FeedbackService::text($_POST['noi_dung']??'');
try {
    $aid=(new FeedbackService($conn))->admin();
    if (!$aid) { http_response_code(403); exit('Phiên Admin không hợp lệ. Vui lòng đăng nhập lại.'); }
    $flash=FeedbackService::getFlash('pa_admin_flash',$aid);
    if ($_SERVER['REQUEST_METHOD']==='POST') {
        try {
            if (!CsrfService::check($_POST['csrf_token']??null)) throw new InvalidArgumentException('Phiên gửi không hợp lệ. Vui lòng tải lại trang.');
            $id=FeedbackService::id($_POST['id']??null); $status=FeedbackService::text($_POST['trang_thai']??'');
            if (!$id || !isset(FeedbackService::statuses()[$status]) || !preg_match('//u',$reply) || FeedbackService::len($reply)>2000 || ($reply!=='' && FeedbackService::len($reply)<5)) throw new InvalidArgumentException('Kiểm tra trạng thái và nội dung phản hồi (5–2.000 ký tự nếu có).');
            $conn->begin_transaction();
            try {
                if (!(new FeedbackService($conn))->one('SELECT id FROM phan_anh WHERE id=? FOR UPDATE','i',[$id])) throw new InvalidArgumentException('Phản ánh không tồn tại.');
                if ($status==='resolved' && $reply==='' && !(new FeedbackService($conn))->one("SELECT id FROM phan_anh_tra_loi WHERE phan_anh_id=? AND nguoi_gui='admin' LIMIT 1",'i',[$id])) throw new InvalidArgumentException('Hãy gửi ít nhất một phản hồi cho khách trước khi đánh dấu đã xử lý.');
                if ($reply!=='') (new FeedbackService($conn))->exec("INSERT INTO phan_anh_tra_loi(phan_anh_id,nguoi_gui,admin_id,noi_dung) VALUES (?,'admin',?,?)",'iis',[$id,$aid,$reply]);
                (new FeedbackService($conn))->exec('UPDATE phan_anh SET trang_thai=?,updated_at=NOW() WHERE id=?','si',[$status,$id]);
                $conn->commit();
            } catch(Throwable $e) { $conn->rollback(); throw $e; }
            FeedbackService::flash('pa_admin_flash',$aid,$reply!==''?'Đã lưu phản hồi và trạng thái. Khách hàng có thể xem ngay trên website.':'Đã cập nhật trạng thái phản ánh.');
            header('Location: feedback.php?id='.$id); exit;
        } catch(InvalidArgumentException $e) { $error=$e->getMessage(); }
    }
    foreach((new FeedbackService($conn))->rows('SELECT trang_thai,COUNT(*) n FROM phan_anh GROUP BY trang_thai') as $r) $counts[$r['trang_thai']]=(int)$r['n'];
    $where=' WHERE 1=1'; $types=''; $args=[];
    if($filter!=='') { $where.=' AND f.trang_thai=?'; $types.='s'; $args[]=$filter; }
    if($q!=='') { $where.=" AND (f.tieu_de LIKE ? OR f.ma_don LIKE ? OR CONCAT_WS(' ',u.ho,u.ten) LIKE ? OR u.email LIKE ? OR CONCAT('PA-',LPAD(f.id,GREATEST(5,CHAR_LENGTH(f.id)),'0')) LIKE ? OR CAST(f.id AS CHAR)=?)"; $like='%'.$q.'%'; $types.='ssssss'; array_push($args,$like,$like,$like,$like,$like,$q); }
    $from=' FROM phan_anh f JOIN users u ON u.id=f.user_id';
    $total=(int)(new FeedbackService($conn))->one('SELECT COUNT(*) n'.$from.$where,$types,$args)['n']; $p=min($p,max(1,(int)ceil($total/15)));
    $items=(new FeedbackService($conn))->rows("SELECT f.id,f.tieu_de,f.loai,f.ma_don,f.trang_thai,f.updated_at,CONCAT_WS(' ',u.ho,u.ten) AS customer,u.email".$from.$where." ORDER BY f.updated_at DESC,f.id DESC LIMIT 15 OFFSET ?",$types.'i',array_merge($args,[($p-1)*15]));
    if($id) {
        $detail=(new FeedbackService($conn))->one("SELECT f.id,f.tieu_de,f.loai,f.ma_don,f.noi_dung,f.trang_thai,f.created_at,f.updated_at,f.anh IS NOT NULL AS has_image,CONCAT_WS(' ',u.ho,u.ten) AS customer,u.email,u.sdt,(SELECT d.id FROM donhang d WHERE CONVERT(d.ma_don USING utf8mb4) COLLATE utf8mb4_unicode_ci=f.ma_don AND d.user_id=f.user_id LIMIT 1) AS order_id".$from.' WHERE f.id=?','i',[$id]);
        if(!$detail) { http_response_code(404); $error='Không tìm thấy phản ánh.'; }
        else $replies=(new FeedbackService($conn))->rows('SELECT nguoi_gui,noi_dung,created_at FROM phan_anh_tra_loi WHERE phan_anh_id=? ORDER BY id','i',[$id]);
    }
} catch(Throwable $e) { $error=FeedbackService::error($e); }


        return get_defined_vars();
    }
}
