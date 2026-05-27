<?php
// Iniciar sesión de forma segura para el control de peticiones (Rate Limit)
if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'cookie_lifetime' => 0,
        'cookie_secure'   => true,   // Solo viaja por HTTPS
        'cookie_httponly' => true,   // Mitiga robos de sesión por JavaScript
        'cookie_samesite' => 'Strict'
    ]);
}

require 'incp/Exception.php';
require 'incp/PHPMailer.php';
require 'incp/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// ---------- CONFIGURACIÓN GENERAL ----------
$destinatario  = 'comercial@igmacob.cl';
$from_email    = 'no-reply@igmacobchile.cl'; 
$from_nombre   = 'Igmacob Chile Web';
$gracias_url   = 'https://igmacobchile.cl/gracias/';

// ---------- CONFIGURACIÓN SMTP (cPanel) ----------
define('SMTP_HOST', 'mail.igmacobchile.cl');       
define('SMTP_USER', 'no-reply@igmacobchile.cl');   
define('SMTP_PASS', 'TU_CONTRASEÑA_AQUÍ');         
define('SMTP_PORT', 465);                          
define('SMTP_SECU', PHPMailer::ENCRYPTION_SMTPS);  

// ---------- CORS ESTRICTO ----------
$allowed_origins = [
    'https://igmacobchile.cl',
    'https://www.igmacobchile.cl',
    'https://prueba.igmacobchile.cl'
];
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, $allowed_origins, true)) {
    header('Access-Control-Allow-Origin: ' . $origin);
} else {
    // Si la petición viene de un origen no autorizado, se bloquea de inmediato
    http_response_code(403);
    exit('Origen no autorizado');
}

// Solo POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Método no permitido');
}

// ---------- CAPA DE SEGURIDAD 1: RATE LIMITING BÁSICO ----------
// No permitir más de 1 envío cada 30 segundos por sesión/usuario para mitigar spam flooding.
$ahora = time();
if (isset($_SESSION['ultimo_envio']) && ($ahora - $_SESSION['ultimo_envio']) < 30) {
    http_response_code(422); // Unprocessable Entity
    header('Location: ' . $gracias_url . '?error=frecuencia');
    exit;
}

// ---------- HONEYPOT ANTI-SPAM ----------
if (!empty($_POST['website'])) {
    // Simular éxito ante el bot para que no intente otro método
    header('Location: ' . $gracias_url . '?ok=1');
    exit;
}

// ---------- CAPA DE SEGURIDAD 2: SANITIZACIÓN AVANZADA ----------
function clean_secure($v, $max_len = 1000) {
    if ($v === null) return '';
    // 1. Cortar el string al máximo permitido para evitar cargas pesadas de memoria
    $v = substr(trim($v), 0, $max_len);
    // 2. Eliminar saltos de línea maliciosos (Inyección de cabeceras)
    $v = str_replace(["\r", "\n", "%0a", "%0d"], '', $v);
    // 3. Quitar etiquetas HTML/PHP
    $v = strip_tags($v);
    // 4. Convertir caracteres especiales a entidades seguras
    return htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
}

// Procesar datos limitando su longitud según el campo razonable
$nombre   = clean_secure($_POST['nombre']   ?? '', 80);
$email    = clean_secure($_POST['email']    ?? '', 100);
$telefono = clean_secure($_POST['telefono'] ?? '', 30);
$empresa  = clean_secure($_POST['empresa']  ?? '', 100);
$conoce   = clean_secure($_POST['conoce']   ?? '', 100);
$origen   = clean_secure($_POST['origen']   ?? 'sin-origen', 50);

// El mensaje permite saltos de línea legítimos, usamos una función un poco más flexible pero igual de limpia
$mensaje = $_POST['mensaje'] ?? '';
$mensaje = substr(trim($mensaje), 0, 3000); // Máximo 3000 caracteres
$mensaje = htmlspecialchars(strip_tags($mensaje), ENT_QUOTES, 'UTF-8');

// Metadatos del sistema (No vienen del usuario, pero se limpian por precaución)
$referer  = clean_secure($_SERVER['HTTP_REFERER'] ?? 'desconocido', 250);
$ip       = filter_var($_SERVER['REMOTE_ADDR'] ?? '', FILTER_VALIDATE_IP) ? $_SERVER['REMOTE_ADDR'] : 'desconocida';

// ---------- VALIDACIÓN ----------
if (empty($nombre) || empty($email) || empty($mensaje)) {
    header('Location: ' . $gracias_url . '?error=campos');
    exit;
}

// RFC estricto para correos electrónicos
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    header('Location: ' . $gracias_url . '?error=email');
    exit;
}

// ---------- ARMAR CUERPO DEL EMAIL ----------
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

// ---------- CONFIGURAR Y ENVIAR CON PHPMAILER ----------
$mail = new PHPMailer(true);

try {
    $mail->isSMTP();
    $mail->Host       = SMTP_HOST;
    $mail->SMTPAuth   = true;
    $mail->Username   = SMTP_USER;
    $mail->Password   = SMTP_PASS;
    $mail->SMTPSecure = SMTP_SECU;
    $mail->Port       = SMTP_PORT;
    $mail->CharSet    = 'UTF-8';

    // Desactivar Debug en producción para no exponer datos del servidor en caso de error
    $mail->SMTPDebug  = 0; 

    // Destinatarios y Cabeceras
    $mail->setFrom($from_email, $from_nombre);
    $mail->addAddress($destinatario);
    
    // Validar el Reply-To para evitar trampas de inyección de cabeceras en PHPMailer
    if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $mail->addReplyTo($email, $nombre);
    }

    // Contenido del correo
    $mail->isHTML(false); 
    $mail->Subject = $subject;
    $mail->Body    = $body;

    // Enviar
    $mail->send();
    
    // Registrar el éxito del envío para el control de tiempo (Rate Limit)
    $_SESSION['ultimo_envio'] = time();
    
    header('Location: ' . $gracias_url . '?ok=1');
    exit;

} catch (Exception $e) {
    // Nota: El error detallado ($mail->ErrorInfo) nunca debe mostrarse al usuario final por seguridad.
    header('Location: ' . $gracias_url . '?error=server');
    exit;
}
