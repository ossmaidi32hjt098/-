<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/header.php';

echo "<h1><i class='bi bi-gear-wide-connected'></i> بدء عملية تهيئة البيانات المالية...</h1>";

try {
    // 0. إنشاء الجداول الجديدة وتعديل الجداول الحالية تلقائياً
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `committee_finances` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `committee_id` int(11) NOT NULL,
          `balance` decimal(15,2) NOT NULL DEFAULT 0.00,
          PRIMARY KEY (`id`),
          UNIQUE KEY `committee_id` (`committee_id`),
          CONSTRAINT `fk_committee_finances_committee` FOREIGN KEY (`committee_id`) REFERENCES `committees` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
    
    try {
        $pdo->exec("ALTER TABLE `donations_history` ADD COLUMN `amount` DECIMAL(10,2) NULL DEFAULT NULL AFTER `donation_type_id`, ADD COLUMN `notes` TEXT NULL DEFAULT NULL AFTER `amount`");
    } catch (PDOException $e) {
        if ($e->getCode() != '42S21') { // 42S21 يعني أن العمود موجود بالفعل
            echo "<div class='alert alert-warning'>تنبيه: تعذر إضافة الأعمدة لجدول سجل التوزيعات: " . $e->getMessage() . "</div>";
        }
    }
    
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
    $pdo->exec("TRUNCATE TABLE `donation_types`");
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");

    // بدء المعاملة لعمليات الإدخال (DML)
    $pdo->beginTransaction();

    // 1. إنشاء سجلات أرصدة ابتدائية (صفر) لجميع اللجان الحالية
    $stmt = $pdo->query("SELECT id FROM committees");
    $committees = $stmt->fetchAll();
    $insert_balance_stmt = $pdo->prepare("INSERT IGNORE INTO committee_finances (committee_id, balance) VALUES (?, 0.00)");
    foreach ($committees as $committee) {
        $insert_balance_stmt->execute([$committee['id']]);
    }
    echo "<p class='text-success fw-bold'>- تم إنشاء سجلات الأرصدة للجان بنجاح.</p>";

    // 2. إضافة أنواع الدعم والتبرعات الجديدة
    $donation_types = [
        ['نقدي', 'زكاة مال'],
        ['نقدي', 'صدقة'],
        ['نقدي', 'كفالة شهرية'],
        ['نقدي', 'دعم طارئ'],
        ['عيني', 'سلة غذائية'],
        ['عيني', 'كسوة (ملابس)'],
        ['عيني', 'مستلزمات طبية'],
        ['عيني', 'مستلزمات تعليمية (قرطاسية)'],
        ['عيني', 'لحوم أضاحي (موسمي)'],
        ['خدمات', 'استشارة طبية مجانية'],
        ['خدمات', 'جلسة علاج فيزيائي'],
        ['خدمات', 'دعم نفسي وتعليمي'],
    ];

    $insert_type_stmt = $pdo->prepare("INSERT INTO donation_types (category, sub_category) VALUES (?, ?)");
    foreach ($donation_types as $type) {
        $insert_type_stmt->execute($type);
    }
    echo "<p class='text-success fw-bold'>- تم إضافة " . count($donation_types) . " نوع من أنواع الدعم بنجاح.</p>";

    $pdo->commit();

    echo "<div class='alert alert-success mt-4'><h2><i class='bi bi-check2-circle'></i> تمت عملية التهيئة بنجاح!</h2><p>يمكنك الآن حذف هذا الملف (`_populate_data.php`) بأمان.</p></div>";

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "<div class='alert alert-danger'>حدث خطأ: " . $e->getMessage() . "</div>";
}

require_once __DIR__ . '/footer.php';
?>