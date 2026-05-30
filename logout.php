<?php
session_start();

// تدمير جميع بيانات الجلسة (تسجيل الخروج)
session_destroy();

// إعادته إلى الصفحة الرئيسية
header("Location: index.php");
exit;
?>