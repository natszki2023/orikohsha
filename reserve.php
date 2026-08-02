<?php
/* 織光舎 ご予約フォーム メール送信 → contact@orikohsha.jp */
// mbstring が有効でない場合でも動作するように PHP 標準関数で処理します。
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: reserve.html');
    exit;
}

// Debug: log raw POST payload and parsed POST for troubleshooting missing fields
@file_put_contents(__DIR__ . '/reserve_post_raw.log', "[" . date('c') . "] RAW INPUT:\n" . file_get_contents('php://input') . "\n\n", FILE_APPEND);
@file_put_contents(__DIR__ . '/reserve_post_parsed.log', "[" . date('c') . "] \\$_POST:\n" . print_r($_POST, true) . "\n\n", FILE_APPEND);

function p($k){ return isset($_POST[$k]) ? trim((string)$_POST[$k]) : ''; }

function is_local_environment() {
    $host = $_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? '');
    $remote = $_SERVER['REMOTE_ADDR'] ?? '';
    return $host === 'localhost' || $host === '127.0.0.1' || $host === '::1' || $remote === '127.0.0.1' || $remote === '::1';
}

function send_mail_with_fallback($to, $subject, $body, $headers) {
    $sent = false;
    $mail_error = null;

    if (function_exists('mb_send_mail')) {
        $sent = @mb_send_mail($to, $subject, $body, $headers);
        if (!$sent) {
            $mail_error = 'mb_send_mail failed';
        }
    } else {
        $sent = @mail($to, $subject, $body, $headers);
        if (!$sent) {
            $mail_error = 'mail() failed';
        }
    }

    if ($sent) {
        return true;
    }

    if (is_local_environment()) {
        $fallback_path = __DIR__ . '/reserve_mail.log';
        $payload = [
            'timestamp' => date('c'),
            'to' => $to,
            'subject' => $subject,
            'headers' => $headers,
            'body' => $body,
            'mail_error' => $mail_error,
            'php_sapi' => PHP_SAPI,
            'remote_addr' => $_SERVER['REMOTE_ADDR'] ?? '',
        ];
        @file_put_contents($fallback_path, print_r($payload, true) . "\n---\n", FILE_APPEND);
        @file_put_contents(__DIR__ . '/reserve_debug.log', "[" . date('c') . "] Mail delivery failed; saved a local fallback copy to reserve_mail.log\n" . print_r($payload, true) . "\n\n", FILE_APPEND);
        return true;
    }

    @file_put_contents(__DIR__ . '/reserve_debug.log', "[" . date('c') . "] Mail delivery failed\n" . ($mail_error ?? 'unknown') . "\n\n", FILE_APPEND);
    return false;
}

function resolve_ca_bundle_path() {
    $candidates = [];

    foreach (['SSL_CERT_FILE', 'CURL_CA_BUNDLE'] as $name) {
        $value = getenv($name);
        if (is_string($value) && $value !== '') {
            $candidates[] = $value;
        }
    }

    foreach ([ini_get('curl.cainfo'), ini_get('openssl.cafile')] as $value) {
        if (is_string($value) && $value !== '') {
            $candidates[] = $value;
        }
    }

    $user = getenv('USERNAME') ?: getenv('USER');
    $windows_candidates = [
        'C:/Program Files/Git/usr/ssl/certs/ca-bundle.crt',
        'C:/Program Files/Git/usr/ssl/certs/ca-bundle.trust.crt',
        'C:/Program Files/Git/mingw64/ssl/certs/ca-bundle.crt',
        'C:/Program Files/Git/mingw64/ssl/certs/ca-bundle.trust.crt',
        'C:/Users/' . $user . '/AppData/Local/Programs/Git/usr/ssl/certs/ca-bundle.crt',
        'C:/Users/' . $user . '/AppData/Local/Programs/Git/usr/ssl/certs/ca-bundle.trust.crt',
        'C:/Users/' . $user . '/AppData/Local/Programs/Git/mingw64/ssl/certs/ca-bundle.crt',
        'C:/Users/' . $user . '/AppData/Local/Programs/Git/mingw64/ssl/certs/ca-bundle.trust.crt',
    ];

    foreach (array_merge($candidates, $windows_candidates) as $candidate) {
        if (is_string($candidate) && $candidate !== '' && is_file($candidate)) {
            return $candidate;
        }
    }

    return null;
}

$to      = 'contact@orikohsha.jp';
$from    = 'contact@orikohsha.jp';        // 送信元（同一ドメイン＝到達性◎）

/* --- reCAPTCHA 検証（Google reCAPTCHA v2） ---
   1) Google 管理画面でサイト用の Site Key / Secret Key を取得
   2) `config.example.php` を `config.php` にコピーし、Secret Key を設定してください
*/
$config_path = __DIR__ . '/config.php';
$config = file_exists($config_path) ? require $config_path : [];
$recaptcha_secret = isset($config['recaptcha_secret']) ? trim((string)$config['recaptcha_secret']) : 'YOUR_SECRET_KEY';
if ($recaptcha_secret === '' || $recaptcha_secret === 'YOUR_SECRET_KEY') {
    @file_put_contents(__DIR__ . '/reserve_debug.log', "[" . date('c') . "] reCAPTCHA secret key is missing or placeholder. config.php found: " . (file_exists($config_path) ? 'yes' : 'no') . "\n\n", FILE_APPEND);
}
$recaptcha_response = isset($_POST['g-recaptcha-response']) ? trim((string)$_POST['g-recaptcha-response']) : '';
$recaptcha_ok = false;
if ($recaptcha_response !== '') {
    $verify_url = 'https://www.google.com/recaptcha/api/siteverify';
    $params = http_build_query(['secret' => $recaptcha_secret, 'response' => $recaptcha_response, 'remoteip' => $_SERVER['REMOTE_ADDR'] ?? '']);
    $http_error = '';
    $use_curl = function_exists('curl_version') && function_exists('curl_init');
    if ($use_curl) {
        $ca_bundle = resolve_ca_bundle_path();
        $ch = curl_init($verify_url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
        if ($ca_bundle !== null) {
            curl_setopt($ch, CURLOPT_CAINFO, $ca_bundle);
            curl_setopt($ch, CURLOPT_CAPATH, dirname($ca_bundle));
        }
        $res = curl_exec($ch);
        if ($res === false) {
            $http_error = 'cURL error: ' . curl_error($ch) . ' (' . curl_errno($ch) . ')';
        }
        curl_close($ch);
    } elseif (ini_get('allow_url_fopen')) {
        $ctx = stream_context_create(['http' => ['method' => 'POST', 'header' => "Content-Type: application/x-www-form-urlencoded\r\n", 'content' => $params]]);
        $res = @file_get_contents($verify_url, false, $ctx);
        if ($res === false) {
            $err = error_get_last();
            $http_error = 'file_get_contents error: ' . ($err['message'] ?? 'unknown');
        }
    } else {
        $res = false;
        $http_error = 'No HTTP transport available: curl unavailable and allow_url_fopen disabled.';
    }
    $json = $res ? json_decode($res, true) : null;
    if ($json && isset($json['success']) && $json['success'] === true) {
        $recaptcha_ok = true;
        @file_put_contents(__DIR__ . '/reserve_debug.log', "[" . date('c') . "] reCAPTCHA verification succeeded\nresponse: " . print_r($json, true) . "\n\n", FILE_APPEND);
    } else {
        @file_put_contents(__DIR__ . '/reserve_debug.log', "[" . date('c') . "] reCAPTCHA siteverify response:\n" . ($res !== false ? $res : 'NO RESPONSE') . "\nhttp_error: " . $http_error . "\nopen_ssl: " . (extension_loaded('openssl') ? 'enabled' : 'disabled') . "\nallow_url_fopen: " . (ini_get('allow_url_fopen') ? 'On' : 'Off') . "\ncurl: " . ($use_curl ? 'available' : 'unavailable') . "\nca_bundle: " . (resolve_ca_bundle_path() ?? 'not-found') . "\nparsed JSON: " . print_r($json, true) . "\n\n", FILE_APPEND);
    }
}
if (!$recaptcha_ok) {
    @file_put_contents(__DIR__ . '/reserve_debug.log', "[" . date('c') . "] reCAPTCHA verification failed\nPOST: " . print_r($_POST, true) . "\n\n", FILE_APPEND);
    header('Location: reserve.html?error=1');
    exit;
}

/* 必須チェック */
$required = ['name_kanji','name_kana','zip','address','tel','email','star_name','appointment_datetime','source','agree'];
$ok = true;
foreach ($required as $r) { if (p($r) === '') { $ok = false; break; } }
if (!filter_var(p('email'), FILTER_VALIDATE_EMAIL)) { $ok = false; }
if (!$ok) {
    $missing = [];
    foreach ($required as $r) { if (p($r) === '') $missing[] = $r; }
    @file_put_contents(__DIR__ . '/reserve_debug.log', "[" . date('c') . "] Required fields missing: " . implode(',', $missing) . "\nPOST: " . print_r($_POST, true) . "\n\n", FILE_APPEND);
    header('Location: reserve.html?error=1');
    exit;
}

/* メール本文 */
$nl   = "\r\n";
$line = str_repeat('-', 40);
$b  = '織光舎 ご予約フォームより、お申し込みがありました。' . $nl . $nl;

$b .= '■ お客様情報' . $nl . $line . $nl;
$b .= 'お名前（漢字）　： ' . p('name_kanji') . $nl;
$b .= 'フリガナ　　　　： ' . p('name_kana') . $nl;
$b .= '郵便番号　　　　： ' . p('zip') . $nl;
$b .= 'ご住所　　　　　： ' . p('address') . $nl;
$b .= 'お電話番号　　　： ' . p('tel') . $nl;
$b .= 'メールアドレス　： ' . p('email') . $nl . $nl;

$b .= '■ ご撮影の主役の情報' . $nl . $line . $nl;
$b .= 'お名前（フリガナ）： ' . p('star_name') . $nl;
$b .= '年齢　　　　　　　： ' . p('star_age') . $nl;
$b .= '性別　　　　　　　： ' . p('star_gender') . $nl;
$b .= 'お誕生日　　　　　： ' . p('star_birthday') . $nl;
$b .= '好きなキャラクター： ' . p('star_character') . $nl . $nl;

$b .= '■ 希望日時' . $nl . $line . $nl;
$b .= 'ご希望日時　　　： ' . p('appointment_datetime') . $nl . $nl;
$b .= '■ オプション・アンケート' . $nl . $line . $nl;
$b .= '撮影対象の追加　： ' . p('option_add') . $nl;
$b .= 'ご移動手段　　　： ' . p('transport') . $nl;
$b .= 'きっかけ　　　　： ' . p('source') . $nl;
$b .= 'ご質問・ご要望　：' . $nl . p('message') . $nl . $nl;

$b .= '■ 注意事項への同意　： ' . p('agree') . $nl;
$b .= $line . $nl;
$b .= '送信日時： ' . date('Y-m-d H:i:s') . $nl;
$b .= '送信元IP： ' . ($_SERVER['REMOTE_ADDR'] ?? '') . $nl;

$subject = '【織光舎】ご予約フォーム送信：' . p('name_kanji') . ' 様';

$from_name = '織光舎 予約フォーム';
if (is_callable('mb_encode_mimeheader')) {
    $tmp = mb_encode_mimeheader('織光舎 予約フォーム');
    if ($tmp) $from_name = $tmp;
}
$headers  = 'From: ' . $from_name . ' <' . $from . '>' . $nl;
$headers .= 'Reply-To: ' . p('email');
$sent = send_mail_with_fallback($to, $subject, $b, $headers);

/* お客様への自動返信（任意・控え） */
if ($sent) {
    $ab  = p('name_kanji') . ' 様' . $nl . $nl;
    $ab .= 'この度は織光舎へご予約フォームをお送りいただき、誠にありがとうございます。' . $nl;
    $ab .= '以下の内容で承りました。担当者より折り返しご連絡を差し上げますので、今しばらくお待ちくださいませ。' . $nl . $nl;
    $ab .= $b;
    $ab .= $nl . '───────────────' . $nl . '織光舎（おりこうしゃ）' . $nl . 'contact@orikohsha.jp' . $nl;
    $reply_from = '織光舎';
    if (is_callable('mb_encode_mimeheader')) {
        $tmp = mb_encode_mimeheader('織光舎');
        if ($tmp) $reply_from = $tmp;
    }
    $ah  = 'From: ' . $reply_from . ' <' . $from . '>' . $nl . 'Reply-To: ' . $from;
    send_mail_with_fallback(p('email'), '【織光舎】ご予約を承りました', $ab, $ah);
}

header('Location: reserve.html?' . ($sent ? 'sent=1' : 'error=1'));
exit;
