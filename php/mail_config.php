<?php
declare(strict_types=1);
// Set these on your server; never commit real credentials.
define('MAIL_USE_SMTP', true);
define('MAIL_SMTP_HOST', getenv('MAIL_SMTP_HOST') ?: 'localhost');
define('MAIL_SMTP_PORT', (int)(getenv('MAIL_SMTP_PORT') ?: 465));
define('MAIL_SMTP_USERNAME', getenv('MAIL_SMTP_USERNAME') ?: '');
define('MAIL_SMTP_PASSWORD', getenv('MAIL_SMTP_PASSWORD') ?: '');
define('MAIL_FROM_EMAIL', getenv('MAIL_FROM_EMAIL') ?: 'demo@example.test');
define('MAIL_FROM_NAME', 'МЭТЧ');