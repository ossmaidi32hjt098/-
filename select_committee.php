<?php
session_start();
require_once __DIR__ . '/db.php';

// إذا كان مسجلاً للدخول بالفعل في لجنة، وجهه إليها
if (isset($_SESSION['logged_in_committee'])) {
    header("Location: committee.php?id=" . $_SESSION['logged_in_committee']);
    exit;
}

// جلب جميع اللجان
try {
    $committees = $pdo->query("SELECT id, committee_name as name FROM committees_registry ORDER BY id ASC")->fetchAll();
} catch (PDOException $e) {
    $committees = [];
}

require_once __DIR__ . '/header.php';
?>

<div class="row justify-content-center mt-4">
    <div class="col-md-8 text-center mb-5">
        <h2 class="text-primary fw-bold"><i class="bi bi-diagram-3-fill me-2"></i><?php echo t('select_committee_title'); ?></h2>
        <p class="text-muted mt-2"><?php echo t('select_committee_desc'); ?></p>
    </div>
</div>

<div class="row justify-content-center">
    <?php foreach ($committees as $com): ?>
    <div class="col-md-4 col-sm-6 mb-4">
        <a href="login.php?id=<?php echo $com['id']; ?>" class="text-decoration-none">
            <div class="card shadow-sm border-0 h-100 text-center hover-lift" style="border-radius: 15px;">
                <div class="card-body p-4">
                    <div class="display-4 text-success mb-3 bg-light rounded-circle d-inline-block p-3"><i class="bi bi-building"></i></div>
                    <h4 class="card-title text-dark fw-bold mb-3"><?php echo htmlspecialchars($com['name']); ?></h4>
                    <span class="btn btn-outline-primary rounded-pill px-4"><?php echo t('enter_platform'); ?> <i class="bi bi-arrow-left ms-1"></i></span>
                </div>
            </div>
        </a>
    </div>
    <?php endforeach; ?>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>