<?php
declare(strict_types=1);

require __DIR__ . '/../config.php';
require __DIR__ . '/../news-content.php';

header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');

$sessionPath = __DIR__ . '/../runtime/php-sessions';

if (!is_dir($sessionPath)) {
    mkdir($sessionPath, 0755, true);
}

session_save_path($sessionPath);
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

const ADMIN_ACTIONS = ['login', 'logout', 'create', 'update', 'delete', 'move', 'save_alert', 'delete_alert', 'create_news', 'update_news', 'delete_news', 'upload_news_inline'];

function redirect_admin(): never
{
    header('Location: ./');
    exit;
}

function is_logged_in(): bool
{
    return !empty($_SESSION['admin_user_id']);
}

function authenticate_admin(string $username, string $password): ?array
{
    $username = trim($username);
    if (!preg_match('/^[\p{L}\p{N}._-]{3,80}$/u', $username) || $password === '') {
        return null;
    }

    try {
        $count = (int) db()->query('SELECT COUNT(*) FROM admin_users')->fetchColumn();
    } catch (PDOException $exception) {
        throw new RuntimeException('No se pudo consultar el acceso. Revisa la conexión y ejecuta la migración SQL indicada en DEPLOYMENT.md.');
    }
    if ($count === 0 && hash_equals(ADMIN_USERNAME, $username) && admin_password_is_valid($password)) {
        $statement = db()->prepare('INSERT INTO admin_users (username, password_hash) VALUES (:username, :password_hash)');
        $statement->execute(['username' => $username, 'password_hash' => password_hash($password, PASSWORD_DEFAULT)]);
    }

    $statement = db()->prepare('SELECT id, username, password_hash FROM admin_users WHERE username = :username AND is_active = 1 LIMIT 1');
    $statement->execute(['username' => $username]);
    $user = $statement->fetch();
    return $user && password_verify($password, $user['password_hash']) ? $user : null;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function verify_csrf(): void
{
    $token = $_POST['csrf_token'] ?? '';

    if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        throw new RuntimeException('Sesión expirada. Recarga e intenta nuevamente.');
    }
}

function rollback_if_needed(): void
{
    try {
        if (db()->inTransaction()) {
            db()->rollBack();
        }
    } catch (Throwable $ignored) {
        return;
    }
}

function login_is_locked(): bool
{
    return (int) ($_SESSION['login_locked_until'] ?? 0) > time();
}

function register_failed_login(): void
{
    $_SESSION['login_attempts'] = ((int) ($_SESSION['login_attempts'] ?? 0)) + 1;

    if ($_SESSION['login_attempts'] >= 5) {
        $_SESSION['login_locked_until'] = time() + 300;
    }
}

function clear_failed_logins(): void
{
    unset($_SESSION['login_attempts'], $_SESSION['login_locked_until']);
}

function text_input(string $key, int $minLength, int $maxLength): string
{
    $value = trim((string) ($_POST[$key] ?? ''));
    $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value) ?? '';
    $length = mb_strlen($value, 'UTF-8');

    if ($length < $minLength || $length > $maxLength) {
        throw new RuntimeException('Revisa los campos: hay información incompleta o demasiado extensa.');
    }

    return $value;
}

function optional_text_input(string $key, int $maxLength): string
{
    $value = trim((string) ($_POST[$key] ?? ''));
    $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value) ?? '';

    if (mb_strlen($value, 'UTF-8') > $maxLength) {
        throw new RuntimeException('Uno de los campos supera el largo permitido.');
    }

    return $value;
}

function optional_url_input(string $key): string
{
    $value = optional_text_input($key, 500);

    if ($value === '') {
        return '';
    }

    if (!filter_var($value, FILTER_VALIDATE_URL)) {
        throw new RuntimeException('El enlace debe ser una URL válida.');
    }

    $scheme = strtolower((string) parse_url($value, PHP_URL_SCHEME));

    if (!in_array($scheme, ['http', 'https'], true)) {
        throw new RuntimeException('El enlace debe comenzar con http o https.');
    }

    return $value;
}

function positive_id(): int
{
    $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 1],
    ]);

    if (!$id) {
        throw new RuntimeException('Registro inválido.');
    }

    return (int) $id;
}

function normalize_image_path(string $path): string
{
    $path = trim($path);

    if ($path === '') {
        throw new RuntimeException('Debes indicar una imagen válida.');
    }

    if (mb_strlen($path, 'UTF-8') > 500) {
        throw new RuntimeException('La ruta de imagen es demasiado larga.');
    }

    if (preg_match('/[\x00-\x1F\x7F]/', $path) || str_contains($path, '..') || str_contains($path, '\\')) {
        throw new RuntimeException('La ruta de imagen no es válida.');
    }

    if (preg_match('/^https?:\/\/[^\s]+\.(jpe?g|png|webp|gif)(\?[^\s]*)?$/i', $path)) {
        return $path;
    }

    if (preg_match('/^(gallery|uploads\/(gallery|alerts|news))\/[^?#]+\.(jpe?g|png|webp|gif)$/iu', $path)) {
        return $path;
    }

    throw new RuntimeException('Usa una ruta de galería, uploads o una URL de imagen válida.');
}

function gallery_items(): array
{
    $statement = db()->query(
        'SELECT id, title, image_path, alt_text, sort_order, is_active
         FROM gallery_images
         ORDER BY sort_order ASC, id DESC'
    );

    return $statement->fetchAll();
}

function current_site_alert(): ?array
{
    $statement = db()->query(
        'SELECT id, title, message, image_path, link_url, link_label, is_active
         FROM site_alerts
         ORDER BY updated_at DESC, id DESC
         LIMIT 1'
    );
    $alert = $statement->fetch();

    return $alert ?: null;
}

function news_items(): array
{
    $statement = db()->query(
        'SELECT id, title, summary, body, body_html, image_path, link_url, link_label, is_active, published_at
         FROM news_posts
         ORDER BY published_at DESC, id DESC'
    );

    return $statement->fetchAll();
}

function datetime_input(string $key): string
{
    $value = trim((string) ($_POST[$key] ?? ''));

    if ($value === '') {
        return date('Y-m-d H:i:s');
    }

    $date = DateTime::createFromFormat('Y-m-d\TH:i', $value);

    if (!$date) {
        throw new RuntimeException('La fecha de publicación no es válida.');
    }

    return $date->format('Y-m-d H:i:s');
}

function datetime_for_input(?string $value): string
{
    if (!$value) {
        return date('Y-m-d\TH:i');
    }

    return date('Y-m-d\TH:i', strtotime($value));
}

function admin_image_src(string $path): string
{
    if (preg_match('/^(https?:|data:image\/|\/)/', $path)) {
        return $path;
    }

    return '../' . ltrim($path, '/');
}

function next_sort_order(): int
{
    $value = db()->query('SELECT COALESCE(MAX(sort_order), 0) + 10 FROM gallery_images')->fetchColumn();
    return (int) $value;
}

function normalize_uploaded_file(array $file, string $uploadDir = UPLOAD_DIR, string $uploadUrl = UPLOAD_URL): string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return '';
    }

    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('No se pudo subir la imagen.');
    }

    if (($file['size'] ?? 0) > 5 * 1024 * 1024) {
        throw new RuntimeException('La imagen supera el máximo de 5 MB.');
    }

    $info = getimagesize($file['tmp_name']);
    $allowed = [
        IMAGETYPE_JPEG => 'jpg',
        IMAGETYPE_PNG => 'png',
        IMAGETYPE_WEBP => 'webp',
        IMAGETYPE_GIF => 'gif',
    ];

    if (!$info || !isset($allowed[$info[2]])) {
        throw new RuntimeException('Formato no permitido.');
    }

    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true)) {
        throw new RuntimeException('No se pudo crear la carpeta de imágenes.');
    }

    $filename = sprintf('%s.%s', bin2hex(random_bytes(16)), $allowed[$info[2]]);
    $target = rtrim($uploadDir, '/\\') . DIRECTORY_SEPARATOR . $filename;

    if (!move_uploaded_file($file['tmp_name'], $target)) {
        throw new RuntimeException('No se pudo guardar la imagen.');
    }

    return rtrim($uploadUrl, '/') . '/' . $filename;
}

function news_html_input(): string
{
    $raw = (string) ($_POST['news_body_html'] ?? '');
    if (strlen($raw) > 60000) {
        throw new RuntimeException('El detalle de la noticia es demasiado extenso.');
    }
    return clean_news_html($raw);
}

$message = '';
$error = '';

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = (string) ($_POST['action'] ?? '');

        if (!in_array($action, ADMIN_ACTIONS, true)) {
            throw new RuntimeException('Acción no permitida.');
        }

        if ($action === 'login') {
            if (login_is_locked()) {
                throw new RuntimeException('Demasiados intentos. Espera unos minutos e intenta nuevamente.');
            }

            $user = authenticate_admin((string) ($_POST['username'] ?? ''), (string) ($_POST['password'] ?? ''));
            if ($user) {
                session_regenerate_id(true);
                $_SESSION['admin_user_id'] = (int) $user['id'];
                $_SESSION['admin_username'] = $user['username'];
                clear_failed_logins();
                csrf_token();
                redirect_admin();
            }

            register_failed_login();
            $error = 'Usuario o clave incorrectos.';
        } elseif ($action === 'logout') {
            verify_csrf();
            session_destroy();
            redirect_admin();
        } elseif (is_logged_in()) {
            verify_csrf();

            if ($action === 'upload_news_inline') {
                $path = normalize_uploaded_file($_FILES['news_inline_image'] ?? [], NEWS_UPLOAD_DIR, NEWS_UPLOAD_URL);
                if ($path === '') {
                    throw new RuntimeException('Selecciona una imagen para insertar.');
                }
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['ok' => true, 'url' => '/' . $path], JSON_UNESCAPED_SLASHES);
                exit;
            }

            if ($action === 'create') {
                $title = text_input('title', 2, 160);
                $altText = optional_text_input('alt_text', 220);
                $imagePath = optional_text_input('image_path', 500);
                $uploadedPath = normalize_uploaded_file($_FILES['image_file'] ?? []);

                if ($uploadedPath !== '') {
                    $imagePath = $uploadedPath;
                }

                $imagePath = normalize_image_path($imagePath);

                $statement = db()->prepare(
                    'INSERT INTO gallery_images (title, image_path, alt_text, sort_order, is_active)
                     VALUES (:title, :image_path, :alt_text, :sort_order, 1)'
                );
                $statement->execute([
                    'title' => $title,
                    'image_path' => $imagePath,
                    'alt_text' => $altText !== '' ? $altText : $title,
                    'sort_order' => next_sort_order(),
                ]);
                $message = 'Imagen agregada.';
            }

            if ($action === 'update') {
                $id = positive_id();
                $exists = db()->prepare('SELECT id FROM gallery_images WHERE id = :id LIMIT 1');
                $exists->execute(['id' => $id]);
                if (!$exists->fetchColumn()) {
                    throw new RuntimeException('No se encontró el registro a actualizar.');
                }
                $title = text_input('title', 2, 160);
                $imagePath = normalize_image_path(text_input('image_path', 1, 500));
                $altText = optional_text_input('alt_text', 220);
                $statement = db()->prepare(
                    'UPDATE gallery_images
                     SET title = :title, image_path = :image_path, alt_text = :alt_text, is_active = :is_active
                     WHERE id = :id'
                );
                $statement->execute([
                    'id' => $id,
                    'title' => $title,
                    'image_path' => $imagePath,
                    'alt_text' => $altText !== '' ? $altText : $title,
                    'is_active' => isset($_POST['is_active']) ? 1 : 0,
                ]);

                $message = 'Imagen actualizada.';
            }

            if ($action === 'delete') {
                $statement = db()->prepare('DELETE FROM gallery_images WHERE id = :id');
                $statement->execute(['id' => positive_id()]);

                if ($statement->rowCount() === 0) {
                    throw new RuntimeException('No se encontró el registro a eliminar.');
                }

                $message = 'Imagen eliminada.';
            }

            if ($action === 'move') {
                $id = positive_id();
                $direction = (string) ($_POST['direction'] ?? '');

                if (!in_array($direction, ['up', 'down'], true)) {
                    throw new RuntimeException('Dirección inválida.');
                }

                db()->beginTransaction();
                $items = gallery_items();
                $index = array_search($id, array_map('intval', array_column($items, 'id')), true);
                $swapIndex = $direction === 'up' ? $index - 1 : $index + 1;

                if ($index !== false && isset($items[$swapIndex])) {
                    $statement = db()->prepare('UPDATE gallery_images SET sort_order = :sort_order WHERE id = :id');
                    $statement->execute([
                        'id' => $items[$index]['id'],
                        'sort_order' => $items[$swapIndex]['sort_order'],
                    ]);
                    $statement->execute([
                        'id' => $items[$swapIndex]['id'],
                        'sort_order' => $items[$index]['sort_order'],
                    ]);
                    $message = 'Orden actualizado.';
                }

                db()->commit();
            }

            if ($action === 'save_alert') {
                $alertId = filter_input(INPUT_POST, 'alert_id', FILTER_VALIDATE_INT, [
                    'options' => ['min_range' => 1],
                ]);
                $title = text_input('alert_title', 2, 160);
                $alertMessage = text_input('alert_message', 8, 1000);
                $imagePath = optional_text_input('alert_image_path', 500);
                $uploadedPath = normalize_uploaded_file($_FILES['alert_image_file'] ?? [], ALERT_UPLOAD_DIR, ALERT_UPLOAD_URL);
                $linkUrl = optional_url_input('alert_link_url');
                $linkLabel = optional_text_input('alert_link_label', 80);
                $isActive = isset($_POST['alert_is_active']) ? 1 : 0;

                if ($uploadedPath !== '') {
                    $imagePath = $uploadedPath;
                }

                if ($imagePath !== '') {
                    $imagePath = normalize_image_path($imagePath);
                }

                if ($linkUrl !== '' && $linkLabel === '') {
                    $linkLabel = 'Abrir formulario';
                }

                db()->beginTransaction();

                if ($isActive === 1) {
                    db()->exec('UPDATE site_alerts SET is_active = 0');
                }

                if ($alertId) {
                    $statement = db()->prepare(
                        'UPDATE site_alerts
                         SET title = :title, message = :message, image_path = :image_path,
                             link_url = :link_url, link_label = :link_label, is_active = :is_active
                         WHERE id = :id'
                    );
                    $statement->execute([
                        'id' => $alertId,
                        'title' => $title,
                        'message' => $alertMessage,
                        'image_path' => $imagePath,
                        'link_url' => $linkUrl,
                        'link_label' => $linkLabel,
                        'is_active' => $isActive,
                    ]);
                } else {
                    $statement = db()->prepare(
                        'INSERT INTO site_alerts (title, message, image_path, link_url, link_label, is_active)
                         VALUES (:title, :message, :image_path, :link_url, :link_label, :is_active)'
                    );
                    $statement->execute([
                        'title' => $title,
                        'message' => $alertMessage,
                        'image_path' => $imagePath,
                        'link_url' => $linkUrl,
                        'link_label' => $linkLabel,
                        'is_active' => $isActive,
                    ]);
                }

                db()->commit();
                $message = $isActive ? 'Novedad publicada.' : 'Novedad guardada como borrador.';
            }

            if ($action === 'delete_alert') {
                $statement = db()->prepare('DELETE FROM site_alerts WHERE id = :id');
                $statement->execute(['id' => positive_id()]);

                if ($statement->rowCount() === 0) {
                    throw new RuntimeException('No se encontró la novedad a eliminar.');
                }

                $message = 'Novedad eliminada.';
            }

            if ($action === 'create_news') {
                $title = text_input('news_title', 2, 180);
                $summary = optional_text_input('news_summary', 320);
                $body = text_input('news_body', 12, 5000);
                $bodyHtml = news_html_input();
                $imagePath = optional_text_input('news_image_path', 500);
                $uploadedPath = normalize_uploaded_file($_FILES['news_image_file'] ?? [], NEWS_UPLOAD_DIR, NEWS_UPLOAD_URL);
                $linkUrl = optional_url_input('news_link_url');
                $linkLabel = optional_text_input('news_link_label', 80);
                $publishedAt = datetime_input('news_published_at');

                if ($uploadedPath !== '') {
                    $imagePath = $uploadedPath;
                }

                if ($imagePath !== '') {
                    $imagePath = normalize_image_path($imagePath);
                }

                if ($linkUrl !== '' && $linkLabel === '') {
                    $linkLabel = 'Ver enlace';
                }

                $statement = db()->prepare(
                    'INSERT INTO news_posts (title, summary, body, body_html, image_path, link_url, link_label, is_active, published_at)
                     VALUES (:title, :summary, :body, :body_html, :image_path, :link_url, :link_label, :is_active, :published_at)'
                );
                $statement->execute([
                    'title' => $title,
                    'summary' => $summary,
                    'body' => $body,
                    'body_html' => $bodyHtml ?: null,
                    'image_path' => $imagePath,
                    'link_url' => $linkUrl,
                    'link_label' => $linkLabel,
                    'is_active' => isset($_POST['news_is_active']) ? 1 : 0,
                    'published_at' => $publishedAt,
                ]);
                $message = isset($_POST['news_is_active']) ? 'Noticia publicada.' : 'Borrador guardado.';
            }

            if ($action === 'update_news') {
                $id = positive_id();
                $title = text_input('news_title', 2, 180);
                $summary = optional_text_input('news_summary', 320);
                $body = text_input('news_body', 12, 5000);
                $bodyHtml = news_html_input();
                $imagePath = optional_text_input('news_image_path', 500);
                $uploadedPath = normalize_uploaded_file($_FILES['news_image_file'] ?? [], NEWS_UPLOAD_DIR, NEWS_UPLOAD_URL);
                $linkUrl = optional_url_input('news_link_url');
                $linkLabel = optional_text_input('news_link_label', 80);
                $publishedAt = datetime_input('news_published_at');

                if ($uploadedPath !== '') {
                    $imagePath = $uploadedPath;
                }

                if ($imagePath !== '') {
                    $imagePath = normalize_image_path($imagePath);
                }

                if ($linkUrl !== '' && $linkLabel === '') {
                    $linkLabel = 'Ver enlace';
                }

                $statement = db()->prepare(
                    'UPDATE news_posts
                     SET title = :title, summary = :summary, body = :body, body_html = :body_html, image_path = :image_path,
                         link_url = :link_url, link_label = :link_label, is_active = :is_active,
                         published_at = :published_at
                     WHERE id = :id'
                );
                $statement->execute([
                    'id' => $id,
                    'title' => $title,
                    'summary' => $summary,
                    'body' => $body,
                    'body_html' => $bodyHtml ?: null,
                    'image_path' => $imagePath,
                    'link_url' => $linkUrl,
                    'link_label' => $linkLabel,
                    'is_active' => isset($_POST['news_is_active']) ? 1 : 0,
                    'published_at' => $publishedAt,
                ]);

                $message = 'Noticia actualizada.';
            }

            if ($action === 'delete_news') {
                $statement = db()->prepare('DELETE FROM news_posts WHERE id = :id');
                $statement->execute(['id' => positive_id()]);

                if ($statement->rowCount() === 0) {
                    throw new RuntimeException('No se encontró la noticia a eliminar.');
                }

                $message = 'Noticia eliminada.';
            }
        }
    }
} catch (Throwable $exception) {
    rollback_if_needed();
    $error = $exception->getMessage();
}

if (($_POST['ajax'] ?? '') === '1') {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code($error === '' && $message !== '' ? 200 : 400);
    echo json_encode(['ok' => $error === '' && $message !== '', 'message' => $message ?: $error], JSON_UNESCAPED_UNICODE);
    exit;
}

$items = is_logged_in() ? gallery_items() : [];
$siteAlert = is_logged_in() ? current_site_alert() : null;
$newsItems = is_logged_in() ? news_items() : [];
$requestedView = (string) ($_GET['view'] ?? 'news');
$adminView = in_array($requestedView, ['news', 'gallery'], true) ? $requestedView : 'news';
$token = csrf_token();
?>
<!doctype html>
<html lang="es">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Backoffice | Grupo de Interés en Robótica Educativa</title>
    <link rel="icon" type="image/png" href="../assets/favicon.png">
    <link rel="apple-touch-icon" href="../assets/favicon.png">
    <link rel="stylesheet" href="../styles.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.snow.css">
  </head>
  <body>
    <header class="site-header admin-header">
      <a class="brand" href="../index.html#inicio" aria-label="Ir al inicio">
        <img class="brand-symbol" src="../assets/robotica-taller-01.jpeg" alt="Logo Grupo de Interés Robótica Educativa">
        <span class="brand-title">
          Grupo de Interés en <strong>ROBÓTICA EDUCATIVA</strong>
        </span>
      </a>
      <a class="ua-brand" href="https://www.uautonoma.cl/experiencia-ua/" target="_blank" rel="noopener noreferrer" aria-label="Experiencia UA">
        <img src="../assets/logo-experiencia-ua-corporativo.png" alt="Experiencia UA">
      </a>
      <nav aria-label="Navegación principal">
        <a href="../index.html#proposito">Propósito</a>
        <a href="../noticias.php">Noticias</a>
        <a href="../directiva.html">Directiva</a>
        <a href="../galeria.html">Galería</a>
        <button class="nav-contact-button" type="button" data-contact-open>Contacto</button>
      </nav>
    </header>

    <main>
      <section class="page-hero compact-hero">
        <div>
          <p class="eyebrow">Gestión interna</p>
          <h1>Backoffice del sitio</h1>
          <p>Administra las noticias y las imágenes del sitio.</p>
        </div>
        <img src="../assets/robotica-taller-01.jpeg" alt="Logo Grupo de Interés Robótica Educativa">
      </section>

      <section class="section admin-section">
        <?php if ($message): ?>
          <p class="admin-alert success"><?= h($message) ?></p>
        <?php endif; ?>
        <?php if ($error): ?>
          <p class="admin-alert error"><?= h($error) ?></p>
        <?php endif; ?>

        <?php if (!is_logged_in()): ?>
          <form class="admin-panel login-panel" method="post">
            <div class="section-heading compact">
              <p class="eyebrow">Acceso</p>
              <h2>Entrar al panel</h2>
            </div>
            <input type="hidden" name="action" value="login">
            <label>
              Usuario
              <input type="text" name="username" autocomplete="username" minlength="3" maxlength="80" required>
            </label>
            <label>
              Clave
              <input type="password" name="password" autocomplete="current-password" required>
            </label>
            <button class="button primary" type="submit">Entrar</button>
          </form>
        <?php else: ?>
          <div class="admin-toolbar">
            <div class="admin-menu" aria-label="Menú de gestión">
              <a href="./?view=news" <?= $adminView === 'news' ? 'aria-current="page"' : '' ?>>Gestión de noticias</a>
              <a href="./?view=gallery" <?= $adminView === 'gallery' ? 'aria-current="page"' : '' ?>>Gestión de imágenes</a>
            </div>
            <form class="admin-session" method="post">
              <input type="hidden" name="csrf_token" value="<?= h($token) ?>">
              <input type="hidden" name="action" value="logout">
              <button class="button secondary" type="submit">Cerrar sesión</button>
            </form>
          </div>

          <?php if ($adminView === 'news'): ?>
          <div class="admin-panel news-panel">
            <div class="section-heading compact">
              <p class="eyebrow">Noticias</p>
              <h2>Crear noticia</h2>
              <p>Agrega novedades con bajada, detalle, imagen y enlace opcional.</p>
            </div>
            <form class="gallery-form news-form" method="post" enctype="multipart/form-data">
              <input type="hidden" name="csrf_token" value="<?= h($token) ?>">
              <input type="hidden" name="action" value="create_news">
              <div class="alert-form-grid">
                <label>
                  Título
                  <input type="text" name="news_title" placeholder="Inscripciones abiertas" required>
                </label>
                <label>
                  Fecha
                  <input type="datetime-local" name="news_published_at" value="<?= h(datetime_for_input(null)) ?>">
                </label>
                <label>
                  Enlace opcional
                  <input type="url" name="news_link_url" placeholder="https://forms.gle/...">
                </label>
                <label>
                  Texto del enlace
                  <input type="text" name="news_link_label" placeholder="Inscribirme">
                </label>
                <label>
                  Ruta o URL imagen
                  <input type="text" name="news_image_path" placeholder="uploads/news/afiche.jpg">
                </label>
                <label class="check-row alert-check">
                  <input type="checkbox" name="news_is_active" checked>
                  Visible en el sitio
                </label>
              </div>
              <label>
                Subir imagen o afiche
                <input type="file" name="news_image_file" accept="image/*">
              </label>
              <label>
                Bajada breve
                <input type="text" name="news_summary" maxlength="320" placeholder="Una frase corta para la portada.">
              </label>
              <div class="news-editor-field">
                <span>Detalle</span>
                <div class="news-editor" data-news-editor></div>
                <textarea name="news_body" rows="6" placeholder="Cuenta qué está pasando, fechas importantes, cómo participar y cualquier dato útil." required></textarea>
                <input type="hidden" name="news_body_html" value="">
              </div>
              <button class="button primary" type="submit">Guardar noticia</button>
            </form>

            <div class="managed-news">
              <?php foreach ($newsItems as $news): ?>
                <article class="managed-news-item">
                  <?php if (!empty($news['image_path'])): ?>
                    <img src="<?= h(admin_image_src($news['image_path'])) ?>" alt="<?= h($news['title']) ?>">
                  <?php endif; ?>
                  <form method="post" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?= h($token) ?>">
                    <input type="hidden" name="id" value="<?= (int) $news['id'] ?>">
                    <div class="alert-form-grid">
                      <label>
                        Título
                        <input type="text" name="news_title" value="<?= h($news['title']) ?>" required>
                      </label>
                      <label>
                        Fecha
                        <input type="datetime-local" name="news_published_at" value="<?= h(datetime_for_input($news['published_at'])) ?>">
                      </label>
                      <label>
                        Enlace
                        <input type="url" name="news_link_url" value="<?= h($news['link_url']) ?>">
                      </label>
                      <label>
                        Texto enlace
                        <input type="text" name="news_link_label" value="<?= h($news['link_label']) ?>">
                      </label>
                      <label>
                        Imagen
                        <input type="text" name="news_image_path" value="<?= h($news['image_path']) ?>">
                      </label>
                      <label class="check-row alert-check">
                        <input type="checkbox" name="news_is_active" <?= $news['is_active'] ? 'checked' : '' ?>>
                        Visible
                      </label>
                    </div>
                    <label>
                      Reemplazar imagen
                      <input type="file" name="news_image_file" accept="image/*">
                    </label>
                    <label>
                      Bajada
                      <input type="text" name="news_summary" value="<?= h($news['summary']) ?>" maxlength="320">
                    </label>
                    <div class="news-editor-field">
                      <span>Detalle</span>
                      <div class="news-editor" data-news-editor><?= !empty($news['body_html']) ? clean_news_html($news['body_html']) : '<p>' . nl2br(h($news['body'])) . '</p>' ?></div>
                      <textarea name="news_body" rows="5" required><?= h($news['body']) ?></textarea>
                      <input type="hidden" name="news_body_html" value="">
                    </div>
                    <div class="managed-actions">
                      <button type="submit" name="action" value="update_news">Guardar</button>
                      <button type="submit" name="action" value="delete_news">Eliminar</button>
                    </div>
                  </form>
                </article>
              <?php endforeach; ?>
            </div>
          </div>

          <?php else: ?>
          <div class="admin-grid gallery-create-grid">
            <form class="admin-panel gallery-form" method="post" enctype="multipart/form-data">
              <input type="hidden" name="csrf_token" value="<?= h($token) ?>">
              <input type="hidden" name="action" value="create">
              <div class="section-heading compact">
                <p class="eyebrow">Nueva imagen</p>
                <h2>Agregar registro</h2>
              </div>
              <label>
                Título
                <input type="text" name="title" required>
              </label>
              <label>
                Ruta o URL
                <input type="text" name="image_path" placeholder="gallery/actividad-13.jpg">
              </label>
              <label>
                Texto alternativo
                <input type="text" name="alt_text">
              </label>
              <label>
                Archivo
                <input type="file" name="image_file" accept="image/*">
              </label>
              <button class="button primary" type="submit">Agregar</button>
            </form>
          </div>

          <div class="admin-panel gallery-manager">
            <div class="section-heading compact">
              <p class="eyebrow">Orden visible</p>
              <h2>Imágenes publicadas</h2>
            </div>

            <div class="managed-gallery">
              <?php foreach ($items as $index => $item): ?>
                <article class="managed-item editable">
                  <img src="<?= h(admin_image_src($item['image_path'])) ?>" alt="<?= h($item['alt_text'] ?: $item['title']) ?>">
                  <form method="post" data-gallery-edit-form>
                    <input type="hidden" name="csrf_token" value="<?= h($token) ?>">
                    <input type="hidden" name="id" value="<?= (int) $item['id'] ?>">
                    <label>
                      Título
                      <input type="text" name="title" value="<?= h($item['title']) ?>" required>
                    </label>
                    <label>
                      Imagen
                      <input type="text" name="image_path" value="<?= h($item['image_path']) ?>" required>
                    </label>
                    <label>
                      Alt
                      <input type="text" name="alt_text" value="<?= h($item['alt_text']) ?>">
                    </label>
                    <label class="check-row">
                      <input type="checkbox" name="is_active" <?= $item['is_active'] ? 'checked' : '' ?>>
                      Visible
                    </label>
                    <div class="managed-actions">
                      <button type="submit" name="action" value="update">Guardar</button>
                      <button type="submit" name="action" value="move" formaction="./" onclick="this.form.direction.value='up'" <?= $index === 0 ? 'disabled' : '' ?>>Subir</button>
                      <button type="submit" name="action" value="move" formaction="./" onclick="this.form.direction.value='down'" <?= $index === count($items) - 1 ? 'disabled' : '' ?>>Bajar</button>
                      <button type="submit" name="action" value="delete" formaction="./">Eliminar</button>
                    </div>
                    <p class="admin-inline-status" role="status" aria-live="polite"></p>
                    <input type="hidden" name="direction" value="">
                  </form>
                </article>
              <?php endforeach; ?>
            </div>
          </div>
          <?php endif; ?>
        <?php endif; ?>
      </section>
    </main>
    <div class="contact-modal" id="contactModal" aria-hidden="true">
      <div class="contact-dialog" role="dialog" aria-modal="true" aria-labelledby="contactModalTitle">
        <button class="modal-close" type="button" data-contact-close aria-label="Cerrar formulario">&times;</button>
        <p class="eyebrow">Contacto</p>
        <h2 id="contactModalTitle">Escríbenos</h2>
        <p class="contact-intro">
          Si tienen una idea, necesitan apoyo para una actividad o quieren que el grupo participe en algo, escríbanos. Somos estudiantes organizándonos para acercar la robótica educativa a más espacios.
        </p>
        <form class="contact-form" action="../contact.php" method="post">
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
    <script src="https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.js"></script>
    <script src="../admin/admin.js"></script>
    <script src="../script.js"></script>
  </body>
</html>
