<?php
// 1. بدء الجلسة والتحقق الأمني
session_start();
require_once __DIR__ . '/db.php';

// 2. استقبال الرقم الوطني والتحقق من وجوده
$national_id = isset($_GET['national_id']) ? trim($_GET['national_id']) : '';
if ($national_id === '') {
    require_once __DIR__ . '/header.php';
    echo '<div class="container mt-5"><div class="alert alert-danger text-center shadow-sm"><i class="bi bi-exclamation-triangle-fill fs-1 d-block mb-3 text-danger"></i> <h4 class="fw-bold">خطأ: الرقم الوطني للمستفيد مطلوب</h4><p>الرجاء الدخول لملف المستفيد بشكل صحيح من خلال النقر على زر "الملف" في جدول سجل المستفيدين.</p></div>';
    echo '<div class="text-center mt-3"><a href="javascript:history.back()" class="btn btn-secondary px-4"><i class="bi bi-arrow-right"></i> العودة للصفحة السابقة</a></div></div>';
    require_once __DIR__ . '/footer.php';
    exit;
}

// --- البحث عن قاعدة بيانات اللجنة الصحيحة للمستفيد (للمدير) ---
$active_pdo = $pdo;
$active_committee_id = $_SESSION['logged_in_committee'] ?? 0;

if ($active_committee_id == 0) {
    // إذا كان المستخدم مديراً، فإن $pdo يتصل بالقاعدة المركزية. يجب البحث عن المستفيد في القواعد الفرعية.
    try {
        $registry = $pdo->query("SELECT id FROM committees_registry")->fetchAll();
        foreach ($registry as $com) {
            $cid = $com['id'];
            if (isset($db_nodes[$cid])) {
                $test_pdo = new PDO("mysql:host=$host;dbname=" . $db_nodes[$cid] . ";charset=$charset", $user, $pass, $options);
                $stmt = $test_pdo->prepare("SELECT national_id FROM beneficiaries WHERE national_id = :nid LIMIT 1");
                $stmt->execute(['nid' => $national_id]);
                if ($stmt->fetch()) {
                    $active_pdo = $test_pdo;
                    $active_committee_id = $cid;
                    break;
                }
            }
        }
    } catch (PDOException $e) {}
}

$pdo = $active_pdo; // توجيه جميع الاستعلامات اللاحقة للقاعدة الصحيحة

// 3. جلب بيانات المستفيد الحالية
$stmt = $pdo->prepare("SELECT * FROM beneficiaries WHERE national_id = :nid LIMIT 1");
$stmt->execute(['nid' => $national_id]);
$beneficiary = $stmt->fetch();

try {
    $central_pdo = new PDO("mysql:host=$host;dbname=zakat_central_db;charset=$charset", $user, $pass, $options);
    $cstmt = $central_pdo->prepare("SELECT committee_name FROM committees_registry WHERE id = ?");
    $cstmt->execute([$active_committee_id]);
    $beneficiary['committee_name'] = $cstmt->fetchColumn() ?: 'السجل الموحد';
} catch (PDOException $e) {
    $beneficiary['committee_name'] = 'اللجنة الحالية';
}

if (!$beneficiary) {
    require_once __DIR__ . '/header.php';
    echo '<div class="container mt-5"><div class="alert alert-warning text-center shadow-sm"><i class="bi bi-search fs-1 d-block mb-3 text-warning"></i> <h4 class="fw-bold">خطأ: المستفيد غير موجود</h4><p>لم يتم العثور على أي مستفيد يحمل هذا الرقم الوطني في قاعدة البيانات.</p></div>';
    echo '<div class="text-center mt-3"><a href="javascript:history.back()" class="btn btn-secondary px-4"><i class="bi bi-arrow-right"></i> العودة للصفحة السابقة</a></div></div>';
    require_once __DIR__ . '/footer.php';
    exit;
}

// 4. التحقق من الصلاحيات
$committee_id = $active_committee_id;

$error_message = '';
$success_message = '';

// دالة مساعدة لمعالجة رفع الملفات بأمان
function handle_upload($file_key, $national_id, $upload_dir) {
    if (isset($_FILES[$file_key]) && $_FILES[$file_key]['error'] == UPLOAD_ERR_OK) {
        $file_tmp_path = $_FILES[$file_key]['tmp_name'];
        $file_name = basename($_FILES[$file_key]['name']);
        $file_extension = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'pdf', 'doc', 'docx'];

        if (in_array($file_extension, $allowed_extensions)) {
            $new_file_name = $national_id . '_' . $file_key . '_' . uniqid() . '.' . $file_extension;
            $dest_path = $upload_dir . $new_file_name;
            if (move_uploaded_file($file_tmp_path, $dest_path)) {
                return $new_file_name;
            }
        }
    }
    return null;
}

// 5. معالجة تحديث البيانات عند إرسال النموذج
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $current_national_id = $beneficiary['national_id'];
        $full_name = trim($_POST['full_name']);

        // التحقق من تكرار رقم الهاتف (إذا تم تغييره)
        $phone_number = trim($_POST['phone_number'] ?? '');
        if (!empty($phone_number) && $phone_number !== $beneficiary['phone_number']) {
            $checkPhoneStmt = $pdo->prepare("SELECT national_id FROM beneficiaries WHERE phone_number = :phone AND national_id != :nid");
            $checkPhoneStmt->execute(['phone' => $phone_number, 'nid' => $current_national_id]);
            if ($checkPhoneStmt->fetch()) {
                throw new Exception("خطأ: رقم الهاتف '$phone_number' مسجل بالفعل لمستفيد آخر في نفس لجنتك.");
            }
        }
        
        // معالجة رفع الملفات (إذا تم رفع ملف جديد، استخدمه، وإلا احتفظ بالقديم)
        $upload_dir = __DIR__ . '/uploads/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        $id_photo_path = handle_upload('id_photo_attachment', $current_national_id, $upload_dir) ?? $beneficiary['id_photo_attachment'];
        $official_docs_path = handle_upload('official_docs_attachment', $current_national_id, $upload_dir) ?? $beneficiary['official_docs_attachment'];
        $medical_report_path = handle_upload('medical_report_attachment', $current_national_id, $upload_dir) ?? $beneficiary['medical_report_attachment'];
        $income_proof_path = handle_upload('income_proof_attachment', $current_national_id, $upload_dir) ?? $beneficiary['income_proof_attachment'];
        $other_attachments_path = handle_upload('other_attachments', $current_national_id, $upload_dir) ?? $beneficiary['other_attachments'];

        // بناء استعلام التحديث
        $sql = "UPDATE beneficiaries SET
            full_name = ?, phone_number = ?, gender = ?, marital_status = ?, address = ?,
            family_file_number = ?, family_size = ?, children_count = ?, elderly_count = ?, has_breadwinner = ?, relationship_to_head = ?, 
            monthly_income = ?, income_source = ?, employment_status = ?, work_type = ?, living_standard = ?, has_debt = ?, 
            health_general = ?, has_chronic_disease = ?, has_disability = ?, disability_type = ?, needs_continuous_treatment = ?, 
            education_level = ?, current_study_status = ?, student_count = ?, has_learning_difficulties = ?, 
            housing_type = ?, housing_condition = ?, room_count = ?, has_basic_services = ?, eviction_risk = ?, 
            gps_coordinates = ?, map_link = ?, nearest_landmark = ?, 
            status = ?, priority_level = ?,
            id_photo_attachment = ?, official_docs_attachment = ?, medical_report_attachment = ?, income_proof_attachment = ?, other_attachments = ?
            WHERE national_id = ?";
        
        $stmt = $pdo->prepare($sql);
        
        $params = [
            $_POST['full_name'], $_POST['phone_number'] ?: NULL, $_POST['gender'] ?: NULL, $_POST['marital_status'] ?: NULL, $_POST['address'] ?: NULL,
            $_POST['family_file_number'] ?: NULL, (int)($_POST['family_size'] ?: 0), (int)($_POST['children_count'] ?: 0), (int)($_POST['elderly_count'] ?: 0), isset($_POST['has_breadwinner']) ? 1 : 0, $_POST['relationship_to_head'] ?: NULL,
            (float)($_POST['monthly_income'] ?: 0.00), $_POST['income_source'] ?: NULL, $_POST['employment_status'] ?: NULL, $_POST['work_type'] ?: NULL, $_POST['living_standard'] ?: NULL, isset($_POST['has_debt']) ? 1 : 0,
            $_POST['health_general'] ?: NULL, isset($_POST['has_chronic_disease']) ? 1 : 0, isset($_POST['has_disability']) ? 1 : 0, $_POST['disability_type'] ?: NULL, isset($_POST['needs_continuous_treatment']) ? 1 : 0,
            $_POST['education_level'] ?: NULL, $_POST['current_study_status'] ?: NULL, (int)($_POST['student_count'] ?: 0), isset($_POST['has_learning_difficulties']) ? 1 : 0,
            $_POST['housing_type'] ?: NULL, $_POST['housing_condition'] ?: NULL, (int)($_POST['room_count'] ?: 0), isset($_POST['has_basic_services']) ? 1 : 0, isset($_POST['eviction_risk']) ? 1 : 0,
            $_POST['gps_coordinates'] ?: NULL, $_POST['map_link'] ?: NULL, $_POST['nearest_landmark'] ?: NULL,
            $_POST['full_name'], $_POST['phone_number'] ?: '', $_POST['gender'] ?: '', $_POST['marital_status'] ?: '', $_POST['address'] ?: '',
            $_POST['family_file_number'] ?: '', (int)($_POST['family_size'] ?: 0), (int)($_POST['children_count'] ?: 0), (int)($_POST['elderly_count'] ?: 0), isset($_POST['has_breadwinner']) ? 1 : 0, $_POST['relationship_to_head'] ?: '',
            (float)($_POST['monthly_income'] ?: 0.00), $_POST['income_source'] ?: '', $_POST['employment_status'] ?: '', $_POST['work_type'] ?: '', $_POST['living_standard'] ?: '', isset($_POST['has_debt']) ? 1 : 0,
            $_POST['health_general'] ?: '', isset($_POST['has_chronic_disease']) ? 1 : 0, isset($_POST['has_disability']) ? 1 : 0, $_POST['disability_type'] ?: '', isset($_POST['needs_continuous_treatment']) ? 1 : 0,
            $_POST['education_level'] ?: '', $_POST['current_study_status'] ?: '', (int)($_POST['student_count'] ?: 0), isset($_POST['has_learning_difficulties']) ? 1 : 0,
            $_POST['housing_type'] ?: '', $_POST['housing_condition'] ?: '', (int)($_POST['room_count'] ?: 0), isset($_POST['has_basic_services']) ? 1 : 0, isset($_POST['eviction_risk']) ? 1 : 0,
            $_POST['gps_coordinates'] ?: '', $_POST['map_link'] ?: '', $_POST['nearest_landmark'] ?: '',
            $_POST['status'], $_POST['priority_level'],
            $id_photo_path, $official_docs_path, $medical_report_path, $income_proof_path, $other_attachments_path,
            $current_national_id
        ];

        $stmt->execute($params);

        $success_message = "تم تحديث بيانات المستفيد '" . htmlspecialchars($full_name) . "' بنجاح.";
        
        // إعادة جلب البيانات بعد التحديث لعرضها في النموذج
        $stmt = $pdo->prepare("SELECT * FROM beneficiaries WHERE national_id = :nid");
        $stmt->execute(['nid' => $current_national_id]);
        $beneficiary = $stmt->fetch();
        $beneficiary['committee_name'] = 'تم التحديث بنجاح';

    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}

// جلب سجل المساعدات والتوزيعات التفصيلي لهذا المستفيد
$historyStmt = $pdo->prepare("
    SELECT 
        dh.amount, dh.donation_date, dh.donation_source, dh.campaign_name, dh.donation_status, dh.delivery_method, dh.notes, dh.receipt_doc,
        dt.category, dt.sub_category,
        u.full_name as employee_name
    FROM donations_history dh
    JOIN zakat_central_db.donation_types dt ON dh.donation_type_id = dt.id
    LEFT JOIN users u ON dh.user_id = u.id
    WHERE dh.national_id = :nid
    ORDER BY dh.donation_date DESC
");
$historyStmt->execute(['nid' => $national_id]);
$donations_history = $historyStmt->fetchAll();

require_once __DIR__ . '/header.php';
?>

<div class="row mb-4 mt-3">
    <div class="col-12 d-flex justify-content-between align-items-center">
        <h3 class="mb-0 text-primary">
            <i class="bi bi-pencil-square"></i> تعديل بيانات المستفيد | <?php echo htmlspecialchars($beneficiary['committee_name']); ?>
        </h3>
        <?php $return_url = (isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true) ? "all_beneficiaries.php" : "committee.php?id=" . ($_SESSION['logged_in_committee'] ?? 0); ?>
        <a href="<?php echo $return_url; ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-right"></i> العودة</a>
    </div>
</div>

<?php if ($error_message): ?>
    <div class="alert alert-danger text-center"><?php echo $error_message; ?></div>
<?php endif; ?>
<?php if ($success_message): ?>
    <div class="alert alert-success text-center"><?php echo $success_message; ?></div>
<?php endif; ?>

<form action="edit_beneficiary.php?national_id=<?php echo htmlspecialchars($national_id); ?>" method="POST" enctype="multipart/form-data" class="card shadow-sm border-0">
    <div class="card-body">
        <div class="accordion" id="beneficiaryAccordion">

            <!-- 1. البيانات الشخصية -->
            <div class="accordion-item">
                <h2 class="accordion-header" id="headingOne">
                    <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#collapseOne" aria-expanded="true" aria-controls="collapseOne">
                        <i class="bi bi-person-badge me-2"></i> 1. البيانات الشخصية
                    </button>
                </h2>
                <div id="collapseOne" class="accordion-collapse collapse show" aria-labelledby="headingOne" data-bs-parent="#beneficiaryAccordion">
                    <div class="accordion-body">
                        <div class="row g-3">
                            <div class="col-md-6"><label class="form-label">الاسم الكامل</label><input type="text" name="full_name" class="form-control" required value="<?php echo htmlspecialchars($beneficiary['full_name']); ?>"></div>
                            <div class="col-md-6"><label class="form-label">الرقم الوطني (غير قابل للتعديل)</label><input type="text" name="national_id" class="form-control" readonly value="<?php echo htmlspecialchars($beneficiary['national_id']); ?>"></div>
                            <div class="col-md-4"><label class="form-label">رقم الهاتف</label><input type="tel" name="phone_number" class="form-control" placeholder="اختياري" value="<?php echo htmlspecialchars($beneficiary['phone_number']); ?>"></div>
                            <div class="col-md-4"><label class="form-label">الجنس</label>
                                <select name="gender" class="form-select">
                                    <option value="">اختر (اختياري)</option>
                                    <option value="ذكر" <?php if($beneficiary['gender'] == 'ذكر') echo 'selected'; ?>>ذكر</option>
                                    <option value="أنثى" <?php if($beneficiary['gender'] == 'أنثى') echo 'selected'; ?>>أنثى</option>
                                </select>
                            </div>
                            <div class="col-md-4"><label class="form-label">تاريخ الميلاد (غير قابل للتعديل)</label><input type="date" name="date_of_birth" class="form-control" readonly value="<?php echo htmlspecialchars($beneficiary['date_of_birth']); ?>"></div>
                            <div class="col-md-4">
                                <label class="form-label">الحالة الاجتماعية</label>
                                <select name="marital_status" class="form-select">
                                    <option value="">اختر (اختياري)</option>
                                    <option value="أعزب" <?php if($beneficiary['marital_status'] == 'أعزب') echo 'selected'; ?>>أعزب</option>
                                    <option value="متزوج" <?php if($beneficiary['marital_status'] == 'متزوج') echo 'selected'; ?>>متزوج</option>
                                    <option value="مطلق" <?php if($beneficiary['marital_status'] == 'مطلق') echo 'selected'; ?>>مطلق</option>
                                    <option value="أرمل" <?php if($beneficiary['marital_status'] == 'أرمل') echo 'selected'; ?>>أرمل</option>
                                </select>
                            </div>
                            <div class="col-12"><label class="form-label">العنوان التفصيلي</label><textarea name="address" class="form-control" placeholder="اختياري"><?php echo htmlspecialchars($beneficiary['address']); ?></textarea></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 2. بيانات الأسرة -->
            <div class="accordion-item">
                <h2 class="accordion-header" id="headingTwo">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseTwo" aria-expanded="false" aria-controls="collapseTwo">
                        <i class="bi bi-people me-2"></i> 2. بيانات الأسرة
                    </button>
                </h2>
                <div id="collapseTwo" class="accordion-collapse collapse" aria-labelledby="headingTwo" data-bs-parent="#beneficiaryAccordion">
                    <div class="accordion-body">
                        <div class="row g-3">
                            <div class="col-md-4"><label class="form-label">رقم ملف الأسرة</label><input type="text" name="family_file_number" class="form-control" placeholder="اختياري" value="<?php echo htmlspecialchars($beneficiary['family_file_number']); ?>"></div>
                            <div class="col-md-4"><label class="form-label">صلة القرابة برب الأسرة</label><input type="text" name="relationship_to_head" class="form-control" placeholder="اختياري" value="<?php echo htmlspecialchars($beneficiary['relationship_to_head']); ?>"></div>
                            <div class="col-md-4"><label class="form-label">عدد أفراد الأسرة</label><input type="number" name="family_size" class="form-control" placeholder="اختياري" value="<?php echo (int)$beneficiary['family_size']; ?>"></div>
                            <div class="col-md-4"><label class="form-label">عدد الأطفال (تحت 18)</label><input type="number" name="children_count" class="form-control" placeholder="اختياري" value="<?php echo (int)$beneficiary['children_count']; ?>"></div>
                            <div class="col-md-4"><label class="form-label">عدد كبار السن (فوق 60)</label><input type="number" name="elderly_count" class="form-control" placeholder="اختياري" value="<?php echo (int)$beneficiary['elderly_count']; ?>"></div>
                            <div class="col-md-4 d-flex align-items-end"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="has_breadwinner" id="has_breadwinner" <?php if($beneficiary['has_breadwinner']) echo 'checked'; ?>><label class="form-check-label" for="has_breadwinner">يوجد معيل للأسرة</label></div></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 3. البيانات الاقتصادية -->
            <div class="accordion-item">
                <h2 class="accordion-header" id="headingThree">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseThree" aria-expanded="false" aria-controls="collapseThree">
                        <i class="bi bi-cash-coin me-2"></i> 3. البيانات الاقتصادية
                    </button>
                </h2>
                <div id="collapseThree" class="accordion-collapse collapse" aria-labelledby="headingThree" data-bs-parent="#beneficiaryAccordion">
                    <div class="accordion-body">
                        <div class="row g-3">
                            <div class="col-md-4"><label class="form-label">الدخل الشهري (تقريبي بالـ JOD)</label><input type="number" step="0.01" name="monthly_income" class="form-control" placeholder="اختياري" value="<?php echo (float)$beneficiary['monthly_income']; ?>"></div>
                            <div class="col-md-4"><label class="form-label">مصدر الدخل</label><input type="text" name="income_source" class="form-control" placeholder="اختياري" value="<?php echo htmlspecialchars($beneficiary['income_source']); ?>"></div>
                            <div class="col-md-4"><label class="form-label">الحالة الوظيفية</label><input type="text" name="employment_status" class="form-control" placeholder="اختياري" value="<?php echo htmlspecialchars($beneficiary['employment_status']); ?>"></div>
                            <div class="col-md-6"><label class="form-label">نوع العمل</label><input type="text" name="work_type" class="form-control" placeholder="اختياري" value="<?php echo htmlspecialchars($beneficiary['work_type']); ?>"></div>
                            <div class="col-md-6"><label class="form-label">مستوى المعيشة</label><input type="text" name="living_standard" class="form-control" placeholder="اختياري" value="<?php echo htmlspecialchars($beneficiary['living_standard']); ?>"></div>
                            <div class="col-md-12"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="has_debt" id="has_debt" <?php if($beneficiary['has_debt']) echo 'checked'; ?>><label class="form-check-label" for="has_debt">يوجد ديون على الأسرة</label></div></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 4. الحالة الصحية -->
            <div class="accordion-item">
                <h2 class="accordion-header" id="headingFour">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseFour" aria-expanded="false" aria-controls="collapseFour">
                        <i class="bi bi-heart-pulse me-2"></i> 4. الحالة الصحية
                    </button>
                </h2>
                <div id="collapseFour" class="accordion-collapse collapse" aria-labelledby="headingFour" data-bs-parent="#beneficiaryAccordion">
                    <div class="accordion-body">
                        <div class="row g-3">
                            <div class="col-12"><label class="form-label">الحالة الصحية العامة (وصف)</label><input type="text" name="health_general" class="form-control" placeholder="اختياري" value="<?php echo htmlspecialchars($beneficiary['health_general']); ?>"></div>
                            <div class="col-md-4"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="has_chronic_disease" id="has_chronic_disease" <?php if($beneficiary['has_chronic_disease']) echo 'checked'; ?>><label class="form-check-label" for="has_chronic_disease">يوجد أمراض مزمنة</label></div></div>
                            <div class="col-md-4"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="needs_continuous_treatment" id="needs_continuous_treatment" <?php if($beneficiary['needs_continuous_treatment']) echo 'checked'; ?>><label class="form-check-label" for="needs_continuous_treatment">بحاجة لعلاج مستمر</label></div></div>
                            <div class="col-md-4"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="has_disability" id="has_disability" onchange="document.getElementById('disability_type_div').style.display = this.checked ? 'block' : 'none'" <?php if($beneficiary['has_disability']) echo 'checked'; ?>><label class="form-check-label" for="has_disability">يوجد إعاقة</label></div></div>
                            <div class="col-12" id="disability_type_div" style="display:<?php echo $beneficiary['has_disability'] ? 'block' : 'none'; ?>;"><label class="form-label">نوع الإعاقة</label><input type="text" name="disability_type" class="form-control" placeholder="اختياري" value="<?php echo htmlspecialchars($beneficiary['disability_type']); ?>"></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 5. الحالة التعليمية -->
            <div class="accordion-item">
                <h2 class="accordion-header" id="headingFive">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseFive" aria-expanded="false" aria-controls="collapseFive">
                        <i class="bi bi-book me-2"></i> 5. الحالة التعليمية
                    </button>
                </h2>
                <div id="collapseFive" class="accordion-collapse collapse" aria-labelledby="headingFive" data-bs-parent="#beneficiaryAccordion">
                    <div class="accordion-body">
                        <div class="row g-3">
                            <div class="col-md-4"><label class="form-label">المستوى التعليمي</label><input type="text" name="education_level" class="form-control" placeholder="اختياري" value="<?php echo htmlspecialchars($beneficiary['education_level']); ?>"></div>
                            <div class="col-md-4"><label class="form-label">الحالة الدراسية الحالية</label><input type="text" name="current_study_status" class="form-control" placeholder="اختياري" value="<?php echo htmlspecialchars($beneficiary['current_study_status']); ?>"></div>
                            <div class="col-md-4"><label class="form-label">عدد الطلاب في الأسرة</label><input type="number" name="student_count" class="form-control" placeholder="اختياري" value="<?php echo (int)$beneficiary['student_count']; ?>"></div>
                            <div class="col-12"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="has_learning_difficulties" id="has_learning_difficulties" <?php if($beneficiary['has_learning_difficulties']) echo 'checked'; ?>><label class="form-check-label" for="has_learning_difficulties">يوجد صعوبات تعليمية</label></div></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 6. بيانات السكن والموقع -->
            <div class="accordion-item">
                <h2 class="accordion-header" id="headingSix">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseSix" aria-expanded="false" aria-controls="collapseSix">
                        <i class="bi bi-house-door me-2"></i> 6. بيانات السكن والموقع
                    </button>
                </h2>
                <div id="collapseSix" class="accordion-collapse collapse" aria-labelledby="headingSix" data-bs-parent="#beneficiaryAccordion">
                    <div class="accordion-body">
                        <div class="row g-3">
                            <div class="col-md-4"><label class="form-label">نوع السكن</label><input type="text" name="housing_type" class="form-control" placeholder="مثال: ملك، إيجار... (اختياري)" value="<?php echo htmlspecialchars($beneficiary['housing_type']); ?>"></div>
                            <div class="col-md-4"><label class="form-label">حالة السكن</label><input type="text" name="housing_condition" class="form-control" placeholder="مثال: جيد، متوسط... (اختياري)" value="<?php echo htmlspecialchars($beneficiary['housing_condition']); ?>"></div>
                            <div class="col-md-4"><label class="form-label">عدد الغرف</label><input type="number" name="room_count" class="form-control" placeholder="اختياري" value="<?php echo (int)$beneficiary['room_count']; ?>"></div>
                            <div class="col-md-6"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="has_basic_services" id="has_basic_services" <?php if($beneficiary['has_basic_services']) echo 'checked'; ?>><label class="form-check-label" for="has_basic_services">تتوفر خدمات أساسية (ماء/كهرباء)</label></div></div>
                            <div class="col-md-6"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="eviction_risk" id="eviction_risk" <?php if($beneficiary['eviction_risk']) echo 'checked'; ?>><label class="form-check-label" for="eviction_risk">يوجد احتمالية إخلاء</label></div></div>
                            <div class="col-md-4"><label class="form-label">إحداثيات GPS</label><input type="text" name="gps_coordinates" class="form-control" placeholder="اختياري" value="<?php echo htmlspecialchars($beneficiary['gps_coordinates']); ?>"></div>
                            <div class="col-md-4"><label class="form-label">رابط الموقع على الخريطة</label><input type="url" name="map_link" class="form-control" placeholder="اختياري" value="<?php echo htmlspecialchars($beneficiary['map_link']); ?>"></div>
                            <div class="col-md-4"><label class="form-label">أقرب معلم</label><input type="text" name="nearest_landmark" class="form-control" placeholder="اختياري" value="<?php echo htmlspecialchars($beneficiary['nearest_landmark']); ?>"></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 7. حالة المستفيد والمرفقات -->
            <div class="accordion-item">
                <h2 class="accordion-header" id="headingSeven">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseSeven" aria-expanded="false" aria-controls="collapseSeven">
                        <i class="bi bi-folder2-open me-2"></i> 7. حالة المستفيد والمرفقات
                    </button>
                </h2>
                <div id="collapseSeven" class="accordion-collapse collapse" aria-labelledby="headingSeven" data-bs-parent="#beneficiaryAccordion">
                    <div class="accordion-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">حالة المستفيد داخل النظام</label>
                                <select name="status" class="form-select">
                                    <option value="جديد" <?php if($beneficiary['status'] == 'جديد') echo 'selected'; ?>>جديد</option>
                                    <option value="قيد الدراسة" <?php if($beneficiary['status'] == 'قيد الدراسة') echo 'selected'; ?>>قيد الدراسة</option>
                                    <option value="مقبول" <?php if($beneficiary['status'] == 'مقبول') echo 'selected'; ?>>مقبول</option>
                                    <option value="مرفوض" <?php if($beneficiary['status'] == 'مرفوض') echo 'selected'; ?>>مرفوض</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">مستوى الأولوية</label>
                                <select name="priority_level" class="form-select">
                                    <option value="منخفضة" <?php if($beneficiary['priority_level'] == 'منخفضة') echo 'selected'; ?>>منخفضة</option>
                                    <option value="متوسطة" <?php if($beneficiary['priority_level'] == 'متوسطة') echo 'selected'; ?>>متوسطة</option>
                                    <option value="عالية" <?php if($beneficiary['priority_level'] == 'عالية') echo 'selected'; ?>>عالية</option>
                                </select>
                            </div>
                            <hr class="my-4">
                            <div class="col-md-6"><label class="form-label">صورة الهوية (اتركه فارغاً لعدم التغيير)</label><input type="file" name="id_photo_attachment" class="form-control" accept=".jpg,.jpeg,.png,.pdf"></div>
                            <div class="col-md-6"><label class="form-label">وثائق رسمية (اتركه فارغاً لعدم التغيير)</label><input type="file" name="official_docs_attachment" class="form-control" accept=".jpg,.jpeg,.png,.pdf,.doc,.docx"></div>
                            <div class="col-md-6"><label class="form-label">تقرير طبي (اتركه فارغاً لعدم التغيير)</label><input type="file" name="medical_report_attachment" class="form-control" accept=".jpg,.jpeg,.png,.pdf,.doc,.docx"></div>
                            <div class="col-md-6"><label class="form-label">إثبات دخل (اتركه فارغاً لعدم التغيير)</label><input type="file" name="income_proof_attachment" class="form-control" accept=".jpg,.jpeg,.png,.pdf,.doc,.docx"></div>
                            <div class="col-12"><label class="form-label">ملفات داعمة أخرى (اتركه فارغاً لعدم التغيير)</label><input type="file" name="other_attachments" class="form-control" accept=".jpg,.jpeg,.png,.pdf,.doc,.docx"></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 8. سجل المساعدات والتوزيعات المستلمة -->
            <div class="accordion-item">
                <h2 class="accordion-header" id="headingEight">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseEight" aria-expanded="false" aria-controls="collapseEight">
                        <i class="bi bi-gift text-success me-2"></i> 8. سجل المساعدات والتوزيعات المستلمة
                    </button>
                </h2>
                <div id="collapseEight" class="accordion-collapse collapse" aria-labelledby="headingEight" data-bs-parent="#beneficiaryAccordion">
                    <div class="accordion-body p-0">
                        <?php if (count($donations_history) > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0 text-nowrap">
                                    <thead class="table-light">
                                        <tr>
                                            <th class="ps-4">نوع وقيمة التبرع</th>
                                            <th>التاريخ والحالة</th>
                                            <th>طريقة التسليم والمصدر</th>
                                            <th>الموظف المسؤول</th>
                                            <th>ملاحظات</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($donations_history as $donation): ?>
                                        <tr>
                                            <td class="ps-4">
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
                                            </td>
                                            <td>
                                                <div class="small mb-1"><i class="bi bi-truck text-primary"></i> <?php echo htmlspecialchars($donation['delivery_method'] ?? 'غير محدد'); ?></div>
                                                <div class="small text-muted"><i class="bi bi-diagram-3"></i> <?php echo htmlspecialchars($donation['donation_source'] ?? 'غير محدد'); ?>
                                                    <?php if(!empty($donation['campaign_name'])) echo ' <br><span class="text-info">(' . htmlspecialchars($donation['campaign_name']) . ')</span>'; ?>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="small text-muted fw-bold"><i class="bi bi-person-circle"></i> <?php echo htmlspecialchars($donation['employee_name'] ?? 'مدير النظام'); ?></span><br>
                                                <span class="small text-muted" style="font-size: 0.75rem;"><i class="bi bi-building"></i> <?php echo htmlspecialchars($donation['committee_name']); ?></span>
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
                            <div class="alert alert-light text-center m-4 text-muted border">
                                <i class="bi bi-info-circle fs-4 d-block mb-2"></i>
                                لم يستلم هذا المستفيد أي مساعدات أو تبرعات حتى الآن.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

        </div>
    </div>
    <div class="card-footer text-center py-3">
        <button type="submit" class="btn btn-primary btn-lg shadow"><i class="bi bi-check-circle-fill"></i> حفظ التعديلات</button>
    </div>
</form>

<?php
require_once __DIR__ . '/footer.php';
?>