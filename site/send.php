<?php
// Обработчик заявок с сайта ipobatumi.ru -> Telegram Светланы
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo '{"ok":false}'; exit; }

$cfg = require __DIR__ . '/tg-config.php';
$d = !empty($_POST) ? $_POST : json_decode(file_get_contents('php://input'), true);
if (!is_array($d)) { http_response_code(400); echo '{"ok":false}'; exit; }

// Защита от ботов: скрытое поле должно быть пустым
if (!empty($d['website'])) { echo '{"ok":true}'; exit; }

function clean($v, $max) { return mb_substr(trim(strip_tags((string)$v)), 0, $max); }
$name  = clean($d['name'] ?? '', 100);
$phone = clean($d['phone'] ?? '', 40);
$msgr  = clean($d['messenger'] ?? '', 40);
$q     = clean($d['question'] ?? '', 1500);
$lang  = clean($d['lang'] ?? '', 5);
$calc  = clean($d['calc'] ?? '', 400);
$src   = ($d['source'] ?? '') === 'calculator' ? 'калькулятор' : 'форма консультации';

if ($name === '' || !preg_match('/\d{5,}/', preg_replace('/\D/', '', $phone))) {
  http_response_code(422); echo '{"ok":false}'; exit;
}

// Простое ограничение частоты: не чаще 1 заявки в 30 секунд с одного IP
$ip = $_SERVER['REMOTE_ADDR'] ?? '';
$lock = sys_get_temp_dir() . '/ipobatumi_' . md5($ip);
if (is_file($lock) && time() - filemtime($lock) < 30) { http_response_code(429); echo '{"ok":false}'; exit; }
@touch($lock);

$e = fn($s) => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
$text = "🏠 <b>Новая заявка с ipobatumi.ru</b> (" . $src . ")\n\n"
      . "<b>Имя:</b> " . $e($name) . "\n"
      . "<b>Телефон:</b> " . $e($phone) . "\n"
      . "<b>Куда написать:</b> " . $e($msgr) . "\n"
      . ($q !== '' ? "<b>Вопрос:</b> " . $e($q) . "\n" : '')
      . ($calc !== '' ? "<b>Расчёт:</b> " . $e($calc) . "\n" : '')
      . "<b>Язык сайта:</b> " . $e(strtoupper($lang)) . "\n"
      . "<i>" . date('d.m.Y H:i') . " (Тбилиси)</i>";

$ok = true;
foreach ((array)$cfg['chat_ids'] as $chat) {
  $ch = curl_init('https://api.telegram.org/bot' . $cfg['token'] . '/sendMessage');
  curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => ['chat_id' => $chat, 'text' => $text, 'parse_mode' => 'HTML'],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 10,
  ]);
  $res = json_decode((string)curl_exec($ch), true);
  curl_close($ch);
  if (empty($res['ok'])) $ok = false;
}

if (!$ok) http_response_code(502);
echo json_encode(['ok' => $ok]);
