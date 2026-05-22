<?php
/**
 * enviar.php - Procesador de formularios para Igmacob Chile
 *
 * UBICACIÓN: subir a la raíz de public_html/ del cPanel de igmacobchile.cl
 * URL FINAL:  https://igmacobchile.cl/enviar.php
 *
 * Recibe POST de cualquier formulario del sitio estático (prueba.igmacobchile.cl
 * o igmacobchile.cl cuando migre) y envía email a comercial@igmacob.cl.
 */

// ---------- CONFIG ----------
$destinatario  = 'comercial@igmacob.cl';
$from_email    = 'no-reply@igmacobchile.cl';   // debe existir como casilla o alias en cPanel
$from_nombre   = 'Igmacob Chile Web';
$gracias_url   = 'https://prueba.igmacobchile.cl/gracias.html';
// Cuando se migre el dominio, cambiar a: 'https://igmacobchile.cl/gracias.html'
// ----------------------------

// CORS: permitir POST desde el subdominio de prueba y el dominio principal
$allowed_origins = [
    'https://prueba.igmacobchile.cl',
    'https://igmacobchile.cl',
    'https://www.igmacobchile.cl'
];
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, $allowed_origins, true)) {
    header('Access-Control-Allow-Origin: ' . $origin);
}

// Solo POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Método no permitido');
}

// ---------- HONEYPOT ANTI-SPAM ----------
// Campo invisible "website": humanos lo dejan vacío, bots lo llenan.
if (!empty($_POST['website'])) {
    // fingir éxito sin enviar nada
    header('Location: ' . $gracias_url . '?ok=1');
    exit;
}

// ---------- SANITIZACIÓN ----------
function clean($v) {
    return trim(strip_tags($v ?? ''));
}

$nombre   = clean($_POST['nombre']   ?? '');
$email    = clean($_POST['email']    ?? '');
$telefono = clean($_POST['telefono'] ?? '');
$empresa  = clean($_POST['empresa']  ?? '');
$mensaje  = clean($_POST['mensaje']  ?? '');
$conoce   = clean($_POST['conoce']   ?? '');
$origen   = clean($_POST['origen']   ?? 'sin-origen');
$referer  = $_SERVER['HTTP_REFERER'] ?? 'desconocido';
$ip       = $_SERVER['REMOTE_ADDR']  ?? 'desconocida';

// ---------- VALIDACIÓN ----------
if (empty($nombre) || empty($email) || empty($mensaje)) {
    header('Location: ' . $gracias_url . '?error=campos');
    exit;
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    header('Location: ' . $gracias_url . '?error=email');
    exit;
}

// ---------- ARMAR EMAIL ----------
$subject = '[Web] Nuevo contacto de ' . $nombre;

$body  = "Has recibido una nueva consulta desde el sitio web.\n";
$body .= str_repeat('-', 50) . "\n\n";
$body .= "Nombre:        $nombre\n";
$body .= "Email:         $email\n";
if (!empty($telefono)) $body .= "Teléfono:      $telefono\n";
if (!empty($empresa))  $body .= "Empresa:       $empresa\n";
if (!empty($conoce))   $body .= "Cómo conoció:  $conoce\n";
$body .= "Página origen: $origen\n";
$body .= "Referrer:      $referer\n";
$body .= "IP:            $ip\n";
$body .= "Fecha:         " . date('Y-m-d H:i:s') . "\n";
$body .= "\nMensaje:\n" . str_repeat('-', 50) . "\n$mensaje\n";

$headers   = [];
$headers[] = 'From: ' . $from_nombre . ' <' . $from_email . '>';
$headers[] = 'Reply-To: ' . $nombre . ' <' . $email . '>';
$headers[] = 'X-Mailer: PHP/' . phpversion();
$headers[] = 'Content-Type: text/plain; charset=UTF-8';
$headers[] = 'MIME-Version: 1.0';

// ---------- ENVIAR ----------
$ok = @mail(
    $destinatario,
    '=?UTF-8?B?' . base64_encode($subject) . '?=',
    $body,
    implode("\r\n", $headers)
);

if ($ok) {
    header('Location: ' . $gracias_url . '?ok=1');
} else {
    header('Location: ' . $gracias_url . '?error=server');
}
exit;
