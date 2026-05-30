<?php
// جلب اللجان برمجياً لعرضها بشكل ديناميكي في القائمة المنسدلة العلوية
$nav_committees = [];
if (isset($pdo)) {
    try {
        $nav_committees = $pdo->query("SELECT id, committee_name as name FROM committees_registry ORDER BY id ASC")->fetchAll();
    } catch (PDOException $e) {
        $nav_committees = $pdo->query("SELECT id, name FROM committees ORDER BY id ASC")->fetchAll();
    }
}

$is_admin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;
$is_committee = isset($_SESSION['logged_in_committee']);

// جلب لغة المستخدم من الإعدادات لتغيير اتجاه واجهة النظام
$user_lang = 'ar';
if (isset($_SESSION['user_id']) && isset($pdo)) {
    try {
        $stmt_lang = $pdo->prepare("SELECT language FROM user_settings WHERE user_id = ?");
        $stmt_lang->execute([$_SESSION['user_id']]);
        $db_lang = $stmt_lang->fetchColumn();
        if ($db_lang) $user_lang = $db_lang;
    } catch (PDOException $e) {}
}
$is_en = ($user_lang === 'en');
$html_lang = $is_en ? 'en' : 'ar';
$html_dir = $is_en ? 'ltr' : 'rtl';
$bootstrap_css = $is_en ? 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css' : 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.rtl.min.css';

$unread_messages = 0;
$my_global_id = '';
if (isset($_SESSION['user_id']) && isset($pdo)) {
    $my_global_id = ($_SESSION['logged_in_committee'] ?? 0) . '_' . $_SESSION['user_id'];
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
        
        $msgStmt = $central_msg_pdo->prepare("SELECT COUNT(*) FROM global_messages WHERE receiver_global_id = ? AND is_read = 0");
        $msgStmt->execute([$my_global_id]);
        $unread_messages = $msgStmt->fetchColumn();
    } catch (PDOException $e) {}
}

// نظام الترجمة (i18n) المصغر
$translations = [
    'ar' => [
        'zakat_platform' => 'منصة زكاة', 'admin_dashboard' => 'لوحة الإدارة', 'receive_donation' => 'استلام تبرع (المتبرعين)',
        'my_committee' => 'لوحة لجنتي', 'committee_login' => 'دخول اللجان', 'beneficiaries_record' => 'سجل المستفيدين',
        'central_search' => 'البحث المركزي', 'statistics_center' => 'مركز الإحصائيات', 'zakat_calculator' => 'حاسبة الزكاة',
        'latest_news' => 'آخر الأخبار', 'system_admin' => 'مدير النظام', 'committee_user' => 'مستخدم اللجنة',
        'profile' => 'الملف الشخصي', 'account_settings' => 'إعدادات الحساب', 'change_password' => 'تغيير كلمة المرور',
        'logout' => 'تسجيل الخروج', 'admin_login' => 'دخول الإدارة', 'toggle_theme' => 'تبديل الوضع الداكن/المضيء',
        'account_settings_title' => 'إعدادات الحساب وتفضيلات النظام', 'display_prefs' => 'تفضيلات العرض واللغة',
        'system_lang' => 'لغة واجهة النظام', 'date_format' => 'تنسيق التاريخ الافتراضي', 'lang_ar' => 'العربية (Arabic)',
        'lang_en' => 'الإنجليزية (English)', 'date_gregorian' => 'الميلادي (Gregorian)', 'date_hijri' => 'الهجري (Hijri)',
        'update_prefs' => 'تحديث التفضيلات',
        'admin_dashboard_title' => 'لوحة تحكم الإدارة العليـا', 'admin_dashboard_desc' => 'نظرة شاملة على أداء النظام، إحصائيات المساعدات، وإدارة اللجان.',
        'users_btn' => 'المستخدمين', 'manage_finances_btn' => 'إدارة الأرصدة', 'total_beneficiaries' => 'إجمالي المستفيدين',
        'total_committees_entities' => 'اللجان والجهات المشاركة', 'active_users' => 'مستخدمي النظام النشطين',
        'manager_balance' => 'رصيد الصندوق الرئيسي (المدير)', 'total_available_balance' => 'إجمالي أرصدة اللجان المتوفرة',
        'latest_notifications' => 'أحدث الإشعارات والتنبيهات', 'aid_distribution_by_type' => 'توزيع المساعدات حسب النوع',
        'beneficiaries_by_committee' => 'المستفيدين حسب اللجنة', 'comprehensive_record' => 'السجل الشامل والتقارير السريعة',
        'export_excel' => 'تصدير (Excel)', 'national_id' => 'الرقم الوطني', 'full_name' => 'الاسم الكامل',
        'phone_number' => 'رقم الهاتف', 'file_status' => 'حالة الملف', 'actions' => 'الإجراءات', 'edit' => 'تعديل',
        'print' => 'طباعة', 'no_data_yet' => 'لا توجد بيانات مسجلة حتى الآن.', 'beneficiary_word' => 'مستفيد',
        'committee_dashboard' => 'لوحة تحكم', 'unique_beneficiaries' => 'إجمالي المستفيدين (بدون تكرار)',
        'completed_operations' => 'إجمالي عمليات التوزيع المنجزة', 'current_committee_balance' => 'رصيد اللجنة الحالي',
        'quick_actions' => 'إجراءات سريعة', 'record_new_distribution' => 'تسجيل توزيع جديد', 'add_new_beneficiary' => 'إضافة مستفيد جديد',
        'print_reports_pdf' => 'طباعة التقارير (PDF)', 'beneficiaries_list' => 'قائمة المستفيدين', 'search_name_id' => 'بحث بالاسم أو الهوية...',
        'detailed_distribution_record' => 'سجل التوزيعات المفصل', 'type_amount' => 'نوع وقيمة التبرع', 'date_status' => 'التاريخ والحالة',
        'delivery_source' => 'طريقة التسليم والمصدر', 'responsible_employee' => 'الموظف المسؤول', 'notes' => 'ملاحظات',
        'system_stable' => 'النظام يعمل بشكل مستقر.', 'admin_login_success' => 'تم تسجيل دخول مدير النظام بنجاح.',
        'check_audit_logs' => 'يرجى مراجعة سجلات التدقيق للاطلاع على محاولات الازدواجية.', 'no_sufficient_data' => 'لا توجد بيانات كافية.',
        'unspecified' => 'غير محدد', 'not_found' => 'لا يوجد',
        'footer_copyright' => 'جميع الحقوق محفوظة.', 'select_committee_title' => 'اختر لجنتك لتسجيل الدخول',
        'select_committee_desc' => 'يرجى اختيار اللجنة أو الجمعية التي تتبع لها من القائمة أدناه للانتقال إلى بوابة الدخول الخاصة بها.', 'enter_platform' => 'دخول المنصة',
        'stats_reports' => 'مركز الإحصائيات والتقارير الشاملة', 'total_incoming' => 'إجمالي التبرعات (الواردة)', 'financial_donations' => 'قيمة التبرعات المالية',
        'inkind_donations_received' => 'تبرعات عينية وردت', 'total_donors' => 'عدد المتبرعين', 'active_campaigns' => 'حملات نشطة', 'registered_committees' => 'لجان وجمعيات مسجلة',
        'aids_this_month' => 'مساعدات الشهر الحالي', 'donations_this_year' => 'تبرعات السنة الحالية', 'req_status' => 'حالة طلبات المستفيدين', 'top_aid_types' => 'أكثر أنواع المساعدات تقديماً',
        'most_active_committees' => 'أكثر اللجان نشاطاً', 'distribution_status' => 'حالة عمليات التوزيع', 'latest_disbursed_aids' => 'أحدث المساعدات المصروفة', 'latest_registered_beneficiaries' => 'أحدث المستفيدين المسجلين',
        'beneficiaries_record_committee' => 'سجل المستفيدين (لجنتي)', 'beneficiaries_record_committee_desc' => 'تحتوي هذه القائمة حصراً على المستفيدين التابعين للجنة الخاصة بك لضمان الخصوصية.', 'beneficiaries_record_system' => 'السجل الشامل للمستفيدين (النظام)', 'beneficiaries_record_system_desc' => 'تحتوي هذه القائمة على جميع المستفيدين المسجلين في النظام عبر كافة اللجان.',
        'search_global' => 'بحث عام بالرقم الوطني أو الاسم...', 'central_search_title' => 'البحث المركزي في السجل الموحد', 'central_search_desc' => 'ابحث عن أي مستفيد للتحقق من بياناته، لمعرفة تاريخ ونوع دعم تلقاه لمنع الازدواجية.', 'search_placeholder' => 'الرقم الوطني أو الاسم...', 'search_btn' => 'بحث', 'search_results_for' => 'نتائج البحث عن:', 'basic_info' => 'البيانات الأساسية', 'family_economic_status' => 'العائلة والوضع الاقتصادي', 'health_education_status' => 'الصحة والتعليم', 'aid_history' => 'سجل المساعدات والتوزيعات المستلمة', 'donor_entity' => 'الجهة المانحة والموظف',
        'receive_donation_title' => 'استلام تبرع وارد (سجل المتبرعين)', 'back_to_finances' => 'العودة لإدارة الأرصدة',
        'donor_info' => 'بيانات المتبرع', 'donor_name_label' => 'اسم المتبرع أو الجهة المانحة', 'donor_name_placeholder' => 'فاعل خير (اتركه فارغاً إذا كان مجهولاً)',
        'donation_receive_date' => 'تاريخ استلام التبرع', 'campaign_name_label' => 'اسم الحملة المرتبطة (إن وجدت)', 'campaign_name_placeholder' => 'مثال: حملة إغاثة غزة، حملة شتاء دافئ...',
        'donation_details' => 'تفاصيل التبرع', 'donation_type_domain' => 'نوع ومجال التبرع', 'select_donation_domain' => '-- اختر مجال التبرع --',
        'donation_delivery_method' => 'طريقة تسليم التبرع', 'cash_payment' => 'نقدي (كاش)', 'bank_transfer' => 'تحويل بنكي', 'electronic_payment' => 'دفع إلكتروني (فيزا/محفظة)',
        'bank_check' => 'شيك بنكي', 'inkind_donation_materials' => 'تبرع عيني (مواد وطرود)', 'donation_value_quantity' => 'قيمة التبرع / الكمية',
        'enter_amount_quantity' => 'أدخل المبلغ أو العدد', 'currency_cash_donations' => 'العملة (للتبرعات النقدية)', 'currency_jod' => 'دينار أردني (JOD)',
        'currency_usd' => 'دولار أمريكي (USD)', 'currency_eur' => 'يورو (EUR)', 'routing_notes' => 'التوجيه والملاحظات', 'benefiting_entity' => 'الجهة المستفيدة من التبرع (أين سيتم إيداع الرصيد؟)',
        'main_institution_fund' => '⭐ المؤسسة الرئيسية (الصندوق العام للإدارة)', 'direct_to_specific_committee' => 'أو توجيه التبرع للجنة توزيع محددة:',
        'committee_label' => 'لجنة:', 'additional_notes_optional' => 'ملاحظات إضافية (اختياري)', 'notes_placeholder' => 'أضف أي شروط أو وصية للمتبرع هنا...',
        'receive_save_donation' => 'استلام وحفظ التبرع بشكل رسمي', 'donations_of' => 'تبرعات ',
        'committee_warehouse' => 'مستودع اللجنة الحالي', 'warehouse_empty' => 'المستودع فارغ حالياً.',
        'guest_search' => 'استعلام المستفيدين'
    ],
    'en' => [
        'zakat_platform' => 'Zakat Platform', 'admin_dashboard' => 'Admin Dashboard', 'receive_donation' => 'Receive Donation',
        'my_committee' => 'My Committee', 'committee_login' => 'Committee Login', 'beneficiaries_record' => 'Beneficiaries Record',
        'central_search' => 'Central Search', 'statistics_center' => 'Statistics', 'zakat_calculator' => 'Zakat Calculator',
        'latest_news' => 'Latest News', 'system_admin' => 'System Admin', 'committee_user' => 'Committee User',
        'profile' => 'Profile', 'account_settings' => 'Account Settings', 'change_password' => 'Change Password',
        'logout' => 'Logout', 'admin_login' => 'Admin Login', 'toggle_theme' => 'Toggle Dark/Light Mode',
        'account_settings_title' => 'Account Settings & Preferences', 'display_prefs' => 'Display & Language Preferences',
        'system_lang' => 'System Language', 'date_format' => 'Default Date Format', 'lang_ar' => 'Arabic (العربية)',
        'lang_en' => 'English (الإنجليزية)', 'date_gregorian' => 'Gregorian', 'date_hijri' => 'Hijri',
        'update_prefs' => 'Update Preferences',
        'admin_dashboard_title' => 'Super Admin Dashboard', 'admin_dashboard_desc' => 'Comprehensive overview of system performance, aid statistics, and committee management.',
        'users_btn' => 'Users', 'manage_finances_btn' => 'Manage Finances', 'total_beneficiaries' => 'Total Beneficiaries',
        'total_committees_entities' => 'Committees & Participating Entities', 'active_users' => 'Active System Users',
        'manager_balance' => 'Main Fund Balance (Admin)', 'total_available_balance' => 'Total Available Committees Balances',
        'latest_notifications' => 'Latest Notifications & Alerts', 'aid_distribution_by_type' => 'Aid Distribution by Type',
        'beneficiaries_by_committee' => 'Beneficiaries by Committee', 'comprehensive_record' => 'Comprehensive Record & Quick Reports',
        'export_excel' => 'Export (Excel)', 'national_id' => 'National ID', 'full_name' => 'Full Name',
        'phone_number' => 'Phone Number', 'file_status' => 'File Status', 'actions' => 'Actions', 'edit' => 'Edit',
        'print' => 'Print', 'no_data_yet' => 'No data registered yet.', 'beneficiary_word' => 'Beneficiary',
        'committee_dashboard' => 'Dashboard', 'unique_beneficiaries' => 'Total Unique Beneficiaries',
        'completed_operations' => 'Completed Distribution Operations', 'current_committee_balance' => 'Current Committee Balance',
        'quick_actions' => 'Quick Actions', 'record_new_distribution' => 'Record New Distribution', 'add_new_beneficiary' => 'Add New Beneficiary',
        'print_reports_pdf' => 'Print Reports (PDF)', 'beneficiaries_list' => 'Beneficiaries List', 'search_name_id' => 'Search by Name or ID...',
        'detailed_distribution_record' => 'Detailed Distribution Record', 'type_amount' => 'Donation Type & Amount', 'date_status' => 'Date & Status',
        'delivery_source' => 'Delivery Method & Source', 'responsible_employee' => 'Responsible Employee', 'notes' => 'Notes',
        'system_stable' => 'System is running stable.', 'admin_login_success' => 'Admin logged in successfully.',
        'check_audit_logs' => 'Please review audit logs for duplication attempts.', 'no_sufficient_data' => 'Not enough data available.',
        'unspecified' => 'Unspecified', 'not_found' => 'N/A',
        'footer_copyright' => 'All rights reserved.', 'select_committee_title' => 'Select Your Committee to Login',
        'select_committee_desc' => 'Please select the committee or association you belong to from the list below to access its portal.', 'enter_platform' => 'Enter Platform',
        'stats_reports' => 'Statistics & Comprehensive Reports Center', 'total_incoming' => 'Total Incoming Donations', 'financial_donations' => 'Financial Donations Value',
        'inkind_donations_received' => 'In-kind Donations Received', 'total_donors' => 'Total Donors', 'active_campaigns' => 'Active Campaigns', 'registered_committees' => 'Registered Committees',
        'aids_this_month' => 'Aids This Month', 'donations_this_year' => 'Donations This Year', 'req_status' => 'Beneficiaries Requests Status', 'top_aid_types' => 'Top Provided Aid Types',
        'most_active_committees' => 'Most Active Committees', 'distribution_status' => 'Distribution Operations Status', 'latest_disbursed_aids' => 'Latest Disbursed Aids', 'latest_registered_beneficiaries' => 'Latest Registered Beneficiaries',
        'beneficiaries_record_committee' => 'Beneficiaries Record (My Committee)', 'beneficiaries_record_committee_desc' => 'This list exclusively contains beneficiaries belonging to your committee to ensure privacy.', 'beneficiaries_record_system' => 'Comprehensive Beneficiaries Record (System)', 'beneficiaries_record_system_desc' => 'This list contains all beneficiaries registered in the system across all committees.',
        'search_global' => 'Global search by National ID or Name...', 'central_search_title' => 'Central Search in Unified Record', 'central_search_desc' => 'Search for any beneficiary to verify their data and check the date and type of the last aid received to prevent duplication.', 'search_placeholder' => 'National ID or Name...', 'search_btn' => 'Search', 'search_results_for' => 'Search Results for:', 'basic_info' => 'Basic Information', 'family_economic_status' => 'Family & Economic Status', 'health_education_status' => 'Health & Education', 'aid_history' => 'Received Aids & Distributions History', 'donor_entity' => 'Donor Entity & Employee',
        'receive_donation_title' => 'Receive Incoming Donation (Donors Log)', 'back_to_finances' => 'Back to Finances Management',
        'donor_info' => 'Donor Information', 'donor_name_label' => 'Donor Name or Grantor Entity', 'donor_name_placeholder' => 'Anonymous (leave blank if unknown)',
        'donation_receive_date' => 'Donation Receive Date', 'campaign_name_label' => 'Associated Campaign Name (if any)', 'campaign_name_placeholder' => 'Example: Gaza Relief Campaign, Warm Winter...',
        'donation_details' => 'Donation Details', 'donation_type_domain' => 'Donation Type & Domain', 'select_donation_domain' => '-- Select Donation Domain --',
        'donation_delivery_method' => 'Donation Delivery Method', 'cash_payment' => 'Cash', 'bank_transfer' => 'Bank Transfer', 'electronic_payment' => 'Electronic Payment (Visa/Wallet)',
        'bank_check' => 'Bank Check', 'inkind_donation_materials' => 'In-kind Donation (Materials & Parcels)', 'donation_value_quantity' => 'Donation Value / Quantity',
        'enter_amount_quantity' => 'Enter Amount or Quantity', 'currency_cash_donations' => 'Currency (for cash donations)', 'currency_jod' => 'Jordanian Dinar (JOD)',
        'currency_usd' => 'US Dollar (USD)', 'currency_eur' => 'Euro (EUR)', 'routing_notes' => 'Routing & Notes', 'benefiting_entity' => 'Benefiting Entity (Where will the balance be deposited?)',
        'main_institution_fund' => '⭐ Main Institution (General Admin Fund)', 'direct_to_specific_committee' => 'Or direct donation to a specific distribution committee:',
        'committee_label' => 'Committee:', 'additional_notes_optional' => 'Additional Notes (Optional)', 'notes_placeholder' => 'Add any conditions or donor\'s will here...',
        'receive_save_donation' => 'Receive and Save Donation Officially', 'donations_of' => 'Donations of ',
        'committee_warehouse' => 'Current Committee Warehouse', 'warehouse_empty' => 'Warehouse is currently empty.',
        'guest_search' => 'Guest Search'
    ]
];

if (!function_exists('t')) {
    function t($key) {
        global $translations, $user_lang;
        $lang = isset($translations[$user_lang]) ? $user_lang : 'ar';
        return $translations[$lang][$key] ?? $translations['ar'][$key] ?? $key;
    }
}

?>
<!DOCTYPE html>
<html lang="<?php echo $html_lang; ?>" dir="<?php echo $html_dir; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>منصة زكاة | لإدارة العمل الخيري</title>
    <link rel="stylesheet" href="<?php echo $bootstrap_css; ?>">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        /* تغيير الألوان الأساسية لنسق حديث (Teal / الفيروزي) */
        :root {
            --bs-primary: #0d9488;
            --bs-primary-rgb: 13, 148, 136;
            --bs-info: #06b6d4;
            --bs-success: #10b981;
            --bs-warning: #f59e0b;
            --bs-body-bg: #f3f4f6;
        }
        body {
            font-family: 'Tajawal', sans-serif;
            background-color: var(--bs-body-bg);
            color: #374151;
        }
        /* تصميم الشريط العلوي */
        .navbar-custom {
            background: linear-gradient(90deg, #115e59, #0d9488) !important;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            padding: 12px 0;
        }
        .navbar-brand { font-weight: 700; font-size: 1.6rem; letter-spacing: 0.5px; }
        /* تصميم البطاقات العصري */
        .card {
            border: none !important;
            border-radius: 16px !important;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        .card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        }
        /* الأزرار الدائرية والناعمة */
        .btn { border-radius: 10px; font-weight: 500; letter-spacing: 0.3px; transition: all 0.3s; }
        .btn-primary { background: linear-gradient(90deg, #0d9488, #14b8a6); border: none; }
        .btn-primary:hover { background: linear-gradient(90deg, #0f766e, #0d9488); transform: translateY(-1px); box-shadow: 0 4px 12px rgba(13, 148, 136, 0.3); }
        /* تحسينات الجداول */
        .table > :not(caption) > * > * { padding: 1rem 0.75rem; }
        .table-light th { background-color: #f8fafc; color: #4b5563; font-weight: 700; border-bottom: 2px solid #e5e7eb; }
        /* شارات الحالة */
        .badge { font-weight: 500; padding: 0.5em 0.85em; border-radius: 8px; }
        
        /* تأثير رفع البطاقات */
        .hover-lift { transition: transform 0.2s ease, box-shadow 0.2s ease; }
        .hover-lift:hover { transform: translateY(-5px); box-shadow: 0 10px 20px rgba(0,0,0,0.1) !important; border-bottom: 4px solid var(--bs-primary) !important; }
        
        /* تنسيقات حاسبة الزكاة */
        .zakat-calc-modal .modal-content { border-radius: 20px; border: none; }
        .zakat-calc-modal .modal-header { background: linear-gradient(90deg, #115e59, #0d9488); color: white; border-top-right-radius: 20px; border-top-left-radius: 20px; }
        .zakat-section { background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 20px; margin-bottom: 15px; position: relative; }
        .zakat-section-title { color: #0d9488; font-weight: bold; border-bottom: 2px solid #e2e8f0; padding-bottom: 10px; margin-bottom: 15px; }
        .btn-delete-sec { position: absolute; top: 15px; left: 15px; border-radius: 8px; }
        .calc-result-box { background-color: #ecfdf5; border: 1px solid #10b981; border-radius: 12px; padding: 20px; text-align: center; }
        .type-checkboxes .btn-check:checked + .btn { background-color: #0d9488; color: white; border-color: #0d9488; }

        /* إيقاف الانتقال العادي أثناء تأثير الانتشار لمنع التداخل */
        html:not(.view-transition-active) body, 
        html:not(.view-transition-active) .card, 
        html:not(.view-transition-active) .navbar, 
        html:not(.view-transition-active) .bg-white, 
        html:not(.view-transition-active) .bg-light, 
        html:not(.view-transition-active) .modal-content { 
            transition: background-color 0.3s ease, color 0.3s ease, border-color 0.3s ease; 
        }

        /* إعدادات تأثير الانتشار الدائري (View Transitions API) */
        ::view-transition-old(root), ::view-transition-new(root) { animation: none; mix-blend-mode: normal; }
        ::view-transition-old(root) { z-index: 1; }
        ::view-transition-new(root) { z-index: 2; }

        /* الوضع الداكن (Dark Mode) - تحسين التناسق الشامل */
        html.dark-mode body { background-color: #0f172a !important; color: #e2e8f0 !important; }
        html.dark-mode .bg-white { background-color: #1e293b !important; color: #e2e8f0 !important; }
        html.dark-mode .bg-light { background-color: #334155 !important; color: #e2e8f0 !important; }
        html.dark-mode .card { background-color: #1e293b !important; border-color: #334155 !important; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.4) !important; }
        html.dark-mode .card-header, html.dark-mode .card-footer { background-color: #1e293b !important; border-color: #334155 !important; }
        
        /* النصوص (تحسين التباين) */
        html.dark-mode .text-dark { color: #f8fafc !important; }
        html.dark-mode .text-muted { color: #94a3b8 !important; }
        html.dark-mode .text-secondary { color: #cbd5e1 !important; }
        html.dark-mode .text-primary { color: #2dd4bf !important; } /* تفتيح اللون الفيروزي */
        html.dark-mode .text-success { color: #34d399 !important; } /* تفتيح اللون الأخضر */
        html.dark-mode .text-info { color: #38bdf8 !important; }
        html.dark-mode .text-warning { color: #fbbf24 !important; }
        html.dark-mode .text-danger { color: #f87171 !important; }
        
        /* الجداول والقوائم */
        html.dark-mode .table, html.dark-mode .table-bordered > :not(caption) > * > * { color: #e2e8f0 !important; border-color: #334155 !important; }
        html.dark-mode .table-light th, html.dark-mode .table-secondary th { background-color: #334155 !important; color: #f8fafc !important; border-color: #475569 !important; }
        html.dark-mode .table-hover tbody tr:hover { background-color: #334155 !important; color: #f8fafc !important; }
        html.dark-mode .list-group-item { background-color: #1e293b !important; color: #e2e8f0 !important; border-color: #334155 !important; }
        
        /* النماذج وحقول الإدخال (Inputs & Forms) */
        html.dark-mode .form-control, html.dark-mode .form-select, html.dark-mode .input-group-text { background-color: #0f172a !important; color: #f8fafc !important; border-color: #475569 !important; }
        html.dark-mode .form-control:focus, html.dark-mode .form-select:focus { background-color: #1e293b !important; border-color: #2dd4bf !important; color: #f8fafc !important; box-shadow: 0 0 0 0.25rem rgba(45, 212, 191, 0.25) !important; }
        html.dark-mode .form-control::placeholder { color: #64748b !important; }
        html.dark-mode .form-check-input { background-color: #334155 !important; border-color: #475569 !important; }
        
        /* التنبيهات (Alerts) */
        html.dark-mode .alert-light { background-color: #1e293b !important; border-color: #334155 !important; color: #e2e8f0 !important; }
        html.dark-mode .alert-info { background-color: #083344 !important; border-color: #164e63 !important; color: #cffafe !important; }
        html.dark-mode .alert-success { background-color: #064e3b !important; border-color: #065f46 !important; color: #d1fae5 !important; }
        html.dark-mode .alert-warning { background-color: #78350f !important; border-color: #92400e !important; color: #fef3c7 !important; }
        html.dark-mode .alert-danger { background-color: #7f1d1d !important; border-color: #991b1b !important; color: #fee2e2 !important; }
        
        /* المكونات الأخرى (Modals, Accordions, Borders) */
        html.dark-mode .modal-content { background-color: #1e293b !important; border-color: #334155 !important; }
        html.dark-mode .border, html.dark-mode .border-bottom, html.dark-mode .border-top { border-color: #334155 !important; }
        html.dark-mode footer { background-color: #1e293b !important; border-top-color: #334155 !important; }
        html.dark-mode .accordion-item { background-color: #1e293b !important; border-color: #334155 !important; }
        html.dark-mode .accordion-button { background-color: #334155 !important; color: #e2e8f0 !important; border-color: #475569 !important; }
        html.dark-mode .accordion-button:not(.collapsed) { background-color: #0f172a !important; color: #2dd4bf !important; }
        html.dark-mode .accordion-button::after { filter: invert(1) grayscale(100%) brightness(200%); }
        
        /* القوائم المنسدلة (Dropdowns) */
        html.dark-mode .dropdown-menu { background-color: #1e293b !important; border-color: #334155 !important; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.4) !important; }
        html.dark-mode .dropdown-item { color: #e2e8f0 !important; }
        html.dark-mode .dropdown-item:hover, html.dark-mode .dropdown-item:focus { background-color: #334155 !important; color: #f8fafc !important; }
        html.dark-mode .dropdown-divider { border-color: #475569 !important; }

        /* حاسبة الزكاة */
        html.dark-mode .zakat-section { background-color: #334155 !important; border-color: #475569 !important; }
        html.dark-mode .zakat-section-title { border-bottom-color: #475569 !important; color: #2dd4bf !important; }
        html.dark-mode .calc-result-box { background-color: #064e3b !important; border-color: #059669 !important; color: #f8fafc !important;}
    </style>
    <script>
        // التحقق من الوضع الداكن المخزن مسبقاً لتطبيقه مبكراً لمنع الوميض الأبيض (FOUC)
        if (localStorage.getItem('dark_mode') === 'enabled') {
            document.documentElement.classList.add('dark-mode');
        }
    </script>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark navbar-custom">
  <div class="container">
    <a class="navbar-brand" href="index.php"><i class="bi bi-heart-pulse-fill text-warning me-1"></i> <?php echo t('zakat_platform'); ?></a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-toggle="collapse" data-bs-target="#navbarNav">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navbarNav">
     <ul class="navbar-nav me-auto">
    <?php if ($is_admin): ?>
    <li class="nav-item">
        <a class="nav-link active" href="index.php"><i class="bi bi-house-door"></i> <?php echo t('admin_dashboard'); ?></a>
    </li>
    <li class="nav-item">
        <a class="nav-link" href="add_donor.php"><i class="bi bi-person-heart"></i> <?php echo t('receive_donation'); ?></a>
    </li>
    <?php elseif ($is_committee): ?>
    <li class="nav-item">
        <a class="nav-link active" href="committee.php?id=<?php echo $_SESSION['logged_in_committee']; ?>"><i class="bi bi-house-door"></i> <?php echo t('my_committee'); ?></a>
    </li>
    <li class="nav-item">
        <a class="nav-link" href="add_donor.php"><i class="bi bi-person-heart"></i> <?php echo t('receive_donation'); ?></a>
    </li>
    <?php endif; ?>
    
    <?php if (!$is_admin && !$is_committee): ?>
    <li class="nav-item">
        <a class="nav-link" href="select_committee.php"><i class="bi bi-diagram-3"></i> <?php echo t('committee_login'); ?></a>
    </li>
    <?php endif; ?>
    
    <?php if ($is_admin || $is_committee): ?>
    <li class="nav-item">
        <a class="nav-link" href="inbox.php">
            <i class="bi bi-envelope"></i> المراسلات
            <?php if($unread_messages > 0): ?>
                <span class="badge bg-danger rounded-pill ms-1"><?php echo $unread_messages; ?></span>
            <?php endif; ?>
        </a>
    </li>
    <?php endif; ?>
    
    <li class="nav-item">
        <a class="nav-link text-info fw-bold" href="guest_search.php"><i class="bi bi-search"></i> <?php echo t('guest_search'); ?></a>
    </li>
    
    <?php if ($is_admin || $is_committee): ?>
    <li class="nav-item">
        <a class="nav-link" href="all_beneficiaries.php"><i class="bi bi-folder2-open"></i> <?php echo t('beneficiaries_record'); ?></a>
    </li>
    
    <li class="nav-item">
        <a class="nav-link" href="statistics.php"><i class="bi bi-bar-chart-line-fill text-warning"></i> <?php echo t('statistics_center'); ?></a>
    </li>
    
    <li class="nav-item">
        <a class="nav-link text-warning fw-bold" href="#" data-bs-toggle="modal" data-bs-target="#zakatCalculatorModal"><i class="bi bi-calculator-fill"></i> <?php echo t('zakat_calculator'); ?></a>
    </li>
    
    <li class="nav-item">
        <a class="nav-link" href="https://news.un.org/ar/news" target="_blank"><i class="bi bi-newspaper text-info"></i> <?php echo t('latest_news'); ?></a>
    </li>
    <?php endif; ?>
</ul>
      <button id="darkModeToggle" class="btn btn-sm btn-outline-light rounded-pill px-3 me-3 ms-2" title="<?php echo t('toggle_theme'); ?>">
          <i class="bi bi-moon-stars-fill" id="darkModeIcon"></i>
      </button>
      <?php if ($is_admin): ?>
          <div class="dropdown d-inline-block">
              <button class="btn btn-sm btn-outline-light rounded-pill px-3 dropdown-toggle d-flex align-items-center gap-2" type="button" id="adminProfileDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                  <i class="bi bi-person-circle fs-5"></i>
                  <span><?php echo htmlspecialchars($_SESSION['user_name'] ?? t('system_admin')); ?></span>
              </button>
              <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0 mt-2" aria-labelledby="adminProfileDropdown" style="border-radius: 12px; min-width: 200px;">
                  <li><a class="dropdown-item py-2" href="profile.php"><i class="bi bi-person me-2 text-primary"></i> <?php echo t('profile'); ?></a></li>
                  <li><a class="dropdown-item py-2" href="account_settings.php"><i class="bi bi-gear me-2 text-secondary"></i> <?php echo t('account_settings'); ?></a></li>
                  <li><hr class="dropdown-divider"></li>
                  <li><a class="dropdown-item py-2 text-danger" href="logout.php"><i class="bi bi-box-arrow-right me-2"></i> <?php echo t('logout'); ?></a></li>
              </ul>
          </div>
      <?php elseif ($is_committee): ?>
          <div class="dropdown d-inline-block">
              <button class="btn btn-sm btn-outline-light rounded-pill px-3 dropdown-toggle d-flex align-items-center gap-2" type="button" id="committeeProfileDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                  <i class="bi bi-person-circle fs-5"></i>
                  <span><?php echo htmlspecialchars($_SESSION['user_name'] ?? t('committee_user')); ?></span>
              </button>
              <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0 mt-2" aria-labelledby="committeeProfileDropdown" style="border-radius: 12px; min-width: 200px;">
                  <li><a class="dropdown-item py-2" href="profile.php"><i class="bi bi-person me-2 text-primary"></i> <?php echo t('profile'); ?></a></li>
                  <li><a class="dropdown-item py-2" href="account_settings.php"><i class="bi bi-gear me-2 text-secondary"></i> <?php echo t('account_settings'); ?></a></li>
                  <li><hr class="dropdown-divider"></li>
                  <li><a class="dropdown-item py-2 text-danger" href="logout.php"><i class="bi bi-box-arrow-right me-2"></i> <?php echo t('logout'); ?></a></li>
              </ul>
          </div>
      <?php else: ?>
          <a href="admin_login.php" class="btn btn-sm btn-light text-primary fw-bold rounded-pill px-3"><i class="bi bi-shield-lock-fill"></i> <?php echo t('admin_login'); ?></a>
      <?php endif; ?>
    </div>
  </div>
</nav>

<!-- نافذة حاسبة الزكاة (Modal) -->
<div class="modal fade zakat-calc-modal" id="zakatCalculatorModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-xl">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title fw-bold"><i class="bi bi-calculator"></i> الحاسبة الذكية للزكاة</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body p-4">
        
        <!-- 1. سنة الحول -->
        <div class="mb-4 p-3 border rounded bg-light">
            <label class="fw-bold mb-2 d-block text-dark">اختر سنة الحول للزكاة:</label>
            <div class="form-check form-check-inline">
                <input class="form-check-input" type="radio" name="hawl_type" id="hawl_hijri" value="hijri" checked onchange="calcZakat()">
                <label class="form-check-label" for="hawl_hijri">سنة هجرية (نسبة 2.5%)</label>
            </div>
            <div class="form-check form-check-inline">
                <input class="form-check-input" type="radio" name="hawl_type" id="hawl_greg" value="gregorian" onchange="calcZakat()">
                <label class="form-check-label" for="hawl_greg">سنة ميلادية (نسبة 2.577%)</label>
            </div>
        </div>

        <!-- 2. اختيار أنواع الزكاة -->
        <div class="mb-4">
            <label class="fw-bold mb-2 d-block text-dark">اختر الموجودات الزكوية (يمكنك اختيار أكثر من خيار):</label>
            <div class="d-flex flex-wrap gap-2 type-checkboxes">
                <input type="checkbox" class="btn-check" id="chk_money" onchange="toggleSec('sec_money', this.checked)">
                <label class="btn btn-outline-secondary rounded-pill" for="chk_money">زكاة المال</label>
                
                <input type="checkbox" class="btn-check" id="chk_gold" onchange="toggleSec('sec_gold', this.checked)">
                <label class="btn btn-outline-secondary rounded-pill" for="chk_gold">زكاة الذهب</label>
                
                <input type="checkbox" class="btn-check" id="chk_silver" onchange="toggleSec('sec_silver', this.checked)">
                <label class="btn btn-outline-secondary rounded-pill" for="chk_silver">زكاة الفضة</label>
                
                <input type="checkbox" class="btn-check" id="chk_stocks" onchange="toggleSec('sec_stocks', this.checked)">
                <label class="btn btn-outline-secondary rounded-pill" for="chk_stocks">زكاة الأسهم والصناديق</label>
                
                <input type="checkbox" class="btn-check" id="chk_crops" onchange="toggleSec('sec_crops', this.checked)">
                <label class="btn btn-outline-secondary rounded-pill" for="chk_crops">زكاة الزروع والثمار</label>
                
                <input type="checkbox" class="btn-check" id="chk_livestock" onchange="toggleSec('sec_livestock', this.checked)">
                <label class="btn btn-outline-secondary rounded-pill" for="chk_livestock">زكاة الأنعام</label>
                
                <input type="checkbox" class="btn-check" id="chk_companies" onchange="toggleSec('sec_companies', this.checked)">
                <label class="btn btn-outline-secondary rounded-pill" for="chk_companies">زكاة المؤسسات والشركات</label>
            </div>
        </div>

        <hr>

        <!-- الأقسام الديناميكية -->
        <div id="calculator_sections">
            
            <!-- زكاة المال -->
            <div class="zakat-section" id="sec_money" style="display:none;">
                <button type="button" class="btn btn-sm btn-danger btn-delete-sec" onclick="document.getElementById('chk_money').click();"><i class="bi bi-trash"></i> Delete</button>
                <h5 class="zakat-section-title"><i class="bi bi-cash-stack"></i> زكاة المال</h5>
                <p class="text-muted small">يجب مرور حول كامل وإضافة الديون المرجوة السداد بالإضافة إلى النقد.</p>
                <label class="form-label">أدخل مبلغ المال:</label>
                <input type="number" id="val_money" class="form-control" placeholder="أدخل المبلغ" oninput="calcZakat()">
            </div>

            <!-- زكاة الذهب -->
            <div class="zakat-section" id="sec_gold" style="display:none;">
                <button type="button" class="btn btn-sm btn-danger btn-delete-sec" onclick="document.getElementById('chk_gold').click();"><i class="bi bi-trash"></i> Delete</button>
                <h5 class="zakat-section-title"><i class="bi bi-heptagon-fill text-warning"></i> زكاة الذهب <span class="badge bg-warning text-dark ms-2 float-end">اخر تحديث لسعر الذهب 15/04/2026</span></h5>
                <p class="text-muted small">يجب مرور حول كامل.</p>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">اختر نوع الذهب:</label>
                        <select id="val_gold_carat" class="form-select" onchange="calcZakat()">
                            <option value="112.30">عيار 24 - السعر: 112.30</option>
                            <option value="98.26">عيار 21 - السعر: 98.26</option>
                            <option value="84.22">عيار 18 - السعر: 84.22</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">أدخل وزن الذهب (بالجرام):</label>
                        <input type="number" id="val_gold_weight" class="form-control" placeholder="أدخل الوزن" oninput="calcZakat()">
                    </div>
                </div>
            </div>

            <!-- زكاة الفضة -->
            <div class="zakat-section" id="sec_silver" style="display:none;">
                <button type="button" class="btn btn-sm btn-danger btn-delete-sec" onclick="document.getElementById('chk_silver').click();"><i class="bi bi-trash"></i> Delete</button>
                <h5 class="zakat-section-title"><i class="bi bi-circle-fill text-secondary"></i> زكاة الفضة</h5>
                <p class="text-muted small">يجب مرور حول كامل.</p>
                <label class="form-label">أدخل وزن الفضة (بالجرام):</label>
                <input type="number" id="val_silver_weight" class="form-control" placeholder="أدخل الوزن" oninput="calcZakat()">
            </div>

            <!-- زكاة الأسهم -->
            <div class="zakat-section" id="sec_stocks" style="display:none;">
                <button type="button" class="btn btn-sm btn-danger btn-delete-sec" onclick="document.getElementById('chk_stocks').click();"><i class="bi bi-trash"></i> Delete</button>
                <h5 class="zakat-section-title"><i class="bi bi-graph-up-arrow"></i> زكاة الأسهم والصناديق</h5>
                <p class="text-muted small">يجب اعتماد سعر الإغلاق في الأسهم. يجب مرور حول كامل.</p>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">أدخل سعر السهم:</label>
                        <input type="number" id="val_stock_price" class="form-control" placeholder="أدخل سعر السهم" oninput="calcZakat()">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">أدخل عدد الأسهم:</label>
                        <input type="number" id="val_stock_count" class="form-control" placeholder="أدخل عدد الأسهم" oninput="calcZakat()">
                    </div>
                </div>
            </div>

            <!-- زكاة الزروع -->
            <div class="zakat-section" id="sec_crops" style="display:none;">
                <button type="button" class="btn btn-sm btn-danger btn-delete-sec" onclick="document.getElementById('chk_crops').click();"><i class="bi bi-trash"></i> Delete</button>
                <h5 class="zakat-section-title"><i class="bi bi-tree"></i> زكاة الزروع والثمار</h5>
                <p class="text-muted small">تجب الزكاة عند الحصاد مباشرة إذا بلغت النصاب (653 كغ) وأن تكون الزروع مدخرة وقابلة للتخزين.</p>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">أدخل وزن المحصول (بالكيلوغرام):</label>
                        <input type="number" id="val_crops_weight" class="form-control" placeholder="أدخل الوزن" oninput="calcZakat()">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">اختر الطبيعة التي اعتمد عليها للسقاية:</label>
                        <select id="val_crops_type" class="form-select" onchange="calcZakat()">
                            <option value="0.10">الاعتماد على ماء المطر (10%)</option>
                            <option value="0.05">الري الصناعي / تكلفة (5%)</option>
                            <option value="0.075">مختلط بين المطر والصناعي (7.5%)</option>
                        </select>
                    </div>
                    <div class="col-12"><small class="text-danger">ملاحظة: إذا كان الزرع مختلط بالري يتم الاحتساب على المدة الأكثر ري.</small></div>
                </div>
            </div>

            <!-- زكاة الأنعام -->
            <div class="zakat-section" id="sec_livestock" style="display:none;">
                <button type="button" class="btn btn-sm btn-danger btn-delete-sec" onclick="document.getElementById('chk_livestock').click();"><i class="bi bi-trash"></i> Delete</button>
                <h5 class="zakat-section-title"><i class="bi bi-twitter"></i> زكاة الأنعام</h5>
                <p class="text-muted small">شروط زكاة الماشية:<br>1. تبلغ النصاب (الإبل 5، البقر 30، الغنم 40).<br>2. يحول عليها الحول.<br>3. أن تكون سائمة (مرعية في الكلأ المباح) وليست معلوفة.</p>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">نوع الأنعام:</label>
                        <select id="val_live_type" class="form-select" onchange="calcZakat()">
                            <option value="camel">إبل</option>
                            <option value="cow">بقر</option>
                            <option value="sheep">غنم (ضأن/ماعز)</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">أدخل العدد:</label>
                        <input type="number" id="val_live_count" class="form-control" placeholder="أدخل العدد" oninput="calcZakat()">
                    </div>
                </div>
            </div>

            <!-- زكاة الشركات -->
            <div class="zakat-section" id="sec_companies" style="display:none;">
                <button type="button" class="btn btn-sm btn-danger btn-delete-sec" onclick="document.getElementById('chk_companies').click();"><i class="bi bi-trash"></i> Delete</button>
                <h5 class="zakat-section-title"><i class="bi bi-buildings"></i> زكاة المؤسسات والشركات</h5>
                <div class="row g-4">
                    <div class="col-md-6 border-end">
                        <h6 class="text-success fw-bold">الموجودات الزكوية (+)</h6>
                        <label class="form-label small">البضاعة بسعر السوق عند إخراج الزكاة:</label>
                        <input type="number" class="form-control form-control-sm mb-2 comp-asset" placeholder="أدخل القيمة" oninput="calcZakat()">
                        <label class="form-label small">المدينون (الذمم المالية الديون الجيدة):</label>
                        <input type="number" class="form-control form-control-sm mb-2 comp-asset" placeholder="أدخل القيمة" oninput="calcZakat()">
                        <label class="form-label small">أوراق القبض (شيكات مستحقة):</label>
                        <input type="number" class="form-control form-control-sm mb-2 comp-asset" placeholder="أدخل القيمة" oninput="calcZakat()">
                        <label class="form-label small">إيرادات مستحقة (ديون مرجوة السداد):</label>
                        <input type="number" class="form-control form-control-sm mb-2 comp-asset" placeholder="أدخل القيمة" oninput="calcZakat()">
                        <label class="form-label small">الحسابات الجارية لدى البنوك (تحت الطلب):</label>
                        <input type="number" class="form-control form-control-sm mb-2 comp-asset" placeholder="أدخل القيمة" oninput="calcZakat()">
                        <label class="form-label small">النقد (العملة المحلية والأجنبية):</label>
                        <input type="number" class="form-control form-control-sm mb-2 comp-asset" placeholder="أدخل القيمة" oninput="calcZakat()">
                        <label class="form-label small">حسابات استثمارية بنوك إسلامية (رأس مال+أرباح):</label>
                        <input type="number" class="form-control form-control-sm mb-2 comp-asset" placeholder="أدخل القيمة" oninput="calcZakat()">
                        <label class="form-label small">حسابات استثمارية بنوك أخرى (رأس المال فقط):</label>
                        <input type="number" class="form-control form-control-sm mb-2 comp-asset" placeholder="أدخل القيمة" oninput="calcZakat()">
                    </div>
                    <div class="col-md-6">
                        <h6 class="text-danger fw-bold">المطلوبات والخصوم (-)</h6>
                        <label class="form-label small">المطلوبات المتداولة (مستحقة قبل نهاية الحول):</label>
                        <input type="number" class="form-control form-control-sm mb-2 comp-liab" placeholder="أدخل القيمة" oninput="calcZakat()">
                        <label class="form-label small">الدائنون (ناشئة عن شراء سلع مستحقة خلال سنة):</label>
                        <input type="number" class="form-control form-control-sm mb-2 comp-liab" placeholder="أدخل القيمة" oninput="calcZakat()">
                        <label class="form-label small">أوراق الدفع (شيكات للغير لا تزيد عن سنة):</label>
                        <input type="number" class="form-control form-control-sm mb-2 comp-liab" placeholder="أدخل القيمة" oninput="calcZakat()">
                        <label class="form-label small">القروض قصيرة الأجل وحسابات السحب عالمكشوف:</label>
                        <input type="number" class="form-control form-control-sm mb-2 comp-liab" placeholder="أدخل القيمة" oninput="calcZakat()">
                        <label class="form-label small">القسط الواجب السداد من القروض طويلة الأجل:</label>
                        <input type="number" class="form-control form-control-sm mb-2 comp-liab" placeholder="أدخل القيمة" oninput="calcZakat()">
                        <label class="form-label small">الإيرادات المقبوضة مقدماً (لخدمات لم تؤد):</label>
                        <input type="number" class="form-control form-control-sm mb-2 comp-liab" placeholder="أدخل القيمة" oninput="calcZakat()">
                        <label class="form-label small">الضرائب المستحقة:</label>
                        <input type="number" class="form-control form-control-sm mb-2 comp-liab" placeholder="أدخل القيمة" oninput="calcZakat()">
                        <label class="form-label small">التأمينات المقدمة من العملاء:</label>
                        <input type="number" class="form-control form-control-sm mb-2 comp-liab" placeholder="أدخل القيمة" oninput="calcZakat()">
                    </div>
                </div>
            </div>

        </div>

        <!-- النتيجة النهائية -->
        <div class="calc-result-box mt-4 shadow-sm">
            <h4 class="text-secondary mb-3">مقدار الزكاة الواجب إخراجها</h4>
            <h2 class="display-5 fw-bold text-success" id="final_zakat_cash">0.00 <span class="fs-4">JOD</span></h2>
            <div id="nisab_warning" class="text-danger fw-bold" style="display:none;">لم يبلغ النصاب (نصاب المال/الذهب يقدر بـ 9545.5 JOD)</div>
            <hr>
            <div id="final_zakat_crops" class="fs-5 text-dark" style="display:none;"></div>
            <div id="final_zakat_live" class="fs-5 text-dark" style="display:none;"></div>
            
            <div class="mt-4">
                <a href="add_donor.php" class="btn btn-primary btn-lg rounded-pill px-5 shadow"><i class="bi bi-wallet2"></i> دفع وتسجيل الزكاة مباشرة</a>
            </div>
        </div>

      </div>
    </div>
  </div>
</div>

<script>
    function toggleSec(id, show) {
        document.getElementById(id).style.display = show ? 'block' : 'none';
        calcZakat();
    }

    function calcZakat() {
        let isHijri = document.getElementById('hawl_hijri').checked;
        let rate = isHijri ? 0.025 : 0.02577;
        let nisabLimit = 85 * 112.30; // 9545.5 JOD

        let totalWealth = 0;

        // 1. المال
        if(document.getElementById('sec_money').style.display !== 'none') {
            totalWealth += parseFloat(document.getElementById('val_money').value) || 0;
        }
        // 2. الذهب
        if(document.getElementById('sec_gold').style.display !== 'none') {
            let gW = parseFloat(document.getElementById('val_gold_weight').value) || 0;
            let gC = parseFloat(document.getElementById('val_gold_carat').value) || 0;
            totalWealth += (gW * gC);
        }
        // 3. الفضة
        if(document.getElementById('sec_silver').style.display !== 'none') {
            let sW = parseFloat(document.getElementById('val_silver_weight').value) || 0;
            totalWealth += (sW * 1.0); // سعر الفضة التقريبي
        }
        // 4. الأسهم
        if(document.getElementById('sec_stocks').style.display !== 'none') {
            let stP = parseFloat(document.getElementById('val_stock_price').value) || 0;
            let stC = parseFloat(document.getElementById('val_stock_count').value) || 0;
            totalWealth += (stP * stC);
        }
        // 5. الشركات
        if(document.getElementById('sec_companies').style.display !== 'none') {
            let assets = 0; let liabs = 0;
            document.querySelectorAll('.comp-asset').forEach(el => assets += parseFloat(el.value) || 0);
            document.querySelectorAll('.comp-liab').forEach(el => liabs += parseFloat(el.value) || 0);
            totalWealth += Math.max(0, assets - liabs);
        }

        // حساب الزكاة النقدية
        let finalCashZakat = 0;
        if(totalWealth >= nisabLimit) {
            finalCashZakat = totalWealth * rate;
            document.getElementById('nisab_warning').style.display = 'none';
        } else if (totalWealth > 0) {
            document.getElementById('nisab_warning').style.display = 'block';
        } else {
            document.getElementById('nisab_warning').style.display = 'none';
        }
        document.getElementById('final_zakat_cash').innerHTML = finalCashZakat.toFixed(2) + ' <span class="fs-4">JOD</span>';

        // زكاة الزروع
        let cropStr = "";
        if(document.getElementById('sec_crops').style.display !== 'none') {
            let cW = parseFloat(document.getElementById('val_crops_weight').value) || 0;
            let cR = parseFloat(document.getElementById('val_crops_type').value) || 0;
            if(cW >= 653) cropStr = "زكاة الزروع: <strong>" + (cW * cR).toFixed(1) + " كغ</strong>";
            else if(cW > 0) cropStr = "زكاة الزروع: <span class='text-danger'>لم تبلغ النصاب (653 كغ)</span>";
        }
        document.getElementById('final_zakat_crops').innerHTML = cropStr;
        document.getElementById('final_zakat_crops').style.display = cropStr ? 'block' : 'none';

        // زكاة الأنعام
        let liveStr = "";
        if(document.getElementById('sec_livestock').style.display !== 'none') {
            let type = document.getElementById('val_live_type').value;
            let count = parseInt(document.getElementById('val_live_count').value) || 0;
            if(type === 'camel') {
                if(count>=5 && count<=9) liveStr="شاة واحدة"; else if(count>=10 && count<=14) liveStr="شاتان"; else if(count>=15 && count<=19) liveStr="3 شياه"; else if(count>=20 && count<=24) liveStr="4 شياه"; else if(count>=25) liveStr="بنت مخاض فأكثر (حسب العدد)"; else liveStr="لم تبلغ النصاب (5)";
            } else if(type === 'cow') {
                if(count>=30 && count<=39) liveStr="تبيع أو تبيعة"; else if(count>=40) liveStr="مسنة فأكثر (حسب العدد)"; else liveStr="لم تبلغ النصاب (30)";
            } else if(type === 'sheep') {
                if(count>=40 && count<=120) liveStr="شاة واحدة"; else if(count>=121 && count<=200) liveStr="شاتان"; else if(count>=201) liveStr="3 شياه فأكثر"; else liveStr="لم تبلغ النصاب (40)";
            }
            if(count > 0) liveStr = "زكاة الأنعام: <strong>" + liveStr + "</strong>"; else liveStr = "";
        }
        document.getElementById('final_zakat_live').innerHTML = liveStr;
        document.getElementById('final_zakat_live').style.display = liveStr ? 'block' : 'none';
    }
</script>

<script>
    // سكربت للتحكم بزر الوضع الداكن وحفظ التفضيل
    document.addEventListener('DOMContentLoaded', function() {
        const darkModeToggle = document.getElementById('darkModeToggle');
        const darkModeIcon = document.getElementById('darkModeIcon');
        
        function updateIcon() {
            if (document.documentElement.classList.contains('dark-mode')) {
                darkModeIcon.className = 'bi bi-sun-fill text-warning';
                darkModeToggle.className = 'btn btn-sm btn-dark border-secondary rounded-pill px-3 me-3 ms-2 shadow-sm';
            } else {
                darkModeIcon.className = 'bi bi-moon-stars-fill';
                darkModeToggle.className = 'btn btn-sm btn-outline-light rounded-pill px-3 me-3 ms-2';
            }
        }

        updateIcon();

        darkModeToggle.addEventListener('click', (e) => {
            const toggleTheme = () => {
                document.documentElement.classList.toggle('dark-mode');
                localStorage.setItem('dark_mode', document.documentElement.classList.contains('dark-mode') ? 'enabled' : 'disabled');
                updateIcon();
            };

            // التحقق من دعم المتصفح لتأثيرات الانتقال الحديثة (View Transitions API)
            if (!document.startViewTransition) {
                toggleTheme(); // للمتصفحات القديمة: الانتقال العادي
                return;
            }

            // إضافة كلاس لإيقاف الـ transition العادي للـ CSS مؤقتاً
            document.documentElement.classList.add('view-transition-active');

            // الحصول على إحداثيات مؤشر الماوس لبدء الانتشار من مكان النقر
            const x = e.clientX;
            const y = e.clientY;
            
            // حساب أقصى مسافة لضمان تغطية الشاشة بالكامل (نصف القطر)
            const endRadius = Math.hypot(
                Math.max(x, innerWidth - x),
                Math.max(y, innerHeight - y)
            );

            // بدء تأثير الانتقال
            const transition = document.startViewTransition(toggleTheme);

            transition.ready.then(() => {
                document.documentElement.animate(
                    { clipPath: [ `circle(0px at ${x}px ${y}px)`, `circle(${endRadius}px at ${x}px ${y}px)` ] },
                    { duration: 600, easing: 'ease-out', pseudoElement: '::view-transition-new(root)' }
                );
            });

            // إزالة الكلاس بعد انتهاء التأثير
            transition.finished.then(() => {
                document.documentElement.classList.remove('view-transition-active');
            });
        });
    });
</script>

<div class="container">