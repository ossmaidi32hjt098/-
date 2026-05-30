<?php
session_start();

// حماية الصفحة: للمدير فقط
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    header("Location: admin_login.php");
    exit;
}
require_once __DIR__ . '/db.php';

// جلب جميع التبرعات الواردة من اللجان والمسجلة في القاعدة المركزية
$donations_history = [];
try {
    $central_pdo = new PDO("mysql:host=$host;dbname=zakat_central_db;charset=$charset", $user, $pass, $options);
    $donations_stmt = $central_pdo->query("
        SELECT 
            inc.*, 
            cr.committee_name, 
            dt.category, 
            dt.sub_category 
        FROM incoming_donations inc
        JOIN committees_registry cr ON inc.committee_id = cr.id
        LEFT JOIN donation_types dt ON inc.donation_type_id = dt.id
        WHERE inc.committee_id != 0
        ORDER BY inc.deposit_date DESC, inc.id DESC
        LIMIT 200
    ");
    $donations_history = $donations_stmt->fetchAll();
} catch (PDOException $e) {
    $error = "خطأ في جلب البيانات: " . $e->getMessage();
}

require_once __DIR__ . '/header.php';
?>

<div class="row mb-4 mt-3">
    <div class="col-12 d-flex justify-content-between align-items-center">
        <h3 class="mb-0 text-primary"><i class="bi bi-archive-fill me-2"></i> سجل التبرعات الواردة للجان</h3>
        <a href="index.php" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-right"></i> العودة للوحة التحكم</a>
    </div>
</div>

<?php if (isset($error)): ?>
    <div class="alert alert-danger"><?php echo $error; ?></div>
<?php endif; ?>

<div class="card shadow-sm border-0 mb-5">
    <div class="card-header bg-white border-0 pt-4 pb-2">
        <h5 class="mb-0 text-success fw-bold"><i class="bi bi-table me-2"></i> أحدث التبرعات الواردة للجان</h5>
        <p class="text-muted mt-1">هذا السجل يعرض نسخة من التبرعات التي استلمتها اللجان الفرعية وتم تسجيلها في النظام المركزي.</p>
    </div>
    <div class="card-body p-0">
        <?php if (count($donations_history) > 0): ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 text-nowrap">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4">التاريخ</th>
                            <th>المتبرع</th>
                            <th>نوع التبرع</th>
                            <th>القيمة / الكمية</th>
                            <th>طريقة الدفع</th>
                            <th>اللجنة المستفيدة</th>
                            <th>ملاحظات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($donations_history as $log): ?>
                        <tr>
                            <td class="ps-4"><div class="fw-bold text-secondary"><?php echo htmlspecialchars($log['donation_date'] ?: date('Y-m-d', strtotime($log['deposit_date']))); ?></div></td>
                            <td><strong><?php echo htmlspecialchars($log['donor_name'] ?: 'فاعل خير'); ?></strong></td>
                            <td>
                                <?php if($log['category'] == 'نقدي' || mb_strpos($log['category'], 'نقد') !== false): ?><span class="badge bg-warning text-dark"><i class="bi bi-cash-stack"></i> <?php echo htmlspecialchars($log['sub_category']); ?></span>
                                <?php else: ?><span class="badge bg-info text-dark"><i class="bi bi-box-seam"></i> <?php echo htmlspecialchars($log['sub_category']); ?></span><?php endif; ?>
                                <?php if(!empty($log['campaign_name'])) echo '<br><small class="text-muted">' . htmlspecialchars($log['campaign_name']) . '</small>'; ?>
                            </td>
                            <td>
                                <?php if($log['category'] == 'نقدي' || mb_strpos($log['category'], 'نقد') !== false): ?><strong class="text-success"><?php echo number_format((float)$log['quantity'], 2); ?> <?php echo htmlspecialchars($log['currency']); ?></strong>
                                <?php else: ?><strong class="text-primary"><?php echo (float)$log['quantity']; ?> وحدة</strong><?php endif; ?>
                            </td>
                            <td><span class="small text-muted"><?php echo htmlspecialchars($log['payment_method'] ?: '-'); ?></span></td>
                            <td><span class="badge bg-secondary"><i class="bi bi-building"></i> <?php echo htmlspecialchars($log['committee_name']); ?></span></td>
                            <td><span class="d-inline-block text-truncate text-muted" style="max-width: 150px;" title="<?php echo htmlspecialchars($log['notes'] ?? ''); ?>"><?php echo htmlspecialchars($log['notes'] ?: '-'); ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="alert alert-light text-center m-4 text-muted border"><i class="bi bi-info-circle fs-4 d-block mb-2"></i> لم يتم تسجيل أي تبرعات واردة من اللجان بعد.</div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>