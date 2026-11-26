<?php
final class AdminDashboardController
{
    public function overview(): array
    {

require_once (dirname(__DIR__, 2) . '/admin') . '/../payment_helpers.php';
include dirname(__DIR__, 2) . '/admin' . '/check_admin.php';
include dirname(__DIR__, 2) . '/admin' . '/../connect.php';

$productCountResult = mysqli_query($conn, "SELECT COUNT(*) AS total FROM products");
$customerCountResult = mysqli_query($conn, "SELECT COUNT(*) AS total FROM users");
$orderStatusResult = mysqli_query($conn, "SELECT trang_thai, COUNT(*) AS total FROM donhang GROUP BY trang_thai");

if (!$productCountResult || !$customerCountResult || !$orderStatusResult) {
    die("Không thể tải dữ liệu tổng quan quản trị.");
}

$productCount = (int)mysqli_fetch_assoc($productCountResult)['total'];
$customerCount = (int)mysqli_fetch_assoc($customerCountResult)['total'];
$orderCounts = [];
while ($row = mysqli_fetch_assoc($orderStatusResult)) {
    $orderCounts[$row['trang_thai']] = (int)$row['total'];
}

$knownOrderCount = ($orderCounts['Chờ xử lý'] ?? 0)
    + ($orderCounts['Chờ lấy hàng'] ?? 0)
    + ($orderCounts['Đang vận chuyển'] ?? 0)
    + ($orderCounts['Đã giao'] ?? 0)
    + ($orderCounts['Đã hủy'] ?? 0)
    + ($orderCounts['Đã huỷ'] ?? 0);
$allOrderCount = array_sum($orderCounts);
$statusChart = [
    ['label' => 'Chờ xử lý / lấy hàng', 'count' => ($orderCounts['Chờ xử lý'] ?? 0) + ($orderCounts['Chờ lấy hàng'] ?? 0), 'color' => '#f0a329'],
    ['label' => 'Đang vận chuyển', 'count' => $orderCounts['Đang vận chuyển'] ?? 0, 'color' => '#4688e8'],
    ['label' => 'Đã giao', 'count' => $orderCounts['Đã giao'] ?? 0, 'color' => '#20a66a'],
    ['label' => 'Đã hủy', 'count' => ($orderCounts['Đã hủy'] ?? 0) + ($orderCounts['Đã huỷ'] ?? 0), 'color' => '#e36161'],
];
if ($allOrderCount > $knownOrderCount) {
    $statusChart[] = ['label' => 'Trạng thái khác', 'count' => $allOrderCount - $knownOrderCount, 'color' => '#98a2b3'];
}

$revenueTrendResult = mysqli_query($conn, "
    SELECT DATE(created_at) AS order_date, SUM(tong_cong) AS daily_revenue
    FROM donhang
    WHERE trang_thai = 'Đã giao'
        AND created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
        AND created_at < DATE_ADD(CURDATE(), INTERVAL 1 DAY)
    GROUP BY DATE(created_at)
    ORDER BY order_date
");
if (!$revenueTrendResult) {
    die("Không thể tải biểu đồ doanh thu.");
}
$revenueByDate = [];
while ($row = mysqli_fetch_assoc($revenueTrendResult)) {
    $revenueByDate[$row['order_date']] = (float)$row['daily_revenue'];
}
$revenueTrend = [];
for ($dayOffset = 6; $dayOffset >= 0; $dayOffset--) {
    $date = date('Y-m-d', strtotime('-' . $dayOffset . ' days'));
    $revenueTrend[] = [
        'label' => date('d/m', strtotime($date)),
        'date' => $date,
        'revenue' => $revenueByDate[$date] ?? 0,
    ];
}
$maxDailyRevenue = max(array_column($revenueTrend, 'revenue'));
$statusChartTotal = array_sum(array_column($statusChart, 'count'));
$statusChartParts = [];
$statusChartAngle = 0;
foreach ($statusChart as $statusItem) {
    if ($statusItem['count'] === 0 || $statusChartTotal === 0) {
        continue;
    }
    $nextAngle = $statusChartAngle + ($statusItem['count'] / $statusChartTotal * 360);
    $statusChartParts[] = $statusItem['color'] . ' ' . number_format($statusChartAngle, 2, '.', '') . 'deg ' . number_format($nextAngle, 2, '.', '') . 'deg';
    $statusChartAngle = $nextAngle;
}
$statusChartBackground = $statusChartParts
    ? 'conic-gradient(' . implode(', ', $statusChartParts) . ')'
    : 'conic-gradient(#e9eeeb 0deg 360deg)';

$pendingCount = ($orderCounts['Chờ xử lý'] ?? 0) + ($orderCounts['Chờ lấy hàng'] ?? 0);
$shippingCount = $orderCounts['Đang vận chuyển'] ?? 0;
$deliveredCount = $orderCounts['Đã giao'] ?? 0;
$revenue = 0;
$revenuePeriod = !isset($_GET['revenue_period'])
    ? 'all'
    : (is_string($_GET['revenue_period']) ? $_GET['revenue_period'] : '');
$fromDate = isset($_GET['from_date']) && is_string($_GET['from_date']) ? $_GET['from_date'] : '';
$toDate = isset($_GET['to_date']) && is_string($_GET['to_date']) ? $_GET['to_date'] : '';
$revenueError = '';
$revenueCondition = '';
$revenueParams = [];

$allowedRevenuePeriods = ['all', 'today', '7days', 'month', 'year', 'custom'];
if (!in_array($revenuePeriod, $allowedRevenuePeriods, true)) {
    $revenueError = 'Khoảng thời gian không hợp lệ.';
} else {
    switch ($revenuePeriod) {
        case 'today':
            $revenueCondition = ' AND created_at >= CURDATE() AND created_at < DATE_ADD(CURDATE(), INTERVAL 1 DAY)';
            break;
        case '7days':
            $revenueCondition = ' AND created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY) AND created_at < DATE_ADD(CURDATE(), INTERVAL 1 DAY)';
            break;
        case 'month':
            $revenueCondition = " AND created_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01') AND created_at < DATE_ADD(LAST_DAY(CURDATE()), INTERVAL 1 DAY)";
            break;
        case 'year':
            $revenueCondition = ' AND created_at >= MAKEDATE(YEAR(CURDATE()), 1) AND created_at < MAKEDATE(YEAR(CURDATE()) + 1, 1)';
            break;
        case 'custom':
            $validFromDate = preg_match('/^\d{4}-\d{2}-\d{2}$/', $fromDate)
                && checkdate((int)substr($fromDate, 5, 2), (int)substr($fromDate, 8, 2), (int)substr($fromDate, 0, 4));
            $validToDate = preg_match('/^\d{4}-\d{2}-\d{2}$/', $toDate)
                && checkdate((int)substr($toDate, 5, 2), (int)substr($toDate, 8, 2), (int)substr($toDate, 0, 4));

            if (!$validFromDate || !$validToDate || $fromDate > $toDate) {
                $revenueError = 'Vui lòng chọn khoảng ngày hợp lệ (từ ngày không lớn hơn đến ngày).';
            } else {
                $revenueCondition = ' AND created_at >= ? AND created_at < DATE_ADD(?, INTERVAL 1 DAY)';
                $revenueParams = [$fromDate, $toDate];
            }
            break;
    }
}

if ($revenueError === '') {
    $revenueStatement = mysqli_prepare($conn, "
        SELECT COALESCE(SUM(tong_cong), 0)
        FROM donhang
        WHERE trang_thai = 'Đã giao'$revenueCondition
    ");
    if (!$revenueStatement) {
        die("Không thể chuẩn bị thống kê doanh thu.");
    }
    if ($revenueParams) {
        mysqli_stmt_bind_param($revenueStatement, "ss", $revenueParams[0], $revenueParams[1]);
    }
    if (!mysqli_stmt_execute($revenueStatement)
        || !mysqli_stmt_bind_result($revenueStatement, $revenue)
        || !mysqli_stmt_fetch($revenueStatement)
    ) {
        die("Không thể tải thống kê doanh thu.");
    }
    mysqli_stmt_close($revenueStatement);
}

$recentOrders = mysqli_query($conn, "
    SELECT id, ma_don, ho_ten, tong_cong, trang_thai, created_at, phuong_thuc, payment_status
    FROM donhang
    ORDER BY id DESC
    LIMIT 8
");
if (!$recentOrders) {
    die("Không thể tải danh sách đơn hàng mới.");
}

$statusClasses = [
    'Chờ xử lý' => 'status-pending',
    'Chờ lấy hàng' => 'status-pending',
    'Đang vận chuyển' => 'status-shipping',
    'Đã giao' => 'status-delivered',
    'Đã hủy' => 'status-cancelled',
    'Đã huỷ' => 'status-cancelled',
];

        return get_defined_vars();
    }

    public function reports(): array
    {

include dirname(__DIR__, 2) . '/admin' . '/check_admin.php';
include dirname(__DIR__, 2) . '/admin' . '/../connect.php';

$today = date('Y-m-d');
$defaultFromDate = date('Y-m-01');
$fromDate = isset($_GET['from_date']) && is_string($_GET['from_date']) ? $_GET['from_date'] : $defaultFromDate;
$toDate = isset($_GET['to_date']) && is_string($_GET['to_date']) ? $_GET['to_date'] : $today;
$dateError = '';
$summary = [
    'order_count' => 0,
    'delivered_count' => 0,
    'cancelled_count' => 0,
    'pending_count' => 0,
    'revenue' => 0,
];
$dailyReport = [];
$topProducts = [];

$validFromDate = preg_match('/^\d{4}-\d{2}-\d{2}$/', $fromDate)
    && checkdate((int)substr($fromDate, 5, 2), (int)substr($fromDate, 8, 2), (int)substr($fromDate, 0, 4));
$validToDate = preg_match('/^\d{4}-\d{2}-\d{2}$/', $toDate)
    && checkdate((int)substr($toDate, 5, 2), (int)substr($toDate, 8, 2), (int)substr($toDate, 0, 4));

if (!$validFromDate || !$validToDate || $fromDate > $toDate) {
    $dateError = 'Vui lòng chọn khoảng ngày hợp lệ, trong đó ngày bắt đầu không sau ngày kết thúc.';
} else {
    $summaryStatement = mysqli_prepare($conn, "
        SELECT
            COUNT(*) AS order_count,
            COALESCE(SUM(CASE WHEN trang_thai = 'Đã giao' THEN 1 ELSE 0 END), 0) AS delivered_count,
            COALESCE(SUM(CASE WHEN trang_thai IN ('Đã hủy', 'Đã huỷ') THEN 1 ELSE 0 END), 0) AS cancelled_count,
            COALESCE(SUM(CASE WHEN trang_thai IN ('Chờ xử lý', 'Chờ lấy hàng') THEN 1 ELSE 0 END), 0) AS pending_count,
            COALESCE(SUM(CASE WHEN trang_thai = 'Đã giao' THEN tong_cong ELSE 0 END), 0) AS revenue
        FROM donhang
        WHERE created_at >= ? AND created_at < DATE_ADD(?, INTERVAL 1 DAY)
    ");
    if (!$summaryStatement) {
        die('Không thể chuẩn bị báo cáo tổng quan.');
    }
    mysqli_stmt_bind_param($summaryStatement, 'ss', $fromDate, $toDate);
    if (!mysqli_stmt_execute($summaryStatement)) {
        die('Không thể tải báo cáo tổng quan.');
    }
    $summaryResult = mysqli_stmt_get_result($summaryStatement);
    if (!$summaryResult || !($summaryRow = mysqli_fetch_assoc($summaryResult))) {
        die('Không thể đọc báo cáo tổng quan.');
    }
    foreach ($summary as $key => $value) {
        $summary[$key] = (float)$summaryRow[$key];
    }
    mysqli_stmt_close($summaryStatement);

    $dailyStatement = mysqli_prepare($conn, "
        SELECT
            DATE(created_at) AS report_date,
            COUNT(*) AS order_count,
            COALESCE(SUM(CASE WHEN trang_thai = 'Đã giao' THEN tong_cong ELSE 0 END), 0) AS revenue
        FROM donhang
        WHERE created_at >= ? AND created_at < DATE_ADD(?, INTERVAL 1 DAY)
        GROUP BY DATE(created_at)
        ORDER BY report_date DESC
    ");
    if (!$dailyStatement) {
        die('Không thể chuẩn bị báo cáo theo ngày.');
    }
    mysqli_stmt_bind_param($dailyStatement, 'ss', $fromDate, $toDate);
    if (!mysqli_stmt_execute($dailyStatement)) {
        die('Không thể tải báo cáo theo ngày.');
    }
    $dailyResult = mysqli_stmt_get_result($dailyStatement);
    if (!$dailyResult) {
        die('Không thể đọc báo cáo theo ngày.');
    }
    while ($row = mysqli_fetch_assoc($dailyResult)) {
        $dailyReport[] = $row;
    }
    mysqli_stmt_close($dailyStatement);

    $productsStatement = mysqli_prepare($conn, "
        SELECT
            chitiet_donhang.ten_san_pham AS product_name,
            SUM(chitiet_donhang.so_luong) AS quantity_sold,
            SUM(chitiet_donhang.thanh_tien) AS product_revenue
        FROM chitiet_donhang
        INNER JOIN donhang ON donhang.id = chitiet_donhang.donhang_id
        WHERE donhang.trang_thai = 'Đã giao'
            AND donhang.created_at >= ?
            AND donhang.created_at < DATE_ADD(?, INTERVAL 1 DAY)
        GROUP BY chitiet_donhang.product_id, chitiet_donhang.catalog_id, chitiet_donhang.ten_san_pham
        ORDER BY quantity_sold DESC, product_revenue DESC
        LIMIT 10
    ");
    if (!$productsStatement) {
        die('Không thể chuẩn bị báo cáo sản phẩm.');
    }
    mysqli_stmt_bind_param($productsStatement, 'ss', $fromDate, $toDate);
    if (!mysqli_stmt_execute($productsStatement)) {
        die('Không thể tải báo cáo sản phẩm.');
    }
    $productsResult = mysqli_stmt_get_result($productsStatement);
    if (!$productsResult) {
        die('Không thể đọc báo cáo sản phẩm.');
    }
    while ($row = mysqli_fetch_assoc($productsResult)) {
        $topProducts[] = $row;
    }
    mysqli_stmt_close($productsStatement);
}

$statusTotal = (int)$summary['order_count'];
$deliveredPercent = $statusTotal > 0 ? round($summary['delivered_count'] / $statusTotal * 100) : 0;
$averageDeliveredOrder = $summary['delivered_count'] > 0
    ? $summary['revenue'] / $summary['delivered_count']
    : 0;
$reportId = 'BC-' . date('Ymd-His');
$issuedAt = date('d/m/Y H:i');
$formattedFromDate = $validFromDate ? date('d/m/Y', strtotime($fromDate)) : $fromDate;
$formattedToDate = $validToDate ? date('d/m/Y', strtotime($toDate)) : $toDate;
$dailyOrderTotal = 0;
$maxDailyRevenue = 0;
foreach ($dailyReport as $day) {
    $dailyOrderTotal += (int)$day['order_count'];
    $maxDailyRevenue = max($maxDailyRevenue, (float)$day['revenue']);
}
$maxProductQuantity = 0;
foreach ($topProducts as $product) {
    $maxProductQuantity = max($maxProductQuantity, (int)$product['quantity_sold']);
}

        return get_defined_vars();
    }

    public static function membership_md_e($v): string { return htmlspecialchars((string)$v, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8'); }

    public static function membership_md_money($v): string { return number_format((int)$v,0,',','.').'đ'; }

    public static function membership_md_get(string $k,string $default=''): string { return isset($_GET[$k]) && is_string($_GET[$k]) ? trim($_GET[$k]) : $default; }

    public static function membership_md_date(string $v): bool { if(!preg_match('/^\d{4}-\d{2}-\d{2}$/D',$v)) return false; $d=DateTimeImmutable::createFromFormat('!Y-m-d',$v); return $d && $d->format('Y-m-d')===$v; }

    public static function membership_md_query(mysqli $db,string $sql,string $types='',array $p=[]): array { $s=$db->prepare($sql); try { if($types!=='') $s->bind_param($types,...$p); $s->execute(); return $s->get_result()->fetch_all(MYSQLI_ASSOC); } finally { $s->close(); } }

    public static function membership_md_csv($v): string { $v=(string)$v; return preg_match('/^[\s\x{FEFF}]*[=+\-@]|^[\t\r\n]/u',$v) ? "'".$v : $v; }

    public function membership(): array
    {

require_once (dirname(__DIR__, 2) . '/admin').'/check_admin.php';
header('Cache-Control: no-store');
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
require_once (dirname(__DIR__, 2) . '/admin').'/../connect.php';






$tiers=['Member','Silver','Gold','Platinum','Diamond'];
$colors=['#5d8be2','#dda638','#8966d5','#168653'];
$statuses=['PAID'=>'Đã thanh toán','PENDING'=>'Chờ thanh toán','REVIEW'=>'Cần kiểm tra','CANCELLED'=>'Đã hủy','EXPIRED'=>'Hết hạn','ALL'=>'Tất cả trạng thái'];
$today=new DateTimeImmutable('today'); $defaultFrom=$today->format('Y-m-01'); $defaultTo=$today->format('Y-m-d');
$from=self::membership_md_get('from',$defaultFrom); $to=self::membership_md_get('to',$defaultTo); $status=self::membership_md_get('status','PAID'); $q=self::membership_md_get('q'); $rank=self::membership_md_get('rank','all');
$error='';
if(!self::membership_md_date($from)||!self::membership_md_date($to)||$from>$to||$to>$defaultTo||$from<'2000-01-01'||($a=new DateTimeImmutable($from))->diff(new DateTimeImmutable($to))->days>365){ $error='Khoảng ngày không hợp lệ (tối đa 366 ngày, không vượt hôm nay). Đã dùng tháng này.'; $from=$defaultFrom; $to=$defaultTo; }
if(!isset($statuses[$status])) $status='PAID';
if(!in_array($rank,['all','1','2','3','4'],true)) $rank='all';
if(strlen($q)>400) $q='';
$start=$from.' 00:00:00'; $end=(new DateTimeImmutable($to))->modify('+1 day')->format('Y-m-d').' 00:00:00';
$filters=['from'=>$from,'to'=>$to,'status'=>$status,'q'=>$q,'rank'=>$rank];

$base=" FROM membership_upgrades m JOIN users u ON u.id=m.user_id ";
$extra='';$xt='';$xp=[];
if($q!==''){ $like='%'.str_replace(['!','%','_'],['!!','!%','!_'],$q).'%'; $extra.=" AND (CONCAT_WS(' ',u.ho,u.ten) LIKE ? ESCAPE '!' OR u.email LIKE ? ESCAPE '!' OR CAST(m.order_code AS CHAR) LIKE ? ESCAPE '!' OR u.sdt LIKE ? ESCAPE '!')"; $xt.='ssss'; $xp=[$like,$like,$like,$like]; }
if($rank!=='all'){ $extra.=' AND m.target_rank=?'; $xt.='i'; $xp[]=(int)$rank; }
$paidWhere=" WHERE m.status='PAID' AND m.paid_at>=? AND m.paid_at<?".$extra;
$rangeP=array_merge([$start,$end],$xp); $rangeT='ss'.$xt;
$dateCol=$status==='PAID'?'m.paid_at':($status==='ALL'?"CASE WHEN m.status='PAID' THEN m.paid_at ELSE m.created_at END":'m.created_at');
$listWhere=" WHERE $dateCol>=? AND $dateCol<?".$extra; $listP=$rangeP;$listT=$rangeT;
if($status!=='ALL'){ $listWhere.=' AND m.status=?'; $listP[]=$status; $listT.='s'; }
$select="SELECT m.id,m.user_id,m.from_rank,m.target_rank,m.amount,m.amount_received,m.order_code,m.status,m.created_at,m.paid_at,CONCAT_WS(' ',u.ho,u.ten) AS customer,u.email,u.sdt";
$dbError='';$rows=[];$daily=[];$dist=[];$total=0;$pages=1;$pageNum=1;$stats=['revenue'=>0,'packages'=>0,'customers'=>0];$created=0;$pending=0;$review=0;
try {
    if(self::membership_md_get('export')==='csv'){
        if($error!==''){ http_response_code(400); exit('Khoảng ngày không hợp lệ.'); }
        $rows=self::membership_md_query($conn,$select.$base.$listWhere." ORDER BY $dateCol DESC,m.id DESC LIMIT 10001",$listT,$listP);
        if(count($rows)>10000){ http_response_code(400); exit('Vui lòng thu hẹp bộ lọc để xuất tối đa 10.000 dòng.'); }
        header('Content-Type: text/csv; charset=UTF-8');header('Content-Disposition: attachment; filename="TTShop-nang-hang-'.$from.'-'.$to.'.csv"');
        $out=fopen('php://output','w');fwrite($out,"\xEF\xBB\xBF");fputcsv($out,['ID khách','Khách hàng','Email','SĐT','Mã giao dịch','Hạng cũ','Hạng mua','Giá (VND)','Đã nhận (VND)','Trạng thái','Ngày tạo','Ngày thanh toán'],',','"','');
        foreach($rows as $r){ fputcsv($out,array_map('md_csv',[$r['user_id'],$r['customer'],$r['email'],$r['sdt'],$r['order_code'],$tiers[$r['from_rank']]??'?', $tiers[$r['target_rank']]??'?', $r['amount'],$r['amount_received'],$statuses[$r['status']]??$r['status'],$r['created_at'],$r['paid_at']]),',','"',''); }
        fclose($out);exit;
    }
    $stats=self::membership_md_query($conn,"SELECT COALESCE(SUM(m.amount),0) revenue,COUNT(*) packages,COUNT(DISTINCT m.user_id) customers".$base.$paidWhere,$rangeT,$rangeP)[0];
    $created=(int)self::membership_md_query($conn,'SELECT COUNT(*) n'.$base.' WHERE m.created_at>=? AND m.created_at<?'.$extra,$rangeT,$rangeP)[0]['n'];
    $pending=(int)self::membership_md_query($conn,"SELECT COUNT(*) n FROM membership_upgrades WHERE status='PENDING'")[0]['n'];
    $review=(int)self::membership_md_query($conn,"SELECT COUNT(*) n FROM membership_upgrades WHERE status='REVIEW'")[0]['n'];
    $daily=self::membership_md_query($conn,'SELECT DATE(m.paid_at) day, SUM(m.amount) revenue'.$base.$paidWhere.' GROUP BY DATE(m.paid_at) ORDER BY day',$rangeT,$rangeP);
    $dist=self::membership_md_query($conn,'SELECT m.target_rank,COUNT(*) packages,SUM(m.amount) revenue'.$base.$paidWhere.' GROUP BY m.target_rank',$rangeT,$rangeP);
    $total=(int)self::membership_md_query($conn,'SELECT COUNT(*) n'.$base.$listWhere,$listT,$listP)[0]['n'];
    $pages=max(1,(int)ceil($total/20));$pageNum=min($pages,max(1,(int)self::membership_md_get('p','1')));$offset=($pageNum-1)*20;
    $rows=self::membership_md_query($conn,$select.$base.$listWhere." ORDER BY $dateCol DESC,m.id DESC LIMIT ? OFFSET ?",$listT.'ii',array_merge($listP,[20,$offset]));
} catch(Throwable $ex){ error_log('Membership dashboard: '.$ex->getMessage());$dbError='Không đọc được dữ liệu nâng hạng. Kiểm tra bảng membership_upgrades và kết nối database.';if(self::membership_md_get('export')==='csv'){http_response_code(503);exit($dbError);} }
$series=[]; foreach($daily as $r) $series[$r['day']]=(int)$r['revenue'];
$days=[];for($d=new DateTimeImmutable($from);$d->format('Y-m-d')<=$to;$d=$d->modify('+1 day')){ $key=$d->format('Y-m-d');$days[]=['day'=>$key,'value'=>$series[$key]??0]; }
$max=max(10000,(int)ceil(max(array_column($days,'value'))/10000)*10000);$n=count($days);$points=[];
foreach($days as $i=>$d){$x=93+($n===1?292:$i*584/($n-1));$y=202-($d['value']/$max)*165;$points[]=round($x,2).','.round($y,2);}
$breakdown=[];foreach($dist as $r)$breakdown[(int)$r['target_rank']]=$r;
$segments=[];$pct=0;$rev=(int)$stats['revenue'];foreach($colors as $i=>$color){$v=(int)($breakdown[$i+1]['revenue']??0);$next=$pct+($rev>0?$v/$rev*100:0);$segments[]=$color.' '.$pct.'% '.$next.'%';$pct=$next;}

        return get_defined_vars();
    }
}
