<?php
/* 織光舎 ご予約フォーム メール送信 → contact@orikohsha.jp */
mb_language('Japanese');
mb_internal_encoding('UTF-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: reserve.html');
    exit;
}

function p($k){ return isset($_POST[$k]) ? trim((string)$_POST[$k]) : ''; }

$to      = 'contact@orikohsha.jp';
$from    = 'contact@orikohsha.jp';        // 送信元（同一ドメイン＝到達性◎）

/* 必須チェック */
$required = ['name_kanji','name_kana','zip','address','tel','email','star_name','source','agree'];
$ok = true;
foreach ($required as $r) { if (p($r) === '') { $ok = false; break; } }
if (!filter_var(p('email'), FILTER_VALIDATE_EMAIL)) { $ok = false; }
if (!$ok) {
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

$headers  = 'From: ' . mb_encode_mimeheader('織光舎 予約フォーム') . ' <' . $from . '>' . $nl;
$headers .= 'Reply-To: ' . p('email');

$sent = mb_send_mail($to, $subject, $b, $headers);

/* お客様への自動返信（任意・控え） */
if ($sent) {
    $ab  = p('name_kanji') . ' 様' . $nl . $nl;
    $ab .= 'この度は織光舎へご予約フォームをお送りいただき、誠にありがとうございます。' . $nl;
    $ab .= '以下の内容で承りました。担当者より折り返しご連絡を差し上げますので、今しばらくお待ちくださいませ。' . $nl . $nl;
    $ab .= $b;
    $ab .= $nl . '───────────────' . $nl . '織光舎（おりこうしゃ）' . $nl . 'contact@orikohsha.jp' . $nl;
    $ah  = 'From: ' . mb_encode_mimeheader('織光舎') . ' <' . $from . '>' . $nl . 'Reply-To: ' . $from;
    @mb_send_mail(p('email'), '【織光舎】ご予約を承りました', $ab, $ah);
}

header('Location: reserve.html?' . ($sent ? 'sent=1' : 'error=1'));
exit;
