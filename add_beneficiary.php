<?php
// 1. بدء الجلسة والتحقق الأمني
session_start();
$committee_id = isset($_GET['committee_id']) ? (int)$_GET['committee_id'] : 0;

if (!isset($_SESSION['logged_in_committee']) || $_SESSION['logged_in_committee'] !== $committee_id) {
    header("Location: login.php?id=" . $committee_id);
    exit;
}

// 2. استدعاء الملفات الضرورية
require_once __DIR__ . '/db.php';

// 3. جلب بيانات اللجنة
try {
    $central_pdo = new PDO("mysql:host=$host;dbname=zakat_central_db;charset=$charset", $user, $pass, $options);
    $stmt = $central_pdo->prepare("SELECT committee_name as name FROM committees_registry WHERE id = :id");
    $stmt->execute(['id' => $committee_id]);
    $committee = $stmt->fetch();
} catch (PDOException $e) { $committee = false; }

if (!$committee) {
    require_once __DIR__ . '/header.php';
    echo '<div class="alert alert-danger text-center mt-5">عذراً، اللجنة غير موجودة في قاعدة البيانات.</div>';
    echo '<div class="text-center mt-3"><a href="index.php" class="btn btn-primary">العودة للصفحة الرئيسية</a></div>';
    require_once __DIR__ . '/footer.php';
    exit;
}

$error_message = '';
$success_message = '';
$warning_message = ''; // متغير جديد لرسائل التنبيه غير الحاسمة

// دالة مساعدة لمعالجة رفع الملفات بأمان
function handle_upload($file_key, $national_id, $upload_dir) {
    if (isset($_FILES[$file_key]) && $_FILES[$file_key]['error'] == UPLOAD_ERR_OK) {
        $file_tmp_path = $_FILES[$file_key]['tmp_name'];
        $file_name = basename($_FILES[$file_key]['name']);
        $file_extension = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'pdf', 'doc', 'docx'];

        if (in_array($file_extension, $allowed_extensions)) {
            // إنشاء اسم فريد للملف لمنع الاستبدال والتخمين
            $new_file_name = $national_id . '_' . $file_key . '_' . uniqid() . '.' . $file_extension;
            $dest_path = $upload_dir . $new_file_name;
            if (move_uploaded_file($file_tmp_path, $dest_path)) {
                return $new_file_name; // إرجاع اسم الملف الجديد لحفظه في قاعدة البيانات
            }
        }
    }
    return null; // إرجاع null في حال الفشل أو عدم وجود ملف
}

// 4. معالجة بيانات النموذج عند الإرسال
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $national_id = trim($_POST['national_id']); // مطلوب
        $phone_number = trim($_POST['phone_number'] ?? ''); // اختياري
        $full_name = trim($_POST['full_name']); // مطلوب
        $address = trim($_POST['address'] ?? ''); // اختياري
        $family_file_number = trim($_POST['family_file_number'] ?? ''); // اختياري
        $date_of_birth = trim($_POST['date_of_birth'] ?? ''); // مطلوب الآن

        if (empty($date_of_birth)) {
            throw new Exception("خطأ: يرجى إدخال تاريخ الميلاد، فهو حقل إجباري للتحقق من الازدواجية.");
        }

        // تأكد من وجود user_id في الجلسة لتسجيل التدقيق
        if (!isset($_SESSION['user_id'])) {
            throw new Exception("خطأ: معرف المستخدم غير موجود في الجلسة. يرجى تسجيل الدخول مرة أخرى.");
        }

        // --- 1. اكتشاف تكرار الرقم الوطني ورقم الهاتف والعنوان (مطابقة تامة) ---

        $was_found = false;
        try {
            $central_pdo = new PDO("mysql:host=$host;dbname=zakat_central_db;charset=$charset", $user, $pass, $options);
            $registry = $central_pdo->query("SELECT id, committee_name as name FROM committees_registry ORDER BY id ASC")->fetchAll();
            
            foreach ($registry as $com) {
                $cid = $com['id'];
                $cname = $com['name'];
                if (isset($db_nodes[$cid])) {
                    try {
                        $node_db = $db_nodes[$cid];
                        $node_pdo = new PDO("mysql:host=$host;dbname=$node_db;charset=$charset", $user, $pass, $options);
                        
                        $checkNIDStmt = $node_pdo->prepare("SELECT national_id, full_name FROM beneficiaries WHERE national_id = :nid LIMIT 1");
                        $checkNIDStmt->execute(['nid' => $national_id]);
                        if ($existing_beneficiary = $checkNIDStmt->fetch()) {
                            $was_found = true;
                            if ($cid == $committee_id) {
                                $ip_address = getUserIP();
                                $central_pdo->prepare("INSERT INTO search_audit_logs (user_id, committee_id, searched_national_id, ip_address, was_successful) VALUES (?, ?, ?, ?, ?)")->execute([$_SESSION['user_id'], $committee_id, $national_id, $ip_address, 1]);
                                throw new Exception("خطأ: المستفيد ذو الرقم الوطني '$national_id' مسجل بالفعل في لجنتكم باسم " . htmlspecialchars($existing_beneficiary['full_name']) . ".");
                            } else {
                                $warning_message .= "تنبيه: هذا المستفيد مسجل أيضاً في السجل الموحد ضمن '" . htmlspecialchars($cname) . "'. تم السماح بالإضافة هنا كملف منفصل.<br>";
                            }
                        }

                        if (!empty($phone_number)) {
                            $checkPhoneStmt = $node_pdo->prepare("SELECT national_id, full_name FROM beneficiaries WHERE phone_number = :phone LIMIT 1");
                            $checkPhoneStmt->execute(['phone' => $phone_number]);
                            if ($existing_phone = $checkPhoneStmt->fetch()) {
                                if ($cid == $committee_id) {
                                    throw new Exception("خطأ: رقم الهاتف '$phone_number' مسجل بالفعل في لجنتكم للمستفيد " . htmlspecialchars($existing_phone['full_name']) . ".");
                                } else {
                                    $warning_message .= "تنبيه: رقم الهاتف '$phone_number' مسجل مسبقاً في السجل الموحد ضمن '" . htmlspecialchars($cname) . "'.<br>";
                                }
                            }
                        }
                    } catch (PDOException $e) {}
                }
            }
            
            $ip_address = getUserIP();
            $central_pdo->prepare("INSERT INTO search_audit_logs (user_id, committee_id, searched_national_id, ip_address, was_successful) VALUES (?, ?, ?, ?, ?)")->execute([$_SESSION['user_id'], $committee_id, $national_id, $ip_address, $was_found ? 1 : 0]);
                        
        } catch (PDOException $e) {}

        // التحقق من عدم تكرار العنوان (إذا تم إدخاله، تنبيه غير حاسم)
        // ملاحظة: المطابقة التامة للعناوين قد تكون صارمة جداً. يمكن تحسينها بخوارزميات مطابقة أكثر مرونة.
        if (!empty($address)) {
            $checkAddressStmt = $pdo->prepare("SELECT national_id, full_name FROM beneficiaries WHERE address = :address LIMIT 1");
            $checkAddressStmt->execute(['address' => $address]);
            if ($existing_beneficiary = $checkAddressStmt->fetch()) {
                $warning_message .= "تنبيه: العنوان '$address' مسجل بالفعل في لجنتكم للمستفيد " . htmlspecialchars($existing_beneficiary['full_name']) . ". يرجى التحقق يدوياً.<br>";
            }
        }

        // --- 2. اكتشاف التشابه في الأسماء (Fuzzy Matching) ---
        // ملاحظة: هذه الطريقة قد تكون بطيئة جداً مع قواعد بيانات كبيرة.
        // يفضل استخدام حلول بحث متخصصة (مثل Elasticsearch) أو وظائف قاعدة بيانات محسّنة.
        $potential_name_duplicates = [];
        $all_names_stmt = $pdo->query("SELECT national_id, full_name FROM beneficiaries");
        while ($existing_name_row = $all_names_stmt->fetch()) {
            $existing_full_name = $existing_name_row['full_name'];
            // استخدام مسافة ليفنشتاين (Levenshtein distance)
            // كلما كانت القيمة أقل، كلما كان التشابه أكبر.
            // يجب تحديد عتبة (threshold) مناسبة بناءً على طول الأسماء والتشابه المقبول.
            // استخدام mb_strtolower و mb_strlen لدعم الأحرف العربية بشكل صحيح.
            $distance = levenshtein(mb_strtolower($full_name, 'UTF-8'), mb_strtolower($existing_full_name, 'UTF-8'));
            $name_length = mb_strlen($full_name, 'UTF-8');

            // مثال: إذا كانت المسافة أقل من 20% من طول الاسم الأصلي (وليس صفراً لتجنب المطابقة الذاتية)
            if ($name_length > 0 && $distance / $name_length < 0.2 && $distance > 0) {
                $potential_name_duplicates[] = htmlspecialchars($existing_full_name) . " (مسافة ليفنشتاين: $distance)";
            }
        }
        if (!empty($potential_name_duplicates)) {
            $warning_message .= "تنبيه: توجد أسماء مشابهة في النظام: " . implode(", ", $potential_name_duplicates) . ". يرجى التحقق يدوياً.<br>";
        }

        // --- 3. كشف التكرار داخل نفس الأسرة عند إضافة مستفيد ---
        if (!empty($family_file_number)) {
            $family_members_stmt = $pdo->prepare("SELECT national_id, phone_number, full_name FROM beneficiaries WHERE family_file_number = :ffn");
            $family_members_stmt->execute(['ffn' => $family_file_number]);
            while ($member = $family_members_stmt->fetch()) {
                if ($national_id !== '' && $member['national_id'] == $national_id) {
                    $warning_message .= "تنبيه: الرقم الوطني '$national_id' مكرر داخل نفس الأسرة للمستفيد " . htmlspecialchars($member['full_name']) . ". يرجى التحقق يدوياً.<br>";
                }
                if (!empty($phone_number) && $member['phone_number'] == $phone_number) {
                    $warning_message .= "تنبيه: رقم الهاتف '$phone_number' مكرر داخل نفس الأسرة للمستفيد " . htmlspecialchars($member['full_name']) . ". يرجى التحقق يدوياً.<br>";
                }
            }
        }

        // تحديد مسار مجلد الرفع والتأكد من وجوده
        $upload_dir = __DIR__ . '/uploads/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        // معالجة رفع الملفات
        $id_photo_path = handle_upload('id_photo_attachment', $national_id, $upload_dir);
        $official_docs_path = handle_upload('official_docs_attachment', $national_id, $upload_dir);
        $medical_report_path = handle_upload('medical_report_attachment', $national_id, $upload_dir);
        $income_proof_path = handle_upload('income_proof_attachment', $national_id, $upload_dir);
        $other_attachments_path = handle_upload('other_attachments', $national_id, $upload_dir);

        // بناء استعلام الإدخال
        $sql = "INSERT INTO beneficiaries (
            full_name, national_id, phone_number, gender, date_of_birth, marital_status, address,
            family_file_number, family_size, children_count, elderly_count, has_breadwinner, relationship_to_head, 
            monthly_income, income_source, employment_status, work_type, living_standard, has_debt, 
            health_general, has_chronic_disease, has_disability, disability_type, needs_continuous_treatment, 
            education_level, current_study_status, student_count, has_learning_difficulties, 
            housing_type, housing_condition, room_count, has_basic_services, eviction_risk, 
            gps_coordinates, map_link, nearest_landmark, 
            status, priority_level,
            id_photo_attachment, official_docs_attachment, medical_report_attachment, income_proof_attachment, other_attachments
        ) VALUES (
            ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
        )";

        $stmt = $pdo->prepare($sql);
        
        $stmt->execute([
            $_POST['full_name'],
            $national_id,
            $_POST['phone_number'] ?: NULL, // اختياري
            $_POST['gender'] ?: NULL, // اختياري
            $date_of_birth, // مطلوب وحقل إجباري
            $_POST['marital_status'] ?: NULL, // اختياري
            $_POST['address'] ?: NULL, // اختياري
            $_POST['family_file_number'] ?: NULL, // اختياري
            (int)($_POST['family_size'] ?: 0), // اختياري، افتراضي 0
            (int)($_POST['children_count'] ?: 0), // اختياري، افتراضي 0
            (int)($_POST['elderly_count'] ?: 0), // اختياري، افتراضي 0
            isset($_POST['has_breadwinner']), // اختياري (checkbox)
            $_POST['relationship_to_head'] ?: NULL, // اختياري
            (float)($_POST['monthly_income'] ?: 0.00), // اختياري، افتراضي 0.00
            $_POST['income_source'] ?: NULL, // اختياري
            $_POST['employment_status'] ?: NULL, // اختياري
            $_POST['work_type'] ?: NULL, // اختياري
            $_POST['living_standard'] ?: NULL, // اختياري
            isset($_POST['has_debt']), // اختياري (checkbox)
            $_POST['health_general'] ?: NULL, // اختياري
            isset($_POST['has_chronic_disease']), // اختياري (checkbox)
            isset($_POST['has_disability']), // اختياري (checkbox)
            $_POST['disability_type'] ?: NULL, // اختياري
            isset($_POST['needs_continuous_treatment']), // اختياري (checkbox)
            $_POST['education_level'] ?: NULL, // اختياري
            $_POST['current_study_status'] ?: NULL, // اختياري
            (int)($_POST['student_count'] ?: 0), // اختياري، افتراضي 0
            isset($_POST['has_learning_difficulties']), // اختياري (checkbox)
            $_POST['housing_type'] ?: NULL, // اختياري
            $_POST['housing_condition'] ?: NULL, // اختياري
            (int)($_POST['room_count'] ?: 0), // اختياري، افتراضي 0
            isset($_POST['has_basic_services']), // اختياري (checkbox)
            isset($_POST['eviction_risk']), // اختياري (checkbox)
            $_POST['gps_coordinates'] ?: NULL, // اختياري
            $_POST['map_link'] ?: NULL, // اختياري
            $_POST['nearest_landmark'] ?: NULL, // اختياري
            $_POST['status'], // له قيمة افتراضية في DB
            $_POST['priority_level'], // له قيمة افتراضية في DB
            $id_photo_path, $official_docs_path, $medical_report_path, $income_proof_path, $other_attachments_path
        ]);

        $success_message = "تمت إضافة المستفيد '" . htmlspecialchars($_POST['full_name']) . "' بنجاح.";
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}
// 5. استدعاء الهيدر
require_once __DIR__ . '/header.php';
?>

<div class="row mb-4 mt-3">
    <div class="col-12 d-flex justify-content-between align-items-center">
        <h3 class="mb-0 text-primary">
            <i class="bi bi-person-plus-fill"></i> إضافة مستفيد جديد | <?php echo htmlspecialchars($committee['name']); ?>
        </h3>
        <a href="committee.php?id=<?php echo $committee_id; ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-right"></i> العودة للوحة التحكم</a>
    </div>
</div>

<?php if ($error_message): ?>
    <div class="alert alert-danger text-center"><?php echo $error_message; ?></div>
<?php endif; ?>
<?php if ($success_message): ?>
    <div class="alert alert-success text-center"><?php echo $success_message; ?></div>
<?php endif; ?>
<?php if ($warning_message): ?>
    <div class="alert alert-warning text-center"><?php echo $warning_message; ?></div>
<?php endif; ?>

<form action="add_beneficiary.php?committee_id=<?php echo $committee_id; ?>" method="POST" enctype="multipart/form-data" class="card shadow-sm border-0">
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
                            <div class="col-md-6"><label class="form-label">الاسم الكامل</label><input type="text" name="full_name" class="form-control" required></div>
                            <div class="col-md-6"><label class="form-label">الرقم الوطني (للتحقق)</label><input type="text" name="national_id" class="form-control" required placeholder="مثال: 1234567890"></div>
                            <div class="col-md-4"><label class="form-label">رقم الهاتف</label><input type="tel" name="phone_number" class="form-control" placeholder="اختياري"></div>
                            <div class="col-md-4"><label class="form-label">الجنس</label><select name="gender" class="form-select"><option value="">اختر (اختياري)</option><option value="ذكر">ذكر</option><option value="أنثى">أنثى</option></select></div>
                            <div class="col-md-4"><label class="form-label">تاريخ الميلاد</label><input type="date" name="date_of_birth" class="form-control" required></div>
                            <div class="col-md-4">
                                <label class="form-label">الحالة الاجتماعية</label>
                                <select name="marital_status" class="form-select">
                                    <option value="">اختر (اختياري)</option>
                                    <option value="أعزب">أعزب</option>
                                    <option value="متزوج">متزوج</option>
                                    <option value="مطلق">مطلق</option>
                                    <option value="أرمل">أرمل</option>
                                </select>
                            </div>
                            <div class="col-12"><label class="form-label">العنوان التفصيلي</label><textarea name="address" class="form-control" placeholder="اختياري"></textarea></div>
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
                            <div class="col-md-4"><label class="form-label">رقم ملف الأسرة</label><input type="text" name="family_file_number" class="form-control" placeholder="اختياري"></div>
                            <div class="col-md-4"><label class="form-label">صلة القرابة برب الأسرة</label><input type="text" name="relationship_to_head" class="form-control" placeholder="اختياري"></div>
                            <div class="col-md-4"><label class="form-label">عدد أفراد الأسرة</label><input type="number" name="family_size" class="form-control" value="0" placeholder="اختياري"></div>
                            <div class="col-md-4"><label class="form-label">عدد الأطفال (تحت 18)</label><input type="number" name="children_count" class="form-control" value="0" placeholder="اختياري"></div>
                            <div class="col-md-4"><label class="form-label">عدد كبار السن (فوق 60)</label><input type="number" name="elderly_count" class="form-control" value="0" placeholder="اختياري"></div>
                            <div class="col-md-4 d-flex align-items-end"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="has_breadwinner" id="has_breadwinner"><label class="form-check-label" for="has_breadwinner">يوجد معيل للأسرة</label></div></div>
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
                            <div class="col-md-4"><label class="form-label">الدخل الشهري (تقريبي بالـ JOD)</label><input type="number" step="0.01" name="monthly_income" class="form-control" value="0" placeholder="اختياري"></div>
                            <div class="col-md-4"><label class="form-label">مصدر الدخل</label><input type="text" name="income_source" class="form-control" placeholder="اختياري"></div>
                            <div class="col-md-4"><label class="form-label">الحالة الوظيفية</label><input type="text" name="employment_status" class="form-control" placeholder="اختياري"></div>
                            <div class="col-md-6"><label class="form-label">نوع العمل</label><input type="text" name="work_type" class="form-control" placeholder="اختياري"></div>
                            <div class="col-md-6"><label class="form-label">مستوى المعيشة</label><input type="text" name="living_standard" class="form-control" placeholder="اختياري"></div>
                            <div class="col-md-12"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="has_debt" id="has_debt"><label class="form-check-label" for="has_debt">يوجد ديون على الأسرة</label></div></div>
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
                            <div class="col-12"><label class="form-label">الحالة الصحية العامة (وصف)</label><input type="text" name="health_general" class="form-control" placeholder="اختياري"></div>
                            <div class="col-md-4"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="has_chronic_disease" id="has_chronic_disease"><label class="form-check-label" for="has_chronic_disease">يوجد أمراض مزمنة</label></div></div>
                            <div class="col-md-4"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="needs_continuous_treatment" id="needs_continuous_treatment"><label class="form-check-label" for="needs_continuous_treatment">بحاجة لعلاج مستمر</label></div></div>
                            <div class="col-md-4"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="has_disability" id="has_disability" onchange="document.getElementById('disability_type_div').style.display = this.checked ? 'block' : 'none'"><label class="form-check-label" for="has_disability">يوجد إعاقة</label></div></div>
                            <div class="col-12" id="disability_type_div" style="display:none;"><label class="form-label">نوع الإعاقة</label><input type="text" name="disability_type" class="form-control" placeholder="اختياري"></div>
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
                            <div class="col-md-4"><label class="form-label">المستوى التعليمي</label><input type="text" name="education_level" class="form-control" placeholder="اختياري"></div>
                            <div class="col-md-4"><label class="form-label">الحالة الدراسية الحالية</label><input type="text" name="current_study_status" class="form-control" placeholder="اختياري"></div>
                            <div class="col-md-4"><label class="form-label">عدد الطلاب في الأسرة</label><input type="number" name="student_count" class="form-control" value="0" placeholder="اختياري"></div>
                            <div class="col-12"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="has_learning_difficulties" id="has_learning_difficulties"><label class="form-check-label" for="has_learning_difficulties">يوجد صعوبات تعليمية</label></div></div>
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
                            <div class="col-md-4"><label class="form-label">نوع السكن</label><input type="text" name="housing_type" class="form-control" placeholder="مثال: ملك، إيجار، مؤقت... (اختياري)"></div>
                            <div class="col-md-4"><label class="form-label">حالة السكن</label><input type="text" name="housing_condition" class="form-control" placeholder="مثال: جيد، متوسط، سيء... (اختياري)"></div>
                            <div class="col-md-4"><label class="form-label">عدد الغرف</label><input type="number" name="room_count" class="form-control" value="0" placeholder="اختياري"></div>
                            <div class="col-md-6"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="has_basic_services" id="has_basic_services"><label class="form-check-label" for="has_basic_services">تتوفر خدمات أساسية (ماء/كهرباء)</label></div></div>
                            <div class="col-md-6"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="eviction_risk" id="eviction_risk"><label class="form-check-label" for="eviction_risk">يوجد احتمالية إخلاء</label></div></div>
                            <div class="col-md-4"><label class="form-label">إحداثيات GPS</label><input type="text" name="gps_coordinates" class="form-control" placeholder="اختياري"></div>
                            <div class="col-md-4"><label class="form-label">رابط الموقع على الخريطة</label><input type="url" name="map_link" class="form-control" placeholder="اختياري"></div>
                            <div class="col-md-4"><label class="form-label">أقرب معلم</label><input type="text" name="nearest_landmark" class="form-control" placeholder="اختياري"></div>
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
                                    <option value="جديد" selected>جديد</option>
                                    <option value="قيد الدراسة">قيد الدراسة</option>
                                    <option value="مقبول">مقبول</option>
                                    <option value="مرفوض">مرفوض</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">مستوى الأولوية</label>
                                <select name="priority_level" class="form-select">
                                    <option value="متوسطة" selected>متوسطة</option>
                                    <option value="عالية">عالية</option>
                                    <option value="منخفضة">منخفضة</option>
                                </select>
                            </div>
                            <hr class="my-4">
                            <div class="col-md-6"><label class="form-label">صورة الهوية</label><input type="file" name="id_photo_attachment" class="form-control" accept=".jpg,.jpeg,.png,.pdf"></div>
                            <div class="col-md-6"><label class="form-label">وثائق رسمية (عقد إيجار..)</label><input type="file" name="official_docs_attachment" class="form-control" accept=".jpg,.jpeg,.png,.pdf,.doc,.docx"></div>
                            <div class="col-md-6"><label class="form-label">تقرير طبي</label><input type="file" name="medical_report_attachment" class="form-control" accept=".jpg,.jpeg,.png,.pdf,.doc,.docx"></div>
                            <div class="col-md-6"><label class="form-label">إثبات دخل / عدم وجوده</label><input type="file" name="income_proof_attachment" class="form-control" accept=".jpg,.jpeg,.png,.pdf,.doc,.docx"></div>
                            <div class="col-12"><label class="form-label">ملفات داعمة أخرى</label><input type="file" name="other_attachments" class="form-control" accept=".jpg,.jpeg,.png,.pdf,.doc,.docx"></div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
    <div class="card-footer text-center py-3">
        <button type="submit" class="btn btn-primary btn-lg shadow"><i class="bi bi-check-circle-fill"></i> حفظ بيانات المستفيد</button>
    </div>
</form>

<?php
// 6. استدعاء الفوتر
require_once __DIR__ . '/footer.php';
?>