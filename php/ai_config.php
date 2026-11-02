<?php

declare(strict_types=1);

/**
 * Настройки внутренней обработки интересов.
 * Ключ хранится только на сервере: в JS его добавлять нельзя.
 * Если ключ не указан или API временно недоступен, сайт использует локальный словарь,
 * чтобы профиль и лента не ломались во время демонстрации.
 */

define('OPENROUTER_API_KEY', getenv('OPENROUTER_API_KEY') ?: '');
define('OPENROUTER_MODEL', getenv('OPENROUTER_MODEL') ?: 'openrouter/free');
define('OPENROUTER_SITE_URL', getenv('OPENROUTER_SITE_URL') ?: 'http://localhost');
define('OPENROUTER_APP_TITLE', 'Match');

// Для разработчика: записывает технические причины переключения на локальный словарь
// в logs/interest_api.log. Пользователи эти сообщения не видят.
define('OPENROUTER_DEBUG_LOG', true);
