<?php
declare(strict_types=1);

require __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $statement = db()->query(
        'SELECT id, title, message, image_path, link_url, link_label
         FROM site_alerts
         WHERE is_active = 1
           AND (starts_at IS NULL OR starts_at <= NOW())
           AND (ends_at IS NULL OR ends_at >= NOW())
         ORDER BY updated_at DESC, id DESC
         LIMIT 1'
    );
    $alert = $statement->fetch();

    echo json_encode([
        'alert' => $alert ?: null,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $error) {
    http_response_code(500);
    echo json_encode([
        'alert' => null,
        'error' => 'No se pudo cargar la novedad.',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
