<?php
session_start();
require_once __DIR__ . '/db.php';

$logged_in_committee = isset($_SESSION['logged_in_committee']) ? $_SESSION['logged_in_committee'] : null;
$is_admin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;

// منع الوصول لغير المسجلين
if (!$logged_in_committee && !$is_admin) {
    header("Location: admin_login.php");
    exit;
}

$all_beneficiaries = [];

// جلب سجل اللجان من البوابة المركزية
try {
    $central_pdo = new PDO("mysql:host=$host;dbname=zakat_central_db;charset=$charset", $user, $pass, $options);
    $registry = $central_pdo->query("SELECT id, committee_name as name FROM committees_registry ORDER BY id ASC")->fetchAll();
} catch (PDOException $e) {
    $registry = [];
}

// تجميع المستفيدين من قواعد البيانات الفرعية (Scatter-Gather)
foreach ($registry as $com) {
    $cid = $com['id'];
    $cname = $com['name'];
    
    // إذا كان الموظف تابع للجنة، نتخطى اللجان الأخرى
    if ($logged_in_committee && $logged_in_committee != $cid) {
        continue;
    }
    
    if (isset($db_nodes[$cid])) {
        $node_db = $db_nodes[$cid];
        try {
            $dsn_node = "mysql:host=$host;dbname=$node_db;charset=$charset";
            $node_pdo = new PDO($dsn_node, $user, $pass, $options);
            
            $bens = $node_pdo->query("SELECT national_id, full_name, phone_number, status FROM beneficiaries ORDER BY full_name ASC")->fetchAll();
            foreach ($bens as $ben) {
                $ben['committee_name'] = $cname;
                $all_beneficiaries[] = $ben;
            }
        } catch (PDOException $e) {}
    }
}

// تجميع المستفيدين حسب اسم اللجنة
$grouped_beneficiaries = [];
foreach ($all_beneficiaries as $ben) {
    $committee = $ben['committee_name'] ?? 'لجنة غير محددة';
    $grouped_beneficiaries[$committee][] = $ben;
}

require_once __DIR__ . '/header.php'; 
?>

<div class="row mb-4 mt-3">
    <div class="col-12">
        <h3 class="mb-0 text-primary">
            <i class="bi bi-globe"></i> <?php echo $logged_in_committee ? t('beneficiaries_record_committee') : t('beneficiaries_record_system'); ?>
        </h3>
        <p class="text-muted mt-2"><?php echo $logged_in_committee ? t('beneficiaries_record_committee_desc') : t('beneficiaries_record_system_desc'); ?></p>
    </div>
</div>

<div class="d-flex justify-content-between mb-3 align-items-center">
    <a href="export_beneficiaries.php" class="btn btn-success shadow-sm"><i class="bi bi-file-earmark-excel me-1"></i> <?php echo t('export_excel'); ?></a>
    <input type="text" class="form-control w-25" id="searchGlobalInput" placeholder="<?php echo t('search_global'); ?>">
</div>

<?php if (empty($grouped_beneficiaries)): ?>
    <div class="alert alert-info text-center mb-0"><?php echo t('no_data_yet'); ?></div>
<?php else: ?>
    <?php foreach ($grouped_beneficiaries as $committee_name => $beneficiaries): ?>
        <div class="card shadow-sm border-0 mb-4 committee-card">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0 text-success"><i class="bi bi-building"></i> <?php echo htmlspecialchars($committee_name); ?> <span class="badge bg-secondary ms-2"><?php echo count($beneficiaries); ?></span></h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover align-middle beneficiary-table">
                        <thead class="table-light">
                            <tr>
                                <th><?php echo t('national_id'); ?></th>
                                <th><?php echo t('full_name'); ?></th>
                                <th><?php echo t('phone_number'); ?></th>
                                <th><?php echo t('file_status'); ?></th>
                                <th><?php echo t('actions'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($beneficiaries as $ben): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($ben['national_id']); ?></td>
                                <td><?php echo htmlspecialchars($ben['full_name']); ?></td>
                                <td><?php echo htmlspecialchars(!empty($ben['phone_number']) ? $ben['phone_number'] : t('not_found')); ?></td>
                                <td><span class="badge bg-success"><?php echo htmlspecialchars($ben['status']); ?></span></td>
                                <td>
                                    <a href="edit_beneficiary.php?national_id=<?php echo urlencode($ben['national_id']); ?>" class="btn btn-sm btn-outline-primary" title="<?php echo t('edit'); ?>"><i class="bi bi-pencil-square"></i></a>
                                    <a href="print_report.php?national_id=<?php echo urlencode($ben['national_id']); ?>" target="_blank" class="btn btn-sm btn-outline-secondary" title="<?php echo t('print'); ?>"><i class="bi bi-printer"></i></a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

<script>
// تفعيل فلترة البحث في السجل الموحد
document.getElementById('searchGlobalInput')?.addEventListener('keyup', function() {
    let filter = this.value.toLowerCase();
    let tables = document.querySelectorAll('.beneficiary-table');
    
    tables.forEach(table => {
        let rows = table.querySelectorAll('tbody tr');
        let tableHasVisibleRow = false;
        
        rows.forEach(row => {
            if (row.textContent.toLowerCase().includes(filter)) {
                row.style.display = '';
                tableHasVisibleRow = true;
            } else {
                row.style.display = 'none';
            }
        });
        
        // إخفاء الكارد (اللجنة) بالكامل إذا لم يكن هناك نتائج فيه
        let card = table.closest('.committee-card');
        if (card) {
            card.style.display = tableHasVisibleRow ? '' : 'none';
        }
    });
});
</script>

<?php require_once __DIR__ . '/footer.php'; ?>