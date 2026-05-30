<?php
session_start();

// حماية الصفحة: للمدير فقط
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    header("Location: admin_login.php");
    exit;
}
require_once __DIR__ . '/db.php';

// جلب جميع التبرعات الصادرة (المساعدات والتوزيعات) من قواعد بيانات اللجان الفرعية
$out_donations_history = [];
$error = null;

try {
    $central_pdo = new PDO("mysql:host=$host;dbname=zakat_central_db;charset=$charset", $user, $pass, $options);
    $registry = $central_pdo->query("SELECT id, committee_name FROM committees_registry ORDER BY id ASC")->fetchAll();
    
    foreach ($registry as $com) {
        $cid = $com['id'];
        $cname = $com['committee_name'];
        
        if (isset($db_nodes[$cid])) {
            try {
                $node_pdo = new PDO("mysql:host=$host;dbname=" . $db_nodes[$cid] . ";charset=$charset", $user, $pass, $options);
                $stmt = $node_pdo->query("
                    SELECT 
                        dh.*, 
                        b.full_name as beneficiary_name,
                        dt.category, 
                        dt.sub_category,
                        u.full_name as employee_name
                    FROM donations_history dh
                    JOIN beneficiaries b ON dh.national_id = b.national_id
                    JOIN zakat_central_db.donation_types dt ON dh.donation_type_id = dt.id
                    LEFT JOIN users u ON dh.user_id = u.id
                    ORDER BY dh.donation_date DESC
                    LIMIT 100
                ");
                while ($row = $stmt->fetch()) {
                    $row['committee_name'] = $cname;
                    $out_donations_history[] = $row;
                }
            } catch (PDOException $e) {
                // تجاهل قواعد البيانات غير المتصلة أو المعطلة
            }
        }
    }
    
    // ترتيب المصفوفة المجمعة حسب التاريخ تنازلياً
    usort($out_donations_history, function($a, $b) {
        $timeA = strtotime($a['donation_date']);
        $timeB = strtotime($b['donation_date']);
        if ($timeA == $timeB) return $b['id'] <=> $a['id'];
        return $timeB <=> $timeA;
    });
    
    // أخذ أحدث 200 عملية للعرض
    $out_donations_history = array_slice($out_donations_history, 0, 200);

} catch (PDOException $e) {
    $error = "خطأ في الاتصال بالقاعدة المركزية: " . $e->getMessage();
}

require_once __DIR__ . '/header.php';
?>

<div class="row mb-4 mt-3">
    <div class="col-12 d-flex justify-content-between align-items-center">
        <h3 class="mb-0 text-primary"><i class="bi bi-box-arrow-up-right me-2"></i> سجل التبرعات الصادرة من اللجان</h3>
        <a href="index.php" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-right"></i> العودة للوحة التحكم</a>
    </div>
</div>

<?php if (isset($error)): ?>
    <div class="alert alert-danger"><?php echo $error; ?></div>
<?php endif; ?>

<div class="card shadow-sm border-0 mb-5">
    <div class="card-header bg-white border-0 pt-4 pb-2">
        <h5 class="mb-0 text-danger fw-bold"><i class="bi bi-table me-2"></i> أحدث المساعدات والتبرعات المصروفة</h5>
        <p class="text-muted mt-1">يعرض هذا السجل التوزيعات والمساعدات التي قامت اللجان بصرفها للمستفيدين (مجمعة من كافة اللجان).</p>
    </div>
    <div class="card-body p-0">
        <?php if (count($out_donations_history) > 0): ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 text-nowrap">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4">التاريخ</th>
                            <th>المستفيد</th>
                            <th>اللجنة / الموظف</th>
                            <th>نوع المساعدة</th>
                            <th>القيمة / الكمية</th>
                            <th>الحالة وطريقة التسليم</th>
                            <th>ملاحظات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($out_donations_history as $log): ?>
                        <tr>
                            <td class="ps-4"><div class="fw-bold text-secondary"><?php echo date('Y-m-d', strtotime($log['donation_date'])); ?></div></td>
                            <td><strong><?php echo htmlspecialchars($log['beneficiary_name']); ?></strong><br><small class="text-muted border bg-light px-1 rounded"><?php echo htmlspecialchars($log['national_id']); ?></small></td>
                            <td><span class="badge bg-secondary mb-1"><i class="bi bi-building"></i> <?php echo htmlspecialchars($log['committee_name']); ?></span><br><small class="text-muted"><i class="bi bi-person-circle"></i> <?php echo htmlspecialchars($log['employee_name'] ?? 'مدير النظام'); ?></small></td>
                            <td><?php if($log['category'] == 'نقدي' || mb_strpos($log['category'], 'نقد') !== false): ?><span class="badge bg-warning text-dark"><i class="bi bi-cash-stack"></i> <?php echo htmlspecialchars($log['sub_category']); ?></span><?php else: ?><span class="badge bg-info text-dark"><i class="bi bi-box-seam"></i> <?php echo htmlspecialchars($log['sub_category']); ?> (عيني)</span><?php endif; ?></td>
                            <td><?php if($log['category'] == 'نقدي' || mb_strpos($log['category'], 'نقد') !== false): ?><strong class="text-success"><?php echo number_format((float)$log['amount'], 2); ?> JOD</strong><?php else: ?><strong class="text-primary"><?php echo (float)$log['amount']; ?> وحدة</strong><?php endif; ?></td>
                            <td><?php $status_class = 'bg-success'; if ($log['donation_status'] == 'قيد الانتظار') $status_class = 'bg-warning text-dark'; if ($log['donation_status'] == 'مرفوض') $status_class = 'bg-danger'; ?><span class="badge <?php echo $status_class; ?> mb-1"><?php echo htmlspecialchars($log['donation_status'] ?? 'تم الصرف'); ?></span><br><small class="text-muted"><i class="bi bi-truck"></i> <?php echo htmlspecialchars($log['delivery_method'] ?? 'غير محدد'); ?></small></td>
                            <td><span class="d-inline-block text-truncate text-muted" style="max-width: 150px;" title="<?php echo htmlspecialchars($log['notes'] ?? ''); ?>"><?php echo htmlspecialchars($log['notes'] ?: '-'); ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="alert alert-light text-center m-4 text-muted border"><i class="bi bi-info-circle fs-4 d-block mb-2"></i> لم يتم تسجيل أي مساعدات مصروفة من اللجان بعد.</div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>