<?php
declare(strict_types=1);

require __DIR__ . '/config.php';

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1],
]);

$news = null;

if ($id) {
    $statement = db()->prepare(
        'SELECT id, title, summary, body, image_path, link_url, link_label, published_at
         FROM news_posts
         WHERE id = :id AND is_active = 1
         LIMIT 1'
    );
    $statement->execute(['id' => $id]);
    $news = $statement->fetch() ?: null;
}

function local_image_src(string $path): string
{
    if (preg_match('/^(https?:|data:image\/|\/)/', $path)) {
        return $path;
    }

    return ltrim($path, '/');
}

function news_date(?string $value): string
{
    if (!$value) {
        return '';
    }

    return date('d/m/Y', strtotime($value));
}
?>
<!doctype html>
<html lang="es">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= h($news['title'] ?? 'Noticia') ?> | Robótica Educativa</title>
    <meta name="description" content="<?= h($news['summary'] ?? 'Noticias del Grupo de Interés en Robótica Educativa.') ?>">
    <link rel="icon" type="image/png" href="assets/favicon.png">
    <link rel="apple-touch-icon" href="assets/favicon.png">
    <link rel="stylesheet" href="styles.css">
  </head>
  <body>
    <section class="accessibility-bar" aria-label="Herramientas de accesibilidad">
      <span class="accessibility-label">Accesibilidad</span>
      <div class="accessibility-controls">
        <button type="button" data-accessibility-action="font-decrease" aria-label="Disminuir tamaño de letra" title="Disminuir tamaño de letra"><span class="robot-action small" aria-hidden="true">A-</span></button>
        <button type="button" data-accessibility-action="font-increase" aria-label="Aumentar tamaño de letra" title="Aumentar tamaño de letra"><span class="robot-action large" aria-hidden="true">A+</span></button>
        <button type="button" data-accessibility-action="contrast" aria-label="Activar alto contraste" aria-pressed="false" title="Alto contraste"><span class="robot-action contrast" aria-hidden="true"></span></button>
        <button type="button" data-accessibility-action="invert" aria-label="Invertir colores" aria-pressed="false" title="Invertir colores"><span class="robot-action invert" aria-hidden="true"></span></button>
        <button type="button" data-accessibility-action="reset" aria-label="Restablecer accesibilidad" title="Restablecer"><span class="robot-action reset" aria-hidden="true"></span></button>
      </div>
    </section>

    <header class="site-header">
      <a class="brand" href="index.html#inicio" aria-label="Ir al inicio">
        <img class="brand-symbol" src="assets/robotica-taller-01.jpeg" alt="Logo Grupo de Interés Robótica Educativa">
        <span class="brand-title">
          Grupo de Interés en <strong>ROBÓTICA EDUCATIVA</strong>
        </span>
      </a>
      <a class="ua-brand" href="https://www.uautonoma.cl/experiencia-ua/" target="_blank" rel="noopener noreferrer" aria-label="Experiencia UA">
        <img src="assets/logo-experiencia-ua-corporativo.png" alt="Experiencia UA">
      </a>
      <nav aria-label="Navegación principal">
        <a href="index.html#proposito">Propósito</a>
        <a href="index.html#actividades">Actividades</a>
        <a href="index.html#noticias">Noticias</a>
        <a href="directiva.html">Directiva</a>
        <a href="galeria.html">Galería</a>
        <button class="nav-contact-button" type="button" data-contact-open>Contacto</button>
      </nav>
    </header>

    <main>
      <?php if (!$news): ?>
        <section class="page-hero compact-hero">
          <div>
            <p class="eyebrow">Noticias</p>
            <h1>Noticia no encontrada</h1>
            <p>Puede que la publicación ya no esté visible o que el enlace no sea correcto.</p>
            <a class="button primary" href="index.html#noticias">Volver a noticias</a>
          </div>
          <img src="assets/robotica-taller-01.jpeg" alt="Logo Grupo de Interés Robótica Educativa">
        </section>
      <?php else: ?>
        <article class="news-detail">
          <?php if (!empty($news['image_path'])): ?>
            <img class="news-detail-image" src="<?= h(local_image_src($news['image_path'])) ?>" alt="<?= h($news['title']) ?>">
          <?php endif; ?>
          <div class="news-detail-content">
            <p class="eyebrow">Noticia · <?= h(news_date($news['published_at'])) ?></p>
            <h1><?= h($news['title']) ?></h1>
            <?php if (!empty($news['summary'])): ?>
              <p class="news-detail-summary"><?= h($news['summary']) ?></p>
            <?php endif; ?>
            <div class="news-detail-body">
              <?php foreach (preg_split('/\R{2,}/', trim($news['body'])) as $paragraph): ?>
                <p><?= nl2br(h($paragraph)) ?></p>
              <?php endforeach; ?>
            </div>
            <div class="hero-actions">
              <?php if (!empty($news['link_url'])): ?>
                <a class="button primary" href="<?= h($news['link_url']) ?>" target="_blank" rel="noopener noreferrer"><?= h($news['link_label'] ?: 'Ver enlace') ?></a>
              <?php endif; ?>
              <a class="button secondary" href="index.html#noticias">Volver a noticias</a>
            </div>
          </div>
        </article>
      <?php endif; ?>
    </main>

    <footer>
      <p>&copy; Grupo de Interés en Robótica Educativa · Todos los derechos reservados</p>
      <a class="secret-admin-link" href="admin/" aria-label="Acceso interno" title="Acceso interno">🤖</a>
    </footer>

    <div class="contact-modal" id="contactModal" aria-hidden="true">
      <div class="contact-dialog" role="dialog" aria-modal="true" aria-labelledby="contactModalTitle">
        <button class="modal-close" type="button" data-contact-close aria-label="Cerrar formulario">&times;</button>
        <p class="eyebrow">Contacto</p>
        <h2 id="contactModalTitle">Escríbenos</h2>
        <p class="contact-intro">
          Si tienen una idea, necesitan apoyo para una actividad o quieren que el grupo participe en algo, escríbanos. Somos estudiantes organizándonos para acercar la robótica educativa a más espacios.
        </p>
        <form class="contact-form" action="contact.php" method="post">
          <label>
            Nombre
            <input type="text" name="name" autocomplete="name" minlength="2" placeholder="Tu nombre o institución" required>
          </label>
          <label>
            Correo
            <input type="email" name="email" autocomplete="email" placeholder="nombre@correo.cl" required>
          </label>
          <label>
            Asunto
            <input type="text" name="subject" minlength="4" placeholder="Taller, colaboración, consulta..." required>
          </label>
          <label>
            Mensaje
            <textarea name="message" rows="5" minlength="12" placeholder="Cuéntanos brevemente qué necesitas y cómo podemos ayudarte." required></textarea>
          </label>
          <button class="button primary robot-submit" type="submit"><span class="button-bot" aria-hidden="true"></span>Enviar mensaje</button>
          <p class="form-status" role="status"></p>
        </form>
      </div>
    </div>
    <script src="script.js"></script>
  </body>
</html>
