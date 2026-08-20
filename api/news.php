<?php
declare(strict_types=1);

require __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $limit = isset($_GET['limit']) ? max(1, min(12, (int) $_GET['limit'])) : 3;
    $statement = db()->query(
        'SELECT id, title, summary, body, image_path, link_url, link_label, published_at
         FROM news_posts
         WHERE is_active = 1
         ORDER BY published_at DESC, id DESC
         LIMIT ' . $limit
    );
    $items = array_map(static function (array $item): array {
        $summary = trim((string) $item['summary']);

        if ($summary === '') {
            $summary = mb_substr(trim((string) $item['body']), 0, 180, 'UTF-8');
        }

        return [
            'id' => (int) $item['id'],
            'title' => $item['title'],
            'summary' => $summary,
            'image_path' => $item['image_path'],
            'link_url' => $item['link_url'],
            'link_label' => $item['link_label'],
            'published_at' => $item['published_at'],
            'detail_url' => 'noticia.php?id=' . (int) $item['id'],
        ];
    }, $statement->fetchAll());

    echo json_encode([
        'items' => $items,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $error) {
    http_response_code(500);
    echo json_encode([
        'items' => [],
        'error' => 'No se pudieron cargar las noticias.',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
