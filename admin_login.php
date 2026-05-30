<?php
session_start();
require_once __DIR__ . '/db.php';

// إذا كان مسجلاً للدخول كمدير مسبقاً، وجهه للوحة التحكم
if (isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true) {
    header("Location: index.php");
    exit;
}

// 1. إنشاء الجدول في حال لم يكن موجوداً من الأساس
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS `users` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `full_name` varchar(255) NOT NULL,
        `username` varchar(255) NOT NULL,
        `password_hash` varchar(255) NOT NULL,
        `committee_id` int(11) NOT NULL DEFAULT 0,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
} catch (PDOException $e) {}

// 2. إضافة عمود الصلاحيات بطريقة تدعم جميع إصدارات MySQL
try {
    $pdo->exec("ALTER TABLE `users` ADD COLUMN `is_admin` TINYINT(1) DEFAULT 0");
} catch (PDOException $e) {
    // تجاهل الخطأ إذا كان العمود موجوداً مسبقاً
}

// التأكد من وجود عمود آخر تسجيل دخول
try {
    $pdo->exec("ALTER TABLE `users` ADD COLUMN `last_login` DATETIME NULL DEFAULT CURRENT_TIMESTAMP");
} catch (PDOException $e) {}

// 3. تهيئة حساب الإدارة الافتراضي
try {
    // التأكد من وجود حساب 'admin' الافتراضي وإعطائه الصلاحيات
    $stmt = $pdo->query("SELECT id, is_admin FROM users WHERE username = 'admin'");
    $admin_check = $stmt->fetch();
    if (!$admin_check) {
        $pdo->exec("INSERT INTO users (full_name, username, password_hash, committee_id, is_admin) VALUES ('مدير النظام', 'admin', 'admin123', 0, 1)");
    } elseif ($admin_check['is_admin'] == 0) {
        // تحديث الحساب لضمان امتلاكه صلاحية الإدارة فقط إذا كان لا يملكها (بدون تصفير كلمة المرور)
        $pdo->exec("UPDATE users SET is_admin = 1 WHERE username = 'admin'");
    }
} catch (PDOException $e) {
    error_log("Error creating admin account: " . $e->getMessage());
}

$error_message = '';

// ميزة سريعة لإعادة تعيين كلمة المرور (للطوارئ)
if (isset($_GET['reset_admin'])) {
    $pdo->exec("UPDATE users SET password_hash = 'admin123', is_admin = 1 WHERE username = 'admin'");
    $error_message = 'تمت إعادة تعيين كلمة المرور إلى admin123 بنجاح. يمكنك تسجيل الدخول الآن.';
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']); // إضافة trim لمنع أخطاء المسافات عند النسخ واللصق

    try {
        // التحقق من بيانات المدير من قاعدة البيانات
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = :uname AND is_admin = 1");
        $stmt->execute(['uname' => $username]);
        $admin = $stmt->fetch();
    } catch (PDOException $e) {
        $admin = false; // في حال وجود مشكلة في الجداول
    }

    // التحقق من كلمة المرور (يدعم قاعدة البيانات أو الدخول الافتراضي المباشر للطوارئ)
    if (($admin && ($password === $admin['password_hash'] || password_verify($password, $admin['password_hash']))) || ($username === 'admin' && $password === 'admin123')) {
        $_SESSION['is_admin'] = true;
        
        // استخدام الـ ID من قاعدة البيانات إن وجد، أو قيمة افتراضية لدخول الطوارئ
        $_SESSION['user_id'] = $admin ? $admin['id'] : 1;
        $_SESSION['user_name'] = $admin ? $admin['full_name'] : 'مدير النظام';
        
        // تنظيف جلسات اللجان إن وجدت لمنع التداخل
        unset($_SESSION['logged_in_committee']); 
        
        // تحديث وقت آخر تسجيل دخول
        try {
            $pdo->prepare("UPDATE users SET last_login = CURRENT_TIMESTAMP WHERE id = ?")->execute([$_SESSION['user_id']]);
        } catch (PDOException $e) {}

        header("Location: index.php");
        exit;
    } else {
        $error_message = 'بيانات دخول الإدارة غير صحيحة!';
    }
}

// جلب اللجان لعرضها للموظفين لكي يتوجهوا لصفحات دخولهم
try {
    $committees = $pdo->query("SELECT id, committee_name as name FROM committees_registry ORDER BY id ASC")->fetchAll();
} catch (PDOException $e) {
    $committees = [];
}

require_once __DIR__ . '/header.php'; 
?>

<style>
    body { background: linear-gradient(135deg, #115e59 0%, #0d9488 100%); }
    .navbar-custom { display: none; }
</style>

<div class="row justify-content-center mt-5 align-items-center">
    <div class="col-md-5 mt-4">
        <div class="text-center mb-4 text-white">
            <i class="bi bi-shield-lock-fill display-2 mb-3 shadow-sm rounded-circle p-3 bg-white text-primary"></i>
            <h2 class="fw-bold">بوابة الإدارة العليا</h2>
            <p>منصة زكاة للعمل الخيري</p>
        </div>
        <div class="card shadow-lg border-0" style="border-radius: 20px; overflow: hidden;">
            <div class="card-body p-4 p-md-5">
                <?php if ($error_message): ?>
                    <div class="alert alert-danger text-center shadow-sm"><i class="bi bi-exclamation-triangle-fill"></i> <?php echo $error_message; ?></div>
                <?php endif; ?>

                <form method="POST">
                    <div class="mb-4">
                        <label class="form-label fw-bold text-muted">اسم المستخدم للإدارة</label>
                        <input type="text" name="username" class="form-control form-control-lg bg-light" required placeholder="أدخل اسم المستخدم">
                    </div>
                    <div class="mb-5">
                        <label class="form-label fw-bold text-muted">كلمة المرور</label>
                        <div class="input-group">
                            <input type="password" name="password" id="password" class="form-control form-control-lg bg-light border-end-0" required placeholder="أدخل كلمة المرور">
                            <button class="btn btn-light border border-start-0 text-muted bg-light" type="button" id="togglePassword">
                                <i class="bi bi-eye-slash" id="toggleIcon"></i>
                            </button>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-dark w-100 btn-lg shadow-sm py-3" style="border-radius: 12px;">تسجيل الدخول للنظام <i class="bi bi-box-arrow-in-left"></i></button>
                </form>
            </div>
            <div class="card-footer bg-light p-4 text-center border-0">
                <h6 class="text-muted mb-3">هل أنت موظف في لجنة توزيع؟</h6>
                <a href="select_committee.php" class="btn btn-outline-primary w-100 rounded-pill py-2 text-decoration-none">اختر لجنتك لتسجيل الدخول <i class="bi bi-arrow-left ms-1"></i></a>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('togglePassword')?.addEventListener('click', function () {
    const passwordInput = document.getElementById('password');
    const toggleIcon = document.getElementById('toggleIcon');
    
    if (passwordInput.type === 'password') {
        passwordInput.type = 'text';
        toggleIcon.classList.remove('bi-eye-slash');
        toggleIcon.classList.add('bi-eye');
    } else {
        passwordInput.type = 'password';
        toggleIcon.classList.remove('bi-eye');
        toggleIcon.classList.add('bi-eye-slash');
    }
});
</script>