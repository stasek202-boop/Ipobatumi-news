<?php
// Настройка бота заявок. Запуск в Shell-клиенте хостинга:  php ~/tg-setup.php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$cfgFile = getenv('HOME') . '/www/ipobatumi.ru/tg-config.php';
$cfg = require $cfgFile;

function api($token, $method, $params = []) {
  $ch = curl_init("https://api.telegram.org/bot$token/$method");
  curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => $params, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15]);
  $r = json_decode((string)curl_exec($ch), true); curl_close($ch); return $r;
}
function save($file, $token, $ids) {
  $ids = implode(', ', array_map(fn($i) => var_export((string)$i, true), $ids));
  file_put_contents($file, "<?php\ndate_default_timezone_set('Asia/Tbilisi');\nreturn [\n  'token' => " . var_export($token, true) . ",\n  'chat_ids' => [$ids],\n];\n");
  chmod($file, 0600);
}

$token = $cfg['token'];
if ($token === 'ВСТАВИТЬ_ТОКЕН' || in_array('--token', $argv)) {
  echo "Вставьте токен бота и нажмите Enter (символы не отображаются): ";
  system('stty -echo'); $token = trim(fgets(STDIN)); system('stty echo'); echo "\n";
}
$me = api($token, 'getMe');
if (empty($me['ok'])) { echo "Токен не подошёл, проверьте и запустите ещё раз.\n"; exit(1); }
echo "Бот: @{$me['result']['username']}\n";
save($cfgFile, $token, array_filter((array)$cfg['chat_ids'], fn($i) => $i !== 'ВСТАВИТЬ_CHAT_ID'));

$u = api($token, 'getUpdates');
$chats = [];
foreach ($u['result'] ?? [] as $up) {
  $c = $up['message']['chat'] ?? $up['my_chat_member']['chat'] ?? null;
  if ($c) $chats[$c['id']] = trim(($c['first_name'] ?? '') . ' ' . ($c['last_name'] ?? '') . ' ' . (isset($c['username']) ? '@' . $c['username'] : '') . ($c['title'] ?? ''));
}
if (!$chats) { echo "Пока никто не нажал «Старт» в боте. Попросите Светлану нажать «Старт» и запустите скрипт снова.\n"; exit(2); }
echo "Нажали «Старт»:\n"; foreach ($chats as $id => $n) echo "  $id  $n\n";
$pick = $argv[1] ?? '';
if ($pick !== '' && $pick !== '--token') {
  $ids = array_map('trim', explode(',', $pick));
  save($cfgFile, $token, $ids);
  foreach ($ids as $id) { $r = api($token, 'sendMessage', ['chat_id' => $id, 'text' => "✅ Бот подключён к сайту ipobatumi.ru. Сюда будут приходить заявки с формы."]); echo "$id: " . (!empty($r['ok']) ? "OK\n" : "ошибка\n"); }
} else {
  echo "Чтобы выбрать получателя: php ~/tg-setup.php <chat_id>\n";
}
