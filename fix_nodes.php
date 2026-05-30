<?php
/**
 * سكربت لحل مشكلة نقص جدول اللجان في قواعد البيانات الموزعة
 * Data Replication Script for Micro-databases
 */

$host = '127.0.0.1';
$user = 'root';
$pass = '';

try {
    $pdo = new PDO("mysql:host=$host;charset=utf8mb4", $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    
    $nodes = ['zakat_aleppo_db', 'zakat_daraa_db', 'zakat_idlib_db'];
    
    echo "<h1><i class='bi bi-tools'></i> جاري إصلاح قواعد البيانات الفرعية...</h1>";
    
    foreach ($nodes as $node) {
        // إنشاء الجدول في قاعدة بيانات اللجنة المعنية
        $pdo->exec("CREATE TABLE IF NOT EXISTS `$node`.`committees` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `name` varchar(255) NOT NULL,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
        
        // استيراد بيانات اللجان من القاعدة المركزية الجديدة لتكون مرجعاً محلياً
        $pdo->exec("TRUNCATE TABLE `$node`.`committees`");
        $pdo->exec("INSERT INTO `$node`.`committees` (id, name) SELECT id, committee_name FROM `zakat_central_db`.`committees_registry`");
        
        echo "<h3 style='color:green;'>- تم إنشاء ونسخ جدول committees في القاعدة <strong>$node</strong> بنجاح.</h3>";
    }
    
    echo "<h2>تم الإصلاح! يمكنك الآن تحديث صفحة اللجنة (committee.php) وستعمل بكفاءة.</h2>";
} catch (PDOException $e) {
    die("خطأ: " . $e->getMessage());
}
?>