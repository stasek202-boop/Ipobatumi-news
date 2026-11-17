<?php
// Отдаёт news.json с GitHub через свой сервер (браузер посетителя не обращается к GitHub).
// Кэш 10 минут во временной папке хостинга.
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
$src = 'https://raw.githubusercontent.com/stasek202-boop/ipobatumi-news/main/news.json';
$cache = sys_get_temp_dir() . '/ipobatumi_news.json';
if (is_file($cache) && time() - filemtime($cache) < 600) { readfile($cache); exit; }
$ch = curl_init($src);
curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 8, CURLOPT_FOLLOWLOCATION => true]);
$body = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
if ($code === 200 && is_array(json_decode((string)$body, true))) { @file_put_contents($cache, $body, LOCK_EX); echo $body; exit; }
if (is_file($cache)) { readfile($cache); exit; }
http_response_code(502); echo '{}';
