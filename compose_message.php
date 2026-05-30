<?php
session_start();
require_once __DIR__ . '/db.php';

$is_admin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;
$is_committee = isset($_SESSION['logged_in_committee']);

if (!$is_admin && !$is_committee) {
    header("Location: admin_login.php");
    exit;
}

$my_global_id = ($_SESSION['logged_in_committee'] ?? 0) . '_' . $_SESSION['user_id'];
$my_committee_id = $_SESSION['logged_in_committee'] ?? 0;
$message_status = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $receiver_global_id = trim($_POST['receiver_id']);
    $subject = trim($_POST['subject']);
    $subject_type = trim($_POST['subject_type']);
    $message_body = trim($_POST['message_body']);
    
    if ($receiver_global_id && $subject && $subject_type && $message_body) {
        try {
            $central_msg_pdo = new PDO("mysql:host=$host;dbname=zakat_central_db;charset=$charset", $user, $pass, $options);
            
            $central_msg_pdo->exec("CREATE TABLE IF NOT EXISTS `global_messages` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `sender_global_id` varchar(50) NOT NULL,
                `receiver_global_id` varchar(50) NOT NULL,
                `subject` varchar(255) NOT NULL,
                `message_body` text NOT NULL,
                `subject_type` varchar(100) NOT NULL,
                `is_read` tinyint(1) DEFAULT 0,
                `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
            
            $stmt = $central_msg_pdo->prepare("INSERT INTO global_messages (sender_global_id, receiver_global_id, subject, subject_type, message_body) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$my_global_id, $receiver_global_id, $subject, $subject_type, $message_body]);
            $message_status = 'تم إرسال الرسالة بنجاح.';
        } catch (PDOException $e) {
            $error = 'حدث خطأ أثناء الإرسال: ' . $e->getMessage();
        }
    } else {
        $error = 'الرجاء تعبئة جميع الحقول.';
    }
}

$grouped_users = [];
try {
    $central_pdo = new PDO("mysql:host=$host;dbname=zakat_central_db;charset=$charset", $user, $pass, $options);
    
    // جلب الإدارة العليا
    $stmt = $central_pdo->query("SELECT id, full_name, username FROM users WHERE is_admin = 1");
    while ($row = $stmt->fetch()) {
        $gid = "0_" . $row['id'];
        if ($gid == $my_global_id) continue;
        $grouped_users['الإدارة العليا (مدراء النظام)'][] = [
            'global_id' => $gid,
            'full_name' => $row['full_name'],
            'username' => $row['username']
        ];
    }
    
    // جلب موظفي اللجان من قواعدهم
    $registry = $central_pdo->query("SELECT id, committee_name FROM committees_registry ORDER BY id ASC")->fetchAll();
    foreach ($registry as $com) {
        $cid = $com['id'];
        $cname = $com['committee_name'];
        
        // استثناء اللجنة الحالية (التي يتواجد بها المُرسل) من قائمة المستقبلين
        if ($cid == $my_committee_id) continue;
        
        if (isset($db_nodes[$cid])) {
            try {
                $node_pdo = new PDO("mysql:host=$host;dbname=" . $db_nodes[$cid] . ";charset=$charset", $user, $pass, $options);
                $uStmt = $node_pdo->query("SELECT id, full_name, username FROM users ORDER BY id ASC LIMIT 1");
                if ($row = $uStmt->fetch()) {
                    $gid = $cid . "_" . $row['id'];
                    $grouped_users[$cname][] = [
                        'global_id' => $gid,
                        'full_name' => $row['full_name'] . ' (مدير اللجنة)',
                        'username' => $row['username']
                    ];
                }
            } catch (PDOException $e) {}
        }
    }
} catch (PDOException $e) {}

$reply_to = isset($_GET['reply_to']) ? $_GET['reply_to'] : '';
$reply_subject = isset($_GET['subject']) ? 'رد: ' . htmlspecialchars($_GET['subject']) : '';

require_once __DIR__ . '/header.php';
?>

<div class="row mb-4 mt-3">
    <div class="col-12 d-flex justify-content-between align-items-center">
        <h3 class="mb-0 text-primary"><i class="bi bi-pencil-square me-2"></i> كتابة رسالة جديدة</h3>
        <a href="inbox.php" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-right"></i> العودة لصندوق الوارد</a>
    </div>
</div>

<div class="card shadow-sm border-0 mb-5 mx-auto" style="max-width: 800px;">
    <div class="card-body p-4 p-md-5">
        <?php if ($message_status): ?>
            <div class="alert alert-success text-center shadow-sm"><i class="bi bi-check-circle-fill"></i> <?php echo $message_status; ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-danger text-center shadow-sm"><i class="bi bi-exclamation-triangle-fill"></i> <?php echo $error; ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="mb-4">
                <label class="form-label fw-bold text-muted">المستقبل (إلى)</label>
                <select name="receiver_id" class="form-select form-select-lg bg-light" required>
                    <option value="">-- اختر المستقبل --</option>
                    <?php foreach ($grouped_users as $group => $group_users): ?>
                        <optgroup label="<?php echo htmlspecialchars($group); ?>">
                            <?php foreach ($group_users as $u): ?>
                                <option value="<?php echo htmlspecialchars($u['global_id']); ?>" <?php echo $reply_to === $u['global_id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($u['full_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </optgroup>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="row g-3 mb-4">
                <div class="col-md-4">
                    <label class="form-label fw-bold text-muted">نوع الموضوع</label>
                    <select name="subject_type" class="form-select bg-light" required>
                        <option value="">-- اختر النوع --</option>
                        <option value="استفسار">استفسار</option>
                        <option value="طلب دعم/رصيد">طلب دعم/رصيد</option>
                        <option value="إشعار إداري">إشعار إداري</option>
                        <option value="تنبيه ازدواجية">تنبيه ازدواجية</option>
                        <option value="أخرى">أخرى</option>
                    </select>
                </div>
                <div class="col-md-8">
                    <label class="form-label fw-bold text-muted">العنوان (الموضوع)</label>
                    <input type="text" name="subject" class="form-control bg-light" required value="<?php echo $reply_subject; ?>">
                </div>
            </div>
            
            <div class="mb-4">
                <label class="form-label fw-bold text-muted">نص الرسالة</label>
                <textarea name="message_body" class="form-control bg-light" rows="8" required placeholder="اكتب رسالتك هنا..."></textarea>
            </div>
            
            <button type="submit" class="btn btn-primary btn-lg w-100 shadow-sm"><i class="bi bi-send-fill me-2"></i> إرسال الرسالة</button>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>