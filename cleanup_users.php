<?php
session_start();
require_once __DIR__ . '/db.php';

// حماية الصفحة: للمدير فقط
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("غير مصرح بالدخول");
}

echo "<div dir='rtl' style='font-family: Arial; padding: 20px;'>";
echo "<h2 style='color: #0d9488;'>بدء تنظيف حسابات اللجان...</h2>";

try {
    $central_pdo = new PDO("mysql:host=$host;dbname=zakat_central_db;charset=$charset", $user, $pass, $options);
    $registry = $central_pdo->query("SELECT id, committee_name FROM committees_registry ORDER BY id ASC")->fetchAll();

    foreach ($registry as $com) {
        $cid = $com['id'];
        if (isset($db_nodes[$cid])) {
            $node_db = $db_nodes[$cid];
            try {
                $node_pdo = new PDO("mysql:host=$host;dbname=$node_db;charset=$charset", $user, $pass, $options);
                
                // حذف المستخدمين الذين لا ينتهي اسم الدخول الخاص بهم بـ _user، بالإضافة إلى بعض الحسابات المحددة
                $stmt = $node_pdo->prepare("DELETE FROM users WHERE username NOT LIKE '%\\_user' OR username IN ('لجنة_درعا_user', 'لجنة_إدلب_user')");
                $stmt->execute();
                $deleted_count = $stmt->rowCount();
                
                if ($deleted_count > 0) {
                    echo "<p style='color: green;'>✔ لجنة <strong>{$com['committee_name']}</strong>: تم حذف ($deleted_count) حسابات غير قياسية.</p>";
                } else {
                    echo "<p style='color: gray;'>- لجنة <strong>{$com['committee_name']}</strong>: نظيفة (لا يوجد حسابات غير قياسية لحذفها).</p>";
                }
            } catch (PDOException $e) {
                echo "<p style='color: red;'>خطأ في لجنة {$com['committee_name']}: " . $e->getMessage() . "</p>";
            }
        }
    }
    echo "<h3 style='color: green; margin-top: 20px;'>تمت عملية التنظيف بنجاح! تم الإبقاء فقط على المستخدمين القياسيين (مع حذف حسابات محددة).</h3>";
    echo "<a href='create_user.php' style='display:inline-block; margin-top:10px; padding:10px 20px; background:#0d9488; color:#fff; text-decoration:none; border-radius:5px;'>العودة لإدارة المستخدمين</a>";
} catch (PDOException $e) {
    echo "<p style='color: red;'>حدث خطأ مركزي: " . $e->getMessage() . "</p>";
}
echo "</div>";
?>