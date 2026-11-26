<?php
require_once __DIR__ . '/app/bootstrap.php';

extract((new CustomerFeedbackController())->show(), EXTR_OVERWRITE);
?>
<!DOCTYPE html><html lang="vi"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Phản ánh & góp ý - TT SHOP</title><link rel="stylesheet" href="style.css"><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"><link rel="stylesheet" href="phananh.css?v=1"></head>
<body class="pa-shop"><?php require __DIR__.'/header.php'; ?>
<main class="pa-wrap pa-ui">
<div class="pa-crumb"><a href="index.php">Trang chủ</a> / Trung tâm hỗ trợ / Phản ánh & góp ý</div>
<section class="pa-hero"><div class="pa-kicker">TT SHOP · TRUNG TÂM HỖ TRỢ</div><h1>Phản ánh & góp ý khách hàng</h1><p>Chia sẻ vấn đề bạn gặp phải và theo dõi câu trả lời từ cửa hàng ngay trên website.</p></section>
<?php if ($error): ?><div role="alert" class="pa-alert pa-error"><?=pa_h($error)?></div><?php endif; ?>
<?php if ($flash): ?><div role="status" class="pa-alert pa-ok"><?=pa_h($flash)?></div><?php endif; ?>
<?php if (!$user): ?><section class="pa-panel"><h2>Đăng nhập để gửi phản ánh</h2><p>Chúng tôi sử dụng tài khoản TT SHOP để bảo vệ nội dung phản ánh và giúp bạn xem lại phản hồi của Admin.</p><a class="pa-btn" href="dangnhap.php">Đăng nhập</a> <a class="pa-btn pa-secondary" href="dangky.php">Đăng ký</a></section>
<?php else: ?>
<div class="pa-grid">
<aside class="pa-panel pa-list"><h2>Phản ánh của tôi</h2><a class="pa-btn" href="phananh.php">+ Tạo phản ánh mới</a>
<?php if (!$items): ?><p class="pa-muted">Bạn chưa có phản ánh nào.</p><?php endif; ?>
<?php foreach($items as $item): ?><a class="pa-item <?= $id===(int)$item['id']?'selected':'' ?>" href="phananh.php?id=<?=(int)$item['id']?>&amp;p=<?=$p?>"><small><?=pa_h(pa_code($item['id']))?> · <?=pa_h(pa_date($item['updated_at']))?></small><strong><?=pa_h($item['tieu_de'])?></strong><?=pa_status_html($item['trang_thai'])?></a><?php endforeach; ?>
<div class="pa-pages"><?php if($p>1): ?><a href="phananh.php?p=<?=$p-1?>">‹ Trước</a><?php endif; ?><span>Trang <?=$p?> / <?=max(1,(int)ceil($total/10))?></span><?php if($p*10<$total): ?><a href="phananh.php?p=<?=$p+1?>">Sau ›</a><?php endif; ?></div><a class="pa-back" href="taikhoan.php">‹ Tài khoản của tôi</a></aside>
<section class="pa-panel">
<?php if($detail): ?>
<div class="pa-detail-head"><div><small><?=pa_h(pa_code($detail['id']))?> · <?=pa_h(pa_date($detail['created_at']))?></small><h2><?=pa_h($detail['tieu_de'])?></h2></div><?=pa_status_html($detail['trang_thai'])?></div><p class="pa-muted"><?=pa_h($detail['loai'])?> · Đơn hàng: <?=pa_h($detail['ma_don']??'Không liên kết')?></p>
<div class="pa-message from-customer"><strong>Nội dung ban đầu</strong><div class="pa-text"><?=pa_h($detail['noi_dung'])?></div><?php if($detail['has_image']): ?><a href="phananh_anh.php?id=<?=$id?>" target="_blank" rel="noopener"><img class="pa-image" src="phananh_anh.php?id=<?=$id?>" alt="Ảnh phản ánh"></a><?php endif; ?></div>
<h3>Lịch sử trao đổi</h3><?php if(!$replies): ?><p class="pa-muted">Chưa có phản hồi. TT SHOP sẽ tiếp nhận phản ánh của bạn.</p><?php endif; ?><?php pa_history($replies); ?>
<form class="pa-form" method="post" action="phananh.php?id=<?=$id?>"><input type="hidden" name="csrf_token" value="<?=pa_h(csrf_token())?>"><input type="hidden" name="action" value="reply"><input type="hidden" name="id" value="<?=$id?>"><label for="reply">Bổ sung thông tin</label><textarea id="reply" name="noi_dung" rows="4" minlength="5" maxlength="2000" required><?=pa_h(($error&&($_POST['action']??'')==='reply')?$body:'')?></textarea><p class="pa-muted">Nội dung bổ sung sẽ chuyển phản ánh về trạng thái chờ xử lý.</p><button class="pa-btn" type="submit">Gửi bổ sung</button></form>
<?php else: ?>
<h2>Gửi phản ánh mới</h2><p class="pa-muted">Thông tin được lưu trực tiếp vào hệ thống, không gửi qua email.</p>
<form class="pa-form" method="post" enctype="multipart/form-data" action="phananh.php"><input type="hidden" name="csrf_token" value="<?=pa_h(csrf_token())?>"><input type="hidden" name="action" value="create">
<label>Tài khoản</label><div class="pa-readonly"><?=pa_h(trim($user['ho'].' '.$user['ten']))?> · <?=pa_h($user['email'])?></div>
<label for="order">Đơn hàng (không bắt buộc)</label><select id="order" name="ma_don"><option value="">Không liên quan đến đơn hàng</option><?php foreach($orders as $o): ?><option value="<?=pa_h($o['ma_don'])?>" <?=$order===$o['ma_don']?'selected':''?>><?=pa_h($o['ma_don'])?> · <?=pa_h(pa_date($o['created_at']))?></option><?php endforeach; ?></select>
<label for="kind">Loại phản ánh *</label><select name="loai" id="kind" required><option value="">Chọn loại phản ánh</option><?php foreach(pa_types() as $t): ?><option <?=$kind===$t?'selected':''?> value="<?=pa_h($t)?>"><?=pa_h($t)?></option><?php endforeach; ?></select>
<label for="subject">Tiêu đề *</label><input id="subject" name="tieu_de" required minlength="3" maxlength="150" value="<?=pa_h($subject)?>" placeholder="Ví dụ: Sản phẩm bị hư hỏng khi nhận hàng">
<label for="body">Nội dung *</label><textarea id="body" name="noi_dung" required minlength="5" maxlength="2000" rows="6" placeholder="Mô tả vấn đề và mong muốn được hỗ trợ..."><?=pa_h(($_POST['action']??'')==='create'?$body:'')?></textarea>
<label for="image">Ảnh minh chứng (không bắt buộc)</label><input type="file" id="image" name="anh" accept="image/jpeg,image/png,image/webp"><p class="pa-muted">Một ảnh JPG, PNG hoặc WEBP, tối đa 2 MB. Không gửi hình ảnh chứa thông tin nhạy cảm.</p>
<button type="submit" class="pa-btn">Gửi phản ánh</button></form>
<?php endif; ?>
</section></div><?php endif; ?>
</main><?php require __DIR__.'/footer.php'; ?></body></html>
