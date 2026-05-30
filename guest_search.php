<?php
session_start();
require_once __DIR__ . '/db.php';

$search_query = isset($_GET['q']) ? trim($_GET['q']) : '';
$results = [];
$searched = false;

// استعلام عن المستفيد باستخدام الرقم الوطني 
if ($search_query !== '') {
    $searched = true;
    
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
                
                $stmt = $node_pdo->prepare("SELECT national_id, full_name, status FROM beneficiaries WHERE national_id = :q_exact");
                $stmt->execute(['q_exact' => $search_query]);
                $bens = $stmt->fetchAll();
                
                foreach ($bens as $row) {
                    $row['committee_name'] = $cname;
                    
                    $dhStmt = $node_pdo->prepare("SELECT dh.amount, dh.donation_date, dh.notes, dt.category, dt.sub_category FROM donations_history dh JOIN zakat_central_db.donation_types dt ON dh.donation_type_id = dt.id WHERE dh.national_id = :nid AND dh.donation_status = 'تم الصرف' ORDER BY dh.donation_date DESC");
                    $dhStmt->execute(['nid' => $row['national_id']]);
                    $row['donations_history'] = $dhStmt->fetchAll();
                    
                    $results[] = $row;
                }
            } catch (PDOException $e) {}
        }
    }
    
    // --- تسجيل العملية في سجل التدقيق ---
    try {
        $was_successful = count($results) > 0 ? 1 : 0;
        $ip_address = getUserIP();
        
        $user_id = $_SESSION['user_id'] ?? null;
        $committee_id = $_SESSION['logged_in_committee'] ?? (isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true ? 0 : null);
        
        $logStmt = $central_pdo->prepare("INSERT INTO search_audit_logs (user_id, committee_id, searched_national_id, ip_address, was_successful) VALUES (?, ?, ?, ?, ?)");
        $logStmt->execute([$user_id, $committee_id, $search_query, $ip_address, $was_successful]);
    } catch (PDOException $e) {}
}

require_once __DIR__ . '/header.php';
?>

<div class="row mb-4 mt-3">
    <div class="col-12 text-center">
        <h3 class="mb-0 text-primary">
            <i class="bi bi-search"></i> <?php echo t('guest_search'); ?>
        </h3>
        <p class="text-muted mt-2">استعلم عن حالة ملفك الخاص باستخدام الرقم الوطني.</p>
    </div>
</div>

<div class="card shadow-sm border-0 mb-5 mx-auto" style="max-width: 800px;">
    <div class="card-body p-4">
        <?php if (isset($error_msg)): ?>
            <div class="alert alert-warning text-center shadow-sm"><i class="bi bi-exclamation-triangle-fill"></i> <?php echo $error_msg; ?></div>
        <?php endif; ?>
        
        <form method="GET" action="guest_search.php" class="row g-3 justify-content-center">
            <div class="col-md-8">
                <div class="input-group input-group-lg">
                    <span class="input-group-text bg-white text-primary"><i class="bi bi-person-vcard"></i></span>
                    <input type="text" name="q" class="form-control" placeholder="<?php echo t('national_id'); ?>" value="<?php echo htmlspecialchars($search_query); ?>" required>
                </div>
            </div>
            <div class="col-md-4">
                <button class="btn btn-primary btn-lg w-100 shadow" type="submit"><i class="bi bi-search"></i> <?php echo t('search_btn'); ?></button>
            </div>
        </form>
    </div>
</div>

<?php if ($searched): ?>
    <?php if (count($results) > 1): ?>
        <div class="alert alert-warning text-center shadow-sm border-0 mb-4 mx-auto py-3" style="max-width: 800px; background-color: #fff3cd; color: #856404; border-radius: 12px;">
            <h5 class="mb-0 fw-bold">⚠️ تنبيه: تم اكتشاف حالة ازدواجية! المستفيد مسجل في أكثر من لجنة</h5>
        </div>
    <?php endif; ?>
    <?php if (count($results) > 0): ?>
        <div class="row g-4 justify-content-center">
            <?php foreach ($results as $row): ?>
                <div class="col-md-8">
                    <div class="card shadow border-0 border-top border-5 border-success text-center hover-lift">
                        <div class="card-body p-4 p-md-5">
                            <h4 class="mb-3 text-primary fw-bold"><i class="bi bi-person-circle"></i> <?php echo htmlspecialchars($row['full_name']); ?></h4>
                            <p class="text-muted mb-4"><i class="bi bi-building"></i> مسجل لدى: <strong><?php echo htmlspecialchars($row['committee_name'] ?? 'السجل الموحد'); ?></strong></p>
                            <h5 class="text-secondary mb-3"><?php echo t('file_status'); ?></h5>
                            <?php 
                            $status_color = 'warning text-dark';
                            if ($row['status'] == 'مقبول') $status_color = 'success';
                            if ($row['status'] == 'مرفوض') $status_color = 'danger';
                            ?>
                            <span class="badge bg-<?php echo $status_color; ?> rounded-pill px-5 py-3 fs-5 shadow-sm"><?php echo htmlspecialchars($row['status']); ?></span>
                            
                            <hr class="my-4 border-secondary opacity-25">
                            <h5 class="text-success mb-3 text-start"><i class="bi bi-gift"></i> سجل المساعدات المستلمة</h5>
                            
                            <?php if (count($row['donations_history']) > 0): ?>
                                <div class="table-responsive text-start">
                                    <table class="table table-bordered table-hover table-sm mt-2 align-middle">
                                        <thead class="table-light">
                                            <tr>
                                                <th>نوع المساعدة</th>
                                                <th>القيمة / الكمية</th>
                                                <th>تاريخ الصرف</th>
                                                <th>ملاحظات</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                        <?php foreach ($row['donations_history'] as $donation): 
                                            $donation_date_obj = new DateTime($donation['donation_date']);
                                            $now_obj = new DateTime();
                                            $donation_date_obj->setTime(0, 0, 0); // تصفير الساعات لحساب دقيق
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
                                                        <span class="badge bg-warning text-dark"><i class="bi bi-cash-stack"></i> <?php echo htmlspecialchars($donation['sub_category']); ?></span>
                                                    <?php else: ?>
                                                        <span class="badge bg-info text-dark"><i class="bi bi-box-seam"></i> <?php echo htmlspecialchars($donation['sub_category']); ?> (عيني)</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if($donation['category'] == 'نقدي'): ?>
                                                        <strong class="text-success"><?php echo number_format((float)$donation['amount'], 2); ?> JOD</strong>
                                                    <?php else: ?>
                                                        <strong class="text-primary"><?php echo (float)$donation['amount']; ?> وحدة</strong>
                                                    <?php endif; ?>
                                                </td>
                                            <td>
                                                <span class="text-secondary fw-bold"><?php echo date('Y-m-d', strtotime($donation['donation_date'])); ?></span>
                                                <?php if ($is_recent): ?>
                                                    <br><span class="badge bg-danger mt-1"><i class="bi bi-exclamation-circle"></i> استلم مساعدة حديثاً (<?php echo $days_text; ?>)</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="d-inline-block text-truncate text-muted small" style="max-width: 150px;" title="<?php echo htmlspecialchars($donation['notes'] ?? ''); ?>"><?php echo htmlspecialchars($donation['notes'] ?: '-'); ?></span>
                                            </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-light text-center border text-muted mb-0"><i class="bi bi-info-circle d-block mb-1 fs-5"></i> لم يتم تسجيل أي مساعدات مصروفة في السجل حتى الآن.</div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="alert alert-info text-center p-5 shadow-sm border-0 mx-auto" style="max-width: 600px;">
            <i class="bi bi-search fs-1 d-block mb-3 text-muted"></i>
            <h5 class="fw-bold">لا توجد نتائج مطابقة لبحثك.</h5>
            <p class="text-muted mb-0">تأكد من صحة الرقم الوطني وحاول مرة أخرى.</p>
        </div>
    <?php endif; ?>
<?php endif; ?>

<?php require_once __DIR__ . '/footer.php'; ?>