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

// التأكد من وجود جدول حساب المدير وإنشائه إن لم يكن موجوداً
$pdo->exec("CREATE TABLE IF NOT EXISTS `manager_finance` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `balance` decimal(15,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
$pdo->exec("INSERT IGNORE INTO `manager_finance` (`id`, `balance`) VALUES (1, 0.00);");

// إنشاء جدول أرصدة اللجان إذا لم يكن موجوداً
$pdo->exec("CREATE TABLE IF NOT EXISTS `committee_finances` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `committee_id` int(11) NOT NULL,
  `balance` decimal(15,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`id`),
  UNIQUE KEY `committee_id` (`committee_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

// إنشاء جدول لتتبع المخزون والأرصدة التفصيلية لكل نوع تبرع (0 للمدير، ورقم اللجنة للجان)
$pdo->exec("CREATE TABLE IF NOT EXISTS `inventory_balances` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `committee_id` int(11) NOT NULL DEFAULT 0,
  `donation_type_id` int(11) NOT NULL,
  `quantity` decimal(15,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_inv` (`committee_id`, `donation_type_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

// إنشاء جدول لتسجيل حركات الإيداع (سجل الواردات)
$pdo->exec("CREATE TABLE IF NOT EXISTS `incoming_donations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `committee_id` int(11) NOT NULL DEFAULT 0,
  `donation_type_id` int(11) NOT NULL,
  `quantity` decimal(15,2) NOT NULL,
  `deposit_date` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

// معالجة إضافة رصيد للجنة
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_balance'])) {
    $target = $_POST['committee_id'];
    $donation_type_id = (int)$_POST['donation_type_id'];
    $amount = (float)$_POST['amount'];
    
    $cid = ($target === 'manager') ? 0 : (int)$target;

    if ($amount > 0) {
        try {
            $catStmt = $pdo->prepare("SELECT category, sub_category FROM donation_types WHERE id = ?");
            $catStmt->execute([$donation_type_id]);
            $type_info = $catStmt->fetch();
            
            $is_cash = false;
            if ($type_info) {
                $cat_str = $type_info['category'] . ' ' . $type_info['sub_category'];
                if (mb_strpos($cat_str, 'نقد') !== false) {
                    $is_cash = true;
                }
            }

            if ($cid === 0) {
                $pdo->beginTransaction();
                $invStmt = $pdo->prepare("INSERT INTO inventory_balances (committee_id, donation_type_id, quantity) VALUES (0, :dtid, :qty) ON DUPLICATE KEY UPDATE quantity = quantity + :qty2");
                $invStmt->execute(['dtid' => $donation_type_id, 'qty' => $amount, 'qty2' => $amount]);
                
                $logStmt = $pdo->prepare("INSERT INTO incoming_donations (committee_id, donation_type_id, quantity) VALUES (0, ?, ?)");
                $logStmt->execute([$donation_type_id, $amount]);

                if ($is_cash) {
                    $amt = (float)$amount;
                    $pdo->exec("INSERT INTO manager_finance (id, balance) VALUES (1, $amt) ON DUPLICATE KEY UPDATE balance = balance + $amt");
                }
                $pdo->commit();
            } else {
                if (!isset($db_nodes[$cid])) throw new Exception("قاعدة بيانات اللجنة غير متصلة.");
                $node_pdo = new PDO("mysql:host=$host;dbname=" . $db_nodes[$cid] . ";charset=$charset", $user, $pass, $options);
                $node_pdo->beginTransaction();
                
                $invStmt = $node_pdo->prepare("INSERT INTO inventory_balances (donation_type_id, quantity) VALUES (:dtid, :qty) ON DUPLICATE KEY UPDATE quantity = quantity + :qty2");
                $invStmt->execute(['dtid' => $donation_type_id, 'qty' => $amount, 'qty2' => $amount]);
                
                $logStmt = $node_pdo->prepare("INSERT INTO incoming_donations (donation_type_id, quantity) VALUES (?, ?)");
                $logStmt->execute([$donation_type_id, $amount]);

                if ($is_cash) {
                    $amt = (float)$amount;
                    $node_pdo->exec("INSERT INTO committee_finances (id, balance) VALUES (1, $amt) ON DUPLICATE KEY UPDATE balance = balance + $amt");
                }
                $node_pdo->commit();
            }
            
            $target_name = ($cid === 0) ? "الصندوق الرئيسي (المدير)" : "اللجنة المحددة";
            $unit = $is_cash ? "مبلغ" : "وحدة";
            $message = "تم إيداع ($amount $unit) كـ [{$type_info['sub_category']}] بنجاح في حساب/مخزون $target_name.";
            
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $error = "حدث خطأ أثناء تحديث الرصيد: " . $e->getMessage();
        }
    } else {
        $error = "الرجاء إدخال كمية/مبلغ صحيح أكبر من الصفر.";
    }
}

// معالجة تحويل رصيد من الإدارة (الصندوق الرئيسي) للجنة
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['transfer_balance'])) {
    $to_committee_id = (int)$_POST['to_committee_id'];
    $donation_type_id = (int)$_POST['transfer_donation_type_id'];
    $amount = (float)$_POST['transfer_amount'];

    if ($amount > 0 && $to_committee_id > 0) {
        try {
            $pdo->beginTransaction();

            // 1. تحقق من توفر الرصيد لدى المدير (الصندوق الرئيسي)
            $checkManager = $pdo->prepare("SELECT quantity FROM inventory_balances WHERE committee_id = 0 AND donation_type_id = ?");
            $checkManager->execute([$donation_type_id]);
            $manager_qty = (float)$checkManager->fetchColumn();

            if ($manager_qty >= $amount) {
                // خصم الكمية من مخزون المدير وإضافة حركة سالبة في السجلات لتتطابق الحسابات
                $pdo->prepare("UPDATE inventory_balances SET quantity = quantity - ? WHERE committee_id = 0 AND donation_type_id = ?")->execute([$amount, $donation_type_id]);
                $pdo->prepare("INSERT INTO incoming_donations (committee_id, donation_type_id, quantity) VALUES (0, ?, ?)")->execute([$donation_type_id, -$amount]);

                // إضافة الكمية للجنة وإضافة حركة موجبة في السجلات
                $pdo->prepare("INSERT INTO inventory_balances (committee_id, donation_type_id, quantity) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE quantity = quantity + ?")->execute([$to_committee_id, $donation_type_id, $amount, $amount]);
                $pdo->prepare("INSERT INTO incoming_donations (committee_id, donation_type_id, quantity) VALUES (?, ?, ?)")->execute([$to_committee_id, $donation_type_id, $amount]);

                // تحديث الأرصدة المالية الإجمالية (إذا كان الرصيد نقدياً)
                $catStmt = $pdo->prepare("SELECT category, sub_category FROM donation_types WHERE id = ?");
                $catStmt->execute([$donation_type_id]);
                $type_info = $catStmt->fetch();
                
                $is_cash = false;
                if ($type_info) {
                    $cat_str = $type_info['category'] . ' ' . $type_info['sub_category'];
                    if (mb_strpos($cat_str, 'نقد') !== false) {
                        $is_cash = true;
                    }
                }

                if ($is_cash) {
                    $amt = (float)$amount;
                    $pdo->exec("UPDATE manager_finance SET balance = balance - $amt WHERE id = 1");
                    $node_pdo->exec("INSERT INTO committee_finances (id, balance) VALUES (1, $amt) ON DUPLICATE KEY UPDATE balance = balance + $amt");
                }

                $pdo->commit();
                $node_pdo->commit();
                $message = "تم تحويل ($amount) بنجاح من الصندوق الرئيسي إلى اللجنة المحددة.";
            } else {
                throw new Exception("عذراً، رصيد الصندوق الرئيسي غير كافٍ لإتمام التحويل. الرصيد المتوفر للتحويل هو: $manager_qty");
            }
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $error = "فشل التحويل: " . $e->getMessage();
        }
    } else {
        $error = "الرجاء إدخال بيانات صحيحة للتحويل.";
    }
}

// معالجة إضافة نوع / مجال تبرع جديد
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_donation_type'])) {
    $category = trim($_POST['category']);
    $sub_category = trim($_POST['sub_category']);

    if (!empty($category) && !empty($sub_category)) {
        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS `donation_types` (
              `id` int(11) NOT NULL AUTO_INCREMENT,
              `category` varchar(50) NOT NULL,
              `sub_category` varchar(150) NOT NULL,
              PRIMARY KEY (`id`),
              UNIQUE KEY `unique_cat_sub` (`category`,`sub_category`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
            
            $stmt = $pdo->prepare("INSERT INTO donation_types (category, sub_category) VALUES (?, ?)");
            $stmt->execute([$category, $sub_category]);
            $message = "تم إضافة التصنيف الجديد [$category - $sub_category] بنجاح في النظام.";
        } catch (PDOException $e) {
            $error = "عذراً، هذا التصنيف موجود بالفعل في النظام.";
        }
    } else {
        $error = "الرجاء تعبئة الفئة واسم التصنيف.";
    }
}

// جلب أنواع التبرعات لاستخدامها في القائمة المنسدلة (باستخدام التجميع لمنع ظهور أي تكرار مستقبلي بالخطأ)
try {
    // التأكد من وجود جدول أنواع التبرع وتعبئته بالبيانات الافتراضية إذا كان فارغاً
    $pdo->exec("CREATE TABLE IF NOT EXISTS `donation_types` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `category` varchar(50) NOT NULL,
      `sub_category` varchar(150) NOT NULL,
      PRIMARY KEY (`id`),
      UNIQUE KEY `unique_cat_sub` (`category`,`sub_category`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    
    $types_count = $pdo->query("SELECT COUNT(*) FROM donation_types")->fetchColumn();
    if ($types_count == 0) {
        $default_types = [['نقدي', 'زكاة مال'], ['نقدي', 'صدقة'], ['نقدي', 'كفالة شهرية'], ['نقدي', 'دعم طارئ'], ['عيني', 'سلة غذائية'], ['عيني', 'كسوة (ملابس)'], ['عيني', 'مستلزمات طبية'], ['عيني', 'مستلزمات تعليمية (قرطاسية)'], ['عيني', 'لحوم أضاحي (موسمي)'], ['خدمات', 'استشارة طبية مجانية'], ['خدمات', 'جلسة علاج فيزيائي'], ['خدمات', 'دعم نفسي وتعليمي']];
        $insert_stmt = $pdo->prepare("INSERT IGNORE INTO donation_types (category, sub_category) VALUES (?, ?)");
        foreach ($default_types as $type) $insert_stmt->execute($type);
    }
    
    $donation_types = $pdo->query("SELECT MIN(id) as id, category, TRIM(sub_category) as sub_category FROM donation_types GROUP BY category, TRIM(sub_category) ORDER BY category, MIN(id)")->fetchAll();
} catch (PDOException $e) {
    $donation_types = [];
}

// جلب اللجان لاستخدامها في القائمة المنسدلة
try {
    $committees_list = $pdo->query("SELECT id, committee_name as name FROM committees_registry ORDER BY id ASC")->fetchAll();
} catch (PDOException $e) {
    $committees_list = [];
}

// جلب المخزون والأرصدة التفصيلية لتجميعها وعرضها
$inventory_data = [];
try {
    $inventory_stmt = $pdo->query("
        SELECT 0 as committee_id, 'الصندوق الرئيسي' as committee_name, dt.category, dt.sub_category, SUM(ib.quantity) as quantity 
        FROM inventory_balances ib
        JOIN donation_types dt ON ib.donation_type_id = dt.id
        WHERE ib.committee_id = 0 AND ib.quantity > 0
        GROUP BY dt.category, dt.sub_category
        ORDER BY dt.category ASC, dt.sub_category ASC
    ");
    $inventory_data = array_merge($inventory_data, $inventory_stmt->fetchAll());
} catch (PDOException $e) {
}

foreach ($committees_list as $com) {
    $cid = $com['id'];
    if (isset($db_nodes[$cid])) {
        try {
            $node_pdo = new PDO("mysql:host=$host;dbname=" . $db_nodes[$cid] . ";charset=$charset", $user, $pass, $options);
            $inv_stmt = $node_pdo->query("
                SELECT $cid as committee_id, dt.category, dt.sub_category, SUM(ib.quantity) as quantity 
                FROM inventory_balances ib
                JOIN zakat_central_db.donation_types dt ON ib.donation_type_id = dt.id
                WHERE ib.quantity > 0
                GROUP BY dt.category, dt.sub_category
                ORDER BY dt.category ASC, dt.sub_category ASC
            ");
            while ($row = $inv_stmt->fetch()) {
                $row['committee_name'] = $com['name'];
                $inventory_data[] = $row;
            }
        } catch (PDOException $e) {}
    }
}

// تجميع البيانات للعرض المنظم
$inventory_grouped = [
    -1 => ['name' => 'إجمالي أرصدة النظام (جميع اللجان + الصندوق الرئيسي)', 'items' => []],
    0  => ['name' => 'الصندوق الرئيسي (مستودع الإدارة المركزية المتاح للتحويل)', 'items' => []]
];
foreach ($committees_list as $com) {
    $inventory_grouped[$com['id']] = ['name' => $com['name'], 'items' => []];
}

// حساب الإجمالي العام لكل الأصناف ليظهر في صندوق المدير (يعرض كل الإيرادات المتاحة في النظام)
try {
    $total_inventory_stmt = $pdo->query("
        SELECT dt.category, dt.sub_category, SUM(ib.quantity) as quantity 
        FROM inventory_balances ib
        JOIN donation_types dt ON ib.donation_type_id = dt.id
        WHERE ib.quantity > 0
        GROUP BY dt.category, dt.sub_category
        ORDER BY dt.category ASC, dt.sub_category ASC
    ");
    $inventory_grouped[-1]['items'] = $total_inventory_stmt->fetchAll();
} catch (PDOException $e) {
    $inventory_grouped[-1]['items'] = [];
}

foreach ($inventory_data as $row) {
    $cid = (int)$row['committee_id'];
    if (isset($inventory_grouped[$cid])) {
        $inventory_grouped[$cid]['items'][] = $row;
    }
}

require_once __DIR__ . '/header.php';
?>

<div class="row mb-4 mt-3">
    <div class="col-12 d-flex justify-content-between align-items-center">
        <h3 class="mb-0 text-primary"><i class="bi bi-boxes me-2"></i> إدارة الأرصدة والمخزون العيني</h3>
        <div>
            <a href="add_donor.php" class="btn btn-sm btn-success me-2 shadow-sm"><i class="bi bi-person-heart"></i> تسجيل تبرع مفصل للمتبرع</a>
            <a href="index.php" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-right"></i> العودة للوحة التحكم</a>
        </div>
    </div>
</div>

<?php if ($message): ?><div class="alert alert-success text-center"><?php echo $message; ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger text-center"><?php echo $error; ?></div><?php endif; ?>

<div class="row">
    <!-- النماذج الجانبية -->
    <div class="col-md-4 mb-4 d-flex flex-column gap-4">
        
        <!-- نموذج إضافة رصيد خارجي -->
        <div class="card shadow-sm border-0">
            <div class="card-header bg-success text-white"><h5 class="mb-0"><i class="bi bi-box-arrow-in-down me-2"></i> استلام تبرع / إيداع جديد</h5></div>
            <div class="card-body p-4">
                <form method="POST">
                    <input type="hidden" name="add_balance" value="1">
                    <div class="mb-3">
                        <label class="form-label fw-bold">الجهة المستلمة</label>
                        <select name="committee_id" id="committee_select" class="form-select form-select-lg" required>
                            <option value="">-- يرجى الاختيار --</option>
                            <option value="manager" class="text-primary fw-bold">⭐ الصندوق الرئيسي (حساب المدير)</option>
                            <optgroup label="لجان التوزيع المتوفرة:">
                                <?php foreach ($committees_list as $com): ?>
                                    <option value="<?php echo $com['id']; ?>"><?php echo htmlspecialchars($com['name']); ?></option>
                                <?php endforeach; ?>
                            </optgroup>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">نوع التبرع الوارد</label>
                        <select name="donation_type_id" id="donation_type_select" class="form-select form-select-lg" required>
                            <option value="">-- اختر التصنيف --</option>
                            <?php 
                            $current_cat = '';
                            foreach ($donation_types as $type): 
                                if ($current_cat !== $type['category']) {
                                    if ($current_cat !== '') echo '</optgroup>';
                                    echo '<optgroup label="تبرعات ' . htmlspecialchars($type['category']) . '">';
                                    $current_cat = $type['category'];
                                }
                            ?>
                                <option value="<?php echo $type['id']; ?>"><?php echo htmlspecialchars($type['sub_category']); ?></option>
                            <?php endforeach; echo '</optgroup>'; ?>
                        </select>
                    </div>
                    <div class="mb-4">
                        <label class="form-label fw-bold">المبلغ النقدي / أو الكمية (العدد)</label>
                        <input type="number" step="0.01" name="amount" class="form-control form-control-lg bg-light" required placeholder="مثال: 5000 للرصيد، أو 50 سلة غذائية">
                        <small class="text-muted">الأرقام للتبرعات العينية تعني عدد الوحدات المخزنة.</small>
                    </div>
                    <button type="submit" class="btn btn-success w-100 btn-lg"><i class="bi bi-check2-circle me-2"></i> تنفيذ الإيداع</button>
                </form>
            </div>
        </div>
        
        <!-- نموذج تحويل رصيد داخلي من المدير للجنة -->
        <div class="card shadow-sm border-0">
            <div class="card-header bg-primary text-white"><h5 class="mb-0"><i class="bi bi-arrow-left-right me-2"></i> تحويل رصيد للجان</h5></div>
            <div class="card-body p-4">
                <form method="POST">
                    <input type="hidden" name="transfer_balance" value="1">
                    <div class="mb-3">
                        <label class="form-label fw-bold">تحويل إلى (اللجنة المستلمة)</label>
                        <select name="to_committee_id" class="form-select form-select-lg" required>
                            <option value="">-- اختر اللجنة --</option>
                            <?php foreach ($committees_list as $com): ?>
                                <option value="<?php echo $com['id']; ?>"><?php echo htmlspecialchars($com['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">نوع الرصيد المراد تحويله</label>
                        <select name="transfer_donation_type_id" class="form-select form-select-lg" required>
                            <option value="">-- اختر التصنيف --</option>
                            <?php 
                            $current_cat = '';
                            foreach ($donation_types as $type): 
                                if ($current_cat !== $type['category']) {
                                    if ($current_cat !== '') echo '</optgroup>';
                                    echo '<optgroup label="تبرعات ' . htmlspecialchars($type['category']) . '">';
                                    $current_cat = $type['category'];
                                }
                            ?>
                                <option value="<?php echo $type['id']; ?>"><?php echo htmlspecialchars($type['sub_category']); ?></option>
                            <?php endforeach; echo '</optgroup>'; ?>
                        </select>
                    </div>
                    <div class="mb-4">
                        <label class="form-label fw-bold">المبلغ / الكمية للتحويل</label>
                        <input type="number" step="0.01" name="transfer_amount" class="form-control form-control-lg bg-light" required placeholder="الكمية المراد خصمها وإرسالها">
                    </div>
                    <button type="submit" class="btn btn-primary w-100 btn-lg"><i class="bi bi-send-check me-2"></i> تأكيد التحويل</button>
                </form>
            </div>
        </div>
        
        <!-- نموذج إضافة نوع تبرع جديد -->
        <div class="card shadow-sm border-0">
            <div class="card-header bg-secondary text-white"><h5 class="mb-0"><i class="bi bi-tags me-2"></i> إضافة نوع / مجال تبرع جديد</h5></div>
            <div class="card-body p-4">
                <form method="POST">
                    <input type="hidden" name="add_donation_type" value="1">
                    <div class="mb-3">
                        <label class="form-label fw-bold">الفئة الرئيسية</label>
                        <select name="category" class="form-select form-select-lg bg-light" required>
                            <option value="">-- اختر الفئة --</option>
                            <option value="نقدي">نقدي (مال وتبرعات نقدية)</option>
                            <option value="عيني">عيني (مواد، طرود، أجهزة)</option>
                            <option value="خدمات">خدمات (طبية، تعليمية، إغاثية)</option>
                        </select>
                    </div>
                    <div class="mb-4">
                        <label class="form-label fw-bold">اسم التصنيف (المجال) الجديد</label>
                        <input type="text" name="sub_category" class="form-control form-control-lg bg-light" required placeholder="مثال: كفالة أيتام، حقيبة مدرسية...">
                    </div>
                    <button type="submit" class="btn btn-secondary w-100 btn-lg"><i class="bi bi-plus-circle me-2"></i> إضافة التصنيف للنظام</button>
                </form>
            </div>
        </div>

    </div>

    <!-- عرض الأرصدة والمخزون التفصيلي -->
    <div class="col-md-8">
        <h4 class="text-secondary border-bottom pb-2 mb-4"><i class="bi bi-bar-chart-line me-2"></i> تقرير محافظ الأرصدة والمخزون المتوفر</h4>
        <div class="accordion" id="inventoryAccordion">
            <?php $i = 0; foreach ($inventory_grouped as $cid => $data): $i++; ?>
                <div class="accordion-item border-0 shadow-sm mb-3 rounded" style="overflow: hidden;">
                    <h2 class="accordion-header" id="heading<?php echo ($cid === -1 ? 'All' : $cid); ?>">
                        <button class="accordion-button <?php echo ($i !== 1) ? 'collapsed' : ''; ?> <?php echo ($cid===0) ? 'bg-primary text-white' : ($cid===-1 ? 'bg-dark text-white' : 'bg-light'); ?>" type="button" data-bs-toggle="collapse" data-bs-target="#collapse<?php echo ($cid === -1 ? 'All' : $cid); ?>">
                            <i class="bi <?php echo ($cid===0 || $cid===-1) ? 'bi-bank' : 'bi-building'; ?> me-2"></i> <?php echo htmlspecialchars($data['name']); ?>
                        </button>
                    </h2>
                    <div id="collapse<?php echo ($cid === -1 ? 'All' : $cid); ?>" class="accordion-collapse collapse <?php echo ($i === 1) ? 'show' : ''; ?>" data-bs-parent="#inventoryAccordion">
                        <div class="accordion-body p-0">
                            <?php if (empty($data['items'])): ?>
                                <div class="alert alert-light text-center m-3 text-muted border">المستودع فارغ، لا توجد أرصدة أو مواد متاحة حالياً.</div>
                            <?php else: ?>
                                <table class="table table-hover mb-0">
                                    <thead class="table-secondary">
                                        <tr>
                                            <th class="ps-3">الصنف / التصنيف</th>
                                            <th>النوع</th>
                                            <th>الكمية المتاحة (المخزون)</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($data['items'] as $item): ?>
                                            <tr>
                                                <td class="ps-3 fw-bold text-dark"><?php echo htmlspecialchars($item['sub_category']); ?></td>
                                                <td>
                                                    <?php if($item['category'] == 'نقدي'): ?>
                                                        <span class="badge bg-warning text-dark"><i class="bi bi-cash"></i> محفظة مالية</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-info text-dark"><i class="bi bi-box-seam"></i> مواد / خدمات</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="fs-5 text-success fw-bold"><?php echo ($item['category'] == 'نقدي') ? number_format($item['quantity'], 2) . ' JOD' : (int)$item['quantity']; ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<script>
// تحديد "الصندوق الرئيسي" تلقائياً عند اختيار "رصيد نقدي عام" لتسريع العملية
document.getElementById('donation_type_select')?.addEventListener('change', function() {
    var selectedText = this.options[this.selectedIndex].text;
    if (selectedText.includes('رصيد نقدي عام')) {
        document.getElementById('committee_select').value = 'manager';
    }
});
</script>

<?php require_once __DIR__ . '/footer.php'; ?>