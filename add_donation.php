<?php
session_start();
$committee_id = isset($_GET['committee_id']) ? (int)$_GET['committee_id'] : 0;

if (!isset($_SESSION['logged_in_committee']) || $_SESSION['logged_in_committee'] !== $committee_id) {
    header("Location: login.php?id=" . $committee_id);
    exit;
}

require_once __DIR__ . '/db.php';

// --- حل تلقائي لمشكلة الأعمدة الناقصة (Unknown column 'amount') ---
$check_column = $pdo->query("SHOW COLUMNS FROM `donations_history` LIKE 'amount'")->fetch();
if (!$check_column) {
    $pdo->exec("ALTER TABLE `donations_history` ADD COLUMN `amount` DECIMAL(10,2) NULL DEFAULT NULL");
}
$check_notes = $pdo->query("SHOW COLUMNS FROM `donations_history` LIKE 'notes'")->fetch();
if (!$check_notes) {
    $pdo->exec("ALTER TABLE `donations_history` ADD COLUMN `notes` TEXT NULL DEFAULT NULL");
}
$check_user_id = $pdo->query("SHOW COLUMNS FROM `donations_history` LIKE 'user_id'")->fetch();
if (!$check_user_id) {
    $pdo->exec("ALTER TABLE `donations_history` ADD COLUMN `user_id` INT NULL DEFAULT NULL");
}

// --- إضافة الأعمدة الجديدة المطلوبة للشفافية والتفاصيل ---
$check_extra = $pdo->query("SHOW COLUMNS FROM `donations_history` LIKE 'donation_source'")->fetch();
if (!$check_extra) {
    $pdo->exec("ALTER TABLE `donations_history` ADD COLUMN `donation_source` VARCHAR(100) NULL DEFAULT NULL");
    $pdo->exec("ALTER TABLE `donations_history` ADD COLUMN `campaign_name` VARCHAR(255) NULL DEFAULT NULL");
    $pdo->exec("ALTER TABLE `donations_history` ADD COLUMN `donation_status` VARCHAR(50) NULL DEFAULT 'تم الصرف'");
    $pdo->exec("ALTER TABLE `donations_history` ADD COLUMN `delivery_method` VARCHAR(100) NULL DEFAULT NULL");
}

$check_receipt = $pdo->query("SHOW COLUMNS FROM `donations_history` LIKE 'receipt_doc'")->fetch();
if (!$check_receipt) {
    $pdo->exec("ALTER TABLE `donations_history` ADD COLUMN `receipt_doc` VARCHAR(255) NULL DEFAULT NULL");
}
// -----------------------------------------------------------

$error_message = '';
$success_message = '';

// جلب قائمة المستفيدين التابعين لهذه اللجنة
$beneficiariesStmt = $pdo->query("SELECT national_id, full_name FROM beneficiaries ORDER BY full_name");
$beneficiaries = $beneficiariesStmt->fetchAll();

// جلب قائمة أنواع الدعم
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS `zakat_central_db`.`donation_types` (
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
    $donationTypes = $pdo->query("SELECT MIN(id) as id, category, TRIM(sub_category) as sub_category FROM zakat_central_db.donation_types GROUP BY category, TRIM(sub_category) ORDER BY category, MIN(id)")->fetchAll();
} catch (PDOException $e) { $donationTypes = []; }

// جلب اسم اللجنة
try {
    $central_pdo = new PDO("mysql:host=$host;dbname=zakat_central_db;charset=$charset", $user, $pass, $options);
    $cstmt = $central_pdo->prepare("SELECT committee_name FROM committees_registry WHERE id = ?");
    $cstmt->execute([$committee_id]);
    $committee_name = $cstmt->fetchColumn() ?: 'اللجنة الحالية';
} catch (PDOException $e) { $committee_name = ''; }

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $national_id = $_POST['national_id'];
    $donation_type_id = (int)$_POST['donation_type_id'];
    $amount = isset($_POST['amount']) ? (float)$_POST['amount'] : null;
    $donation_date = $_POST['donation_date'];
    $notes = trim($_POST['notes']);
    $donation_source = $_POST['donation_source'] ?? null;
    $campaign_name = trim($_POST['campaign_name'] ?? '');
    $donation_status = $_POST['donation_status'] ?? 'تم الصرف';
    $delivery_method = $_POST['delivery_method'] ?? null;

    // معالجة رفع ملف الوثيقة الرسمية (PDF)
    $receipt_doc_path = null;
    if (isset($_FILES['receipt_doc']) && $_FILES['receipt_doc']['error'] == UPLOAD_ERR_OK) {
        $upload_dir = __DIR__ . '/uploads/receipts/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        $file_tmp_path = $_FILES['receipt_doc']['tmp_name'];
        $file_name = basename($_FILES['receipt_doc']['name']);
        $file_extension = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        
        if ($file_extension === 'pdf') {
            $new_file_name = 'receipt_' . time() . '_' . uniqid() . '.pdf';
            if (move_uploaded_file($file_tmp_path, $upload_dir . $new_file_name)) {
                $receipt_doc_path = $new_file_name;
            }
        }
    }

    // جلب نوع التبرع للتحقق إذا كان نقدياً
    $typeStmt = $pdo->prepare("SELECT category FROM zakat_central_db.donation_types WHERE id = ?");
    $typeStmt->execute([$donation_type_id]);
    $donation_category = $typeStmt->fetchColumn();

    try {
        $pdo->beginTransaction();

        $is_cash_out = (trim($donation_category) === 'نقدي' || mb_strpos($donation_category, 'نقدي') !== false);
        
        // التحقق من الرصيد في المستودع وخصم الكمية (للنقدي والعيني) إذا كانت الحالة "تم الصرف"
        if ($donation_status === 'تم الصرف') {
            if ($amount === null || $amount <= 0) {
                throw new Exception("الرجاء إدخال مبلغ أو كمية صحيحة أكبر من الصفر.");
            }

            // قفل صف الرصيد لمنع التضارب
            $checkInv = $pdo->prepare("SELECT quantity FROM inventory_balances WHERE donation_type_id = :dtid FOR UPDATE");
            $checkInv->execute(['dtid' => $donation_type_id]);
            $current_qty = (float)$checkInv->fetchColumn();

            if ($current_qty < $amount) {
                throw new Exception("رصيد المستودع للجنة غير كافٍ لهذا الصنف. المتاح حالياً: " . $current_qty);
            }

            // تحديث رصيد المستودع
            $updateInvStmt = $pdo->prepare("UPDATE inventory_balances SET quantity = quantity - :amount WHERE donation_type_id = :dtid");
            $updateInvStmt->execute(['amount' => $amount, 'dtid' => $donation_type_id]);
        }

        // تسجيل عملية التوزيع في السجل
        $insertStmt = $pdo->prepare(
            "INSERT INTO donations_history (national_id, donation_type_id, amount, donation_date, notes, user_id, donation_source, campaign_name, donation_status, delivery_method, receipt_doc) 
             VALUES (:nid, :dtid, :amount, :ddate, :notes, :uid, :dsource, :cname, :dstatus, :dmethod, :receipt)"
        );
        $insertStmt->execute([
            'nid' => $national_id,
            'dtid' => $donation_type_id,
            'amount' => $amount,
            'ddate' => $donation_date,
            'notes' => $notes ?: null,
            'uid' => $_SESSION['user_id'],
            'dsource' => $donation_source,
            'cname' => $campaign_name ?: null,
            'dstatus' => $donation_status,
            'dmethod' => $delivery_method,
            'receipt' => $receipt_doc_path
        ]);

        $pdo->commit();
        $success_message = "تم تسجيل عملية التوزيع بنجاح!";

    } catch (Exception $e) {
        $pdo->rollBack();
        $error_message = "فشلت العملية: " . $e->getMessage();
    }
}

require_once __DIR__ . '/header.php';
?>

<div class="row mb-4 mt-3">
    <div class="col-12 d-flex justify-content-between align-items-center">
        <h3 class="mb-0 text-primary"><i class="bi bi-box2-heart-fill me-2"></i> تسجيل عملية توزيع جديدة | <?php echo htmlspecialchars($committee_name); ?></h3>
        <a href="committee.php?id=<?php echo $committee_id; ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-right"></i> العودة للوحة التحكم</a>
    </div>
</div>

<?php if ($error_message): ?><div class="alert alert-danger text-center"><?php echo $error_message; ?></div><?php endif; ?>
<?php if ($success_message): ?><div class="alert alert-success text-center"><?php echo $success_message; ?></div><?php endif; ?>

<?php
$selected_nid = $_GET['national_id'] ?? '';
$prefill_notes = isset($_GET['exceptional']) ? 'مساعدة استثنائية (تجاوز تحذير الازدواجية والتكرار)' : '';
?>
<form method="POST" enctype="multipart/form-data" class="card shadow-sm border-0">
    <div class="card-body p-4">
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label fw-bold">اختر المستفيد</label>
                <select name="national_id" class="form-select form-select-lg" required>
                    <option value="">-- قائمة المستفيدين --</option>
                    <?php foreach ($beneficiaries as $ben): ?>
                        <option value="<?php echo htmlspecialchars($ben['national_id']); ?>" <?php if($ben['national_id'] == $selected_nid) echo 'selected'; ?>><?php echo htmlspecialchars($ben['full_name']); ?> (<?php echo htmlspecialchars($ben['national_id']); ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label fw-bold">تاريخ التوزيع</label>
                <input type="date" name="donation_date" class="form-control form-control-lg" required value="<?php echo date('Y-m-d'); ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label fw-bold">نوع الدعم</label>
                <select name="donation_type_id" id="donation_type_id" class="form-select form-select-lg" required>
                    <option value="">-- اختر نوع الدعم --</option>
                    <?php foreach ($donationTypes as $type): ?>
                        <option value="<?php echo $type['id']; ?>" data-category="<?php echo $type['category']; ?>"><?php echo htmlspecialchars($type['category'] . ' - ' . $type['sub_category']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-6" id="amount_field" style="display: none;">
                <label class="form-label fw-bold" id="amount_label">المبلغ / الكمية</label>
                <input type="number" step="0.01" name="amount" id="amount_input" class="form-control form-control-lg" placeholder="أدخل الرقم">
            </div>
            
            <!-- الحقول الجديدة للتفاصيل الإضافية -->
            <div class="col-md-4">
                <label class="form-label fw-bold text-secondary">حالة التبرع</label>
                <select name="donation_status" class="form-select bg-light">
                    <option value="تم الصرف" selected>تم الصرف (مكتمل)</option>
                    <option value="قيد الانتظار">قيد الانتظار (لم يسلم بعد)</option>
                    <option value="مرفوض">مرفوض</option>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label fw-bold text-secondary">طريقة التسليم</label>
                <select name="delivery_method" class="form-select bg-light">
                    <option value="">-- اختر طريقة التسليم --</option>
                    <option value="تسليم نقدي (يدوي)">تسليم نقدي (يدوي)</option>
                    <option value="تحويل بنكي">تحويل بنكي / محفظة إلكترونية</option>
                    <option value="قسيمة مشتريات (Voucher)">قسيمة مشتريات (Voucher)</option>
                    <option value="تسليم عيني مباشر">تسليم عيني مباشر</option>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label fw-bold text-secondary">مصدر الدعم</label>
                <select name="donation_source" class="form-select bg-light">
                    <option value="">-- اختر مصدر الدعم --</option>
                    <option value="حملة تبرعات">حملة تبرعات عامة</option>
                    <option value="متبرع فرد (فاعل خير)">متبرع فرد (فاعل خير)</option>
                    <option value="جمعية شريكة">جمعية / مؤسسة شريكة</option>
                    <option value="موازنة اللجنة الأساسية">موازنة اللجنة الأساسية</option>
                </select>
            </div>
            <div class="col-md-12">
                <label class="form-label fw-bold text-secondary">اسم الحملة أو المؤسسة المانحة (إن وجد)</label>
                <input type="text" name="campaign_name" class="form-control bg-light" placeholder="مثال: حملة شتاء دافئ، فاعل خير (أبو محمد)...">
            </div>
            
            <div class="col-md-12">
                <label class="form-label fw-bold text-secondary">إرفاق ملف رسمي للتبرع (PDF)</label>
                <input type="file" name="receipt_doc" class="form-control bg-light" accept=".pdf">
                <small class="text-muted">اختياري: يمكنك رفع وصل استلام، فاتورة، أو أي وثيقة رسمية بصيغة PDF.</small>
            </div>

            <div class="col-12">
                <label class="form-label fw-bold">ملاحظات (اختياري)</label>
                <textarea name="notes" class="form-control" rows="3" placeholder="أضف أي تفاصيل إضافية عن عملية التوزيع..."><?php echo htmlspecialchars($prefill_notes); ?></textarea>
            </div>
        </div>
    </div>
    <div class="card-footer text-center py-3">
        <button type="submit" class="btn btn-primary btn-lg shadow"><i class="bi bi-check-circle-fill me-2"></i> حفظ وتوثيق التوزيع</button>
    </div>
</form>

<script>
document.getElementById('donation_type_id').addEventListener('change', function() {
    var selectedOption = this.options[this.selectedIndex];
    if (!selectedOption.value) {
        document.getElementById('amount_field').style.display = 'none';
        return;
    }
    var category = selectedOption.getAttribute('data-category');
    var amountField = document.getElementById('amount_field');
    var amountLabel = document.getElementById('amount_label');
    var amountInput = document.getElementById('amount_input');
    
    amountField.style.display = 'block';
    amountInput.required = true;
    if (category === 'نقدي' || category.includes('نقد')) {
        amountLabel.innerText = 'المبلغ النقدي (JOD)';
    } else {
        amountLabel.innerText = 'الكمية المصروفة (عدد)';
    }
});
</script>

<?php require_once __DIR__ . '/footer.php'; ?>