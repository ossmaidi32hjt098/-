<?php
session_start();

// حماية الصفحة: للمدير فقط
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    header("Location: admin_login.php");
    exit;
}
require_once __DIR__ . '/db.php';

$message = '';
$error = '';

// معالجة إنشاء/إعادة تعيين كلمة مرور لمستخدم لجنة
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['create_reset_user'])) {
    $committee_id = (int)$_POST['committee_id'];
    $committee_name = trim($_POST['committee_name']);
    $new_password = !empty($_POST['custom_password']) ? trim($_POST['custom_password']) : '12345678'; // استخدام كلمة المرور المدخلة
    $password_hash = password_hash($new_password, PASSWORD_DEFAULT);

    if ($committee_id > 0) {
        try {
            $node_db = $db_nodes[$committee_id] ?? null;
            if ($node_db) {
                $node_pdo = new PDO("mysql:host=$host;dbname=$node_db;charset=$charset", $user, $pass, $options);
                
                // التأكد من وجود جدول المستخدمين
                $node_pdo->exec("CREATE TABLE IF NOT EXISTS `users` (
                    `id` int(11) NOT NULL AUTO_INCREMENT, `full_name` varchar(255) NOT NULL, `username` varchar(255) NOT NULL, `password_hash` varchar(255) NOT NULL,
                    PRIMARY KEY (`id`), UNIQUE KEY `username` (`username`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

                // استخدم اسم المستخدم المحدد إذا وجد، وإلا قم بإنشاء واحد بناءً على اسم اللجنة
                $username = isset($_POST['target_username']) && !empty($_POST['target_username']) ? trim($_POST['target_username']) : str_replace(' ', '_', $committee_name) . '_user';
                $full_name = 'موظف ' . $committee_name;

                // التحقق من وجود المستخدم
                $user_stmt = $node_pdo->prepare("SELECT id FROM users WHERE username = ?");
                $user_stmt->execute([$username]);
                
                if ($user_stmt->fetchColumn()) {
                    // المستخدم موجود، يتم تحديث كلمة المرور
                    $update_stmt = $node_pdo->prepare("UPDATE users SET password_hash = ? WHERE username = ?");
                    $update_stmt->execute([$password_hash, $username]);
                    $message = "تمت إعادة تعيين كلمة مرور المستخدم ($username) للجنة '" . htmlspecialchars($committee_name) . "' بنجاح. كلمة المرور الجديدة هي: <strong class='fs-5' dir='ltr'>$new_password</strong>";
                } else {
                    // المستخدم غير موجود، يتم إنشاؤه
                    $insert_stmt = $node_pdo->prepare("INSERT INTO users (full_name, username, password_hash) VALUES (?, ?, ?)");
                    $insert_stmt->execute([$full_name, $username, $password_hash]);
                    $message = "تم إنشاء مستخدم ($username) للجنة '" . htmlspecialchars($committee_name) . "' بنجاح. كلمة المرور هي: <strong class='fs-5' dir='ltr'>$new_password</strong>";
                }
            } else {
                $error = "لم يتم العثور على قاعدة البيانات للجنة المحددة في ملف db.php.";
            }
        } catch (PDOException $e) {
            $error = "حدث خطأ: " . $e->getMessage();
        }
    }
}

// معالجة إضافة لجنة جديدة مع إنشاء قاعدة بياناتها وحساب الدخول
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_committee'])) {
    $committee_name = trim($_POST['committee_name'] ?? '');
    $new_username = trim($_POST['new_username'] ?? '');
    $new_password = trim($_POST['new_password'] ?? '');
    
    // التأكد من أن اسم المستخدم ينتهي بـ _user حسب القيود الأمنية
    if (!empty($new_username) && substr($new_username, -5) !== '_user') {
        $new_username .= '_user';
    }

    if (!empty($committee_name) && !empty($new_username) && !empty($new_password)) {
        try {
            $central_pdo = new PDO("mysql:host=$host;dbname=zakat_central_db;charset=$charset", $user, $pass, $options);
            $stmt = $central_pdo->prepare("INSERT INTO committees_registry (committee_name) VALUES (:name)");
            $stmt->execute(['name' => $committee_name]);
            $new_id = $central_pdo->lastInsertId();
            
            // 1. إنشاء قاعدة بيانات فرعية مستقلة للجنة الجديدة
            $new_db_name = "zakat_node_" . $new_id . "_db";
            $central_pdo->exec("CREATE DATABASE IF NOT EXISTS `$new_db_name` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");
            
            // 2. نسخ هيكل الجداول من أول قاعدة بيانات متاحة (لتجهيز اللجنة بالكامل)
        $template_db = !empty($db_nodes) ? reset($db_nodes) : 'zakat_aleppo_db'; 
            if ($template_db) {
                $tables = ['beneficiaries', 'committee_finances', 'donations_history', 'incoming_donations', 'inventory_balances'];
                foreach ($tables as $table) {
                    $central_pdo->exec("CREATE TABLE IF NOT EXISTS `$new_db_name`.`$table` LIKE `$template_db`.`$table`");
                }
            }
            
            // 3. الاتصال بالقاعدة الجديدة لإنشاء جدول المستخدمين وإضافة الحساب
            $node_pdo = new PDO("mysql:host=$host;dbname=$new_db_name;charset=$charset", $user, $pass, $options);
            $node_pdo->exec("CREATE TABLE IF NOT EXISTS `users` (
                `id` int(11) NOT NULL AUTO_INCREMENT, `full_name` varchar(255) NOT NULL, `username` varchar(255) NOT NULL, `password_hash` varchar(255) NOT NULL,
                PRIMARY KEY (`id`), UNIQUE KEY `username` (`username`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
            
            $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
            $full_name = 'موظف ' . $committee_name;
            $insert_stmt = $node_pdo->prepare("INSERT INTO users (full_name, username, password_hash) VALUES (?, ?, ?)");
            $insert_stmt->execute([$full_name, $new_username, $password_hash]);
            
            // 4. تحديث ملف db.php ديناميكياً لإضافة القاعدة الجديدة
            $db_file = __DIR__ . '/db.php';
        if (file_exists($db_file) && is_writable($db_file)) {
                $db_content = file_get_contents($db_file);
                $append_code = "\n// إضافة تلقائية للجنة ($committee_name)\n\$db_nodes[$new_id] = '$new_db_name';\n";
                if (strpos($db_content, '?>') !== false) {
                    $db_content = str_replace('?>', $append_code . '?>', $db_content);
                } else {
                    $db_content .= $append_code;
                }
                file_put_contents($db_file, $db_content);
                $db_nodes[$new_id] = $new_db_name; // تحديث المصفوفة في الذاكرة لتظهر فوراً
            }
            
            $message = "تمت إضافة اللجنة '" . htmlspecialchars($committee_name) . "' وإنشاء حساب الدخول بنجاح!";
        } catch (PDOException $e) {
            $error = "حدث خطأ أثناء إضافة اللجنة: " . $e->getMessage();
        }
    } else {
        $error = "الرجاء إدخال اسم اللجنة وبيانات الدخول.";
    }
}

// معالجة حذف اللجنة نهائياً
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_committee'])) {
    $committee_id_to_delete = (int)$_POST['committee_id_to_delete'];
    if ($committee_id_to_delete > 0) {
        try {
            $central_pdo = new PDO("mysql:host=$host;dbname=zakat_central_db;charset=$charset", $user, $pass, $options);
            $central_pdo->prepare("DELETE FROM committees_registry WHERE id = ?")->execute([$committee_id_to_delete]);
            
            $node_db = $db_nodes[$committee_id_to_delete] ?? null;
            if ($node_db) {
                try {
                    $node_pdo = new PDO("mysql:host=$host;dbname=$node_db;charset=$charset", $user, $pass, $options);
                    $node_pdo->exec("DELETE FROM users");
                } catch (PDOException $e) {}
            }
            $message = "تم حذف اللجنة من السجل المركزي بنجاح.";
        } catch (PDOException $e) {
            $error = "عذراً، حدث خطأ أثناء حذف اللجنة.";
        }
    }
}

// جلب اللجان والمستخدمين التابعين لها للعرض
$committees = [];

// 1. إضافة الإدارة العليا أولاً لعرضها
$committees[0] = ['name' => 'الإدارة العليا (مدراء النظام)', 'users' => []];
try {
    $central_pdo = new PDO("mysql:host=$host;dbname=zakat_central_db;charset=$charset", $user, $pass, $options);
    $adminStmt = $central_pdo->query("SELECT id as user_id, full_name, username FROM users WHERE is_admin = 1");
    while ($u = $adminStmt->fetch()) {
        $committees[0]['users'][] = [
            'user_id' => $u['user_id'],
            'full_name' => $u['full_name'],
            'username' => $u['username']
        ];
    }
} catch (PDOException $e) {}

// 2. جلب باقي اللجان ومستخدميها الحقيقيين
try {
    $central_pdo = new PDO("mysql:host=$host;dbname=zakat_central_db;charset=$charset", $user, $pass, $options);
    $committeesList = $central_pdo->query("SELECT id, committee_name as name FROM committees_registry ORDER BY id ASC")->fetchAll();
    
    foreach ($committeesList as $com) {
        $cid = $com['id'];
        $committees[$cid] = ['name' => $com['name'], 'users' => []];

        // جلب المستخدمين الحقيقيين من قاعدة بيانات اللجنة
        $node_db = $db_nodes[$cid] ?? null;
        if ($node_db) {
            try {
                $node_pdo = new PDO("mysql:host=$host;dbname=$node_db;charset=$charset", $user, $pass, $options);
                $userStmt = $node_pdo->query("SELECT id as user_id, full_name, username FROM users");
                while ($u = $userStmt->fetch()) {
                    $committees[$cid]['users'][] = $u;
                }
            } catch (PDOException $e) {}
        }
    }
} catch (PDOException $e) {
    $committeesList = [];
}

require_once __DIR__ . '/header.php';
?>

<div class="row mb-4 mt-3">
    <div class="col-12 d-flex justify-content-between align-items-center">
        <h3 class="mb-0 text-primary"><i class="bi bi-diagram-3-fill"></i> إدارة اللجان والمستخدمين</h3>
        <a href="index.php" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-right"></i> العودة للوحة التحكم</a>
    </div>
</div>

<?php if ($message): ?>
    <div class="alert alert-success text-center"><?php echo $message; ?></div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="alert alert-danger text-center"><?php echo $error; ?></div>
<?php endif; ?>

<div class="row mb-5 justify-content-center">
    <!-- نموذج إضافة لجنة جديدة -->
    <div class="col-md-6 mb-4">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-success text-white"><h5 class="mb-0"><i class="bi bi-building-add"></i> إضافة لجنة جديدة</h5></div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="add_committee" value="1">
                    <div class="mb-3">
                    <label class="form-label fw-bold">اسم اللجنة / الجمعية</label>
                    <input type="text" name="committee_name" class="form-control bg-light" required placeholder="مثال: لجنة حمص">
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">اسم مستخدم الدخول</label>
                    <div class="input-group">
                        <input type="text" name="new_username" class="form-control text-start bg-light" dir="ltr" required placeholder="مثال: homs">
                        <span class="input-group-text bg-secondary text-white">_user</span>
                    </div>
                </div>
                <div class="mb-4">
                    <label class="form-label fw-bold">كلمة المرور الأولية</label>
                    <input type="text" name="new_password" class="form-control text-center bg-light" dir="ltr" required placeholder="أدخل كلمة المرور" minlength="6">
                    </div>
                <button type="submit" class="btn btn-success btn-lg w-100 shadow-sm"><i class="bi bi-building-check me-2"></i> إنشاء واعتماد اللجنة</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- عرض اللجان الحالية والمستخدمين -->
<div class="row">
    <div class="col-12 mb-3">
        <h4 class="text-secondary border-bottom pb-2"><i class="bi bi-list-check"></i> اللجان والمستخدمين المسجلين حالياً</h4>
    </div>
    <?php foreach ($committees as $cid => $cdata): ?>
        <div class="col-md-4 mb-4">
            <div class="card shadow-sm border-0 h-100 <?php echo $cid === 0 ? 'border-top border-4 border-primary' : ''; ?>">
                <div class="card-header <?php echo $cid === 0 ? 'bg-primary' : 'bg-dark'; ?> text-white d-flex justify-content-between align-items-center">
                    <h6 class="mb-0"><?php echo htmlspecialchars($cdata['name']); ?></h6>
                    <div class="d-flex align-items-center gap-2">
                        <span class="badge bg-secondary"><?php echo count($cdata['users']); ?> مستخدمين</span>
                        <?php if ($cid !== 0): ?>
                            <form method="POST" class="m-0 p-0" onsubmit="return confirm('هل أنت متأكد من رغبتك في حذف هذه اللجنة نهائياً؟\n\nتنبيه: سيتم حذف جميع حسابات الموظفين التابعين لها ولن تتمكن من التراجع.');">
                                <input type="hidden" name="delete_committee" value="1">
                                <input type="hidden" name="committee_id_to_delete" value="<?php echo $cid; ?>">
                                <button type="submit" class="btn btn-sm btn-danger px-2 py-1" title="حذف اللجنة"><i class="bi bi-trash"></i></button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="card-body">
                    <?php if (empty($cdata['users'])): ?>
                        <p class="text-muted text-center mb-2 mt-3">لا يوجد مستخدمين لهذه اللجنة بعد.</p>
                        <?php if ($cid !== 0): ?>
                            <form method="POST" class="m-0 p-0">
                                <input type="hidden" name="create_reset_user" value="1">
                                <input type="hidden" name="committee_id" value="<?php echo $cid; ?>">
                                <input type="hidden" name="committee_name" value="<?php echo htmlspecialchars($cdata['name']); ?>">
                                <div class="input-group input-group-sm">
                                    <input type="text" name="custom_password" class="form-control text-center" placeholder="كلمة المرور (12345678)" required minlength="6">
                                    <button type="submit" class="btn btn-info">إنشاء مستخدم</button>
                                </div>
                            </form>
                        <?php endif; ?>
                    <?php else: ?>
                        <ul class="list-group list-group-flush">
                            <?php foreach ($cdata['users'] as $u): ?>
                                <li class="list-group-item px-0 border-0 d-flex justify-content-between align-items-center flex-wrap gap-2">
                                    <div>
                                        <i class="bi bi-person-circle text-primary me-2"></i> <strong><?php echo htmlspecialchars($u['full_name']); ?></strong><br>
                                        <small class="text-muted ms-4">دخول: <strong class="text-dark"><?php echo htmlspecialchars($u['username']); ?></strong></small>
                                    </div>
                                    <?php if ($cid !== 0): ?>
                                        <form method="POST" class="m-0 p-0">
                                            <input type="hidden" name="create_reset_user" value="1">
                                            <input type="hidden" name="committee_id" value="<?php echo $cid; ?>">
                                            <input type="hidden" name="committee_name" value="<?php echo htmlspecialchars($cdata['name']); ?>">
                                            <input type="hidden" name="target_username" value="<?php echo htmlspecialchars($u['username']); ?>">
                                            <div class="input-group input-group-sm" style="max-width: 250px;">
                                                <input type="text" name="custom_password" class="form-control text-center bg-white" placeholder="كلمة مرور جديدة" required minlength="6">
                                                <button type="submit" class="btn btn-warning text-dark fw-bold"><i class="bi bi-key"></i> تعيين</button>
                                            </div>
                                        </form>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
