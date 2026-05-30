<?php
session_start();

if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    header("Location: admin_login.php");
    exit;
}

require_once __DIR__ . '/db.php';

$action_msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $target_nid = $_POST['target_nid'] ?? '';
    $target_cid = (int)($_POST['target_cid'] ?? 0);
    
    if ($target_nid && $target_cid) {
        $node_db = $db_nodes[$target_cid] ?? null;
        if ($node_db) {
            try {
                $action_pdo = new PDO("mysql:host=$host;dbname=$node_db;charset=$charset", $user, $pass, $options);
                
                if ($_POST['action'] === 'reject') {
                    $action_pdo->prepare("UPDATE beneficiaries SET status = 'مرفوض' WHERE national_id = ?")->execute([$target_nid]);
                    $action_msg = "<div class='alert alert-success text-center shadow-sm border-0'><i class='bi bi-check-circle-fill'></i> تم تغيير حالة الملف إلى 'مرفوض' بنجاح.</div>";
                } elseif ($_POST['action'] === 'add_note') {
                    $check = $action_pdo->query("SHOW COLUMNS FROM beneficiaries LIKE 'admin_notes'")->fetch();
                    if (!$check) {
                        $action_pdo->exec("ALTER TABLE beneficiaries ADD COLUMN admin_notes TEXT NULL");
                    }
                    $note = trim($_POST['note_text'] ?? '');
                    $current_user = $_SESSION['user_name'] ?? 'مدير النظام';
                    $timestamp = date('Y-m-d H:i');
                    $formatted_note = "[$timestamp - $current_user]: $note";
                    
                    $action_pdo->prepare("UPDATE beneficiaries SET admin_notes = CONCAT(IFNULL(admin_notes, ''), '\n', ?) WHERE national_id = ?")->execute([$formatted_note, $target_nid]);
                    $action_msg = "<div class='alert alert-success text-center shadow-sm border-0'><i class='bi bi-check-circle-fill'></i> تم تسجيل الملاحظة في سجل التدقيق للمستفيد بنجاح.</div>";
                }
            } catch (PDOException $e) {
                $action_msg = "<div class='alert alert-danger text-center shadow-sm'><i class='bi bi-exclamation-triangle'></i> خطأ أثناء تنفيذ الإجراء.</div>";
            }
        }
    }
}

$search_query = isset($_GET['q']) ? trim($_GET['q']) : '';
$search_dob = isset($_GET['dob']) ? trim($_GET['dob']) : '';
$results = [];

if ($search_query !== '' || $search_dob !== '') {
    $conditions = [];
    $params = [];

    if ($search_query !== '') {
        $conditions[] = "(b.national_id = :q_exact OR b.full_name LIKE :q_like)";
        $params['q_exact'] = $search_query;
        $params['q_like'] = '%' . $search_query . '%';
    }
    
    if ($search_dob !== '') {
        $conditions[] = "date_of_birth = :dob";
        $params['dob'] = $search_dob;
    }

    $where_sql = implode(" AND ", $conditions);

    try {
        $central_pdo = new PDO("mysql:host=$host;dbname=zakat_central_db;charset=$charset", $user, $pass, $options);
        $registry = $central_pdo->query("SELECT id, committee_name as name FROM committees_registry ORDER BY id ASC")->fetchAll();
    } catch (PDOException $e) {
        $registry = [];
    }

    foreach ($registry as $com) {
        $cid = $com['id'];
        $cname = $com['name'];
        if (isset($db_nodes[$cid])) {
            $node_db = $db_nodes[$cid];
            try {
                $dsn_node = "mysql:host=$host;dbname=$node_db;charset=$charset";
                $node_pdo = new PDO($dsn_node, $user, $pass, $options);
                
                $stmt = $node_pdo->prepare("SELECT * FROM beneficiaries WHERE $where_sql ORDER BY full_name ASC");
                $stmt->execute($params);
                $bens = $stmt->fetchAll();
                
                foreach ($bens as $row) {
                    $row['committee_name'] = $cname;
                    $row['committee_id_actual'] = $cid;
                    
                    $dhStmt = $node_pdo->prepare("
                        SELECT dh.amount, dh.donation_date, dh.donation_source, dh.campaign_name, dh.donation_status, dh.delivery_method, dh.notes, dh.receipt_doc,
                               dt.category, dt.sub_category,
                               u.full_name as employee_name
                        FROM donations_history dh
                        JOIN zakat_central_db.donation_types dt ON dh.donation_type_id = dt.id
                        LEFT JOIN users u ON dh.user_id = u.id
                        WHERE dh.national_id = :nid
                        ORDER BY dh.donation_date DESC
                    ");
                    $dhStmt->execute(['nid' => $row['national_id']]);
                    $donations = $dhStmt->fetchAll();
                    foreach($donations as &$d) { $d['committee_name'] = $cname; }
                    $row['donations_history'] = $donations;
                    $results[] = $row;
                }
            } catch (PDOException $e) {}
        }
    }
    
    // --- تسجيل العملية في سجل التدقيق ---
    if ($search_query !== '') {
        try {
            $was_successful = count($results) > 0 ? 1 : 0;
            $ip_address = getUserIP();
            $user_id = $_SESSION['user_id'] ?? null;
            $committee_id = $_SESSION['logged_in_committee'] ?? 0; // 0 للإدارة
            
            $logStmt = $central_pdo->prepare("INSERT INTO search_audit_logs (user_id, committee_id, searched_national_id, ip_address, was_successful) VALUES (?, ?, ?, ?, ?)");
            $logStmt->execute([$user_id, $committee_id, $search_query, $ip_address, $was_successful]);
        } catch (PDOException $e) {}
    }
}

require_once __DIR__ . '/header.php';
?>

<div class="row mb-4 mt-3">
    <div class="col-12">
        <h3 class="mb-0 text-primary">
            <i class="bi bi-search"></i> <?php echo t('central_search_title'); ?>
        </h3>
        <p class="text-muted mt-2"><?php echo t('central_search_desc'); ?></p>
    </div>
</div>

<div class="card shadow-sm border-0 mb-5">
    <?php if (!empty($action_msg)) echo $action_msg; ?>
    <div class="card-body p-4">
        <form method="GET" action="central_search.php" class="row g-3 justify-content-center">
            <div class="col-md-5">
                <div class="input-group input-group-lg">
                    <span class="input-group-text bg-white text-primary"><i class="bi bi-person-vcard"></i></span>
                    <input type="text" name="q" class="form-control" placeholder="<?php echo t('search_placeholder'); ?>" value="<?php echo htmlspecialchars($search_query); ?>">
                </div>
            </div>
            <div class="col-md-4">
                <div class="input-group input-group-lg">
                    <span class="input-group-text bg-white text-primary"><i class="bi bi-calendar-date"></i></span>
                    <input type="date" name="dob" class="form-control" title="تاريخ الميلاد" value="<?php echo htmlspecialchars($search_dob); ?>">
                </div>
            </div>
            <div class="col-md-2">
                <button class="btn btn-primary btn-lg w-100" type="submit"><i class="bi bi-search"></i> <?php echo t('search_btn'); ?></button>
            </div>
        </form>
    </div>
</div>

<?php if ($search_query !== '' || $search_dob !== ''): ?>
    <?php 
    $search_terms = [];
    if ($search_query !== '') $search_terms[] = htmlspecialchars($search_query);
    if ($search_dob !== '') $search_terms[] = htmlspecialchars($search_dob);
    ?>
    <h4 class="mb-3 text-secondary border-bottom pb-2"><?php echo t('search_results_for'); ?> "<?php echo implode(' + ', $search_terms); ?>"</h4>
    
    <?php if (count($results) > 0): ?>
        <div class="row g-4">
            <?php foreach ($results as $row): ?>
                <div class="col-12">
                    <div class="card shadow border-0 border-top border-5 <?php echo count($row['donations_history']) > 0 ? 'border-warning' : 'border-success'; ?>">
                        <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
                            <h4 class="mb-0 text-primary fw-bold"><i class="bi bi-person-vcard"></i> <?php echo htmlspecialchars($row['full_name']); ?></h4>
                            <span class="badge bg-secondary fs-6"><i class="bi bi-building"></i> <?php echo htmlspecialchars($row['committee_name'] ?? 'غير محدد'); ?></span>
                        </div>
                        <div class="card-body p-4">
                            <div class="row mb-4 g-3">
                                <!-- 1. البيانات الأساسية -->
                                <div class="col-md-4">
                                    <div class="p-3 bg-light rounded h-100">
                                        <h6 class="text-primary border-bottom pb-2 fw-bold"><i class="bi bi-info-circle"></i> <?php echo t('basic_info'); ?></h6>
                                        <table class="table table-sm table-borderless mb-0">
                                            <tr><th class="text-muted w-50"><?php echo t('national_id'); ?>:</th><td class="fw-bold"><?php echo htmlspecialchars($row['national_id']); ?></td></tr>
                                            <tr><th class="text-muted">تاريخ الميلاد:</th><td><?php echo htmlspecialchars($row['date_of_birth'] ?: 'غير متوفر'); ?></td></tr>
                                            <tr><th class="text-muted">رقم الهاتف:</th><td><span dir="ltr"><?php echo htmlspecialchars($row['phone_number'] ?: 'لا يوجد'); ?></span></td></tr>
                                            <tr><th class="text-muted"><?php echo t('file_status'); ?>:</th><td><span class="badge bg-<?php echo $row['status'] == 'مقبول' ? 'success' : ($row['status'] == 'مرفوض' ? 'danger' : 'warning text-dark'); ?>"><?php echo htmlspecialchars($row['status']); ?></span></td></tr>
                                        </table>
                                    </div>
                                </div>
                                <!-- 2. العائلة والوضع الاقتصادي -->
                                <div class="col-md-4">
                                    <div class="p-3 bg-light rounded h-100">
                                        <h6 class="text-primary border-bottom pb-2 fw-bold"><i class="bi bi-people"></i> <?php echo t('family_economic_status'); ?></h6>
                                        <table class="table table-sm table-borderless mb-0">
                                            <tr><th class="text-muted w-50">عدد أفراد الأسرة:</th><td class="fw-bold"><?php echo (int)$row['family_size']; ?> أفراد</td></tr>
                                            <tr><th class="text-muted">الدخل الشهري:</th><td class="text-success fw-bold"><?php echo (float)$row['monthly_income']; ?> JOD</td></tr>
                                            <tr><th class="text-muted">الحالة الوظيفية:</th><td><?php echo htmlspecialchars($row['employment_status'] ?: 'غير محدد'); ?></td></tr>
                                            <tr><th class="text-muted">نوع السكن:</th><td><?php echo htmlspecialchars($row['housing_type'] ?: 'غير محدد'); ?></td></tr>
                                        </table>
                                    </div>
                                </div>
                                <!-- 3. الصحة والتعليم -->
                                <div class="col-md-4">
                                    <div class="p-3 bg-light rounded h-100">
                                        <h6 class="text-primary border-bottom pb-2 fw-bold"><i class="bi bi-heart-pulse"></i> <?php echo t('health_education_status'); ?></h6>
                                        <table class="table table-sm table-borderless mb-0">
                                            <tr><th class="text-muted w-50">الحالة الصحية:</th><td><?php echo htmlspecialchars($row['health_general'] ?: 'غير محدد'); ?></td></tr>
                                            <tr><th class="text-muted">أمراض مزمنة/إعاقة:</th><td><?php echo ($row['has_chronic_disease'] || $row['has_disability']) ? '<span class="badge bg-danger">نعم</span>' : '<span class="badge bg-success">لا</span>'; ?></td></tr>
                                            <tr><th class="text-muted">مستوى التعليم:</th><td><?php echo htmlspecialchars($row['education_level'] ?: 'غير محدد'); ?></td></tr>
                                            <tr><th class="text-muted">عدد الطلاب:</th><td><?php echo (int)$row['student_count']; ?> طلاب</td></tr>
                                        </table>
                                    </div>
                                </div>
                            </div>

                            <h5 class="text-success border-bottom pb-2 mb-3 fw-bold"><i class="bi bi-gift"></i> <?php echo t('aid_history'); ?></h5>
                            <?php if (count($row['donations_history']) > 0): ?>
                                <div class="table-responsive">
                                    <table class="table table-hover table-bordered align-middle text-nowrap mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th><?php echo t('type_amount'); ?></th>
                                                <th><?php echo t('date_status'); ?></th>
                                                <th><?php echo t('delivery_source'); ?></th>
                                                <th><?php echo t('donor_entity'); ?></th>
                                                <th><?php echo t('notes'); ?></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                        <?php foreach ($row['donations_history'] as $donation): 
                                            $donation_date_obj = new DateTime($donation['donation_date']);
                                            $now_obj = new DateTime();
                                            $donation_date_obj->setTime(0, 0, 0); // تصفير الساعات
                                            $now_obj->setTime(0, 0, 0);
                                            $interval = $now_obj->diff($donation_date_obj);
                                            $days_ago = $interval->days;
                                            $is_recent = ($days_ago <= 30 && $donation_date_obj <= $now_obj);
                                            $row_class = $is_recent ? 'class="table-danger"' : '';
                                            
                                            $days_text = "قبل " . $days_ago . " يوم";
                                            if ($days_ago == 0) $days_text = "اليوم";
                                            elseif ($days_ago == 1) $days_text = "أمس";
                                            elseif ($days_ago == 2) $days_text = "قبل يومين";
                                            elseif ($days_ago <= 10) $days_text = "قبل " . $days_ago . " أيام";
                                        ?>
                                        <tr <?php echo $row_class; ?>>
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
                                                    <span class="badge <?php echo $status_class; ?>"><?php echo htmlspecialchars($donation['donation_status'] ?? 'تم الصرف'); ?></span>
                                                <?php if ($is_recent): ?>
                                                    <br><span class="badge bg-danger mt-1"><i class="bi bi-clock-history"></i> استلم مساعدة حديثاً (<?php echo $days_text; ?>)</span>
                                                <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div class="small mb-1"><i class="bi bi-truck text-primary"></i> <?php echo htmlspecialchars($donation['delivery_method'] ?? 'غير محدد'); ?></div>
                                                    <div class="small text-muted"><i class="bi bi-diagram-3"></i> <?php echo htmlspecialchars($donation['donation_source'] ?? 'غير محدد'); ?>
                                                        <?php if(!empty($donation['campaign_name'])) echo ' <br><span class="text-info">(' . htmlspecialchars($donation['campaign_name']) . ')</span>'; ?>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="small fw-bold"><i class="bi bi-building text-secondary"></i> <?php echo htmlspecialchars($donation['committee_name'] ?? 'الصندوق الرئيسي'); ?></div>
                                                    <div class="small text-muted"><i class="bi bi-person-circle"></i> <?php echo htmlspecialchars($donation['employee_name'] ?? 'مدير النظام'); ?></div>
                                                </td>
                                                <td>
                                                    <span class="d-inline-block text-truncate text-muted" style="max-width: 150px;" title="<?php echo htmlspecialchars($donation['notes']); ?>">
                                                        <?php echo htmlspecialchars($donation['notes'] ?: '-'); ?>
                                                    </span>
                                                    <?php if(!empty($donation['receipt_doc'])): ?>
                                                        <br><a href="uploads/receipts/<?php echo htmlspecialchars($donation['receipt_doc']); ?>" target="_blank" class="badge bg-danger text-decoration-none mt-1"><i class="bi bi-file-earmark-pdf"></i> المرفق</a>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-light text-center border text-muted mb-0">
                                    <i class="bi bi-info-circle fs-4 d-block mb-2"></i>
                                    لم يستلم هذا المستفيد أي مساعدات أو تبرعات حتى الآن.
                                </div>
                            <?php endif; ?>
                            
                            <?php 
                            $is_admin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;
                            $my_committee = isset($_SESSION['logged_in_committee']) ? $_SESSION['logged_in_committee'] : null;
                            $can_edit = ($is_admin || $my_committee == $row['committee_id_actual']);
                            ?>
                            
                            <hr class="my-4">
                            <h6 class="text-danger fw-bold mb-3"><i class="bi bi-shield-exclamation"></i> لوحة القرارات والإجراءات (التعامل مع الحالة)</h6>
                            
                            <?php if (!empty($row['admin_notes'])): ?>
                            <div class="mb-3 p-3 bg-white rounded border border-warning shadow-sm small">
                                <strong class="text-dark"><i class="bi bi-journal-check text-warning"></i> ملاحظات سجل التدقيق المسجلة سابقاً:</strong><br>
                                <div class="mt-2 text-muted" style="white-space: pre-wrap;"><?php echo htmlspecialchars($row['admin_notes']); ?></div>
                            </div>
                            <?php endif; ?>

                            <div class="d-flex flex-wrap gap-2 align-items-center bg-light p-3 rounded border">
                                <?php if ($can_edit): ?>
                                    <!-- Reject Form -->
                                    <form method="POST" class="m-0">
                                        <input type="hidden" name="action" value="reject">
                                        <input type="hidden" name="target_nid" value="<?php echo htmlspecialchars($row['national_id']); ?>">
                                        <input type="hidden" name="target_cid" value="<?php echo $row['committee_id_actual']; ?>">
                                        <button type="submit" class="btn btn-danger btn-sm shadow-sm" onclick="return confirm('تأكيد رفض الطلب وتغيير حالة الملف إلى مرفوض؟');"><i class="bi bi-x-octagon-fill"></i> رفض الطلب لوجود ازدواجية</button>
                                    </form>
                                    
                                    <!-- Add Note Modal Trigger -->
                                    <button type="button" class="btn btn-warning btn-sm text-dark shadow-sm" data-bs-toggle="modal" data-bs-target="#noteModal_<?php echo md5($row['national_id'].$row['committee_id_actual']); ?>"><i class="bi bi-journal-plus"></i> تسجيل ملاحظة في سجل التدقيق</button>
                                    
                                    <!-- Exceptional Aid -->
                                    <a href="add_donation.php?committee_id=<?php echo $row['committee_id_actual']; ?>&national_id=<?php echo urlencode($row['national_id']); ?>&exceptional=1" class="btn btn-success btn-sm shadow-sm"><i class="bi bi-check-circle-fill"></i> تجاوز وإضافة مساعدة استثنائية</a>
                                    
                                    <!-- Edit Beneficiary -->
                                    <a href="edit_beneficiary.php?national_id=<?php echo urlencode($row['national_id']); ?>" class="btn btn-outline-primary btn-sm shadow-sm"><i class="bi bi-pencil-square"></i> تعديل الملف</a>
                                <?php else: ?>
                                    <button class="btn btn-outline-secondary btn-sm" disabled><i class="bi bi-lock-fill"></i> غير مصرح بالتعديل أو القرار (يتبع للجنة أخرى)</button>
                                <?php endif; ?>
                                
                                <!-- Print & Exit -->
                                <a href="print_report.php?national_id=<?php echo urlencode($row['national_id']); ?>" target="_blank" class="btn btn-outline-secondary btn-sm shadow-sm"><i class="bi bi-printer"></i> طباعة</a>
                                <a href="index.php" class="btn btn-dark btn-sm shadow-sm ms-auto"><i class="bi bi-box-arrow-left"></i> الخروج من الصفحة</a>
                            </div>

                            <?php if ($can_edit): ?>
                            <!-- Modal for Note -->
                            <div class="modal fade" id="noteModal_<?php echo md5($row['national_id'].$row['committee_id_actual']); ?>" tabindex="-1">
                              <div class="modal-dialog">
                                <form method="POST" class="modal-content border-0 shadow">
                                  <div class="modal-header bg-warning text-dark border-0">
                                    <h5 class="modal-title fw-bold"><i class="bi bi-journal-text"></i> إضافة ملاحظة في سجل التدقيق للمستفيد</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                  </div>
                                  <div class="modal-body p-4 bg-light">
                                    <input type="hidden" name="action" value="add_note">
                                    <input type="hidden" name="target_nid" value="<?php echo htmlspecialchars($row['national_id']); ?>">
                                    <input type="hidden" name="target_cid" value="<?php echo $row['committee_id_actual']; ?>">
                                    <div class="mb-3">
                                        <label class="form-label fw-bold">نص الملاحظة والقرار:</label>
                                        <textarea name="note_text" class="form-control" rows="5" required placeholder="اكتب ملاحظتك هنا... (مثال: تم اكتشاف ازدواجية في الصرف مع لجنة أخرى، ولكن تقرر التجاوز بسبب... )"></textarea>
                                    </div>
                                  </div>
                                  <div class="modal-footer border-0">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                                    <button type="submit" class="btn btn-warning text-dark fw-bold px-4">حفظ الملاحظة <i class="bi bi-save"></i></button>
                                  </div>
                                </form>
                              </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="alert alert-info text-center p-5 shadow-sm border-0">
            <i class="bi bi-search fs-1 d-block mb-3 text-muted"></i>
            <h5>لا توجد نتائج مطابقة لبحثك في قاعدة البيانات المركزية.</h5>
            <p class="text-muted mb-0">تأكد من صحة الرقم الوطني أو الاسم وحاول مرة أخرى.</p>
        </div>
    <?php endif; ?>
<?php endif; ?>

<?php require_once __DIR__ . '/footer.php'; ?>