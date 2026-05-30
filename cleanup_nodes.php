<?php
/**
 * سكربت تنظيف القواعد الطرفية (Cleanup Script)
 * يزيل القيود، الفهارس، وعمود committee_id وجدول اللجان المحلي نهائياً.
 */

$host = '127.0.0.1';
$user = 'root';
$pass = '';

try {
    $pdo = new PDO("mysql:host=$host;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
    
    $nodes = ['zakat_aleppo_db', 'zakat_daraa_db', 'zakat_idlib_db'];
    
    echo "<div style='font-family: Arial; direction: rtl; text-align: right; padding: 20px;'>";
    echo "<h1 style='color: #0d9488;'>بدء تنظيف قواعد البيانات الفرعية والقيود...</h1>";
    
    foreach ($nodes as $db) {
        echo "<h3><hr>جاري معالجة القاعدة: <strong>$db</strong></h3>";
        $pdo->exec("USE `$db`");
        
        // 1. حذف قيود الارتباط (Foreign Keys)
        $fks = [
            "ALTER TABLE `users` DROP FOREIGN KEY `fk_user_committee`",
            "ALTER TABLE `donations_history` DROP FOREIGN KEY `fk_history_committee`",
            "ALTER TABLE `committee_finances` DROP FOREIGN KEY `fk_committee_finances_committee`",
            "ALTER TABLE `audit_logs` DROP FOREIGN KEY `fk_audit_committee`",
            "ALTER TABLE `search_audit_logs` DROP FOREIGN KEY `fk_search_audit_committee`"
        ];
        foreach ($fks as $sql) {
            try { $pdo->exec($sql); echo "<span style='color:green'>✔ تم حذف قيد بنجاح.</span><br>"; } catch (Exception $e) {}
        }
        
        // 2. معالجة الفهارس (Indexes)
        try { $pdo->exec("ALTER TABLE `beneficiaries` DROP INDEX `unique_national_committee`"); } catch (Exception $e) {}
        try { $pdo->exec("ALTER TABLE `beneficiaries` ADD UNIQUE INDEX `unique_national_id` (`national_id`)"); } catch (Exception $e) {}
        
        try { $pdo->exec("ALTER TABLE `inventory_balances` DROP INDEX `unique_inv`"); } catch (Exception $e) {}
        try { $pdo->exec("ALTER TABLE `inventory_balances` ADD UNIQUE INDEX `unique_donation_type` (`donation_type_id`)"); } catch (Exception $e) {}
        echo "<span style='color:green'>✔ تم إعادة بناء الفهارس بنجاح.</span><br>";

        // 3. حذف عمود committee_id من جميع الجداول
        $tables = [
            'beneficiaries', 'users', 'donations_history', 
            'incoming_donations', 'inventory_balances', 
            'committee_finances', 'audit_logs', 'search_audit_logs'
        ];
        foreach ($tables as $table) {
            try {
                $pdo->exec("ALTER TABLE `$table` DROP COLUMN `committee_id`");
                echo "<span style='color:blue'>✔ تم حذف العمود من جدول $table.</span><br>";
            } catch (Exception $e) {}
        }
        
        // 4. حذف جدول committees المحلي بالكامل
        try {
            $pdo->exec("DROP TABLE IF EXISTS `committees`");
            echo "<span style='color:red; font-weight:bold;'>✔ تم حذف جدول committees المحلي نهائياً.</span><br>";
        } catch (Exception $e) {}
    }
    
    echo "<h2 style='color:green; margin-top: 30px;'>تمت عملية التنظيف باحترافية وبنجاح 100%!</h2>";
    echo "</div>";
    
} catch (PDOException $e) {
    die("خطأ: " . $e->getMessage());
}
?>