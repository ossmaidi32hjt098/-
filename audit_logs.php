<?php
session_start();

// حماية الصفحة: للمدير فقط
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    header("Location: admin_login.php");
    exit;
}
require_once __DIR__ . '/db.php';

try {
    $central_pdo = new PDO("mysql:host=$host;dbname=zakat_central_db;charset=$charset", $user, $pass, $options);
    $stmt = $central_pdo->query("
        SELECT l.*, c.committee_name as committee_name
        FROM search_audit_logs l
        LEFT JOIN committees_registry c ON l.committee_id = c.id
        ORDER BY l.created_at DESC
        LIMIT 500
    ");
    $raw_logs = $stmt->fetchAll();
} catch (PDOException $e) {
    $raw_logs = [];
}

$logs = [];
$node_connections = []; // تخزين الاتصالات بالذاكرة لتسريع الأداء

foreach ($raw_logs as $log) {
    $log['employee_name'] = null;
    $log['employee_username'] = null;
    
    if ($log['user_id']) {
        if ($log['committee_id'] == 0) {
            // مدير النظام (القاعدة المركزية)
            try {
                $uStmt = $central_pdo->prepare("SELECT full_name, username FROM users WHERE id = ?");
                $uStmt->execute([$log['user_id']]);
                $usr = $uStmt->fetch();
                if ($usr) {
                    $log['employee_name'] = $usr['full_name'];
                    $log['employee_username'] = $usr['username'];
                }
            } catch (PDOException $e) {}
        } else {
            // موظف لجنة (القواعد الفرعية)
            $cid = $log['committee_id'];
            if (isset($db_nodes[$cid])) {
                if (!isset($node_connections[$cid])) {
                    try {
                        $node_connections[$cid] = new PDO("mysql:host=$host;dbname=" . $db_nodes[$cid] . ";charset=$charset", $user, $pass, $options);
                    } catch (PDOException $e) {
                        $node_connections[$cid] = null;
                    }
                }
                if ($node_connections[$cid]) {
                    try {
                        $uStmt = $node_connections[$cid]->prepare("SELECT full_name, username FROM users WHERE id = ?");
                        $uStmt->execute([$log['user_id']]);
                        $usr = $uStmt->fetch();
                        if ($usr) {
                            $log['employee_name'] = $usr['full_name'];
                            $log['employee_username'] = $usr['username'];
                        }
                    } catch (PDOException $e) {}
                }
            }
        }
    }
    $logs[] = $log;
}

require_once __DIR__ . '/header.php';
?>

<div class="row mb-4 mt-3">
    <div class="col-12 d-flex justify-content-between align-items-center">
        <h3 class="mb-0 text-primary"><i class="bi bi-shield-check me-2"></i> سجل التدقيق (Audit Logs)</h3>
        <a href="index.php" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-right"></i> العودة للوحة التحكم</a>
    </div>
</div>

<div class="card shadow-sm border-0 mb-5">
    <div class="card-header bg-white border-0 pt-4 pb-2">
        <h5 class="mb-0 text-dark fw-bold"><i class="bi bi-list-columns-reverse me-2"></i> سجل عمليات البحث والاستعلام</h5>
        <p class="text-muted mt-2 mb-0">يعرض هذا السجل محاولات البحث عن المستفيدين والتحقق من الازدواجية التي قام بها موظفو اللجان أو الزوار.</p>
    </div>
    <div class="card-body p-0">
        <?php if (count($logs) > 0): ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 text-nowrap">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4">التاريخ والوقت</th>
                            <th>الموظف (الاسم الكامل)</th>
                            <th>اللجنة المعنية</th>
                            <th>الرقم المستعلم عنه (الملف)</th>
                            <th>IP Address</th>
                            <th>النتيجة (موجود؟)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logs as $log): ?>
                        <tr>
                            <td class="ps-4">
                                <span class="fw-bold text-secondary" dir="ltr"><?php echo date('Y-m-d H:i:s', strtotime($log['created_at'])); ?></span>
                            </td>
                            <td>
                                <?php if ($log['employee_name']): ?>
                                    <strong><?php echo htmlspecialchars($log['employee_name']); ?></strong>
                                    <div class="text-muted small mt-1" style="font-size: 0.8rem;">
                                        <i class="bi bi-person-vcard"></i> حساب الدخول: <?php echo htmlspecialchars($log['employee_username']); ?>
                                    </div>
                                <?php elseif ($log['user_id']): ?>
                                    <strong class="text-danger">موظف / مستخدم محذوف</strong>
                                <?php else: ?>
                                    <strong class="text-muted">زائر / استعلام عام</strong>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($log['committee_id'] == 0 && $log['user_id'] != null): ?>
                                    <span class="badge bg-primary"><i class="bi bi-shield-lock"></i> الإدارة العليا</span>
                                <?php elseif ($log['committee_id']): ?>
                                    <span class="badge bg-secondary"><i class="bi bi-building"></i> <?php echo htmlspecialchars($log['committee_name'] ?? 'لجنة محذوفة'); ?></span>
                                <?php else: ?>
                                    <span class="badge bg-light text-dark border">بحث عام (زائر)</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge bg-light border text-dark fs-6"><?php echo htmlspecialchars($log['searched_national_id']); ?></span>
                            </td>
                            <td>
                                <span class="text-muted font-monospace small"><?php echo htmlspecialchars($log['ip_address']); ?></span>
                            </td>
                            <td>
                                <?php if ($log['was_successful']): ?>
                                    <span class="badge bg-success"><i class="bi bi-check-circle"></i> نعم (موجود)</span>
                                <?php else: ?>
                                    <span class="badge bg-danger"><i class="bi bi-x-circle"></i> لا (غير موجود)</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="alert alert-light text-center m-4 text-muted border">
                <i class="bi bi-info-circle fs-4 d-block mb-2"></i>
                لا توجد سجلات تدقيق مسجلة حتى الآن.
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>