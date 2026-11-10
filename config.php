<?php
declare(strict_types=1);

$localConfig = __DIR__ . '/config.local.php';

if (is_file($localConfig)) {
    require $localConfig;
}

defined('DB_HOST') || define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
defined('DB_NAME') || define('DB_NAME', getenv('DB_NAME') ?: 'robotica_ua');
defined('DB_USER') || define('DB_USER', getenv('DB_USER') ?: 'robotica_ua');
defined('DB_PASS') || define('DB_PASS', getenv('DB_PASS') ?: '');
defined('DB_CHARSET') || define('DB_CHARSET', 'utf8mb4');

defined('ADMIN_PASSWORD_HASH') || define('ADMIN_PASSWORD_HASH', getenv('ADMIN_PASSWORD_HASH') ?: '');
defined('ADMIN_PASSWORD') || define('ADMIN_PASSWORD', getenv('ADMIN_PASSWORD') ?: '');
defined('ADMIN_USERNAME') || define('ADMIN_USERNAME', getenv('ADMIN_USERNAME') ?: 'admin');

defined('UPLOAD_DIR') || define('UPLOAD_DIR', __DIR__ . '/uploads/gallery');
defined('UPLOAD_URL') || define('UPLOAD_URL', 'uploads/gallery');
defined('ALERT_UPLOAD_DIR') || define('ALERT_UPLOAD_DIR', __DIR__ . '/uploads/alerts');
defined('ALERT_UPLOAD_URL') || define('ALERT_UPLOAD_URL', 'uploads/alerts');
defined('NEWS_UPLOAD_DIR') || define('NEWS_UPLOAD_DIR', __DIR__ . '/uploads/news');
defined('NEWS_UPLOAD_URL') || define('NEWS_UPLOAD_URL', 'uploads/news');

function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', DB_HOST, DB_NAME, DB_CHARSET);
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    return $pdo;
}

function admin_password_is_valid(string $password): bool
{
    if (ADMIN_PASSWORD_HASH !== '') {
        return password_verify($password, ADMIN_PASSWORD_HASH);
    }

    if (ADMIN_PASSWORD !== '') {
        return hash_equals(ADMIN_PASSWORD, $password);
    }

    return false;
}

function h(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}
