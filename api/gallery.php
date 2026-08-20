<?php
declare(strict_types=1);

require __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $requestedOrder = (string) ($_GET['order'] ?? '');
    $order = match ($requestedOrder) {
        'latest' => 'created_at DESC, id DESC',
        'oldest' => 'created_at ASC, id ASC',
        default => 'sort_order ASC, id DESC',
    };
    $limit = isset($_GET['limit']) ? max(1, min(60, (int) $_GET['limit'])) : 0;
    $sql = 'SELECT id, title AS caption, image_path AS src, alt_text, sort_order, created_at
            FROM gallery_images
            WHERE is_active = 1
            ORDER BY ' . $order;

    if ($limit > 0) {
        $sql .= ' LIMIT ' . $limit;
    }

    $statement = db()->query($sql);

    echo json_encode([
        'items' => $statement->fetchAll(),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $error) {
    http_response_code(500);
    echo json_encode([
        'items' => [],
        'error' => 'No se pudo cargar la galería.',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
