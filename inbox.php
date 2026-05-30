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
$tab = $_GET['tab'] ?? 'received';

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

if ($tab === 'sent') {
    $stmt = $central_msg_pdo->prepare("SELECT * FROM global_messages WHERE sender_global_id = ? ORDER BY created_at DESC");
} else {
    $stmt = $central_msg_pdo->prepare("SELECT * FROM global_messages WHERE receiver_global_id = ? ORDER BY created_at DESC");
}
$stmt->execute([$my_global_id]);
$messages = $stmt->fetchAll();

$unique_gids = [];
foreach ($messages as $msg) {
    $unique_gids[] = $tab === 'sent' ? $msg['receiver_global_id'] : $msg['sender_global_id'];
}
$unique_gids = array_unique($unique_gids);

$user_map = [];
foreach ($unique_gids as $gid) {
    list($cid, $uid) = explode('_', $gid);
    $cname = 'الإدارة'; $name = 'غير معروف'; $uname = '';
    if ($cid == 0) {
        $stmtU = $central_msg_pdo->prepare("SELECT full_name, username FROM users WHERE id = ?");
        $stmtU->execute([$uid]);
        if ($row = $stmtU->fetch()) { $name = $row['full_name']; $uname = $row['username']; }
    } elseif (isset($db_nodes[$cid])) {
        try {
            $cStmt = $central_msg_pdo->prepare("SELECT committee_name FROM committees_registry WHERE id = ?");
            $cStmt->execute([$cid]);
            $cname = $cStmt->fetchColumn() ?: 'لجنة';
            $node_pdo = new PDO("mysql:host=$host;dbname=" . $db_nodes[$cid] . ";charset=$charset", $user, $pass, $options);
            $stmtU = $node_pdo->prepare("SELECT full_name, username FROM users WHERE id = ?");
            $stmtU->execute([$uid]);
            if ($row = $stmtU->fetch()) { $name = $row['full_name']; $uname = $row['username']; }
        } catch (PDOException $e) {}
    }
    $user_map[$gid] = ['name' => $name, 'details' => "$uname - $cname"];
}

foreach ($messages as &$msg) {
    $target_gid = $tab === 'sent' ? $msg['receiver_global_id'] : $msg['sender_global_id'];
    $msg['other_party'] = $user_map[$target_gid]['name'] ?? 'مستخدم غير معروف';
    $msg['other_username'] = $user_map[$target_gid]['details'] ?? '';
}

require_once __DIR__ . '/header.php';
?>

<div class="row mb-4 mt-3">
    <div class="col-12 d-flex justify-content-between align-items-center">
        <h3 class="mb-0 text-primary"><i class="bi bi-envelope me-2"></i> صندوق المراسلات</h3>
        <a href="compose_message.php" class="btn btn-primary shadow-sm"><i class="bi bi-pencil-square"></i> رسالة جديدة</a>
    </div>
</div>

<div class="card shadow-sm border-0 mb-5">
    <div class="card-header bg-white p-0 border-bottom">
        <ul class="nav nav-tabs nav-fill" id="myTab" role="tablist">
            <li class="nav-item" role="presentation">
                <a href="inbox.php?tab=received" class="nav-link <?php echo $tab === 'received' ? 'active fw-bold text-primary' : 'text-muted'; ?>">
                    <i class="bi bi-inbox-fill me-1"></i> البريد الوارد
                </a>
            </li>
            <li class="nav-item" role="presentation">
                <a href="inbox.php?tab=sent" class="nav-link <?php echo $tab === 'sent' ? 'active fw-bold text-primary' : 'text-muted'; ?>">
                    <i class="bi bi-send-fill me-1"></i> البريد المرسل
                </a>
            </li>
        </ul>
    </div>
    <div class="card-body p-0">
        <?php if (count($messages) > 0): ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4" style="width: 5%;"></th>
                            <th style="width: 20%;"><?php echo $tab === 'sent' ? 'إلى (المستقبل)' : 'من (المرسل)'; ?></th>
                            <th style="width: 40%;">الموضوع</th>
                            <th style="width: 15%;">النوع</th>
                            <th style="width: 20%;">التاريخ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($messages as $msg): ?>
                            <tr class="<?php echo ($tab === 'received' && $msg['is_read'] == 0) ? 'table-warning' : ''; ?>" style="cursor: pointer;" onclick="window.location='view_message.php?id=<?php echo $msg['id']; ?>'">
                                <td class="ps-4 text-center">
                                    <?php if ($tab === 'received' && $msg['is_read'] == 0): ?>
                                        <i class="bi bi-envelope-fill text-warning fs-5"></i>
                                    <?php else: ?>
                                        <i class="bi bi-envelope-open text-muted fs-5"></i>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="fw-bold text-dark"><?php echo htmlspecialchars($msg['other_party']); ?></div>
                                    <div class="small text-muted"><?php echo htmlspecialchars($msg['other_username']); ?></div>
                                </td>
                                <td>
                                    <div class="fw-bold text-primary"><?php echo htmlspecialchars($msg['subject']); ?></div>
                                    <div class="small text-muted text-truncate" style="max-width: 300px;"><?php echo htmlspecialchars(mb_substr($msg['message_body'], 0, 50)) . '...'; ?></div>
                                </td>
                                <td><span class="badge bg-secondary"><?php echo htmlspecialchars($msg['subject_type']); ?></span></td>
                                <td>
                                    <div class="small fw-bold text-secondary" dir="ltr"><?php echo date('Y-m-d H:i', strtotime($msg['created_at'])); ?></div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="alert alert-light text-center m-5 text-muted border border-0">
                <i class="bi bi-envelope-x display-4 d-block mb-3"></i>
                <h5 class="fw-bold">لا توجد رسائل</h5>
                <p>صندوق البريد فارغ حالياً.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>