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

// تحديث قاعدة البيانات لإضافة الحقول الجديدة للمستخدمين إن لم تكن موجودة
try {
    $pdo->exec("ALTER TABLE `users` ADD COLUMN IF NOT EXISTS `email` VARCHAR(255) NULL DEFAULT NULL");
    $pdo->exec("ALTER TABLE `users` ADD COLUMN IF NOT EXISTS `phone` VARCHAR(20) NULL DEFAULT NULL");
    $pdo->exec("ALTER TABLE `users` ADD COLUMN IF NOT EXISTS `profile_pic` VARCHAR(255) NULL DEFAULT NULL");
    $pdo->exec("ALTER TABLE `users` ADD COLUMN IF NOT EXISTS `last_login` DATETIME NULL DEFAULT CURRENT_TIMESTAMP");
} catch (PDOException $e) {
    // تجاهل الأخطاء إذا كانت الأعمدة موجودة
}

$message = '';

// جلب بيانات المستخدم من قاعدة البيانات
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$db_user = $stmt->fetch();

$committee_name = '';
if ($is_committee && isset($_SESSION['logged_in_committee'])) {
    try {
        $central_pdo = new PDO("mysql:host=$host;dbname=zakat_central_db;charset=$charset", $user, $pass, $options);
        $cstmt = $central_pdo->prepare("SELECT committee_name FROM committees_registry WHERE id = ?");
        $cstmt->execute([$_SESSION['logged_in_committee']]);
        $committee_name = $cstmt->fetchColumn() ?: 'لجنة غير معروفة';
    } catch (PDOException $e) {}
}

$user_data = [
    'full_name' => $db_user['full_name'],
    'email' => $db_user['email'] ?? '',
    'phone' => $db_user['phone'] ?? '',
    'profile_pic' => $db_user['profile_pic'] ?? null,
    'role' => $is_admin ? 'الإدارة العليا (مدير عام)' : 'موظف لجنة زكاة (' . $committee_name . ')',
    'last_login' => $db_user['last_login'] ?? date('Y-m-d H:i:s')
];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    
    // معالجة الصورة الشخصية
    $profile_pic = $user_data['profile_pic'];
    
    // التحقق من طلب حذف الصورة الحالية
    if (isset($_POST['remove_pic']) && $_POST['remove_pic'] == '1') {
        if ($profile_pic && file_exists(__DIR__ . '/uploads/profiles/' . $profile_pic)) {
            unlink(__DIR__ . '/uploads/profiles/' . $profile_pic); // حذف الملف من الخادم
        }
        $profile_pic = null;
    }

    if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] == UPLOAD_ERR_OK) {
        $upload_dir = __DIR__ . '/uploads/profiles/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
        
        $file_ext = strtolower(pathinfo($_FILES['profile_pic']['name'], PATHINFO_EXTENSION));
        if (in_array($file_ext, ['jpg', 'jpeg', 'png'])) {
            $profile_pic = 'user_' . time() . '.' . $file_ext;
            move_uploaded_file($_FILES['profile_pic']['tmp_name'], $upload_dir . $profile_pic);
        }
    }

    // تحديث قاعدة البيانات
    $updateStmt = $pdo->prepare("UPDATE users SET full_name = ?, email = ?, phone = ?, profile_pic = ? WHERE id = ?");
    $updateStmt->execute([$full_name, $email, $phone, $profile_pic, $_SESSION['user_id']]);
    $_SESSION['user_name'] = $full_name; // تحديث الجلسة
    $message = "تم تحديث بياناتك الشخصية بنجاح.";
    
    // تحديث المصفوفة للعرض
    $user_data['full_name'] = $full_name; $user_data['email'] = $email; $user_data['phone'] = $phone; $user_data['profile_pic'] = $profile_pic;
}

require_once __DIR__ . '/header.php';
?>

<div class="row justify-content-center mt-5 mb-5">
    <div class="col-md-8">
        <div class="card shadow-lg border-0" style="border-radius: 20px;">
            <div class="card-header bg-white border-0 pt-4 pb-0 text-center">
                <h3 class="fw-bold text-primary"><i class="bi bi-person-vcard me-2"></i> الملف الشخصي</h3>
            </div>
            <div class="card-body p-4 p-md-5">
                <?php if ($message): ?>
                    <div class="alert alert-success text-center shadow-sm"><i class="bi bi-check-circle-fill"></i> <?php echo $message; ?></div>
                <?php endif; ?>
                
                <form method="POST" enctype="multipart/form-data">
                    <div class="text-center mb-4">
                        <div class="position-relative d-inline-block">
                            <?php if ($user_data['profile_pic']): ?>
                                <img src="uploads/profiles/<?php echo htmlspecialchars($user_data['profile_pic']); ?>" class="rounded-circle shadow" style="width: 120px; height: 120px; object-fit: cover; border: 4px solid var(--bs-primary);">
                            <?php else: ?>
                                <div class="rounded-circle shadow d-flex align-items-center justify-content-center bg-light text-primary" style="width: 120px; height: 120px; border: 4px solid var(--bs-primary);">
                                    <i class="bi bi-person-fill display-1"></i>
                                </div>
                            <?php endif; ?>
                            <label for="profilePicInput" class="position-absolute bottom-0 start-0 bg-primary text-white rounded-circle p-2" style="cursor: pointer;" title="تغيير الصورة">
                                <i class="bi bi-camera-fill"></i>
                            </label>
                            <input type="file" id="profilePicInput" name="profile_pic" class="d-none" accept=".jpg,.jpeg,.png">
                        </div>
                        <?php if ($user_data['profile_pic']): ?>
                        <div class="mt-3">
                            <div class="form-check d-inline-block text-start bg-light px-3 py-2 rounded border">
                                <input class="form-check-input ms-0 me-2" type="checkbox" name="remove_pic" value="1" id="removePic">
                                <label class="form-check-label text-danger small fw-bold" style="cursor: pointer;" for="removePic">
                                    <i class="bi bi-trash"></i> حذف الصورة والرجوع للافتراضية
                                </label>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>

                    <div class="row g-4">
                        <div class="col-md-12"><label class="form-label text-muted fw-bold">الاسم الكامل</label><input type="text" name="full_name" class="form-control form-control-lg bg-light" value="<?php echo htmlspecialchars($user_data['full_name']); ?>" required></div>
                        <div class="col-md-6"><label class="form-label text-muted fw-bold">البريد الإلكتروني</label><input type="email" name="email" class="form-control form-control-lg bg-light" value="<?php echo htmlspecialchars($user_data['email']); ?>" placeholder="example@domain.com"></div>
                        <div class="col-md-6"><label class="form-label text-muted fw-bold">رقم الهاتف</label><input type="tel" name="phone" class="form-control form-control-lg bg-light" value="<?php echo htmlspecialchars($user_data['phone']); ?>" placeholder="07xxxxxxxx"></div>
                        
                        <div class="col-12 mt-5 mb-2"><h5 class="border-bottom pb-2 text-secondary"><i class="bi bi-shield-lock"></i> معلومات الصلاحيات والأمان (للعرض فقط)</h5></div>
                        <div class="col-md-6">
                            <label class="form-label text-muted fw-bold">الدور والصلاحية</label>
                            <div class="form-control form-control-lg bg-light text-secondary"><i class="bi bi-person-badge"></i> <?php echo $user_data['role']; ?></div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted fw-bold">آخر تسجيل دخول</label>
                            <div class="form-control form-control-lg bg-light text-secondary"><i class="bi bi-clock-history"></i> <span dir="ltr"><?php echo $user_data['last_login']; ?></span></div>
                        </div>
                    </div>
                    <hr class="my-4">
                    <button type="submit" class="btn btn-primary btn-lg w-100 shadow-sm rounded-pill"><i class="bi bi-save"></i> حفظ التعديلات</button>
                </form>
            </div>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/footer.php'; ?>