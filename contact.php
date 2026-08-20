<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

function json_response(int $status, array $payload): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function clean_text(string $value, int $maxLength): string
{
    $value = trim(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value) ?? '');
    return mb_substr($value, 0, $maxLength, 'UTF-8');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(405, [
        'ok' => false,
        'message' => 'Método no permitido.',
    ]);
}

$name = clean_text((string) ($_POST['name'] ?? ''), 120);
$email = filter_var(trim((string) ($_POST['email'] ?? '')), FILTER_VALIDATE_EMAIL);
$subject = clean_text((string) ($_POST['subject'] ?? ''), 140);
$message = clean_text((string) ($_POST['message'] ?? ''), 3000);

if (mb_strlen($name, 'UTF-8') < 2 || !$email || mb_strlen($subject, 'UTF-8') < 4 || mb_strlen($message, 'UTF-8') < 12) {
    json_response(422, [
        'ok' => false,
        'message' => 'Completa nombre, correo, asunto y un mensaje con un poco más de contexto.',
    ]);
}

$to = 'contacto@roboticaua.cl';
$mailSubject = 'Contacto web: ' . $subject;
$body = implode("\n", [
    'Nuevo mensaje desde roboticaua.cl',
    '',
    'Nombre: ' . $name,
    'Correo: ' . $email,
    'Asunto: ' . $subject,
    '',
    'Mensaje:',
    $message,
]);
$headers = [
    'From: contacto@roboticaua.cl',
    'Reply-To: ' . $email,
    'Content-Type: text/plain; charset=UTF-8',
    'X-Mailer: PHP/' . phpversion(),
];

$sent = @mail($to, $mailSubject, $body, implode("\r\n", $headers));

if (!$sent) {
    $logDir = __DIR__ . '/runtime';

    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }

    file_put_contents(
        $logDir . '/contact-messages.log',
        sprintf("[%s]\n%s\n\n", date('c'), $body),
        FILE_APPEND | LOCK_EX
    );
}

json_response(200, [
    'ok' => true,
    'message' => $sent
        ? 'Mensaje enviado correctamente.'
        : 'Mensaje recibido. En localhost quedó guardado para prueba.',
]);
