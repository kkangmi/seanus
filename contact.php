<?php
/* 홈페이지 문의 폼 → 메일 발송 */
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
date_default_timezone_set('Asia/Seoul');

const MAIL_TO   = 'kkang313@gmail.com';
const MAIL_FROM = 'noreply@forseanus.com';

function respond(bool $ok, string $code, int $status = 200): void {
    http_response_code($status);
    echo json_encode(['ok' => $ok, 'code' => $code]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(false, 'method', 405);
}

// 스팸 방지: 사람에게는 보이지 않는 필드가 채워져 있으면 봇으로 간주 (성공처럼 응답)
if (!empty($_POST['website'])) {
    respond(true, 'ok');
}

// 스팸 방지: 폼을 연 뒤 3초 안에 제출되면 봇으로 간주
$loadedAt = (int)($_POST['t'] ?? 0);
if ($loadedAt > 0 && (time() - intdiv($loadedAt, 1000)) < 3) {
    respond(true, 'ok');
}

// 헤더 주입 방지를 위해 한 줄 필드는 줄바꿈 제거
$line = static fn(string $k, int $max): string =>
    mb_substr(trim(preg_replace('/[\r\n]+/', ' ', (string)($_POST[$k] ?? ''))), 0, $max);

$name    = $line('name', 100);
$email   = $line('email', 200);
$phone   = $line('phone', 50);
$type    = $line('type', 100);
$message = mb_substr(trim((string)($_POST['message'] ?? '')), 0, 5000);

if ($name === '' || $message === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    respond(false, 'invalid', 422);
}

$subject = '[SeaNus 홈페이지 문의] ' . ($type !== '' ? $type . ' - ' : '') . $name;
$body = implode("\n", [
    '이름: ' . $name,
    '이메일: ' . $email,
    '연락처: ' . ($phone !== '' ? $phone : '-'),
    '문의 유형: ' . ($type !== '' ? $type : '-'),
    '',
    '문의 내용:',
    $message,
    '',
    '---',
    '접수 시각: ' . date('Y-m-d H:i:s'),
    'IP: ' . ($_SERVER['REMOTE_ADDR'] ?? '-'),
]);

$headers = implode("\r\n", [
    'From: ' . mb_encode_mimeheader('SeaNus 홈페이지', 'UTF-8') . ' <' . MAIL_FROM . '>',
    'Reply-To: ' . $email,
    'MIME-Version: 1.0',
    'Content-Type: text/plain; charset=UTF-8',
    'Content-Transfer-Encoding: 8bit',
]);

$sent = mail(MAIL_TO, mb_encode_mimeheader($subject, 'UTF-8'), $body, $headers, '-f' . MAIL_FROM);
respond($sent, $sent ? 'ok' : 'send_failed', $sent ? 200 : 500);
