<?php
session_start();

require_once __DIR__ . '/db.php';

$is_admin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;
$is_committee = isset($_SESSION['logged_in_committee']);

if (!$is_admin && !$is_committee) {
    header("Location: admin_login.php");
    exit;
}

$active_committee_id = $is_committee ? $_SESSION['logged_in_committee'] : 0;

// --- تحديث قاعدة البيانات تلقائياً لإضافة حقول المتبرعين ---
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS `incoming_donations` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `committee_id` int(11) NOT NULL DEFAULT 0,
      `donation_type_id` int(11) NOT NULL,
      `quantity` decimal(15,2) NOT NULL,
      `deposit_date` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    
    $pdo->exec("CREATE TABLE IF NOT EXISTS `inventory_balances` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `committee_id` int(11) NOT NULL DEFAULT 0,
      `donation_type_id` int(11) NOT NULL,
      `quantity` decimal(15,2) NOT NULL DEFAULT 0.00,
      PRIMARY KEY (`id`),
      UNIQUE KEY `unique_inv` (`committee_id`, `donation_type_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `manager_finance` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `balance` decimal(15,2) NOT NULL DEFAULT 0.00,
      PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    $pdo->exec("INSERT IGNORE INTO `manager_finance` (`id`, `balance`) VALUES (1, 0.00);");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `committee_finances` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `committee_id` int(11) NOT NULL,
      `balance` decimal(15,2) NOT NULL DEFAULT 0.00,
      PRIMARY KEY (`id`),
      UNIQUE KEY `committee_id` (`committee_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    $check_donor = $pdo->query("SHOW COLUMNS FROM `incoming_donations` LIKE 'donor_name'")->fetch();
    if (!$check_donor) {
        $pdo->exec("ALTER TABLE `incoming_donations` ADD COLUMN `donor_name` VARCHAR(255) NULL DEFAULT 'فاعل خير'");
        $pdo->exec("ALTER TABLE `incoming_donations` ADD COLUMN `currency` VARCHAR(10) NULL DEFAULT 'JOD'");
        $pdo->exec("ALTER TABLE `incoming_donations` ADD COLUMN `payment_method` VARCHAR(100) NULL DEFAULT NULL");
        $pdo->exec("ALTER TABLE `incoming_donations` ADD COLUMN `campaign_name` VARCHAR(255) NULL DEFAULT NULL");
        $pdo->exec("ALTER TABLE `incoming_donations` ADD COLUMN `donation_date` DATE NULL DEFAULT NULL");
        $pdo->exec("ALTER TABLE `incoming_donations` ADD COLUMN `notes` TEXT NULL DEFAULT NULL");
    }
} catch (PDOException $e) {}
// -----------------------------------------------------------

$message = '';
$error = '';
$committee_name_display = '';

// جلب لغة المستخدم لعرض الرسائل قبل الهيدر
$user_lang = 'ar';
if (isset($_SESSION['user_id'])) {
    try {
        $stmt_lang = $pdo->prepare("SELECT language FROM user_settings WHERE user_id = ?");
        $stmt_lang->execute([$_SESSION['user_id']]);
        $db_lang = $stmt_lang->fetchColumn();
        if ($db_lang) $user_lang = $db_lang;
    } catch (PDOException $e) {}
}
$is_en = ($user_lang === 'en');

if ($is_committee) {
    try {
        $central_pdo = new PDO("mysql:host=$host;dbname=zakat_central_db;charset=$charset", $user, $pass, $options);
        $cstmt = $central_pdo->prepare("SELECT committee_name FROM committees_registry WHERE id = ?");
        $cstmt->execute([$active_committee_id]);
        $committee_name_display = $cstmt->fetchColumn() ?: 'اللجنة الحالية';
    } catch (PDOException $e) { $committee_name_display = 'اللجنة الحالية'; }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $donor_name = trim($_POST['donor_name']) ?: ($is_en ? 'Anonymous' : 'فاعل خير');
    $donation_type_id = (int)$_POST['donation_type_id'];
    $amount = (float)$_POST['amount'];
    $currency = $_POST['currency'] ?? 'JOD';
    $payment_method = $_POST['payment_method'] ?? '';
    $donation_date = $_POST['donation_date'] ?: date('Y-m-d');
    $campaign_name = trim($_POST['campaign_name']);
    $target = $is_admin ? $_POST['committee_id'] : $active_committee_id; // 'manager' أو ID اللجنة
    $notes = trim($_POST['notes']);

    $cid = ($target === 'manager') ? 0 : (int)$target;

    if ($amount > 0) {
        try {
            $catStmt = $pdo->prepare("SELECT category, sub_category FROM zakat_central_db.donation_types WHERE id = ?");
            $catStmt->execute([$donation_type_id]);
            $type_info = $catStmt->fetch();
            
            $is_cash = false;
            if ($type_info) {
                $cat_str = $type_info['category'] . ' ' . $type_info['sub_category'];
                if (mb_strpos($cat_str, 'نقد') !== false) {
                    $is_cash = true;
                }
            }

            if ($cid === 0) {
                $pdo->beginTransaction();
                
                $invStmt = $pdo->prepare("INSERT INTO inventory_balances (committee_id, donation_type_id, quantity) VALUES (0, :dtid, :qty) ON DUPLICATE KEY UPDATE quantity = quantity + :qty2");
                $invStmt->execute(['dtid' => $donation_type_id, 'qty' => $amount, 'qty2' => $amount]);
                
                $logStmt = $pdo->prepare("INSERT INTO incoming_donations (committee_id, donation_type_id, quantity, donor_name, currency, payment_method, campaign_name, donation_date, notes) VALUES (0, ?, ?, ?, ?, ?, ?, ?, ?)");
                $logStmt->execute([$donation_type_id, $amount, $donor_name, $currency, $payment_method, $campaign_name, $donation_date, $notes]);

                if ($is_cash) {
                    $amt = (float)$amount;
                    $pdo->exec("INSERT INTO manager_finance (id, balance) VALUES (1, $amt) ON DUPLICATE KEY UPDATE balance = balance + $amt");
                }
                $pdo->commit();
            } else {
                if (!isset($db_nodes[$cid])) throw new Exception("قاعدة بيانات اللجنة غير متصلة.");
                $node_pdo = new PDO("mysql:host=$host;dbname=" . $db_nodes[$cid] . ";charset=$charset", $user, $pass, $options);
                
                // Check missing columns in node just in case
                $check_donor = $node_pdo->query("SHOW COLUMNS FROM `incoming_donations` LIKE 'donor_name'")->fetch();
                if (!$check_donor) {
                    $node_pdo->exec("ALTER TABLE `incoming_donations` ADD COLUMN `donor_name` VARCHAR(255) NULL DEFAULT 'فاعل خير'");
                    $node_pdo->exec("ALTER TABLE `incoming_donations` ADD COLUMN `currency` VARCHAR(10) NULL DEFAULT 'JOD'");
                    $node_pdo->exec("ALTER TABLE `incoming_donations` ADD COLUMN `payment_method` VARCHAR(100) NULL DEFAULT NULL");
                    $node_pdo->exec("ALTER TABLE `incoming_donations` ADD COLUMN `campaign_name` VARCHAR(255) NULL DEFAULT NULL");
                    $node_pdo->exec("ALTER TABLE `incoming_donations` ADD COLUMN `donation_date` DATE NULL DEFAULT NULL");
                    $node_pdo->exec("ALTER TABLE `incoming_donations` ADD COLUMN `notes` TEXT NULL DEFAULT NULL");
                }
                
                $node_pdo->beginTransaction();
                
                $invStmt = $node_pdo->prepare("INSERT INTO inventory_balances (donation_type_id, quantity) VALUES (:dtid, :qty) ON DUPLICATE KEY UPDATE quantity = quantity + :qty2");
                $invStmt->execute(['dtid' => $donation_type_id, 'qty' => $amount, 'qty2' => $amount]);
                
                $logStmt = $node_pdo->prepare("INSERT INTO incoming_donations (donation_type_id, quantity, donor_name, currency, payment_method, campaign_name, donation_date, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $logStmt->execute([$donation_type_id, $amount, $donor_name, $currency, $payment_method, $campaign_name, $donation_date, $notes]);

                if ($is_cash) {
                    $amt = (float)$amount;
                    $node_pdo->exec("INSERT INTO committee_finances (id, balance) VALUES (1, $amt) ON DUPLICATE KEY UPDATE balance = balance + $amt");
                }
                $node_pdo->commit();

                // إضافة نسخة من السجل إلى قاعدة البيانات المركزية للتدقيق والمراقبة
                try {
                    $central_pdo = new PDO("mysql:host=$host;dbname=zakat_central_db;charset=$charset", $user, $pass, $options);
                    $centralLogStmt = $central_pdo->prepare("INSERT INTO incoming_donations (committee_id, donation_type_id, quantity, donor_name, currency, payment_method, campaign_name, donation_date, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $centralLogStmt->execute([$cid, $donation_type_id, $amount, $donor_name, $currency, $payment_method, $campaign_name, $donation_date, $notes]);
                } catch (PDOException $e) {
                    error_log("Central DB logging failed for committee donation: " . $e->getMessage());
                }
            }
            
            
            $target_name = ($cid === 0) ? ($is_en ? "Main Institution (General Fund)" : "المؤسسة الرئيسية (الصندوق العام)") : ($is_en ? "Specified Committee" : "اللجنة المحددة");
            $unit = $is_cash ? $currency : ($is_en ? "Unit/Piece" : "وحدة/قطعة");
            $message = $is_en ? "Donation from ($donor_name) with value ($amount $unit) directed to $target_name has been successfully recorded." : "تم تسجيل تبرع السيد/ة ($donor_name) بقيمة ($amount $unit) وتوجيهه إلى $target_name بنجاح.";
            
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $error = ($is_en ? "An error occurred while recording donation: " : "حدث خطأ أثناء تسجيل التبرع: ") . $e->getMessage();
        }
    } else {
        $error = $is_en ? "Please enter a valid amount greater than zero." : "الرجاء إدخال قيمة صحيحة أكبر من الصفر.";
    }
}

// جلب أنواع التبرعات واللجان للقوائم المنسدلة
try {
    // التأكد من وجود جدول أنواع التبرع وتعبئته بالبيانات الافتراضية إذا كان فارغاً
    $pdo->exec("CREATE TABLE IF NOT EXISTS `donation_types` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `category` varchar(50) NOT NULL,
      `sub_category` varchar(150) NOT NULL,
      PRIMARY KEY (`id`),
      UNIQUE KEY `unique_cat_sub` (`category`,`sub_category`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    
    $types_count = $pdo->query("SELECT COUNT(*) FROM zakat_central_db.donation_types")->fetchColumn();
    if ($types_count == 0) {
        $default_types = [['نقدي', 'زكاة مال'], ['نقدي', 'صدقة'], ['نقدي', 'كفالة شهرية'], ['نقدي', 'دعم طارئ'], ['عيني', 'سلة غذائية'], ['عيني', 'كسوة (ملابس)'], ['عيني', 'مستلزمات طبية'], ['عيني', 'مستلزمات تعليمية (قرطاسية)'], ['عيني', 'لحوم أضاحي (موسمي)'], ['خدمات', 'استشارة طبية مجانية'], ['خدمات', 'جلسة علاج فيزيائي'], ['خدمات', 'دعم نفسي وتعليمي']];
        $insert_stmt = $pdo->prepare("INSERT IGNORE INTO zakat_central_db.donation_types (category, sub_category) VALUES (?, ?)");
        foreach ($default_types as $type) $insert_stmt->execute($type);
    }
    
    $donation_types = $pdo->query("SELECT MIN(id) as id, category, TRIM(sub_category) as sub_category FROM zakat_central_db.donation_types GROUP BY category, TRIM(sub_category) ORDER BY category, MIN(id)")->fetchAll();
} catch (PDOException $e) {
    $donation_types = [];
}

try {
    $committees_list = $pdo->query("SELECT id, committee_name as name FROM committees_registry ORDER BY id ASC")->fetchAll();
} catch (PDOException $e) {
    try {
        $committees_list = $pdo->query("SELECT id, name FROM committees ORDER BY id ASC")->fetchAll();
    } catch (PDOException $e2) {
        $committees_list = [];
    }
}

// جلب سجل التبرعات الواردة (أحدث 50 حركة)
$donors_history = [];
try {
    if ($is_admin) {
        $donations_stmt = $pdo->query("
            SELECT inc.*, 'الصندوق الرئيسي' as committee_name, dt.category, dt.sub_category 
            FROM incoming_donations inc
            LEFT JOIN zakat_central_db.donation_types dt ON inc.donation_type_id = dt.id
            WHERE inc.committee_id = 0
            ORDER BY inc.deposit_date DESC, inc.id DESC
            LIMIT 50
        ");
        while ($row = $donations_stmt->fetch()) {
            $row['committee_id'] = 0;
            $donors_history[] = $row;
        }
        foreach ($committees_list as $com) {
            $cid = $com['id'];
            if (isset($db_nodes[$cid])) {
                try {
                    $node_pdo = new PDO("mysql:host=$host;dbname=" . $db_nodes[$cid] . ";charset=$charset", $user, $pass, $options);
                    $donations_stmt = $node_pdo->query("SELECT inc.*, dt.category, dt.sub_category FROM incoming_donations inc LEFT JOIN zakat_central_db.donation_types dt ON inc.donation_type_id = dt.id ORDER BY inc.deposit_date DESC, inc.id DESC LIMIT 50");
                    while ($row = $donations_stmt->fetch()) {
                        $row['committee_name'] = $com['name'];
                        $row['committee_id'] = $cid;
                        $donors_history[] = $row;
                    }
                } catch (PDOException $e) {}
            }
        }
    } else {
        $donations_stmt = $pdo->query("SELECT inc.*, dt.category, dt.sub_category FROM incoming_donations inc LEFT JOIN zakat_central_db.donation_types dt ON inc.donation_type_id = dt.id ORDER BY inc.deposit_date DESC, inc.id DESC LIMIT 50");
        while ($row = $donations_stmt->fetch()) {
            $row['committee_name'] = $committee_name_display;
            $row['committee_id'] = $active_committee_id;
            $donors_history[] = $row;
        }
    }
} catch (PDOException $e) {}
usort($donors_history, function($a, $b) {
    $timeA = strtotime($a['deposit_date']);
    $timeB = strtotime($b['deposit_date']);
    if ($timeA == $timeB) return $b['id'] <=> $a['id'];
    return $timeB <=> $timeA;
});
$donors_history = array_slice($donors_history, 0, 50);

require_once __DIR__ . '/header.php';
?>

<div class="row mb-4 mt-3">
    <div class="col-12 d-flex justify-content-between align-items-center">
        <h3 class="mb-0 text-primary"><i class="bi bi-person-heart me-2"></i> <?php echo t('receive_donation_title'); ?></h3>
        <?php if ($is_admin): ?>
        <a href="manage_finances.php" class="btn btn-sm btn-outline-secondary"><i class="bi bi-boxes"></i> <?php echo t('back_to_finances'); ?></a>
        <?php else: ?>
        <a href="committee.php?id=<?php echo $active_committee_id; ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-right"></i> العودة للوحة التحكم</a>
        <?php endif; ?>
    </div>
</div>

<?php if ($message): ?><div class="alert alert-success text-center shadow-sm"><i class="bi bi-check-circle-fill me-2"></i><?php echo $message; ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger text-center shadow-sm"><i class="bi bi-exclamation-triangle-fill me-2"></i><?php echo $error; ?></div><?php endif; ?>

<form method="POST" class="card shadow-sm border-0 mb-5">
    <div class="card-body p-4 p-md-5">
        
        <h5 class="text-secondary border-bottom pb-2 mb-4"><i class="bi bi-1-circle-fill text-primary me-1"></i> <?php echo t('donor_info'); ?></h5>
        <div class="row g-4 mb-5">
            <div class="col-md-6">
                <label class="form-label fw-bold"><?php echo t('donor_name_label'); ?></label>
                <input type="text" name="donor_name" class="form-control form-control-lg bg-light" placeholder="<?php echo t('donor_name_placeholder'); ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label fw-bold"><?php echo t('donation_receive_date'); ?></label>
                <input type="date" name="donation_date" class="form-control form-control-lg bg-light" required value="<?php echo date('Y-m-d'); ?>">
            </div>
            <div class="col-md-12">
                <label class="form-label fw-bold"><?php echo t('campaign_name_label'); ?></label>
                <input type="text" name="campaign_name" class="form-control bg-light" placeholder="<?php echo t('campaign_name_placeholder'); ?>">
            </div>
        </div>

        <h5 class="text-secondary border-bottom pb-2 mb-4"><i class="bi bi-2-circle-fill text-primary me-1"></i> <?php echo t('donation_details'); ?></h5>
        <div class="row g-4 mb-5">
            <div class="col-md-6">
                <label class="form-label fw-bold"><?php echo t('donation_type_domain'); ?></label>
                <select name="donation_type_id" class="form-select form-select-lg bg-light" required>
                    <option value=""><?php echo t('select_donation_domain'); ?></option>
                    <?php 
                    $current_cat = '';
                    foreach ($donation_types as $type): 
                        if ($current_cat !== $type['category']) {
                            if ($current_cat !== '') echo '</optgroup>';
                            echo '<optgroup label="' . t('donations_of') . htmlspecialchars($type['category']) . '">';
                            $current_cat = $type['category'];
                        }
                    ?>
                        <option value="<?php echo $type['id']; ?>"><?php echo htmlspecialchars($type['sub_category']); ?></option>
                    <?php endforeach; echo '</optgroup>'; ?>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label fw-bold"><?php echo t('donation_delivery_method'); ?></label>
                <select name="payment_method" class="form-select form-select-lg bg-light" required>
                    <option value="نقدي (كاش)"><?php echo t('cash_payment'); ?></option>
                    <option value="تحويل بنكي"><?php echo t('bank_transfer'); ?></option>
                    <option value="دفع إلكتروني (فيزا/محفظة)"><?php echo t('electronic_payment'); ?></option>
                    <option value="شيك بنكي"><?php echo t('bank_check'); ?></option>
                    <option value="تبرع عيني (مواد)"><?php echo t('inkind_donation_materials'); ?></option>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label fw-bold text-success"><?php echo t('donation_value_quantity'); ?></label>
                <input type="number" step="0.01" name="amount" class="form-control form-control-lg border-success" required placeholder="<?php echo t('enter_amount_quantity'); ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label fw-bold"><?php echo t('currency_cash_donations'); ?></label>
                <select name="currency" class="form-select form-select-lg bg-light">
                    <option value="JOD" selected><?php echo t('currency_jod'); ?></option>
                    <option value="USD"><?php echo t('currency_usd'); ?></option>
                    <option value="EUR"><?php echo t('currency_eur'); ?></option>
                </select>
            </div>
        </div>

        <h5 class="text-secondary border-bottom pb-2 mb-4"><i class="bi bi-3-circle-fill text-primary me-1"></i> <?php echo t('routing_notes'); ?></h5>
        <div class="row g-4">
            <div class="col-md-12">
                <label class="form-label fw-bold"><?php echo t('benefiting_entity'); ?></label>
                <?php if ($is_admin): ?>
                <select name="committee_id" class="form-select form-select-lg bg-light" required>
                    <option value="manager" class="text-primary fw-bold"><?php echo t('main_institution_fund'); ?></option>
                    <optgroup label="<?php echo t('direct_to_specific_committee'); ?>">
                        <?php foreach ($committees_list as $com): ?>
                            <option value="<?php echo $com['id']; ?>"><?php echo t('committee_label'); ?> <?php echo htmlspecialchars($com['name']); ?></option>
                        <?php endforeach; ?>
                    </optgroup>
                </select>
                <?php else: ?>
                <input type="hidden" name="committee_id" value="<?php echo $active_committee_id; ?>">
                <div class="form-control form-control-lg bg-light text-muted"><i class="bi bi-building"></i> <?php echo htmlspecialchars($committee_name_display); ?> (لجنتك)</div>
                <?php endif; ?>
            </div>
            <div class="col-md-12">
                <label class="form-label fw-bold"><?php echo t('additional_notes_optional'); ?></label>
                <textarea name="notes" class="form-control bg-light" rows="3" placeholder="<?php echo t('notes_placeholder'); ?>"></textarea>
            </div>
        </div>

    </div>
    <div class="card-footer bg-white border-0 text-center py-4">
        <button type="submit" class="btn btn-primary btn-lg shadow px-5 rounded-pill"><i class="bi bi-box-arrow-in-down me-2"></i> <?php echo t('receive_save_donation'); ?></button>
    </div>
</form>

<!-- سجل التبرعات الواردة (الجدول) -->
<div class="card shadow-sm border-0 mb-5">
    <div class="card-header bg-white border-0 pt-4 pb-2">
        <h5 class="mb-0 text-success fw-bold"><i class="bi bi-table me-2"></i> <?php echo $is_en ? 'Incoming Donations Log (Recent 50)' : 'سجل التبرعات الواردة (أحدث 50 حركة)'; ?></h5>
    </div>
    <div class="card-body p-0">
        <?php if (count($donors_history) > 0): ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 text-nowrap">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4"><?php echo $is_en ? 'Date' : 'التاريخ'; ?></th>
                            <th><?php echo $is_en ? 'Donor' : 'المتبرع'; ?></th>
                            <th><?php echo $is_en ? 'Donation Type' : 'نوع التبرع'; ?></th>
                            <th><?php echo $is_en ? 'Amount / Quantity' : 'القيمة / الكمية'; ?></th>
                            <th><?php echo $is_en ? 'Payment Method' : 'طريقة الدفع'; ?></th>
                            <th><?php echo $is_en ? 'Benefiting Entity' : 'الجهة المستفيدة'; ?></th>
                            <th><?php echo $is_en ? 'Notes' : 'ملاحظات'; ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($donors_history as $log): ?>
                        <tr>
                            <td class="ps-4">
                                <div class="fw-bold text-secondary"><?php echo htmlspecialchars($log['donation_date'] ?: date('Y-m-d', strtotime($log['deposit_date']))); ?></div>
                            </td>
                            <td>
                                <strong><?php echo htmlspecialchars($log['donor_name'] ?: ($is_en ? 'Anonymous' : 'فاعل خير')); ?></strong>
                            </td>
                            <td>
                                <?php if($log['category'] == 'نقدي' || mb_strpos($log['category'], 'نقد') !== false): ?>
                                    <span class="badge bg-warning text-dark"><i class="bi bi-cash-stack"></i> <?php echo htmlspecialchars($log['sub_category']); ?></span>
                                <?php else: ?>
                                    <span class="badge bg-info text-dark"><i class="bi bi-box-seam"></i> <?php echo htmlspecialchars($log['sub_category']); ?></span>
                                <?php endif; ?>
                                <?php if(!empty($log['campaign_name'])) echo '<br><small class="text-muted">' . htmlspecialchars($log['campaign_name']) . '</small>'; ?>
                            </td>
                            <td>
                                <?php if($log['category'] == 'نقدي' || mb_strpos($log['category'], 'نقد') !== false): ?>
                                    <strong class="text-success"><?php echo number_format((float)$log['quantity'], 2); ?> <?php echo htmlspecialchars($log['currency']); ?></strong>
                                <?php else: ?>
                                    <strong class="text-primary"><?php echo (float)$log['quantity']; ?> وحدة</strong>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="small text-muted"><?php echo htmlspecialchars($log['payment_method'] ?: '-'); ?></span>
                            </td>
                            <td>
                                <?php if($log['committee_id'] == 0): ?>
                                    <span class="badge bg-primary"><i class="bi bi-bank"></i> <?php echo $is_en ? 'Main Institution' : 'الصندوق الرئيسي'; ?></span>
                                <?php else: ?>
                                    <span class="badge bg-secondary"><i class="bi bi-building"></i> <?php echo htmlspecialchars($log['committee_name']); ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="d-inline-block text-truncate text-muted" style="max-width: 150px;" title="<?php echo htmlspecialchars($log['notes'] ?? ''); ?>">
                                    <?php echo htmlspecialchars($log['notes'] ?: '-'); ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="alert alert-light text-center m-4 text-muted border">
                <i class="bi bi-info-circle fs-4 d-block mb-2"></i>
                <?php echo $is_en ? 'No incoming donations recorded yet.' : 'لا توجد تبرعات واردة مسجلة حتى الآن.'; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>