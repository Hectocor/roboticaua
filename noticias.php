<?php
declare(strict_types=1);
require __DIR__ . '/config.php';

$perPage = 9;
$total = (int) db()->query('SELECT COUNT(*) FROM news_posts WHERE is_active = 1')->fetchColumn();
$pageCount = max(1, (int) ceil($total / $perPage));
$requested = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 1;
$page = min($requested, $pageCount);
$statement = db()->prepare(
    'SELECT id, title, summary, image_path, published_at FROM news_posts
     WHERE is_active = 1 ORDER BY published_at DESC, id DESC LIMIT :limit OFFSET :offset'
);
$statement->bindValue(':limit', $perPage, PDO::PARAM_INT);
$statement->bindValue(':offset', ($page - 1) * $perPage, PDO::PARAM_INT);
$statement->execute();
$items = $statement->fetchAll();

function news_image_src(string $path): string
{
    return preg_match('/^https?:\/\//', $path) ? $path : ltrim($path, '/');
}
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Noticias | Robótica Educativa</title>
  <meta name="description" content="Archivo de noticias del Grupo de Interés en Robótica Educativa.">
  <link rel="icon" type="image/png" href="assets/favicon.png">
  <link rel="stylesheet" href="styles.css">
</head>
<body>
  <section class="accessibility-bar" aria-label="Herramientas de accesibilidad">
    <span class="accessibility-label">Accesibilidad</span>
    <div class="accessibility-controls">
      <button type="button" data-accessibility-action="font-decrease" aria-label="Disminuir tamaño de letra"><span class="robot-action small" aria-hidden="true">A-</span></button>
      <button type="button" data-accessibility-action="font-increase" aria-label="Aumentar tamaño de letra"><span class="robot-action large" aria-hidden="true">A+</span></button>
      <button type="button" data-accessibility-action="contrast" aria-label="Activar alto contraste" aria-pressed="false"><span class="robot-action contrast" aria-hidden="true"></span></button>
      <button type="button" data-accessibility-action="invert" aria-label="Invertir colores" aria-pressed="false"><span class="robot-action invert" aria-hidden="true"></span></button>
      <button type="button" data-accessibility-action="reset" aria-label="Restablecer accesibilidad"><span class="robot-action reset" aria-hidden="true"></span></button>
    </div>
  </section>
  <header class="site-header">
    <a class="brand" href="index.html#inicio" aria-label="Ir al inicio">
      <img class="brand-symbol" src="assets/robotica-taller-01.jpeg" alt="Logo Grupo de Interés Robótica Educativa">
      <span class="brand-title">Grupo de Interés en <strong>ROBÓTICA EDUCATIVA</strong></span>
    </a>
    <a class="ua-brand" href="https://www.uautonoma.cl/experiencia-ua/" target="_blank" rel="noopener noreferrer" aria-label="Experiencia UA">
      <img src="assets/logo-experiencia-ua-corporativo.png" alt="Experiencia UA">
    </a>
    <nav aria-label="Navegación principal">
      <a href="index.html#proposito">Propósito</a>
      <a href="index.html#actividades">Actividades</a>
      <a href="noticias.php" aria-current="page">Noticias</a>
      <a href="directiva.html">Directiva</a>
      <a href="galeria.html">Galería</a>
      <a href="index.html#contacto">Contacto</a>
    </nav>
  </header>
  <main>
    <section class="section news-section news-archive">
      <div class="section-heading">
        <p class="eyebrow">Noticias</p>
        <h1>Noticias del grupo</h1>
        <p>Convocatorias, proyectos y actividades, desde las publicaciones más recientes.</p>
      </div>
      <?php if (!$items): ?>
        <p class="gallery-status">Pronto compartiremos noticias del grupo.</p>
      <?php else: ?>
        <div class="news-list">
          <?php foreach ($items as $item): ?>
            <article class="news-card">
              <?php if ($item['image_path'] !== ''): ?>
                <img src="<?= h(news_image_src($item['image_path'])) ?>" alt="<?= h($item['title']) ?>" loading="lazy">
              <?php endif; ?>
              <div class="news-card-content">
                <span><?= h(date('d/m/Y', strtotime($item['published_at']))) ?></span>
                <h2><?= h($item['title']) ?></h2>
                <p><?= h($item['summary'] ?: 'Lee el detalle de esta noticia del grupo.') ?></p>
                <a href="noticia.php?id=<?= (int) $item['id'] ?>">Leer detalle</a>
              </div>
            </article>
          <?php endforeach; ?>
        </div>
        <?php if ($pageCount > 1): ?>
          <nav class="gallery-page-controls news-page-controls" aria-label="Páginas de noticias">
            <?php if ($page > 1): ?>
              <a class="page-control" href="?page=<?= $page - 1 ?>" aria-label="Página anterior"><span class="pager-bot is-left" aria-hidden="true"></span></a>
            <?php endif; ?>
            <span class="gallery-page-numbers">
              <?php for ($number = 1; $number <= $pageCount; $number++): ?>
                <a href="?page=<?= $number ?>" <?= $number === $page ? 'aria-current="page"' : '' ?>><?= $number ?></a>
              <?php endfor; ?>
            </span>
            <?php if ($page < $pageCount): ?>
              <a class="page-control" href="?page=<?= $page + 1 ?>" aria-label="Página siguiente"><span class="pager-bot is-right" aria-hidden="true"></span></a>
            <?php endif; ?>
          </nav>
        <?php endif; ?>
      <?php endif; ?>
    </section>
  </main>
  <footer>
    <p>&copy; Grupo de Interés en Robótica Educativa · Todos los derechos reservados</p>
    <a class="secret-admin-link" href="admin/" aria-label="Acceso interno" title="Acceso interno">🤖</a>
  </footer>
  <script src="script.js"></script>
</body>
</html>
