<?php
// إعدادات خادم قاعدة البيانات
$host = '127.0.0.1';
$user = 'root';     // اسم مستخدم قاعدة البيانات
$pass = '';         // كلمة المرور
$charset = 'utf8mb4';

// 1. خريطة توجيه قواعد البيانات (Database Routing Map)
// نربط كل معرّف لجنة بقاعدة البيانات المستقلة الخاصة بها (Node)
$db_nodes = [
    1 => 'zakat_aleppo_db',
    2 => 'zakat_daraa_db',
    3 => 'zakat_idlib_db'
];

// قاعدة البيانات الافتراضية (البوابة المركزية) للإدارة أو عند عدم التحديد
$db = 'zakat_central_db'; 

// 2. التوجيه الديناميكي (Dynamic Routing) لمعرفة أي قاعدة نتصل بها
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$target_committee_id = null;

if (isset($_SESSION['logged_in_committee'])) {
    $target_committee_id = (int)$_SESSION['logged_in_committee'];
} elseif (isset($_GET['id'])) {
    $target_committee_id = (int)$_GET['id'];
} elseif (isset($_GET['committee_id'])) {
    $target_committee_id = (int)$_GET['committee_id'];
}

// تبديل الاتصال إلى قاعدة بيانات اللجنة المعنية إذا تم التعرف عليها
if ($target_committee_id && isset($db_nodes[$target_committee_id])) {
    $db = $db_nodes[$target_committee_id];
}

// 3. إنشاء الاتصال (PDO Connection)
$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    die("خطأ في الاتصال بقاعدة البيانات ($db): " . $e->getMessage());
}

// دالة لجلب عنوان IP الحقيقي للمستخدم
if (!function_exists('getUserIP')) {
    function getUserIP() {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        }
        
        // إذا كان هناك أكثر من IP، نأخذ الأول (عنوان العميل الحقيقي)
        if (strpos($ip, ',') !== false) {
            $ip = explode(',', $ip)[0];
        }
        
        // تنظيف العنوان
        return trim($ip);
    }
}

// إضافة تلقائية للجنة (حمص)
$db_nodes[4] = 'zakat_node_4_db';
?>