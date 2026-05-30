<?php
session_start();
require_once __DIR__ . '/db.php';

$beneficiaries = [];

try {
    $central_pdo = new PDO("mysql:host=$host;dbname=zakat_central_db;charset=$charset", $user, $pass, $options);
    $registry = $central_pdo->query("SELECT id, committee_name as name FROM committees_registry ORDER BY id ASC")->fetchAll();
} catch (PDOException $e) {
    $registry = [];
}

foreach ($registry as $com) {
    $cid = $com['id'];
    $cname = $com['name'];
    
    if (isset($_GET['committee_id']) && $_GET['committee_id'] != $cid) continue;
    
    if (isset($db_nodes[$cid])) {
        $node_db = $db_nodes[$cid];
        try {
            $dsn_node = "mysql:host=$host;dbname=$node_db;charset=$charset";
            $node_pdo = new PDO($dsn_node, $user, $pass, $options);
            
            if (isset($_GET['national_id'])) {
                $stmt = $node_pdo->prepare("SELECT * FROM beneficiaries WHERE national_id = ?");
                $stmt->execute([$_GET['national_id']]);
            } else {
                $stmt = $node_pdo->query("SELECT * FROM beneficiaries ORDER BY full_name ASC");
            }
            
            while ($row = $stmt->fetch()) {
                $row['committee_name'] = $cname;
                $beneficiaries[] = $row;
            }
        } catch (PDOException $e) {}
    }
}

if (!$beneficiaries) {
    die("<h3 style='text-align:center; font-family:sans-serif; margin-top:50px;'>لا توجد بيانات للطباعة.</h3>");
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>تقرير المستفيدين</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.rtl.min.css">
    <style>
        body { font-family: 'Tajawal', sans-serif; background-color: #fff; }
        /* فواصل الصفحات لضمان طباعة كل مستفيد في ورقة مستقلة */
        .print-page { page-break-after: always; padding: 20px; }
        .print-page:last-child { page-break-after: auto; }
        .table th { width: 30%; background-color: #f8f9fa !important; }
        @media print {
            @page { margin: 1cm; }
            body { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .no-print { display: none !important; }
        }
    </style>
</head>
<body onload="window.print()">
    <div class="text-center mb-4 no-print mt-3">
        <button onclick="window.print()" class="btn btn-primary shadow"><i class="bi bi-printer"></i> طباعة / حفظ كـ PDF</button>
        <button onclick="window.close()" class="btn btn-secondary shadow">إغلاق الصفحة</button>
    </div>

    <?php foreach ($beneficiaries as $ben): ?>
    <div class="print-page border border-2 border-dark rounded mb-4 p-4">
        <div class="text-center border-bottom border-dark pb-3 mb-4">
            <h2><?php echo htmlspecialchars($ben['committee_name'] ?? 'السجل الموحد'); ?></h2>
            <h4>تقرير بيانات المستفيد الشامل</h4>
        </div>

        <!-- 1. البيانات الشخصية -->
        <h5 class="mt-2 border-bottom border-secondary pb-1"><i class="bi bi-person-badge"></i> 1. البيانات الشخصية</h5>
        <table class="table table-bordered border-dark table-sm align-middle mb-3">
            <tbody>
                <tr><th style="width:20%">الاسم الكامل</th><td style="width:30%"><strong><?php echo htmlspecialchars($ben['full_name']); ?></strong></td><th style="width:20%">الرقم الوطني</th><td style="width:30%"><?php echo htmlspecialchars($ben['national_id']); ?></td></tr>
                <tr><th>رقم الهاتف</th><td><?php echo htmlspecialchars($ben['phone_number'] ?: 'لا يوجد'); ?></td><th>الجنس</th><td><?php echo htmlspecialchars($ben['gender'] ?: 'غير محدد'); ?></td></tr>
                <tr><th>تاريخ الميلاد</th><td><?php echo htmlspecialchars($ben['date_of_birth'] ?: 'غير محدد'); ?></td><th>الحالة الاجتماعية</th><td><?php echo htmlspecialchars($ben['marital_status'] ?: 'غير محدد'); ?></td></tr>
                <tr><th>العنوان التفصيلي</th><td colspan="3"><?php echo htmlspecialchars($ben['address'] ?: 'غير محدد'); ?></td></tr>
            </tbody>
        </table>

        <!-- 2. بيانات الأسرة -->
        <h5 class="mt-2 border-bottom border-secondary pb-1"><i class="bi bi-people"></i> 2. بيانات الأسرة</h5>
        <table class="table table-bordered border-dark table-sm align-middle mb-3">
            <tbody>
                <tr><th style="width:20%">رقم ملف الأسرة</th><td style="width:30%"><?php echo htmlspecialchars($ben['family_file_number'] ?: 'غير محدد'); ?></td><th style="width:20%">صلة القرابة برب الأسرة</th><td style="width:30%"><?php echo htmlspecialchars($ben['relationship_to_head'] ?: 'غير محدد'); ?></td></tr>
                <tr><th>عدد أفراد الأسرة</th><td><?php echo (int)$ben['family_size']; ?></td><th>عدد الأطفال (تحت 18)</th><td><?php echo (int)$ben['children_count']; ?></td></tr>
                <tr><th>عدد كبار السن (فوق 60)</th><td><?php echo (int)$ben['elderly_count']; ?></td><th>يوجد معيل للأسرة</th><td><?php echo $ben['has_breadwinner'] ? 'نعم' : 'لا'; ?></td></tr>
            </tbody>
        </table>

        <!-- 3. البيانات الاقتصادية -->
        <h5 class="mt-2 border-bottom border-secondary pb-1"><i class="bi bi-cash-coin"></i> 3. البيانات الاقتصادية</h5>
        <table class="table table-bordered border-dark table-sm align-middle mb-3">
            <tbody>
                <tr><th style="width:20%">الدخل الشهري</th><td style="width:30%"><?php echo (float)$ben['monthly_income']; ?> JOD</td><th style="width:20%">مصدر الدخل</th><td style="width:30%"><?php echo htmlspecialchars($ben['income_source'] ?: 'غير محدد'); ?></td></tr>
                <tr><th>الحالة الوظيفية</th><td><?php echo htmlspecialchars($ben['employment_status'] ?: 'غير محدد'); ?></td><th>نوع العمل</th><td><?php echo htmlspecialchars($ben['work_type'] ?: 'غير محدد'); ?></td></tr>
                <tr><th>مستوى المعيشة</th><td><?php echo htmlspecialchars($ben['living_standard'] ?: 'غير محدد'); ?></td><th>يوجد ديون على الأسرة</th><td><?php echo $ben['has_debt'] ? 'نعم' : 'لا'; ?></td></tr>
            </tbody>
        </table>

        <!-- 4. الحالة الصحية -->
        <h5 class="mt-2 border-bottom border-secondary pb-1"><i class="bi bi-heart-pulse"></i> 4. الحالة الصحية</h5>
        <table class="table table-bordered border-dark table-sm align-middle mb-3">
            <tbody>
                <tr><th style="width:20%">الحالة الصحية العامة</th><td colspan="3"><?php echo htmlspecialchars($ben['health_general'] ?: 'غير محدد'); ?></td></tr>
                <tr><th>أمراض مزمنة</th><td style="width:30%"><?php echo $ben['has_chronic_disease'] ? 'نعم' : 'لا'; ?></td><th style="width:20%">بحاجة لعلاج مستمر</th><td style="width:30%"><?php echo $ben['needs_continuous_treatment'] ? 'نعم' : 'لا'; ?></td></tr>
                <tr><th>يوجد إعاقة</th><td><?php echo $ben['has_disability'] ? 'نعم' : 'لا'; ?></td><th>نوع الإعاقة</th><td><?php echo htmlspecialchars($ben['disability_type'] ?: 'لا يوجد'); ?></td></tr>
            </tbody>
        </table>

        <!-- 5. الحالة التعليمية -->
        <h5 class="mt-2 border-bottom border-secondary pb-1"><i class="bi bi-book"></i> 5. الحالة التعليمية</h5>
        <table class="table table-bordered border-dark table-sm align-middle mb-3">
            <tbody>
                <tr><th style="width:20%">المستوى التعليمي</th><td style="width:30%"><?php echo htmlspecialchars($ben['education_level'] ?: 'غير محدد'); ?></td><th style="width:20%">الحالة الدراسية الحالية</th><td style="width:30%"><?php echo htmlspecialchars($ben['current_study_status'] ?: 'غير محدد'); ?></td></tr>
                <tr><th>عدد الطلاب في الأسرة</th><td><?php echo (int)$ben['student_count']; ?></td><th>صعوبات تعليمية</th><td><?php echo $ben['has_learning_difficulties'] ? 'نعم' : 'لا'; ?></td></tr>
            </tbody>
        </table>

        <!-- 6. بيانات السكن والموقع -->
        <h5 class="mt-2 border-bottom border-secondary pb-1"><i class="bi bi-house-door"></i> 6. بيانات السكن والموقع</h5>
        <table class="table table-bordered border-dark table-sm align-middle mb-3">
            <tbody>
                <tr><th style="width:20%">نوع السكن</th><td style="width:30%"><?php echo htmlspecialchars($ben['housing_type'] ?: 'غير محدد'); ?></td><th style="width:20%">حالة السكن</th><td style="width:30%"><?php echo htmlspecialchars($ben['housing_condition'] ?: 'غير محدد'); ?></td></tr>
                <tr><th>عدد الغرف</th><td><?php echo (int)$ben['room_count']; ?></td><th>خدمات أساسية (ماء/كهرباء)</th><td><?php echo $ben['has_basic_services'] ? 'متوفرة' : 'غير متوفرة'; ?></td></tr>
                <tr><th>احتمالية إخلاء</th><td><?php echo $ben['eviction_risk'] ? 'نعم' : 'لا'; ?></td><th>أقرب معلم</th><td><?php echo htmlspecialchars($ben['nearest_landmark'] ?: 'غير محدد'); ?></td></tr>
            </tbody>
        </table>

        <!-- 7. حالة المستفيد والمرفقات -->
        <h5 class="mt-2 border-bottom border-secondary pb-1"><i class="bi bi-folder2-open"></i> 7. حالة المستفيد والمرفقات</h5>
        <table class="table table-bordered border-dark table-sm align-middle mb-3">
            <tbody>
                <tr><th style="width:20%">حالة الملف</th><td style="width:30%"><strong><?php echo htmlspecialchars($ben['status']); ?></strong></td><th style="width:20%">مستوى الأولوية</th><td style="width:30%"><strong><?php echo htmlspecialchars($ben['priority_level']); ?></strong></td></tr>
                <tr><th>المرفقات المحفوظة بالنظام</th><td colspan="3">
                    <?php echo !empty($ben['id_photo_attachment']) ? '<span class="badge bg-secondary me-1">هوية</span>' : ''; ?>
                    <?php echo !empty($ben['official_docs_attachment']) ? '<span class="badge bg-secondary me-1">وثائق رسمية</span>' : ''; ?>
                    <?php echo !empty($ben['medical_report_attachment']) ? '<span class="badge bg-secondary me-1">تقرير طبي</span>' : ''; ?>
                    <?php echo !empty($ben['income_proof_attachment']) ? '<span class="badge bg-secondary me-1">إثبات دخل</span>' : ''; ?>
                    <?php echo !empty($ben['other_attachments']) ? '<span class="badge bg-secondary me-1">أخرى</span>' : ''; ?>
                    <?php echo (empty($ben['id_photo_attachment']) && empty($ben['official_docs_attachment']) && empty($ben['medical_report_attachment']) && empty($ben['income_proof_attachment']) && empty($ben['other_attachments'])) ? 'لا توجد مرفقات' : ''; ?>
                </td></tr>
            </tbody>
        </table>

        <div class="mt-4 text-start d-flex justify-content-between">
            <p>تاريخ الطباعة: <?php echo date('Y-m-d H:i'); ?></p>
            <p>توقيع مدير اللجنة: ..........................</p>
        </div>
    </div>
    <?php endforeach; ?>
</body>
</html>