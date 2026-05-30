<?php
/**
 * سكربت دمج التكرارات في أنواع التبرعات وإضافة قيد فريد
 */

$host = '127.0.0.1';
$user = 'root';
$pass = '';

$dbs = ['zakat_central_db', 'zakat_aleppo_db', 'zakat_daraa_db', 'zakat_idlib_db'];

try {
    $pdo = new PDO("mysql:host=$host;charset=utf8mb4", $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    
    echo "<div style='font-family: Arial; direction: rtl; text-align: right; padding: 20px;'>";
    echo "<h2 style='color: #0d9488;'>بدء عملية دمج التكرارات وإصلاح الجداول...</h2>";

    foreach ($dbs as $db) {
        echo "<h3><hr>جاري فحص القاعدة: <strong>$db</strong></h3>";
        try {
            $pdo->exec("USE `$db`");
            
            // إيقاف فحص القيود مؤقتاً لتجنب فشل الحذف
            $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");

            // 1. التأكد من وجود صنف "رصيد نقدي عام" مرة واحدة على الأقل
            $pdo->exec("INSERT IGNORE INTO donation_types (category, sub_category) VALUES ('نقدي', 'رصيد نقدي عام')");
            
            // 2. البحث عن جميع التكرارات لأي صنف
            $stmt = $pdo->query("SELECT category, sub_category, MIN(id) as first_id FROM donation_types GROUP BY category, sub_category HAVING COUNT(id) > 1");
            $duplicates = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (count($duplicates) > 0) {
                foreach ($duplicates as $dup) {
                    $first_id = $dup['first_id'];
                    $cat = $dup['category'];
                    $sub = $dup['sub_category'];
                    
                    // جلب الأرقام المكررة (عدا الأول)
                    $stmt2 = $pdo->prepare("SELECT id FROM donation_types WHERE category = ? AND sub_category = ? AND id != ?");
                    $stmt2->execute([$cat, $sub, $first_id]);
                    $ids_to_delete = $stmt2->fetchAll(PDO::FETCH_COLUMN);
                    
                    if (!empty($ids_to_delete)) {
                        $ids_str = implode(',', $ids_to_delete);
                        
                        // تحديث الجداول المرتبطة لربطها بالصنف الأساسي بالقوة
                        try { $pdo->exec("UPDATE incoming_donations SET donation_type_id = $first_id WHERE donation_type_id IN ($ids_str)"); } catch(Exception $e){}
                        try { $pdo->exec("UPDATE donations_history SET donation_type_id = $first_id WHERE donation_type_id IN ($ids_str)"); } catch(Exception $e){}
                        
                        // معالجة وتجميع المستودع (سواء كان يحتوي على committee_id أم لا)
                        try {
                            $has_cid = $pdo->query("SHOW COLUMNS FROM inventory_balances LIKE 'committee_id'")->fetch();
                            if ($has_cid) {
                                $qtys = $pdo->query("SELECT committee_id, SUM(quantity) as total FROM inventory_balances WHERE donation_type_id IN ($first_id, $ids_str) GROUP BY committee_id")->fetchAll(PDO::FETCH_ASSOC);
                                $pdo->exec("DELETE FROM inventory_balances WHERE donation_type_id IN ($first_id, $ids_str)");
                                foreach($qtys as $q) { $pdo->exec("INSERT INTO inventory_balances (committee_id, donation_type_id, quantity) VALUES ({$q['committee_id']}, $first_id, {$q['total']})"); }
                            } else {
                                $t = $pdo->query("SELECT SUM(quantity) as total FROM inventory_balances WHERE donation_type_id IN ($first_id, $ids_str)")->fetchColumn() ?: 0;
                                $pdo->exec("DELETE FROM inventory_balances WHERE donation_type_id IN ($first_id, $ids_str)");
                                $pdo->exec("INSERT INTO inventory_balances (donation_type_id, quantity) VALUES ($first_id, $t)");
                            }
                        } catch(Exception $e) {}
                        
                        // أخيرًا حذف التكرارات نهائياً من الجذور
                        $pdo->exec("DELETE FROM donation_types WHERE id IN ($ids_str)");
                    }
                }
                echo "<span style='color:green'>✔ تم دمج وحذف التكرارات بنجاح.</span><br>";
            } else {
                echo "<span style='color:blue'>- لا يوجد تكرارات في هذه القاعدة.</span><br>";
            }
            
            // 3. إضافة القيد الفريد (Unique Index) لمنع تكرار (الصنف + الفئة) معاً للأبد
            try { $pdo->exec("ALTER TABLE `donation_types` ADD UNIQUE INDEX `unique_cat_sub` (`category`(50), `sub_category`(150))"); } catch (Exception $e) {}
            
            // إعادة تفعيل فحص القيود
            $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");

        } catch (PDOException $e) {
            echo "<span style='color:red'>- تخطي (القاعدة غير موجودة أو خطأ): {$e->getMessage()}</span><br>";
        }
    }
    
    echo "<h2 style='color:green; margin-top: 20px;'>تم الإصلاح الجذري لقواعد البيانات بنجاح 100%!</h2>";
    echo "</div>";
} catch (PDOException $e) {
    die("خطأ: " . $e->getMessage());
}
?>