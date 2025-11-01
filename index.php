<?php
// ==========================================================
// 1. إعدادات البوت ومعالجة البيانات
// ==========================================================

// *** رمز البوت الخاص بك (مدمج وجاهز) ***
define('BOT_TOKEN', '7999289076:AAFWkAJ-PFSCpUOL1skuO3_1tMKOFZwLX1c'); 
define('API_URL', 'https://api.telegram.org/bot' . BOT_TOKEN . '/');

$user_data = null;
$profile_image_url = 'https://via.placeholder.com/120/5ac8fa/ffffff?text=?'; // صورة افتراضية
$error_message = 'فشل في استقبال بيانات تليجرام (tgWebAppData). تأكد من تشغيل التطبيق عبر البوت.';

// دالة الاتصال الآمن مع Bot API باستخدام cURL (أكثر موثوقية)
function callApi($method, $params = []) {
    $ch = curl_init(API_URL . $method);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
    $response = curl_exec($ch);
    curl_close($ch);
    return json_decode($response, true);
}

// محاولة استلام ومعالجة البيانات من تليجرام
if (isset($_GET['tgWebAppData'])) {
    $data_array = [];
    parse_str($_GET['tgWebAppData'], $data_array);

    if (isset($data_array['user'])) {
        $user_data = json_decode($data_array['user'], true);
        
        if ($user_data && isset($user_data['id'])) {
            $user_id = $user_data['id'];
            $error_message = null; // مسح رسالة الخطأ عند نجاح التحميل الأساسي

            // محاولة الحصول على صورة البروفايل الحقيقية
            $photo_data = callApi('getUserProfilePhotos', ['user_id' => $user_id, 'limit' => 1]);
            
            if ($photo_data && $photo_data['ok'] && !empty($photo_data['result']['photos'])) {
                $photos = $photo_data['result']['photos'][0];
                $largest_photo = end($photos);
                $file_id = $largest_photo['file_id'];
                
                // الحصول على رابط الملف
                $file_info = callApi('getFile', ['file_id' => $file_id]);
                
                if ($file_info && $file_info['ok'] && isset($file_info['result']['file_path'])) {
                    $file_path = $file_info['result']['file_path'];
                    $profile_image_url = 'https://api.telegram.org/file/bot' . BOT_TOKEN . '/' . $file_path;
                }
            }
            
            // تهيئة صورة احتياطية (الحرف الأول من الاسم) إذا لم يتم الحصول على الصورة الحقيقية
            if (strpos($profile_image_url, 'placeholder') !== false && isset($user_data['first_name'])) {
                $initial = strtoupper($user_data['first_name'][0] ?? '?');
                $profile_image_url = 'https://via.placeholder.com/120/5ac8fa/ffffff?text=' . $initial;
            }
        }
    }
}

// ==========================================================
// 2. واجهة المستخدم (HTML/CSS/JavaScript)
// ==========================================================
?>
<!DOCTYPE html>
<html>
<head>
    <title>Telegram Profile Viewer</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://telegram.org/js/telegram-web-app.js"></script>
    <style>
        /* التنسيقات تعتمد على ثيم تليجرام */
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            background-color: var(--tg-theme-bg-color, #17212b); 
            color: var(--tg-theme-text-color, #ffffff);
            text-align: center;
            padding: 30px 15px;
            margin: 0;
            transition: background-color 0.3s;
        }
        .profile-card {
            background-color: var(--tg-theme-secondary-bg-color, #243140);
            border-radius: 18px;
            padding: 30px 20px;
            max-width: 380px;
            margin: 20px auto;
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.2);
        }
        #profile-image {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            margin-bottom: 20px;
            border: 4px solid var(--tg-theme-link-color, #007aff); 
            box-shadow: 0 0 15px rgba(0, 122, 255, 0.4); 
        }
        .full-name {
            font-size: 1.8em;
            font-weight: 700;
            color: var(--tg-theme-text-color, #ffffff);
            margin-bottom: 5px;
            line-height: 1.2;
        }
        .username {
            font-size: 1.2em;
            color: var(--tg-theme-hint-color, #8e8e93);
            margin-bottom: 30px;
        }
        .info-detail {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            font-size: 1em;
            border-bottom: 1px solid var(--tg-theme-separator-color, #434a53);
            align-items: center;
        }
        .info-detail:first-of-type {
            border-top: 1px solid var(--tg-theme-separator-color, #434a53);
        }
        .info-detail strong {
            color: var(--tg-theme-hint-color, #8e8e93);
            font-weight: 400;
        }
        .info-detail span {
            font-weight: 600;
            color: var(--tg-theme-text-color, #ffffff);
        }
        .status-message {
            color: var(--tg-theme-destructive-text-color, #ff453a);
            font-weight: bold;
            padding: 15px;
            border-radius: 8px;
            background-color: rgba(255, 69, 58, 0.1);
        }
    </style>
</head>
<body>

    <div class="profile-card">
        <?php if ($user_data && !$error_message): ?>
            <img id="profile-image" src="<?php echo htmlspecialchars($profile_image_url); ?>" alt="صورة الملف الشخصي">
            
            <div class="full-name">
                <?php 
                    $full_name = htmlspecialchars(($user_data['first_name'] ?? '') . ' ' . ($user_data['last_name'] ?? ''));
                    echo empty(trim($full_name)) ? 'الاسم غير مُسجل في تليجرام' : $full_name; 
                ?>
            </div>
            
            <div class="username">
                <?php 
                    echo isset($user_data['username']) ? '@' . htmlspecialchars($user_data['username']) : 'لا يوجد اسم مستخدم للحساب'; 
                ?>
            </div>
            
            <span class="info-detail">
                <strong>معرّف المستخدم (ID):</strong> 
                <span><?php echo htmlspecialchars($user_data['id']); ?></span>
            </span>
            <span class="info-detail">
                <strong>حالة Premium:</strong> 
                <span><?php echo $user_data['is_premium'] ? '✅ مشترك' : '❌ لا'; ?></span>
            </span>

        <?php else: ?>
            <p class="status-message">❌ فشل تحميل البيانات</p>
            <p style="font-size: 0.9em; color: var(--tg-theme-hint-color);">
                <?php echo $error_message; ?>
            </p>
        <?php endif; ?>
    </div>

    <script>
        // إعدادات التطبيق المصغر لـ تليجرام
        if (window.Telegram && window.Telegram.WebApp) {
            window.Telegram.WebApp.BackButton.hide();
            window.Telegram.WebApp.MainButton.setText('عرض البروفايل مكتمل').show();
        }
    </script>
</body>
</html>
