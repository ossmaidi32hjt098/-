<?php
/**
 * Verification Gateway API
 * واجهة برمجية للتحقق من وجود المستفيد في لجان متعددة (لمنع الازدواجية)
 * مع جلب تفاصيل آخر مساعدة تلقاها للتحقق من أهلية الصرف.
 */
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *'); // السماح بالوصول من تطبيقات ومواقع خارجية
header('Access-Control-Allow-Methods: GET');
require_once __DIR__ . '/db.php';

$search_id = $_GET['national_id'] ?? '';

if (empty($search_id)) {
    http_response_code(400); // إرجاع كود خطأ HTTP 400 (Bad Request)
    echo json_encode(['status' => 'error', 'message' => 'الرقم الوطني مطلوب.']);
    exit;
}

$response = [
    'status' => 'success',
    'searched_id' => $search_id,
    'is_duplicate' => false,
    'found_in_count' => 0,
    'committees' => []
];

// 1. جلب سجل اللجان الفعالة ديناميكياً من البوابة المركزية
try {
    $registry = $pdo->query("SELECT id, committee_name FROM committees_registry ORDER BY id ASC")->fetchAll();
} catch (PDOException $e) {
    $registry = [];
}

// 2. المرور على قواعد بيانات اللجان (Scatter-Gather) بدون استخدام (break) لكشف كل التكرارات
foreach ($registry as $com) {
    $cid = $com['id'];
    
    if (isset($db_nodes[$cid])) {
        $db_name = $db_nodes[$cid];
        try {
            $node_dsn = "mysql:host=$host;dbname=$db_name;charset=$charset";
            $node_pdo = new PDO($node_dsn, $user, $pass, $options);
            
            // 1. التحقق من وجود المستفيد
            $stmt = $node_pdo->prepare('SELECT full_name, status FROM beneficiaries WHERE national_id = ? LIMIT 1');
            $stmt->execute([$search_id]);
            $result = $stmt->fetch();
            
            if ($result) {
                $committee_data = [
                    'committee_name' => $com['committee_name'],
                    'beneficiary_name' => $result['full_name'],
                    'file_status' => $result['status'],
                    'last_donation' => null
                ];
                
                // 2. جلب آخر مساعدة مصروفة (إن وجدت) لتقييم الازدواجية الزمنية
                $lastDonationStmt = $node_pdo->prepare("
                    SELECT dh.donation_date, dh.amount, dt.sub_category, dt.category
                    FROM donations_history dh
                    JOIN zakat_central_db.donation_types dt ON dh.donation_type_id = dt.id
                    WHERE dh.national_id = ? AND dh.donation_status = 'تم الصرف'
                    ORDER BY dh.donation_date DESC LIMIT 1
                ");
                $lastDonationStmt->execute([$search_id]);
                $lastDonation = $lastDonationStmt->fetch();
                
                if ($lastDonation) {
                    $committee_data['last_donation'] = [
                        'date' => $lastDonation['donation_date'],
                        'type' => $lastDonation['sub_category'],
                        'amount' => (float)$lastDonation['amount'],
                        'is_cash' => ($lastDonation['category'] == 'نقدي' || mb_strpos($lastDonation['category'], 'نقد') !== false)
                    ];
                }
                
                $response['committees'][] = $committee_data;
                $response['found_in_count']++;
            }
        } catch (PDOException $e) {
            // تجاهل القاعدة المعطلة والمتابعة
        }
    }
}

$response['is_duplicate'] = ($response['found_in_count'] > 1);
echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
?>