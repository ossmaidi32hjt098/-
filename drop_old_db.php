<?php
/**
 * سكربت حذف قاعدة البيانات المركزية القديمة
 */

$host = '127.0.0.1';
$user = 'root';
$pass = '';

try {
    // الاتصال بخادم MySQL
    $pdo = new PDO("mysql:host=$host;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
    
    // حذف قاعدة البيانات القديمة
    $pdo->exec("DROP DATABASE IF EXISTS `shefa_db`");
    
    echo "<h1 style='color: green; text-align: center; margin-top: 50px;'><i class='bi bi-trash-fill'></i> تم حذف قاعدة البيانات القديمة (shefa_db) بنجاح!</h1>";
} catch (PDOException $e) {
    die("<div style='color: red; text-align: center; margin-top: 50px;'>خطأ أثناء الحذف: " . $e->getMessage() . "</div>");
}
?>