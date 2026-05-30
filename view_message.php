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
$msg_id = (int)($_GET['id'] ?? 0);

if (!$msg_id) {
    header("Location: inbox.php");
    exit;
}

$central_msg_pdo = new PDO("mysql:host=$host;dbname=zakat_central_db;charset=$charset", $user, $pass, $options);

try {
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
} catch (PDOException $e) {}

$stmt = $central_msg_pdo->prepare("SELECT * FROM global_messages WHERE id = ? AND (sender_global_id = ? OR receiver_global_id = ?)");
$stmt->execute([$msg_id, $my_global_id, $my_global_id]);
$message = $stmt->fetch();

if (!$message) {
    header("Location: inbox.php");
    exit;
}

function resolve_single_user($gid, $central_pdo, $db_nodes, $host, $user, $pass, $options, $charset) {
    list($cid, $uid) = explode('_', $gid);
    if ($cid == 0) {
        $stmt = $central_pdo->prepare("SELECT full_name FROM users WHERE id = ?");
        $stmt->execute([$uid]);
        return $stmt->fetchColumn() . ' (الإدارة العليا)';
    } elseif (isset($db_nodes[$cid])) {
        try {
            $cStmt = $central_pdo->prepare("SELECT committee_name FROM committees_registry WHERE id = ?");
            $cStmt->execute([$cid]);
            $cname = $cStmt->fetchColumn() ?: 'لجنة';
            $node_pdo = new PDO("mysql:host=$host;dbname=" . $db_nodes[$cid] . ";charset=$charset", $user, $pass, $options);
            $stmt = $node_pdo->prepare("SELECT full_name FROM users WHERE id = ?");
            $stmt->execute([$uid]);
            return $stmt->fetchColumn() . " ($cname)";
        } catch (PDOException $e) {}
    }
    return 'مستخدم غير معروف';
}

$message['sender_name'] = resolve_single_user($message['sender_global_id'], $central_msg_pdo, $db_nodes, $host, $user, $pass, $options, $charset);
$message['receiver_name'] = resolve_single_user($message['receiver_global_id'], $central_msg_pdo, $db_nodes, $host, $user, $pass, $options, $charset);

// تغيير حالة الرسالة إلى "مقروءة" إذا كان الذي فتحها هو المستقبِل
if ($message['receiver_global_id'] == $my_global_id && $message['is_read'] == 0) {
    $central_msg_pdo->prepare("UPDATE global_messages SET is_read = 1 WHERE id = ?")->execute([$msg_id]);
}

$is_sender = ($message['sender_global_id'] == $my_global_id);

require_once __DIR__ . '/header.php';
?>

<div class="row mb-4 mt-3">
    <div class="col-12 d-flex justify-content-between align-items-center">
        <h3 class="mb-0 text-primary"><i class="bi bi-envelope-open me-2"></i> قراءة الرسالة</h3>
        <div>
            <?php if (!$is_sender): ?>
                <a href="compose_message.php?reply_to=<?php echo htmlspecialchars($message['sender_global_id']); ?>&subject=<?php echo urlencode($message['subject']); ?>" class="btn btn-sm btn-success me-2"><i class="bi bi-reply-fill"></i> رد على الرسالة</a>
            <?php endif; ?>
            <a href="inbox.php" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-right"></i> العودة</a>
        </div>
    </div>
</div>

<div class="card shadow-sm border-0 mb-5 mx-auto" style="max-width: 800px;">
    <div class="card-header bg-light border-bottom p-4">
        <h4 class="text-primary fw-bold mb-3"><?php echo htmlspecialchars($message['subject']); ?></h4>
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <span class="badge bg-secondary mb-2"><?php echo htmlspecialchars($message['subject_type']); ?></span>
                <p class="mb-1 text-dark"><strong>من:</strong> <?php echo htmlspecialchars($message['sender_name']); ?> <?php if($is_sender) echo '(أنت)'; ?></p>
                <p class="mb-0 text-dark"><strong>إلى:</strong> <?php echo htmlspecialchars($message['receiver_name']); ?> <?php if(!$is_sender) echo '(أنت)'; ?></p>
            </div>
            <div class="text-end text-muted small fw-bold" dir="ltr">
                <i class="bi bi-clock"></i> <?php echo date('Y-m-d h:i A', strtotime($message['created_at'])); ?>
            </div>
        </div>
    </div>
    <div class="card-body p-4 p-md-5 bg-white" style="min-height: 300px;">
        <div class="message-content text-dark" style="white-space: pre-wrap; font-size: 1.1rem; line-height: 1.8;">
            <?php echo htmlspecialchars($message['message_body']); ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>