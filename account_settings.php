<?php
session_start();
require_once __DIR__ . '/db.php';

$is_admin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;
$is_committee = isset($_SESSION['logged_in_committee']);

if (!$is_admin && !$is_committee) {
    header("Location: admin_login.php");
    exit;
}

// حماية في حال كان المدير مسجلاً دخوله بالجلسة القديمة قبل التحديث
if ($is_admin && !isset($_SESSION['user_id'])) {
    header("Location: logout.php");
    exit;
}

// بناء جدول الإعدادات ديناميكياً لتخزين تفضيلات المستخدمين
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS `user_settings` (
        `user_id` int(11) NOT NULL,
        `email_notif` tinyint(1) DEFAULT 1,
        `system_notif` tinyint(1) DEFAULT 1,
        `language` varchar(10) DEFAULT 'ar',
        `date_format` varchar(20) DEFAULT 'gregorian',
        `two_factor_auth` tinyint(1) DEFAULT 0,
        PRIMARY KEY (`user_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
} catch (PDOException $e) {}

$message = '';
$user_id = $_SESSION['user_id'];

// جلب الإعدادات
$stmt = $pdo->prepare("SELECT * FROM user_settings WHERE user_id = ?");
$stmt->execute([$user_id]);
$settings = $stmt->fetch() ?: ['email_notif'=>1, 'system_notif'=>1, 'language'=>'ar', 'date_format'=>'gregorian', 'two_factor_auth'=>0];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $language = $_POST['language'];
    $date_format = $_POST['date_format'];

    $saveStmt = $pdo->prepare("INSERT INTO user_settings (user_id, language, date_format) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE language=?, date_format=?");
    $saveStmt->execute([$user_id, $language, $date_format, $language, $date_format]);
    
    $settings['language'] = $language; $settings['date_format'] = $date_format;
    $message = ($language === 'en') ? "Account settings saved successfully." : "تم حفظ إعدادات الحساب بنجاح.";
}

require_once __DIR__ . '/header.php';
?>

<div class="row justify-content-center mt-5 mb-5">
    <div class="col-md-8">
        <div class="card shadow-lg border-0" style="border-radius: 20px;">
            <div class="card-header bg-white border-0 pt-4 pb-0">
                <h3 class="fw-bold text-primary"><i class="bi bi-sliders me-2"></i> <?php echo t('account_settings_title'); ?></h3>
            </div>
            <div class="card-body p-4 p-md-5">
                <?php if ($message): ?>
                    <div class="alert alert-success text-center shadow-sm"><i class="bi bi-check-circle-fill"></i> <?php echo $message; ?></div>
                <?php endif; ?>
                
                <form method="POST">
                    <h5 class="text-secondary border-bottom pb-2 mb-3"><i class="bi bi-display"></i> <?php echo t('display_prefs'); ?></h5>
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label text-muted fw-bold"><?php echo t('system_lang'); ?></label>
                            <select name="language" class="form-select form-select-lg bg-light"><option value="ar" <?php echo $settings['language']=='ar'?'selected':''; ?>><?php echo t('lang_ar'); ?></option><option value="en" <?php echo $settings['language']=='en'?'selected':''; ?>><?php echo t('lang_en'); ?></option></select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted fw-bold"><?php echo t('date_format'); ?></label>
                            <select name="date_format" class="form-select form-select-lg bg-light"><option value="gregorian" <?php echo $settings['date_format']=='gregorian'?'selected':''; ?>><?php echo t('date_gregorian'); ?></option><option value="hijri" <?php echo $settings['date_format']=='hijri'?'selected':''; ?>><?php echo t('date_hijri'); ?></option></select>
                        </div>
                    </div>

                    <hr class="my-4">
                    <button type="submit" class="btn btn-primary btn-lg w-100 shadow-sm rounded-pill"><i class="bi bi-save"></i> <?php echo t('update_prefs'); ?></button>
                </form>
            </div>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/footer.php'; ?>