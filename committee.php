<?php
// 1. بدء الجلسة والتحقق الأمني
session_start();
$committee_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!isset($_SESSION['logged_in_committee']) || $_SESSION['logged_in_committee'] !== $committee_id) {
    header("Location: login.php?id=" . $committee_id);
    exit;
}

// 2. استدعاء ملف الاتصال بقاعدة البيانات
require_once __DIR__ . '/db.php';

try {
    $central_pdo = new PDO("mysql:host=$host;dbname=zakat_central_db;charset=$charset", $user, $pass, $options);
    $stmt = $central_pdo->prepare("SELECT committee_name as name FROM committees_registry WHERE id = :id");
    $stmt->execute(['id' => $committee_id]);
    $committee = $stmt->fetch();
} catch (PDOException $e) { $committee = false; }

// إذا لم يتم العثور على اللجنة في قاعدة البيانات
if (!$committee) {
    require_once __DIR__ . '/header.php';
    echo '<div class="alert alert-danger text-center mt-5">عذراً، الجمعية أو اللجنة غير موجودة في قاعدة البيانات.</div>';
    require_once __DIR__ . '/footer.php';
    exit;
}

// 4. جلب إحصائية سريعة: عدد المستفيدين المختلفين الذين تلقوا دعماً من هذه اللجنة
$countStmt = $pdo->query("SELECT COUNT(DISTINCT national_id) FROM donations_history");
$total_beneficiaries = $countStmt->fetchColumn();

// جلب إحصائية: إجمالي عمليات التوزيع
$opsStmt = $pdo->query("SELECT COUNT(*) FROM donations_history");
$total_operations = $opsStmt->fetchColumn();

// جلب رصيد اللجنة الحالي
$balanceStmt = $pdo->query("SELECT balance FROM committee_finances LIMIT 1");
$committee_balance = $balanceStmt->fetchColumn() ?: 0.00;

// --- الحل الجذري: الحساب المباشر من سجل الإيداعات والتوزيعات (مزامنة تلقائية للرصيد النقدي للجنة) ---
try {
    $in_stmt = $pdo->prepare("
        SELECT SUM(inc.quantity) 
        FROM incoming_donations inc
        JOIN zakat_central_db.donation_types dt ON inc.donation_type_id = dt.id
        WHERE (dt.category = 'نقدي' OR dt.category LIKE '%نقد%' OR dt.sub_category LIKE '%نقد%')
    ");
    $in_stmt->execute();
    $com_in = (float)$in_stmt->fetchColumn();

    $out_stmt = $pdo->prepare("
        SELECT SUM(dh.amount) 
        FROM donations_history dh
        JOIN zakat_central_db.donation_types dt ON dh.donation_type_id = dt.id
        WHERE dh.donation_status = 'تم الصرف' AND (dt.category = 'نقدي' OR dt.category LIKE '%نقد%' OR dt.sub_category LIKE '%نقد%')
    ");
    $out_stmt->execute();
    $com_out = (float)$out_stmt->fetchColumn();

    $real_com_balance = $com_in - $com_out;
    
    // تحديث الجدول ليتطابق مع الواقع
    $pdo->exec("DELETE FROM committee_finances; INSERT INTO committee_finances (id, balance) VALUES (1, $real_com_balance)");
    $committee_balance = $real_com_balance;
} catch (PDOException $e) {}

// جلب مخزون اللجنة (المستودع العيني والنقدي التفصيلي)
$invStmt = $pdo->prepare("
    SELECT dt.category, dt.sub_category, SUM(ib.quantity) as quantity 
    FROM inventory_balances ib
    JOIN zakat_central_db.donation_types dt ON ib.donation_type_id = dt.id
    WHERE ib.quantity > 0
    GROUP BY dt.category, dt.sub_category
    ORDER BY dt.category ASC, dt.sub_category ASC
");
$invStmt->execute();
$committee_inventory = $invStmt->fetchAll();

// 5. جلب سجل التوزيعات التفصيلي لهذه اللجنة (يشمل جميع التفاصيل والموظف المسؤول)
$donationsStmt = $pdo->prepare("
    SELECT 
        b.national_id, 
        b.full_name, 
        dt.sub_category, 
        dt.category,
        dh.amount,
        dh.donation_date,
        dh.donation_source,
        dh.campaign_name,
        dh.donation_status,
        dh.delivery_method,
        dh.notes,
        dh.receipt_doc,
        u.full_name as employee_name
    FROM donations_history dh
    JOIN beneficiaries b ON dh.national_id = b.national_id
    JOIN zakat_central_db.donation_types dt ON dh.donation_type_id = dt.id
    LEFT JOIN users u ON dh.user_id = u.id
    ORDER BY dh.donation_date DESC
    LIMIT 50
");
$donationsStmt->execute();
$recent_donations = $donationsStmt->fetchAll();

// 5.5 جلب قائمة المستفيدين الذين تم حفظهم بنجاح
$beneficiariesStmt = $pdo->query("
    SELECT national_id, full_name, phone_number, status 
    FROM beneficiaries ORDER BY full_name ASC
");
$saved_beneficiaries = $beneficiariesStmt->fetchAll();

// 6. بيانات المخططات البيانية (للجنة)
// أ- حالات المستفيدين (مقبول، قيد الدراسة...)
$statusStmt = $pdo->query("SELECT status, COUNT(national_id) as count FROM beneficiaries GROUP BY status");
$status_data = $statusStmt->fetchAll();
$chart_status_labels = [];
$chart_status_counts = [];
foreach ($status_data as $row) {
    $chart_status_labels[] = $row['status'];
    $chart_status_counts[] = $row['count'];
}

// ب- أنواع المساعدات الموزعة
$catStmt = $pdo->query("SELECT dt.category, COUNT(dh.id) as count FROM donations_history dh JOIN zakat_central_db.donation_types dt ON dh.donation_type_id = dt.id GROUP BY dt.category");
$cat_data = $catStmt->fetchAll();
$chart_com_cat_labels = [];
$chart_com_cat_counts = [];
foreach ($cat_data as $row) {
    $chart_com_cat_labels[] = $row['category'];
    $chart_com_cat_counts[] = $row['count'];
}

// 7. استدعاء الهيدر (التصميم)
require_once __DIR__ . '/header.php'; 
?>

<div class="row mb-4 mt-3">
    <div class="col-12 d-flex justify-content-between align-items-center">
        <h3 class="mb-0 text-primary">
            <i class="bi bi-buildings-fill me-2"></i> <?php echo t('committee_dashboard'); ?> | <span class="text-dark"><?php echo htmlspecialchars($committee['name']); ?></span>
        </h3>
    </div>
</div>

<!-- بطاقات الإحصائيات العلوية للوحة التحكم -->
<div class="row mb-5">
    <div class="col-md-4">
        <div class="card text-white bg-primary bg-gradient shadow mb-3 border-0">
            <div class="card-body d-flex align-items-center justify-content-between p-4">
                <div>
                    <h5 class="card-title mb-2 opacity-75"><i class="bi bi-people-fill me-1"></i> <?php echo t('unique_beneficiaries'); ?></h5>
                    <p class="card-text display-5 fw-bold mb-0"><?php echo $total_beneficiaries; ?></p>
                </div>
                <i class="bi bi-person-hearts display-1 opacity-50"></i>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card text-white bg-success bg-gradient shadow mb-3 border-0">
            <div class="card-body d-flex align-items-center justify-content-between p-4">
                <div>
                    <h5 class="card-title mb-2 opacity-75"><i class="bi bi-box2-heart-fill me-1"></i> <?php echo t('completed_operations'); ?></h5>
                    <p class="card-text display-5 fw-bold mb-0"><?php echo $total_operations; ?></p>
                </div>
                <i class="bi bi-bag-check-fill display-1 opacity-50"></i>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card text-white bg-warning bg-gradient shadow mb-3 border-0">
            <div class="card-body d-flex align-items-center justify-content-between p-4">
                <div>
                    <h5 class="card-title mb-2 opacity-75 text-dark"><i class="bi bi-wallet-fill me-1"></i> <?php echo t('current_committee_balance'); ?></h5>
                    <p class="card-text display-5 fw-bold mb-0 text-dark"><?php echo number_format($committee_balance, 2); ?> JOD</p>
                </div>
                <i class="bi bi-cash-coin display-1 opacity-50 text-dark"></i>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- القائمة الجانبية: إجراءات سريعة -->
    <div class="col-md-3">
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white border-0 pt-4 pb-2">
                <h5 class="mb-0 text-dark fw-bold"><i class="bi bi-lightning-charge-fill text-warning me-2"></i> <?php echo t('quick_actions'); ?></h5>
            </div>
            <div class="card-body d-flex flex-column gap-2 p-3">
                <a href="add_donation.php?committee_id=<?php echo $committee_id; ?>" class="btn btn-success btn-lg text-start shadow-sm"><i class="bi bi-plus-circle me-2"></i> <?php echo t('record_new_distribution'); ?></a>
                <a href="add_donor.php" class="btn btn-warning btn-lg text-dark text-start shadow-sm"><i class="bi bi-box-arrow-in-down me-2"></i> استلام تبرع للجنة</a>
                <a href="add_beneficiary.php?committee_id=<?php echo $committee_id; ?>" class="btn btn-primary btn-lg text-start shadow-sm"><i class="bi bi-person-plus-fill me-2"></i> <?php echo t('add_new_beneficiary'); ?></a>
                <a href="print_report.php?committee_id=<?php echo $committee_id; ?>" target="_blank" class="btn btn-light border text-danger btn-lg text-start shadow-sm mt-2"><i class="bi bi-file-earmark-pdf-fill me-2"></i> <?php echo t('print_reports_pdf'); ?></a>
            </div>
        </div>

        <!-- مستودع اللجنة -->
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-dark text-white border-0 pt-4 pb-2">
                <h5 class="mb-0 fw-bold"><i class="bi bi-box-seam me-2"></i> <?php echo t('committee_warehouse'); ?></h5>
            </div>
            <div class="card-body p-0">
                <?php if (count($committee_inventory) > 0): ?>
                    <ul class="list-group list-group-flush">
                        <?php foreach ($committee_inventory as $item): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <div>
                                    <span class="fw-bold text-secondary"><?php echo htmlspecialchars($item['sub_category']); ?></span>
                                </div>
                                <?php if($item['category'] == 'نقدي' || mb_strpos($item['category'], 'نقد') !== false): ?>
                                    <span class="badge bg-warning text-dark rounded-pill fs-6"><?php echo number_format($item['quantity'], 2); ?></span>
                                <?php else: ?>
                                    <span class="badge bg-info text-dark rounded-pill fs-6"><?php echo (float)$item['quantity']; ?></span>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <div class="p-3 text-center text-muted small"><?php echo t('warehouse_empty'); ?></div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- منطقة المحتوى الرئيسي: جدول العمليات -->
    <div class="col-md-9">
        <!-- قائمة المستفيدين المسجلين -->
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi bi-person-lines-fill"></i> <?php echo t('beneficiaries_list'); ?></h5>
                <div class="d-flex gap-2 w-50 justify-content-end">
                    <a href="export_beneficiaries.php" class="btn btn-sm btn-success shadow-sm" title="<?php echo t('export_excel'); ?>"><i class="bi bi-file-earmark-excel"></i> <?php echo t('export_excel'); ?></a>
                    <input type="text" class="form-control" id="searchCommitteeInput" placeholder="<?php echo t('search_name_id'); ?>">
                </div>
            </div>
            <div class="card-body">
                <?php if (count($saved_beneficiaries) > 0): ?>
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
                            <tbody id="committeeBeneficiariesTable">
                                <?php foreach ($saved_beneficiaries as $ben): ?>
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
                    <div class="alert alert-info text-center mb-0"><?php echo t('no_data_yet'); ?></div>
                <?php endif; ?>
            </div>
        </div>

        <div class="card shadow-sm border-0">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi bi-card-list text-primary"></i> <?php echo t('detailed_distribution_record'); ?></h5>
            </div>
            <div class="card-body p-0">
                <?php if (count($recent_donations) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0 text-nowrap">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-4"><?php echo t('beneficiary_word'); ?></th>
                                    <th><?php echo t('type_amount'); ?></th>
                                    <th><?php echo t('date_status'); ?></th>
                                    <th><?php echo t('delivery_source'); ?></th>
                                    <th><?php echo t('responsible_employee'); ?></th>
                                    <th><?php echo t('notes'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_donations as $donation): ?>
                                <tr>
                                    <td class="ps-4">
                                        <strong><?php echo htmlspecialchars($donation['full_name']); ?></strong><br>
                                        <span class="text-muted small border bg-light px-1 rounded"><?php echo htmlspecialchars($donation['national_id']); ?></span>
                                    </td>
                                    <td>
                                        <?php if($donation['category'] == 'نقدي'): ?>
                                            <span class="badge bg-warning text-dark mb-1"><i class="bi bi-cash-stack"></i> <?php echo htmlspecialchars($donation['sub_category']); ?></span><br>
                                            <strong class="text-success"><?php echo number_format((float)$donation['amount'], 2); ?> JOD</strong>
                                        <?php else: ?>
                                            <span class="badge bg-info text-dark"><i class="bi bi-box-seam"></i> <?php echo htmlspecialchars($donation['sub_category']); ?> (عيني)</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="fw-bold text-secondary"><?php echo date('Y-m-d', strtotime($donation['donation_date'])); ?></div>
                                        <?php 
                                        $status_class = 'bg-success';
                                        if ($donation['donation_status'] == 'قيد الانتظار') $status_class = 'bg-warning text-dark';
                                        if ($donation['donation_status'] == 'مرفوض') $status_class = 'bg-danger';
                                        ?>
                                        <span class="badge <?php echo $status_class; ?>"><?php echo htmlspecialchars($donation['donation_status'] ?? t('unspecified')); ?></span>
                                    </td>
                                    <td>
                                        <div class="small mb-1"><i class="bi bi-truck text-primary"></i> <?php echo htmlspecialchars($donation['delivery_method'] ?? t('unspecified')); ?></div>
                                        <div class="small text-muted"><i class="bi bi-diagram-3"></i> <?php echo htmlspecialchars($donation['donation_source'] ?? t('unspecified')); ?>
                                            <?php if(!empty($donation['campaign_name'])) echo ' <br><span class="text-info">(' . htmlspecialchars($donation['campaign_name']) . ')</span>'; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="small text-muted fw-bold"><i class="bi bi-person-circle"></i> <?php echo htmlspecialchars($donation['employee_name'] ?? 'مدير النظام'); ?></span>
                                    </td>
                                    <td>
                                        <span class="d-inline-block text-truncate text-muted" style="max-width: 150px;" title="<?php echo htmlspecialchars($donation['notes']); ?>">
                                            <?php echo htmlspecialchars($donation['notes'] ?: '-'); ?>
                                        </span>
                                        <?php if(!empty($donation['receipt_doc'])): ?>
                                            <br><a href="uploads/receipts/<?php echo htmlspecialchars($donation['receipt_doc']); ?>" target="_blank" class="badge bg-danger text-decoration-none mt-1"><i class="bi bi-file-earmark-pdf"></i> عرض المرفق</a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="alert alert-warning text-center m-4"><?php echo t('no_data_yet'); ?></div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
// تفعيل فلترة البحث السريع في قائمة المستفيدين داخل اللجنة
document.getElementById('searchCommitteeInput')?.addEventListener('keyup', function() {
    let filter = this.value.toLowerCase();
    let rows = document.querySelectorAll('#committeeBeneficiariesTable tr');
    
    rows.forEach(row => {
        let text = row.textContent.toLowerCase();
        row.style.display = text.includes(filter) ? '' : 'none';
    });
});
</script>

<!-- تضمين مكتبة Chart.js لإنشاء المخططات -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener("DOMContentLoaded", function() {
    // مخطط حالات المستفيدين (Pie)
    <?php if(!empty($chart_status_counts)): ?>
    var ctxStatus = document.getElementById('comStatusChart').getContext('2d');
    new Chart(ctxStatus, {
        type: 'pie',
        data: {
            labels: <?php echo json_encode($chart_status_labels); ?>,
            datasets: [{
                data: <?php echo json_encode($chart_status_counts); ?>,
                backgroundColor: ['#10b981', '#f59e0b', '#ef4444', '#6b7280'],
                borderWidth: 0
            }]
        },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'right' } } }
    });
    <?php endif; ?>

    // مخطط أنواع المساعدات (Bar)
    <?php if(!empty($chart_com_cat_counts)): ?>
    var ctxCat = document.getElementById('comCatChart').getContext('2d');
    new Chart(ctxCat, {
        type: 'bar',
        data: {
            labels: <?php echo json_encode($chart_com_cat_labels); ?>,
            datasets: [{
                label: 'عدد العمليات',
                data: <?php echo json_encode($chart_com_cat_counts); ?>,
                backgroundColor: '#06b6d4',
                borderRadius: 4
            }]
        },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } } }
    });
    <?php endif; ?>
});
</script>

<?php 
// 8. استدعاء الفوتر
require_once __DIR__ . '/footer.php'; 
?>