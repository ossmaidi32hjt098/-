<?php
session_start();
require_once __DIR__ . '/db.php';

$is_admin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;
$is_committee = isset($_SESSION['logged_in_committee']);

if (!$is_admin && !$is_committee) {
    header("Location: admin_login.php");
    exit;
}

$committee_id = $is_committee ? $_SESSION['logged_in_committee'] : 0;

// --- جلب الإحصائيات الرقمية ---
$stats = [
    'total_beneficiaries' => 0,
    'pending_req' => 0,
    'accepted_req' => 0,
    'rejected_req' => 0,
    'total_donors' => 0,
    'total_incoming' => 0,
    'total_financial_in' => 0,
    'total_inkind_in' => 0,
    'donations_this_year' => 0,
    'active_campaigns' => 0,
    'disbursed_aids' => 0,
    'pending_aids' => 0,
    'aids_this_month' => 0,
    'total_committees' => 0
];

$chart_active_committees = [];
$chart_top_types_map = [];
$latest_donations = [];
$latest_beneficiaries = [];

try {
    $central_pdo = new PDO("mysql:host=$host;dbname=zakat_central_db;charset=$charset", $user, $pass, $options);
    $registry = $central_pdo->query("SELECT id, committee_name as name FROM committees_registry ORDER BY id ASC")->fetchAll();
    $stats['total_committees'] = count($registry);
} catch (PDOException $e) {
    $registry = [];
}

// تطبيق نظام (Scatter-Gather)
foreach ($registry as $com) {
    $cid = $com['id'];
    $cname = $com['name'];

    if ($is_committee && $committee_id != $cid) continue;

    if (isset($db_nodes[$cid])) {
        $node_db = $db_nodes[$cid];
        try {
            $dsn_node = "mysql:host=$host;dbname=$node_db;charset=$charset";
            $node_pdo = new PDO($dsn_node, $user, $pass, $options);

            $b_stats = $node_pdo->query("SELECT status, COUNT(*) as c FROM beneficiaries GROUP BY status")->fetchAll();
            foreach ($b_stats as $b) {
                $stats['total_beneficiaries'] += $b['c'];
                if ($b['status'] == 'قيد الدراسة') $stats['pending_req'] += $b['c'];
                if ($b['status'] == 'مقبول') $stats['accepted_req'] += $b['c'];
                if ($b['status'] == 'مرفوض') $stats['rejected_req'] += $b['c'];
            }

            $inc_stats = $node_pdo->query("
                SELECT 
                    COUNT(DISTINCT CASE WHEN donor_name != 'فاعل خير' AND donor_name != 'Anonymous' THEN donor_name ELSE NULL END) as donors,
                    COUNT(*) as total_inc,
                    COUNT(DISTINCT CASE WHEN campaign_name IS NOT NULL AND campaign_name != '' THEN campaign_name ELSE NULL END) as campaigns,
                    SUM(CASE WHEN dt.category = 'نقدي' OR dt.category LIKE '%نقد%' THEN inc.quantity ELSE 0 END) as financial_in,
                    SUM(CASE WHEN dt.category != 'نقدي' AND dt.category NOT LIKE '%نقد%' THEN inc.quantity ELSE 0 END) as inkind_in,
                    SUM(CASE WHEN (dt.category = 'نقدي' OR dt.category LIKE '%نقد%') AND YEAR(inc.deposit_date) = YEAR(CURRENT_DATE()) THEN inc.quantity ELSE 0 END) as this_year
                FROM incoming_donations inc
                JOIN zakat_central_db.donation_types dt ON inc.donation_type_id = dt.id
            ")->fetch();

            $stats['total_donors'] += (int)$inc_stats['donors'];
            $stats['total_incoming'] += (int)$inc_stats['total_inc'];
            $stats['total_financial_in'] += (float)$inc_stats['financial_in'];
            $stats['total_inkind_in'] += (float)$inc_stats['inkind_in'];
            $stats['donations_this_year'] += (float)$inc_stats['this_year'];
            $stats['active_campaigns'] += (int)$inc_stats['campaigns'];

            $out_stats = $node_pdo->query("
                SELECT 
                    SUM(CASE WHEN donation_status = 'تم الصرف' THEN 1 ELSE 0 END) as disbursed,
                    SUM(CASE WHEN donation_status = 'قيد الانتظار' THEN 1 ELSE 0 END) as pending,
                    SUM(CASE WHEN donation_status = 'تم الصرف' AND MONTH(donation_date) = MONTH(CURRENT_DATE()) AND YEAR(donation_date) = YEAR(CURRENT_DATE()) THEN 1 ELSE 0 END) as this_month
                FROM donations_history
            ")->fetch();

            $disbursed = (int)$out_stats['disbursed'];
            $stats['disbursed_aids'] += $disbursed;
            $stats['pending_aids'] += (int)$out_stats['pending'];
            $stats['aids_this_month'] += (int)$out_stats['this_month'];

            if ($is_admin) {
                $chart_active_committees[] = ['name' => $cname, 'total' => $disbursed];
            }

            $top_types = $node_pdo->query("SELECT dt.sub_category, COUNT(dh.id) as total FROM donations_history dh JOIN zakat_central_db.donation_types dt ON dh.donation_type_id = dt.id GROUP BY dt.id")->fetchAll();
            foreach ($top_types as $tt) {
                $sub = $tt['sub_category'];
                if (!isset($chart_top_types_map[$sub])) $chart_top_types_map[$sub] = 0;
                $chart_top_types_map[$sub] += $tt['total'];
            }

            $latest_d = $node_pdo->query("SELECT b.full_name, dt.sub_category, dh.amount, dh.donation_date FROM donations_history dh JOIN beneficiaries b ON dh.national_id = b.national_id JOIN zakat_central_db.donation_types dt ON dh.donation_type_id = dt.id ORDER BY dh.donation_date DESC LIMIT 5")->fetchAll();
            $latest_donations = array_merge($latest_donations, $latest_d);

            $latest_b = $node_pdo->query("SELECT full_name, status, national_id FROM beneficiaries ORDER BY full_name DESC LIMIT 5")->fetchAll();
            $latest_beneficiaries = array_merge($latest_beneficiaries, $latest_b);

        } catch (PDOException $e) {}
    }
}

// حساب التبرعات الواردة للصندوق المركزي (المدير)
if ($is_admin) {
    try {
        $inc_stats = $pdo->query("
            SELECT 
                COUNT(DISTINCT CASE WHEN donor_name != 'فاعل خير' AND donor_name != 'Anonymous' THEN donor_name ELSE NULL END) as donors,
                COUNT(*) as total_inc,
                COUNT(DISTINCT CASE WHEN campaign_name IS NOT NULL AND campaign_name != '' THEN campaign_name ELSE NULL END) as campaigns,
                SUM(CASE WHEN dt.category = 'نقدي' OR dt.category LIKE '%نقد%' THEN inc.quantity ELSE 0 END) as financial_in,
                SUM(CASE WHEN dt.category != 'نقدي' AND dt.category NOT LIKE '%نقد%' THEN inc.quantity ELSE 0 END) as inkind_in,
                SUM(CASE WHEN (dt.category = 'نقدي' OR dt.category LIKE '%نقد%') AND YEAR(inc.deposit_date) = YEAR(CURRENT_DATE()) THEN inc.quantity ELSE 0 END) as this_year
            FROM incoming_donations inc
            JOIN donation_types dt ON inc.donation_type_id = dt.id
            WHERE inc.committee_id = 0
        ")->fetch();

        $stats['total_donors'] += (int)$inc_stats['donors'];
        $stats['total_incoming'] += (int)$inc_stats['total_inc'];
        $stats['total_financial_in'] += (float)$inc_stats['financial_in'];
        $stats['total_inkind_in'] += (float)$inc_stats['inkind_in'];
        $stats['donations_this_year'] += (float)$inc_stats['this_year'];
        $stats['active_campaigns'] += (int)$inc_stats['campaigns'];
    } catch (PDOException $e) {}
}

// ترتيب البيانات للمخططات
usort($chart_active_committees, function($a, $b) { return $b['total'] <=> $a['total']; });
$chart_active_committees = array_slice($chart_active_committees, 0, 5);

arsort($chart_top_types_map);
$chart_top_types = [];
foreach (array_slice($chart_top_types_map, 0, 5, true) as $k => $v) {
    $chart_top_types[] = ['sub_category' => $k, 'total' => $v];
}

usort($latest_donations, function($a, $b) { return strtotime($b['donation_date']) - strtotime($a['donation_date']); });
$latest_donations = array_slice($latest_donations, 0, 5);
$latest_beneficiaries = array_slice($latest_beneficiaries, 0, 5);

require_once __DIR__ . '/header.php';
?>

<div class="row mb-4 mt-3">
    <div class="col-12 d-flex justify-content-between align-items-center">
        <h3 class="mb-0 text-primary"><i class="bi bi-bar-chart-steps me-2"></i> <?php echo t('stats_reports'); ?></h3>
        <button onclick="window.print()" class="btn btn-sm btn-outline-secondary d-none d-md-block"><i class="bi bi-printer"></i> <?php echo t('print'); ?></button>
    </div>
</div>

<style>
    @media print {
        .navbar, .btn, footer { display: none !important; }
        body { background-color: white !important; }
        .card { border: 1px solid #ddd !important; box-shadow: none !important; break-inside: avoid; }
    }
    .stat-card { border-radius: 15px; border-right: 5px solid; transition: transform 0.2s; }
    .stat-card:hover { transform: translateY(-3px); }
</style>

<!-- 1. الإحصائيات العلوية (الأرقام الكبيرة) -->
<div class="row g-3 mb-4">
    <div class="col-md-3 col-sm-6">
        <div class="card stat-card bg-white shadow-sm border-primary h-100 p-3">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="text-muted mb-1"><?php echo t('total_beneficiaries'); ?></h6>
                    <h3 class="fw-bold mb-0 text-dark"><?php echo number_format($stats['total_beneficiaries']); ?></h3>
                </div>
                <i class="bi bi-people fs-1 text-primary opacity-50"></i>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6">
        <div class="card stat-card bg-white shadow-sm border-success h-100 p-3">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="text-muted mb-1"><?php echo t('disbursed_aids'); ?></h6>
                    <h3 class="fw-bold mb-0 text-dark"><?php echo number_format($stats['disbursed_aids']); ?></h3>
                </div>
                <i class="bi bi-box2-heart fs-1 text-success opacity-50"></i>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6">
        <div class="card stat-card bg-white shadow-sm border-warning h-100 p-3">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="text-muted mb-1"><?php echo t('total_incoming'); ?></h6>
                    <h3 class="fw-bold mb-0 text-dark"><?php echo number_format($stats['total_incoming']); ?></h3>
                </div>
                <i class="bi bi-arrow-down-circle fs-1 text-warning opacity-50"></i>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6">
        <div class="card stat-card bg-white shadow-sm border-info h-100 p-3">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="text-muted mb-1"><?php echo t('financial_donations'); ?></h6>
                    <h3 class="fw-bold mb-0 text-dark"><?php echo number_format($stats['total_financial_in']); ?> <span class="fs-6">JOD</span></h3>
                </div>
                <i class="bi bi-cash-coin fs-1 text-info opacity-50"></i>
            </div>
        </div>
    </div>
</div>

<!-- 2. تفاصيل إضافية (أرقام صغيرة) -->
<div class="row mb-5">
    <div class="col-12">
        <div class="card shadow-sm border-0 bg-light">
            <div class="card-body p-4 d-flex flex-wrap justify-content-center justify-content-md-between gap-4 text-center">
                <div><h6 class="text-muted mb-1"><i class="bi bi-boxes text-secondary"></i> <?php echo t('inkind_donations_received'); ?></h6><h4 class="fw-bold"><?php echo number_format($stats['total_inkind_in']); ?></h4></div>
                <div><h6 class="text-muted mb-1"><i class="bi bi-person-heart text-danger"></i> <?php echo t('total_donors'); ?></h6><h4 class="fw-bold"><?php echo number_format($stats['total_donors']); ?></h4></div>
                <div><h6 class="text-muted mb-1"><i class="bi bi-megaphone text-info"></i> <?php echo t('active_campaigns'); ?></h6><h4 class="fw-bold"><?php echo number_format($stats['active_campaigns']); ?></h4></div>
                <?php if($is_admin): ?><div><h6 class="text-muted mb-1"><i class="bi bi-buildings text-primary"></i> <?php echo t('registered_committees'); ?></h6><h4 class="fw-bold"><?php echo number_format($stats['total_committees']); ?></h4></div><?php endif; ?>
                <div><h6 class="text-muted mb-1"><i class="bi bi-calendar-check text-success"></i> <?php echo t('aids_this_month'); ?></h6><h4 class="fw-bold"><?php echo number_format($stats['aids_this_month']); ?></h4></div>
                <div><h6 class="text-muted mb-1"><i class="bi bi-graph-up-arrow text-warning"></i> <?php echo t('donations_this_year'); ?></h6><h4 class="fw-bold"><?php echo number_format($stats['donations_this_year']); ?> JOD</h4></div>
            </div>
        </div>
    </div>
</div>

<!-- 3. المخططات البيانية (Chart.js) -->
<div class="row mb-4 g-4">
    <div class="col-md-6">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-white pb-0 border-0"><h6 class="fw-bold text-dark"><i class="bi bi-pie-chart text-primary"></i> <?php echo t('req_status'); ?></h6></div>
            <div class="card-body d-flex justify-content-center align-items-center">
                <canvas id="reqStatusChart" style="max-height: 250px;"></canvas>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-white pb-0 border-0"><h6 class="fw-bold text-dark"><i class="bi bi-bar-chart text-success"></i> <?php echo t('top_aid_types'); ?></h6></div>
            <div class="card-body">
                <canvas id="topTypesChart" style="max-height: 250px;"></canvas>
            </div>
        </div>
    </div>
    <?php if($is_admin): ?>
    <div class="col-md-8">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-white pb-0 border-0"><h6 class="fw-bold text-dark"><i class="bi bi-activity text-info"></i> <?php echo t('most_active_committees'); ?></h6></div>
            <div class="card-body">
                <canvas id="activeComChart" style="max-height: 250px;"></canvas>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-white pb-0 border-0"><h6 class="fw-bold text-dark"><i class="bi bi-clock-history text-warning"></i> <?php echo t('distribution_status'); ?></h6></div>
            <div class="card-body d-flex justify-content-center align-items-center">
                <canvas id="aidStatusChart" style="max-height: 250px;"></canvas>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- 4. القوائم السريعة (أحدث الحركات) -->
<div class="row mb-5 g-4">
    <div class="col-md-6">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-white"><h6 class="mb-0 fw-bold"><i class="bi bi-gift text-success me-2"></i> <?php echo t('latest_disbursed_aids'); ?></h6></div>
            <ul class="list-group list-group-flush">
                <?php foreach($latest_donations as $ld): ?>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <div>
                            <strong><?php echo htmlspecialchars($ld['full_name']); ?></strong><br>
                            <small class="text-muted"><?php echo htmlspecialchars($ld['sub_category']); ?></small>
                        </div>
                        <div class="text-end">
                            <span class="badge bg-light text-dark border"><?php echo date('Y-m-d', strtotime($ld['donation_date'])); ?></span><br>
                            <?php if($ld['amount']) echo "<strong class='text-success small'>".number_format($ld['amount'])." JOD</strong>"; ?>
                        </div>
                    </li>
                <?php endforeach; ?>
                <?php if(empty($latest_donations)) echo "<li class='list-group-item text-center text-muted'>لا يوجد مساعدات بعد</li>"; ?>
            </ul>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-white"><h6 class="mb-0 fw-bold"><i class="bi bi-person-plus text-primary me-2"></i> <?php echo t('latest_registered_beneficiaries'); ?></h6></div>
            <ul class="list-group list-group-flush">
                <?php foreach($latest_beneficiaries as $lb): ?>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <span><i class="bi bi-person-circle text-secondary me-2"></i> <?php echo htmlspecialchars($lb['full_name']); ?></span>
                        <span class="badge bg-<?php echo $lb['status']=='مقبول'?'success':($lb['status']=='مرفوض'?'danger':'warning text-dark'); ?> rounded-pill"><?php echo htmlspecialchars($lb['status']); ?></span>
                    </li>
                <?php endforeach; ?>
                <?php if(empty($latest_beneficiaries)) echo "<li class='list-group-item text-center text-muted'>لا يوجد مستفيدين بعد</li>"; ?>
            </ul>
        </div>
    </div>
</div>

<!-- مكتبة Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener("DOMContentLoaded", function() {
    // 1. حالة المستفيدين (Pie)
    new Chart(document.getElementById('reqStatusChart').getContext('2d'), {
        type: 'pie',
        data: {
            labels: ['مقبول', 'قيد الدراسة', 'مرفوض'],
            datasets: [{
                data: [<?php echo $stats['accepted_req']; ?>, <?php echo $stats['pending_req']; ?>, <?php echo $stats['rejected_req']; ?>],
                backgroundColor: ['#10b981', '#f59e0b', '#ef4444'], borderWidth: 0
            }]
        }, options: { responsive: true, maintainAspectRatio: false }
    });

    // 2. أكثر أنواع المساعدات (Bar)
    new Chart(document.getElementById('topTypesChart').getContext('2d'), {
        type: 'bar',
        data: {
            labels: <?php echo json_encode(array_column($chart_top_types, 'sub_category')); ?>,
            datasets: [{
                label: 'عدد التوزيعات',
                data: <?php echo json_encode(array_column($chart_top_types, 'total')); ?>,
                backgroundColor: '#06b6d4', borderRadius: 4
            }]
        }, options: { responsive: true, maintainAspectRatio: false, plugins:{legend:{display:false}} }
    });

    <?php if($is_admin): ?>
    // 3. اللجان الأنشط (Bar)
    new Chart(document.getElementById('activeComChart').getContext('2d'), {
        type: 'bar',
        data: {
            labels: <?php echo json_encode(array_column($chart_active_committees, 'name')); ?>,
            datasets: [{
                label: 'عدد التوزيعات المنفذة',
                data: <?php echo json_encode(array_column($chart_active_committees, 'total')); ?>,
                backgroundColor: '#8b5cf6', borderRadius: 4
            }]
        }, options: { responsive: true, maintainAspectRatio: false, plugins:{legend:{display:false}} }
    });
    
    // 4. حالة التوزيعات (Doughnut)
    new Chart(document.getElementById('aidStatusChart').getContext('2d'), {
        type: 'doughnut',
        data: {
            labels: ['تم الصرف', 'قيد الانتظار'],
            datasets: [{
                data: [<?php echo $stats['disbursed_aids']; ?>, <?php echo $stats['pending_aids']; ?>],
                backgroundColor: ['#10b981', '#f59e0b'], borderWidth: 0
            }]
        }, options: { responsive: true, maintainAspectRatio: false }
    });
    <?php endif; ?>
});
</script>

<?php require_once __DIR__ . '/footer.php'; ?>