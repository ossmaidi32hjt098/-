<?php
/**
 * سكربت هجرة وفصل قواعد البيانات (Migration Script)
 * يقوم بفصل قاعدة البيانات المركزية (shefa_db) إلى قواعد مستقلة لكل لجنة.
 */

$host = '127.0.0.1';
$user = 'root';
$pass = '';
$old_db = 'shefa_db';

try {
    // الاتصال بخادم MySQL العام (بدون تحديد قاعدة بيانات محددة)
    $pdo = new PDO("mysql:host=$host;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    die("فشل الاتصال بقاعدة البيانات: " . $e->getMessage());
}

echo "<h1><i class='bi bi-hdd-network'></i> بدء عملية فصل قواعد البيانات...</h1>";

// إيقاف فحص المفاتيح الأجنبية مؤقتاً لتجنب أخطاء النسخ
$pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");

// 1. خريطة اللجان: يجب التأكد من أن الأرقام (1, 2, 3) تتطابق مع معرفات اللجان في جدول `committees`
// يمكنك تعديل الأرقام هنا لتتطابق مع قاعدة بياناتك الحالية.
$mapping = [
    1 => 'zakat_aleppo_db',
    2 => 'zakat_daraa_db',
    3 => 'zakat_idlib_db'
];

// الجداول التي سيتم فلترتها بناءً على committee_id
$tables_to_filter = [
    'beneficiaries',
    'users',
    'donations_history',
    'incoming_donations',
    'inventory_balances',
    'committee_finances'
];

// الجداول المرجعية (التي تحتوي على بيانات ثابتة وتنسخ بالكامل لكل لجنة)
$lookup_tables = [
    'donation_types'
];

foreach ($mapping as $committee_id => $new_db) {
    echo "<h3>جاري إنشاء قاعدة البيانات: <strong>$new_db</strong> (لجنة رقم $committee_id)</h3>";
    
    // إنشاء قاعدة البيانات المستقلة
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$new_db` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");
    
    // نسخ الجداول المرجعية
    foreach ($lookup_tables as $table) {
        $tableExists = $pdo->query("SHOW TABLES IN `$old_db` LIKE '$table'")->fetch();
        if ($tableExists) {
            $pdo->exec("CREATE TABLE IF NOT EXISTS `$new_db`.`$table` LIKE `$old_db`.`$table`");
            $pdo->exec("TRUNCATE TABLE `$new_db`.`$table`");
            $pdo->exec("INSERT INTO `$new_db`.`$table` SELECT * FROM `$old_db`.`$table`");
            echo "<span style='color: green;'>- تم نسخ الجدول المرجعي: $table</span><br>";
        }
    }

    // نسخ الجداول المفلترة الخاصة باللجنة فقط
    foreach ($tables_to_filter as $table) {
        $tableExists = $pdo->query("SHOW TABLES IN `$old_db` LIKE '$table'")->fetch();
        if ($tableExists) {
            // إنشاء هيكل الجدول
            $pdo->exec("CREATE TABLE IF NOT EXISTS `$new_db`.`$table` LIKE `$old_db`.`$table`");
            $pdo->exec("TRUNCATE TABLE `$new_db`.`$table`");
            
            // استيراد بيانات اللجنة المعنية فقط
            $stmt = $pdo->prepare("INSERT INTO `$new_db`.`$table` SELECT * FROM `$old_db`.`$table` WHERE committee_id = :cid");
            $stmt->execute(['cid' => $committee_id]);
            
            $count = $stmt->rowCount();
            echo "<span style='color: blue;'>- تم استيراد ($count) صف للجدول: $table</span><br>";
            
            // ملاحظة: احتفظنا بعمود committee_id مؤقتاً كي لا ينهار كود PHP الحالي. 
            // بعد تحديث الكود لاحقاً يمكنك حذفه برمجياً عبر: ALTER TABLE DROP COLUMN committee_id
        }
    }
    echo "<hr>";
}

// 2. إنشاء قاعدة بيانات البوابة المركزية (Central Gateway)
$central_db = 'zakat_central_db';
echo "<h3>جاري إنشاء قاعدة البوابة المركزية: <strong>$central_db</strong></h3>";
$pdo->exec("CREATE DATABASE IF NOT EXISTS `$central_db` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");

// إنشاء جدول سجل اللجان (Committees Registry)
$pdo->exec("CREATE TABLE IF NOT EXISTS `$central_db`.`committees_registry` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `committee_name` varchar(255) NOT NULL,
    `api_base_url` varchar(255) DEFAULT NULL,
    `api_auth_token` varchar(255) DEFAULT NULL,
    `status` varchar(50) DEFAULT 'Active',
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// إدراج اللجان الحالية إلى السجل المركزي
$pdo->exec("TRUNCATE TABLE `$central_db`.`committees_registry`");
$pdo->exec("INSERT INTO `$central_db`.`committees_registry` (id, committee_name) SELECT id, name FROM `$old_db`.`committees`");

// إنشاء جدول تدقيق البوابة المركزية (Gateway Audit Logs)
$pdo->exec("CREATE TABLE IF NOT EXISTS `$central_db`.`gateway_audit_logs` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `requesting_committee_id` int(11) NOT NULL,
    `hashed_national_id` varchar(255) NOT NULL,
    `timestamp` datetime DEFAULT CURRENT_TIMESTAMP,
    `duplicate_found` tinyint(1) DEFAULT 0,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

echo "<span style='color: green;'>- تم إعداد قاعدة البوابة المركزية بنجاح.</span><br>";

$pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");
echo "<h2 style='color: green;'>تم فصل قواعد البيانات بنجاح!</h2>";
?>