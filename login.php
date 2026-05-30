<?php
session_start();
require_once __DIR__ . '/db.php'; // استدعاء الاتصال بقاعدة البيانات

// 1. استقبال معرف اللجنة من الرابط
$committee_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$error_message = '';

// 2. جلب اسم اللجنة لعرضه في واجهة تسجيل الدخول
try {
    $central_pdo = new PDO("mysql:host=$host;dbname=zakat_central_db;charset=$charset", $user, $pass, $options);
    $stmt = $central_pdo->prepare("SELECT committee_name as name FROM committees_registry WHERE id = :id");
    $stmt->execute(['id' => $committee_id]);
    $committee = $stmt->fetch();
} catch (PDOException $e) { $committee = false; }

// إذا كان رقم اللجنة غير صحيح، نعيده للرئيسية
if (!$committee) {
    header("Location: index.php");
    exit;
}

// 3. معالجة بيانات الدخول عند إرسال النموذج
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    // تقييد تسجيل الدخول للجان فقط للمستخدمين الذين ينتهي اسمهم بـ _user
    if (substr($username, -5) !== '_user') {
        $error_message = 'عذراً، غير مسموح بالدخول إلا للحسابات الرسمية للجان (التي تنتهي بـ _user).';
    } else {
        // البحث عن المستخدم في قاعدة البيانات والتأكد أنه يتبع لنفس اللجنة المحددة
        $userStmt = $pdo->prepare("SELECT * FROM users WHERE username = :username");
        $userStmt->execute([
            'username' => $username
        ]);
        $user = $userStmt->fetch();

        // التحقق من وجود المستخدم ومطابقة كلمة المرور (نص عادي أو مشفر)
        if ($user && ($password === $user['password_hash'] || password_verify($password, $user['password_hash']))) {
            // إنشاء جلسات (Sessions) آمنة للمستخدم
            $_SESSION['logged_in_committee'] = $committee_id;
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['full_name'];
            
            // التأكد من وجود عمود آخر تسجيل دخول
            try {
                $pdo->exec("ALTER TABLE `users` ADD COLUMN `last_login` DATETIME NULL DEFAULT CURRENT_TIMESTAMP");
            } catch (PDOException $e) {}
            // تحديث وقت آخر تسجيل دخول
            try {
                $pdo->prepare("UPDATE users SET last_login = CURRENT_TIMESTAMP WHERE id = ?")->execute([$user['id']]);
            } catch (PDOException $e) {}

            // توجيهه إلى صفحة اللجنة الخاصة به
            header("Location: committee.php?id=" . $committee_id);
            exit;
        } else {
            $error_message = 'اسم المستخدم أو كلمة المرور غير صحيحة، أو أنك لا تملك صلاحية الدخول لهذه اللجنة.';
        }
    }
}

// استدعاء التصميم (الهيدر)
require_once __DIR__ . '/header.php'; 
?>

<style>
    body { background: linear-gradient(135deg, #0f766e 0%, #0d9488 100%); }
    .navbar-custom { display: none; } /* إخفاء الهيدر في شاشة الدخول للتركيز */
</style>

<div class="row justify-content-center mt-5">
    <div class="col-md-5 mt-5">
        <div class="text-center mb-4 text-white">
            <i class="bi bi-heart-pulse-fill display-2 mb-3 shadow-sm rounded-circle p-3 bg-white text-primary"></i>
            <h2 class="fw-bold">منصة زكاة للعمل الخيري</h2>
        </div>
        <div class="card shadow-lg border-0" style="border-radius: 20px; overflow: hidden;">
            <div class="card-header bg-white text-center py-4 border-0">
                <h4 class="mb-0">
                    تسجيل الدخول الموحد
                </h4>
                <p class="mt-2 mb-0 text-primary fw-bold fs-5"><i class="bi bi-building me-1"></i> <?php echo htmlspecialchars($committee['name']); ?></p>
            </div>
            
            <div class="card-body p-4 p-md-5 pt-0">
                <?php if ($error_message): ?>
                    <div class="alert alert-danger text-center shadow-sm">
                        <i class="bi bi-exclamation-triangle-fill"></i> <?php echo $error_message; ?>
                    </div>
                <?php endif; ?>

                <form action="login.php?id=<?php echo $committee_id; ?>" method="POST">
                    <div class="mb-4">
                        <label for="username" class="form-label fw-bold text-muted">اسم المستخدم</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light text-primary border-end-0"><i class="bi bi-person-fill"></i></span>
                            <input type="text" name="username" id="username" class="form-control form-control-lg border-start-0 bg-light" required placeholder="أدخل اسم المستخدم">
                        </div>
                    </div>
                    
                    <div class="mb-5">
                        <label for="password" class="form-label fw-bold text-muted">كلمة المرور</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light text-primary border-end-0"><i class="bi bi-shield-lock-fill"></i></span>
                            <input type="password" name="password" id="password" class="form-control form-control-lg border-start-0 border-end-0 bg-light" required placeholder="أدخل كلمة المرور">
                            <button class="btn btn-light border border-start-0 text-primary bg-light" type="button" id="togglePassword">
                                <i class="bi bi-eye-slash" id="toggleIcon"></i>
                            </button>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary w-100 btn-lg shadow-sm py-3" style="border-radius: 12px;">
                        تسجيل الدخول <i class="bi bi-box-arrow-in-left"></i>
                    </button>
                </form>
            </div>
        </div>
        
        <div class="text-center mt-3">
            <a href="index.php" class="text-decoration-none text-white opacity-75 hover-opacity-100"><i class="bi bi-arrow-right"></i> العودة للرئيسية</a>
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

<?php 
// استدعاء التصميم (الفوتر)
require_once __DIR__ . '/footer.php'; 
?>