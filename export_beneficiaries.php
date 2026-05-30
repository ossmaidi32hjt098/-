<?php
session_start();
require_once __DIR__ . '/db.php';

$logged_in_committee = isset($_SESSION['logged_in_committee']) ? $_SESSION['logged_in_committee'] : null;
$is_admin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;

// التحقق من الصلاحيات
if (!$logged_in_committee && !$is_admin) {
    header("Location: admin_login.php");
    exit;
}

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
    
    if ($logged_in_committee && $logged_in_committee != $cid) continue;
    
    if (isset($db_nodes[$cid])) {
        $node_db = $db_nodes[$cid];
        try {
            $dsn_node = "mysql:host=$host;dbname=$node_db;charset=$charset";
            $node_pdo = new PDO($dsn_node, $user, $pass, $options);
            
            $bens = $node_pdo->query("SELECT * FROM beneficiaries ORDER BY full_name ASC")->fetchAll(PDO::FETCH_ASSOC);
            foreach ($bens as $ben) {
                $ben['committee_name'] = $cname;
                $beneficiaries[] = $ben;
            }
        } catch (PDOException $e) {}
    }
}

// إعداد الترويسات (Headers) لإجبار المتصفح على تحميل الملف كملف Excel بصيغة HTML (يدعم اللغة العربية بامتياز)
header("Content-Type: application/vnd.ms-excel; charset=utf-8");
header("Content-Disposition: attachment; filename=beneficiaries_export_" . date('Y-m-d') . ".xls");
header("Pragma: no-cache");
header("Expires: 0");

echo '<html xmlns:x="urn:schemas-microsoft-com:office:excel">';
echo '<head><meta charset="utf-8"></head><body dir="rtl">';
echo '<table border="1">';
echo '<tr style="background-color: #d1e7dd; font-weight: bold;">';
echo '<th>الرقم الوطني</th>';
echo '<th>الاسم الكامل</th>';
echo '<th>رقم الهاتف</th>';
echo '<th>الجنس</th>';
echo '<th>الحالة الاجتماعية</th>';
echo '<th>عدد أفراد الأسرة</th>';
echo '<th>الدخل الشهري</th>';
echo '<th>الحالة الوظيفية</th>';
echo '<th>نوع السكن</th>';
echo '<th>حالة الملف</th>';
echo '<th>مستوى الأولوية</th>';
if ($is_admin) {
    echo '<th>اللجنة/الجمعية</th>';
}
echo '</tr>';

foreach ($beneficiaries as $row) {
    echo '<tr>';
    // استخدام تنسيق mso-number-format لمنع Excel من تحويل الأرقام الطويلة أو التي تبدأ بصفر لمعادلات علمية
    echo '<td style="mso-number-format:\'@\';">' . htmlspecialchars($row['national_id'] ?? '') . '</td>';
    echo '<td>' . htmlspecialchars($row['full_name'] ?? '') . '</td>';
    echo '<td style="mso-number-format:\'@\';">' . htmlspecialchars($row['phone_number'] ?? '') . '</td>';
    echo '<td>' . htmlspecialchars($row['gender'] ?? '') . '</td>';
    echo '<td>' . htmlspecialchars($row['marital_status'] ?? '') . '</td>';
    echo '<td>' . htmlspecialchars($row['family_size'] ?? '0') . '</td>';
    echo '<td>' . htmlspecialchars($row['monthly_income'] ?? '0') . '</td>';
    echo '<td>' . htmlspecialchars($row['employment_status'] ?? '') . '</td>';
    echo '<td>' . htmlspecialchars($row['housing_type'] ?? '') . '</td>';
    echo '<td>' . htmlspecialchars($row['status'] ?? '') . '</td>';
    echo '<td>' . htmlspecialchars($row['priority_level'] ?? '') . '</td>';
    if ($is_admin) {
        echo '<td>' . htmlspecialchars($row['committee_name'] ?? 'غير محدد') . '</td>';
    }
    echo '</tr>';
}
echo '</table></body></html>';
exit;