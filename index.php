<?php
session_start();

// حماية الصفحة: التأكد من تسجيل الدخول كمدير
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    header("Location: admin_login.php");
    exit;
}
require_once __DIR__ . '/db.php';

// جلب سجل اللجان من البوابة المركزية
try {
    $registry = $pdo->query("SELECT id, committee_name as name FROM committees_registry ORDER BY id ASC")->fetchAll();
} catch (PDOException $e) {
    $registry = [];
}

$total_committees = count($registry);
$grouped_beneficiaries = [];
$total_beneficiaries = 0;
$total_users = 0;
$total_balance = 0.00;
$donation_categories_count = [];

// حساب عدد مدراء النظام في القاعدة المركزية
try {
    $total_users += (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
} catch (PDOException $e) {}

// تطبيق نظام (Scatter-Gather) لجمع الإحصائيات من كل قواعد البيانات الفرعية (Nodes)
foreach ($registry as $com) {
    $cid = $com['id'];
    $cname = $com['name'];
    $grouped_beneficiaries[$cname] = [];
    
    if (isset($db_nodes[$cid])) {
        $node_db = $db_nodes[$cid];
        try {
            // الاتصال بقاعدة بيانات اللجنة
            $dsn_node = "mysql:host=$host;dbname=$node_db;charset=$charset";
            $node_pdo = new PDO($dsn_node, $user, $pass, $options);
            
            // جلب المستفيدين
            $bens = $node_pdo->query("SELECT national_id, full_name, phone_number, status FROM beneficiaries ORDER BY full_name ASC")->fetchAll();
            $grouped_beneficiaries[$cname] = $bens;
            $total_beneficiaries += count($bens);
            
            // حساب عدد موظفي اللجنة
            $total_users += (int)$node_pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
            
            // حساب الرصيد المتوفر للجنة
            try {
                $in_com = $node_pdo->query("SELECT SUM(inc.quantity) FROM incoming_donations inc JOIN zakat_central_db.donation_types dt ON inc.donation_type_id = dt.id WHERE (dt.category = 'نقدي' OR dt.category LIKE '%نقد%' OR dt.sub_category LIKE '%نقد%')")->fetchColumn() ?: 0;
                $out_com = $node_pdo->query("SELECT SUM(dh.amount) FROM donations_history dh JOIN zakat_central_db.donation_types dt ON dh.donation_type_id = dt.id WHERE dh.donation_status = 'تم الصرف' AND (dt.category = 'نقدي' OR dt.category LIKE '%نقد%' OR dt.sub_category LIKE '%نقد%')")->fetchColumn() ?: 0;
                $total_balance += (float)($in_com - $out_com);
            } catch (PDOException $e) {
                try {
                    $total_balance += (float)$node_pdo->query("SELECT balance FROM committee_finances LIMIT 1")->fetchColumn();
                } catch (PDOException $e2) {}
            }
            
            // جمع بيانات المخطط البياني لأنواع المساعدات
            $cats = $node_pdo->query("SELECT dt.category, COUNT(dh.id) as count FROM donations_history dh JOIN zakat_central_db.donation_types dt ON dh.donation_type_id = dt.id GROUP BY dt.category")->fetchAll();
            foreach ($cats as $cat_row) {
                $cat_name = $cat_row['category'];
                if (!isset($donation_categories_count[$cat_name])) {
                    $donation_categories_count[$cat_name] = 0;
                }
                $donation_categories_count[$cat_name] += $cat_row['count'];
            }
            
        } catch (PDOException $e) {
            // تجاهل اللجنة في حال كانت القاعدة الخاصة بها غير موجودة أو معطلة
        }
    }
}

// التأكد من وجود جدول حساب المدير وإنشائه إن لم يكن موجوداً (لضمان عمل لوحة التحكم دائماً)
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS `manager_finance` (`id` int(11) NOT NULL AUTO_INCREMENT, `balance` decimal(15,2) NOT NULL DEFAULT 0.00, PRIMARY KEY (`id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    $pdo->exec("INSERT IGNORE INTO `manager_finance` (`id`, `balance`) VALUES (1, 0.00);");
} catch (PDOException $e) {}

// إنشاء جدول سجل التدقيق (Audit Log)
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS `search_audit_logs` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `user_id` INT(11) NULL,
        `committee_id` INT(11) NULL,
        `searched_national_id` VARCHAR(50) NOT NULL,
        `ip_address` VARCHAR(45) NOT NULL,
        `was_successful` TINYINT(1) NOT NULL DEFAULT 0,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
} catch (PDOException $e) {}

// --- الحل الجذري والنهائي 100%: الحساب المباشر من سجل الإيداعات ---
$manager_balance = 0.00;
try {
    $stmt_manager = $pdo->query("
        SELECT SUM(inc.quantity) 
        FROM incoming_donations inc
        JOIN donation_types dt ON inc.donation_type_id = dt.id
        WHERE inc.committee_id = 0 
        AND (dt.category = 'نقدي' OR dt.category LIKE '%نقد%' OR dt.sub_category LIKE '%نقد%')
    ");
    $manager_balance = (float)$stmt_manager->fetchColumn();
    // تحديث احتياطي للجدول
    $pdo->exec("UPDATE manager_finance SET balance = $manager_balance WHERE id = 1");
} catch (PDOException $e) {
    try {
        $manager_balance = (float)$pdo->query("SELECT balance FROM manager_finance WHERE id = 1")->fetchColumn();
    } catch (PDOException $e2) {}
}

// --- بيانات المخططات البيانية (للمدير) ---
// 1. توزيع المستفيدين على اللجان
$chart_committees = [];
$chart_beneficiaries_count = [];
foreach ($grouped_beneficiaries as $name => $bens) {
    $chart_committees[] = $name;
    $chart_beneficiaries_count[] = count($bens);
}

// 2. أنواع التبرعات الموزعة
$chart_cat_labels = array_keys($donation_categories_count);
$chart_cat_data = array_values($donation_categories_count);
// ------------------------------------------

require_once __DIR__ . '/header.php';
?>

<!-- 1. الترحيب وأزرار الوصول السريع -->
<div class="row mb-5 mt-2">
    <div class="col-12">
        <div class="card bg-white" style="background: url('data:image/svg+xml,%3Csvg width=\'60\' height=\'60\' viewBox=\'0 0 60 60\' xmlns=\'http://www.w3.org/2000/svg\'%3E%3Cg fill=\'none\' fill-rule=\'evenodd\'%3E%3Cg fill=\'#0d9488\' fill-opacity=\'0.05\'%3E%3Cpath d=\'M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z\'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E');">
            <div class="card-body py-5 px-4 d-flex flex-column flex-md-row justify-content-between align-items-center">
                <div>
                    <h2 class="card-title text-primary fw-bold mb-2"><i class="bi bi-grid-1x2-fill me-2"></i> <?php echo t('admin_dashboard_title'); ?></h2>
                    <p class="card-text text-muted mb-0"><?php echo t('admin_dashboard_desc'); ?></p>
                </div>
                <div class="d-flex gap-2 mt-3 mt-md-0">
                    <a href="create_user.php" class="btn btn-light border btn-lg shadow-sm text-primary"><i class="bi bi-people-fill me-1"></i> <?php echo t('users_btn'); ?></a>
                    <a href="manage_finances.php" class="btn btn-success btn-lg shadow-sm"><i class="bi bi-wallet2 me-1"></i> <?php echo t('manage_finances_btn'); ?></a>
                    <a href="committee_donations_log.php" class="btn btn-info btn-lg shadow-sm"><i class="bi bi-archive-fill me-1"></i> سجل التبرعات الواردة للجان</a>
                    <a href="committee_out_donations_log.php" class="btn btn-danger text-white btn-lg shadow-sm"><i class="bi bi-box-arrow-up-right me-1"></i> سجل التبرعات الصادرة</a>
                    <a href="audit_logs.php" class="btn btn-warning btn-lg shadow-sm text-dark"><i class="bi bi-shield-check me-1"></i> سجل التدقيق</a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- 2. ملخص عام للنظام (بطاقات الإحصائيات) -->
<div class="row mb-4">
    <div class="col-md-4 mb-3 mb-md-0">
        <div class="card text-white bg-primary bg-gradient shadow-sm border-0 h-100">
            <div class="card-body d-flex align-items-center">
                <div class="display-4 ms-4 bg-white bg-opacity-25 rounded-circle p-3"><i class="bi bi-people-fill"></i></div>
                <div>
                    <h5 class="card-title mb-1"><?php echo t('total_beneficiaries'); ?></h5>
                    <h2 class="mb-0 fw-bold"><?php echo $total_beneficiaries; ?></h2>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4 mb-3 mb-md-0">
        <div class="card text-white bg-success bg-gradient shadow-sm border-0 h-100">
            <div class="card-body d-flex align-items-center">
                <div class="display-4 ms-4 bg-white bg-opacity-25 rounded-circle p-3"><i class="bi bi-building-check"></i></div>
                <div>
                    <h5 class="card-title mb-1"><?php echo t('total_committees_entities'); ?></h5>
                    <h2 class="mb-0 fw-bold"><?php echo $total_committees; ?></h2>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card text-white bg-info bg-gradient shadow-sm border-0 h-100">
            <div class="card-body d-flex align-items-center">
                <div class="display-4 ms-4 bg-white bg-opacity-25 rounded-circle p-3"><i class="bi bi-person-badge-fill"></i></div>
                <div>
                    <h5 class="card-title mb-1"><?php echo t('active_users'); ?></h5>
                    <h2 class="mb-0 fw-bold"><?php echo $total_users; ?></h2>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- بطاقة الرصيد الإجمالي -->
<div class="row mb-4">
    <div class="col-md-6 mb-3 mb-md-0">
        <div class="card text-white bg-dark bg-gradient">
            <div class="card-body text-center p-4">
                <h5 class="card-title mb-2 opacity-75"><i class="bi bi-bank me-2"></i> <?php echo t('manager_balance'); ?></h5>
                <p class="card-text display-5 fw-bold mb-0 text-success"><?php echo number_format($manager_balance, 2); ?> JOD</p>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card text-white bg-secondary bg-gradient">
            <div class="card-body text-center p-4">
                <h5 class="card-title mb-2 opacity-75"><i class="bi bi-safe-fill me-2"></i> <?php echo t('total_available_balance'); ?></h5>
                <p class="card-text display-5 fw-bold mb-0"><?php echo number_format($total_balance, 2); ?> JOD</p>
            </div>
        </div>
    </div>
</div>

<!-- 3. المخططات البيانية والإحصائيات -->
<div class="row mb-4">
    <div class="col-md-4 mb-3 mb-md-0">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-white"><h5 class="mb-0 fw-bold text-dark"><i class="bi bi-bell text-warning"></i> <?php echo t('latest_notifications'); ?></h5></div>
            <div class="card-body">
                <ul class="list-group list-group-flush">
                    <li class="list-group-item px-0 border-0 text-muted"><i class="bi bi-circle-fill text-success ms-2" style="font-size: 8px;"></i> <?php echo t('system_stable'); ?></li>
                    <li class="list-group-item px-0 border-0 text-muted"><i class="bi bi-circle-fill text-info ms-2" style="font-size: 8px;"></i> <?php echo t('admin_login_success'); ?></li>
                    <li class="list-group-item px-0 border-0 text-muted"><i class="bi bi-circle-fill text-danger ms-2" style="font-size: 8px;"></i> <?php echo t('check_audit_logs'); ?></li>
                </ul>
            </div>
        </div>
    </div>
    <div class="col-md-4 mb-3 mb-md-0">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-white"><h5 class="mb-0 fw-bold text-dark"><i class="bi bi-pie-chart-fill text-primary"></i> <?php echo t('aid_distribution_by_type'); ?></h5></div>
            <div class="card-body d-flex justify-content-center align-items-center">
                <?php if(empty($chart_cat_data)): ?>
                    <p class="text-muted mb-0"><?php echo t('no_sufficient_data'); ?></p>
                <?php else: ?>
                    <canvas id="adminPieChart" style="max-height: 220px;"></canvas>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-white"><h5 class="mb-0 fw-bold text-dark"><i class="bi bi-bar-chart-fill text-success"></i> <?php echo t('beneficiaries_by_committee'); ?></h5></div>
            <div class="card-body d-flex justify-content-center align-items-center">
                <?php if(empty($chart_beneficiaries_count)): ?>
                    <p class="text-muted mb-0"><?php echo t('no_sufficient_data'); ?></p>
                <?php else: ?>
                    <canvas id="adminBarChart" style="max-height: 220px;"></canvas>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- عرض جداول اللجان والمستفيدين -->
<div class="row mt-4">
    <div class="col-12 mb-3 d-flex justify-content-between align-items-center border-bottom pb-2">
        <h4 class="text-secondary mb-0"><i class="bi bi-table"></i> <?php echo t('comprehensive_record'); ?></h4>
        <a href="export_beneficiaries.php" class="btn btn-sm btn-success shadow-sm"><i class="bi bi-file-earmark-excel me-1"></i> <?php echo t('export_excel'); ?></a>
    </div>

    <?php if (empty($grouped_beneficiaries)): ?>
        <div class="col-12"><div class="alert alert-info text-center"><?php echo t('no_data_yet'); ?></div></div>
    <?php else: ?>
        <?php foreach ($grouped_beneficiaries as $committee_name => $beneficiaries): ?>
        <div class="col-12 mb-4">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0 text-success"><i class="bi bi-building"></i> <?php echo htmlspecialchars($committee_name); ?> <span class="badge bg-secondary ms-2"><?php echo count($beneficiaries); ?> <?php echo t('beneficiary_word'); ?></span></h5>
                </div>
                <div class="card-body">
                    <?php if (count($beneficiaries) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th><?php echo t('national_id'); ?></th>
                                        <th><?php echo t('full_name'); ?></th>
                                        <th><?php echo t('phone_number'); ?></th>
                                        <th><?php echo t('file_status'); ?></th>
                                        <th style="width: 10%;"><?php echo t('actions'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($beneficiaries as $ben): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($ben['national_id']); ?></td>
                                        <td><?php echo htmlspecialchars($ben['full_name']); ?></td>
                                        <td><?php echo htmlspecialchars(!empty($ben['phone_number']) ? $ben['phone_number'] : t('not_found')); ?></td>
                                        <td><span class="badge bg-success"><?php echo htmlspecialchars($ben['status']); ?></span></td>
                                        <td>
                                            <a href="edit_beneficiary.php?national_id=<?php echo urlencode($ben['national_id']); ?>" class="btn btn-sm btn-outline-primary" title="<?php echo t('edit'); ?>"><i class="bi bi-pencil-square"></i></a>
                                            <a href="print_report.php?national_id=<?php echo urlencode($ben['national_id']); ?>" target="_blank" class="btn btn-sm btn-outline-secondary" title="<?php echo t('print'); ?>"><i class="bi bi-printer"></i></a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-light text-center mb-0 text-muted border"><?php echo t('no_data_yet'); ?></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- تضمين مكتبة Chart.js لإنشاء المخططات -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener("DOMContentLoaded", function() {
    // 1. مخطط توزيع المساعدات (Pie Chart)
    <?php if(!empty($chart_cat_data)): ?>
    var ctxPie = document.getElementById('adminPieChart').getContext('2d');
    new Chart(ctxPie, {
        type: 'doughnut',
        data: {
            labels: <?php echo json_encode($chart_cat_labels); ?>,
            datasets: [{
                data: <?php echo json_encode($chart_cat_data); ?>,
                backgroundColor: ['#0d9488', '#f59e0b', '#06b6d4', '#8b5cf6', '#10b981'],
                borderWidth: 0
            }]
        },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } } }
    });
    <?php endif; ?>

    // 2. مخطط المستفيدين حسب اللجنة (Bar Chart)
    <?php if(!empty($chart_beneficiaries_count)): ?>
    var ctxBar = document.getElementById('adminBarChart').getContext('2d');
    new Chart(ctxBar, {
        type: 'bar',
        data: {
            labels: <?php echo json_encode($chart_committees); ?>,
            datasets: [{
                label: 'عدد المستفيدين',
                data: <?php echo json_encode($chart_beneficiaries_count); ?>,
                backgroundColor: '#10b981',
                borderRadius: 4
            }]
        },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } } }
    });
    <?php endif; ?>
});
</script>

<?php
require_once __DIR__ . '/footer.php';
?>