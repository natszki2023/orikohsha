<?php
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: contact.html');
    exit;
}

function contact_value($key) {
    return isset($_POST[$key]) ? trim((string) $_POST[$key]) : '';
}

function contact_length($value) {
    if (function_exists('mb_strlen')) {
        return mb_strlen($value, 'UTF-8');
    }

    return preg_match_all('/./us', $value, $matches) ?: 0;
}

function contact_is_local() {
    $host = $_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? '');
    $remote = $_SERVER['REMOTE_ADDR'] ?? '';
    return $host === 'localhost' || $host === '127.0.0.1' || $host === '::1' || $remote === '127.0.0.1' || $remote === '::1';
}

function contact_send_mail($to, $subject, $body, $headers) {
    $sent = function_exists('mb_send_mail') ? @mb_send_mail($to, $subject, $body, $headers) : @mail($to, $subject, $body, $headers);
    if ($sent || !contact_is_local()) {
        return $sent;
    }

    @file_put_contents(__DIR__ . '/contact_mail.log', "[" . date('c') . "]\nTo: " . $to . "\nSubject: " . $subject . "\n" . $body . "\n---\n", FILE_APPEND);
    return true;
}

function contact_verify_recaptcha($secret, $response) {
    if ($secret === '' || $secret === 'YOUR_SECRET_KEY' || $response === '') {
        return false;
    }

    $payload = http_build_query([
        'secret' => $secret,
        'response' => $response,
        'remoteip' => $_SERVER['REMOTE_ADDR'] ?? '',
    ]);
    $result = false;
    if (function_exists('curl_init')) {
        $curl = curl_init('https://www.google.com/recaptcha/api/siteverify');
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
        $result = curl_exec($curl);
        curl_close($curl);
    } elseif (ini_get('allow_url_fopen')) {
        $context = stream_context_create(['http' => ['method' => 'POST', 'header' => "Content-Type: application/x-www-form-urlencoded\r\n", 'content' => $payload]]);
        $result = @file_get_contents('https://www.google.com/recaptcha/api/siteverify', false, $context);
    }

    $decoded = $result ? json_decode($result, true) : null;
    return is_array($decoded) && !empty($decoded['success']);
}

$configPath = __DIR__ . '/config.php';
$config = file_exists($configPath) ? require $configPath : [];
$secret = isset($config['recaptcha_secret']) ? trim((string) $config['recaptcha_secret']) : '';

$name = contact_value('name');
$email = contact_value('email');
$tel = contact_value('tel');
$message = contact_value('message');
$types = isset($_POST['inquiry_type']) && is_array($_POST['inquiry_type']) ? array_map('trim', $_POST['inquiry_type']) : [];
$messageLength = contact_length($message);

if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || $message === '' || $messageLength > 3000 || !contact_verify_recaptcha($secret, contact_value('g-recaptcha-response'))) {
    header('Location: contact.html?error=1');
    exit;
}

$newline = "\r\n";
$typeText = $types ? implode('、', $types) : '未選択';
$body = '織光舎 お問い合わせフォームより、メッセージが届きました。' . $newline . $newline;
$body .= 'お名前： ' . $name . $newline;
$body .= 'メールアドレス： ' . $email . $newline;
$body .= '電話連絡先： ' . ($tel ?: '未入力') . $newline;
$body .= 'お問い合わせの種類： ' . $typeText . $newline . $newline;
$body .= 'お問い合わせ内容：' . $newline . $message . $newline . $newline;
$body .= '送信日時： ' . date('Y-m-d H:i:s') . $newline;
$body .= '送信元IP： ' . ($_SERVER['REMOTE_ADDR'] ?? '') . $newline;

$to = 'contact@orikohsha.jp';
$from = 'contact@orikohsha.jp';
$fromName = is_callable('mb_encode_mimeheader') ? mb_encode_mimeheader('織光舎 お問い合わせフォーム') : 'Orikohsha Contact';
$headers = 'From: ' . $fromName . ' <' . $from . '>' . $newline . 'Reply-To: ' . $email;
$subject = '【織光舎】お問い合わせフォーム送信：' . $name . ' 様';
$sent = contact_send_mail($to, $subject, $body, $headers);

if ($sent) {
    $replyBody = $name . ' 様' . $newline . $newline;
    $replyBody .= 'この度は織光舎へお問い合わせいただき、誠にありがとうございます。' . $newline;
    $replyBody .= '内容を確認の上、担当者より折り返しご連絡を差し上げます。' . $newline . $newline;
    $replyBody .= $body;
    $replyHeaders = 'From: ' . (is_callable('mb_encode_mimeheader') ? mb_encode_mimeheader('織光舎') : 'Orikohsha') . ' <' . $from . '>' . $newline . 'Reply-To: ' . $from;
    contact_send_mail($email, '【織光舎】お問い合わせを承りました', $replyBody, $replyHeaders);
}

header('Location: contact.html?' . ($sent ? 'sent=1' : 'error=1'));
exit;