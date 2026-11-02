<?php

declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/ai_interest_helpers.php';

require_role([MATCH_ROLE_ADMIN]);

$configPath = __DIR__ . '/ai_config.php';
if (file_exists($configPath)) {
    require_once $configPath;
}

$hasKey = trim((string)(defined('OPENROUTER_API_KEY') ? OPENROUTER_API_KEY : '')) !== '';
$model = defined('OPENROUTER_MODEL') ? (string)OPENROUTER_MODEL : 'не указан';
$hasCurl = function_exists('curl_init');
$logPath = dirname(__DIR__) . '/logs/interest_api.log';
$logLines = [];

if (is_file($logPath)) {
    $lines = file($logPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
    $logLines = array_slice($lines, -30);
}

$testResult = null;
if (isset($_GET['test']) && $_GET['test'] === '1') {
    $testResult = ai_extract_interests_from_text('Люблю книги, кофе, природу и спокойные прогулки. Не люблю шумные компании.');
}

function d(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

?><!doctype html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Диагностика обработки интересов</title>
    <style>
        body{font-family:Arial,sans-serif;line-height:1.5;background:#f7f4ff;color:#17114d;margin:0;padding:32px}
        .box{max-width:980px;margin:0 auto 18px;background:#fff;border:1px solid #ded7ff;border-radius:18px;padding:22px;box-shadow:0 12px 30px rgba(30,20,80,.08)}
        h1,h2{margin:0 0 14px}.ok{color:#137333}.bad{color:#b3261e}.muted{color:#665f88}
        code,pre{background:#f4f0ff;border-radius:10px}code{padding:2px 6px}pre{padding:14px;overflow:auto;white-space:pre-wrap}
        a{color:#5b3df5;font-weight:700}.row{display:grid;grid-template-columns:220px 1fr;gap:10px;padding:6px 0;border-bottom:1px solid #f0edff}.row:last-child{border-bottom:0}
    </style>
</head>
<body>
    <div class="box">
        <h1>Диагностика обработки интересов</h1>
        <p class="muted">Эта страница доступна только администратору. Обычные пользователи её не видят.</p>
        <div class="row"><b>API-ключ</b><span class="<?= $hasKey ? 'ok' : 'bad' ?>"><?= $hasKey ? 'указан' : 'не указан' ?></span></div>
        <div class="row"><b>Модель</b><span><?= d($model) ?></span></div>
        <div class="row"><b>PHP cURL</b><span class="<?= $hasCurl ? 'ok' : 'bad' ?>"><?= $hasCurl ? 'включён' : 'не включён' ?></span></div>
        <div class="row"><b>Лог</b><span><code><?= d($logPath) ?></code></span></div>
        <p><a href="?test=1">Выполнить тестовый запрос</a></p>
    </div>

    <?php if ($testResult !== null): ?>
        <div class="box">
            <h2>Результат теста</h2>
            <pre><?= d(json_encode($testResult, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)) ?></pre>
        </div>
    <?php endif; ?>

    <div class="box">
        <h2>Последние строки logs/interest_api.log</h2>
        <?php if ($logLines === []): ?>
            <p class="muted">Лог пока пустой.</p>
        <?php else: ?>
            <pre><?= d(implode("\n", $logLines)) ?></pre>
        <?php endif; ?>
    </div>
</body>
</html>
