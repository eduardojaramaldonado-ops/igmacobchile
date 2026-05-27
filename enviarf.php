<?php
echo "HOLA MUNDO";
exit;
/*
// ---------- CAPA DE SEGURIDAD INTERNA: RESTRICCIÓN DE ORIGEN DE SERVIDOR ----------
// Asegurar que el script sea llamado exclusivamente desde el entorno del propio servidor local.
// Previene que servidores externos usen este backend de envío.
$ip_servidor_local = $_SERVER['SERVER_ADDR'] ?? '127.0.0.1'; 
//$ip_remota         = $_SERVER['REMOTE_ADDR'] ?? '';

// Si requieres ser ultra estricto con que la petición de backend se originó en tu propio hosting,
// validamos que no se ejecute si viene desde una IP externa no autorizada.
// Nota: Dado que es un formulario web que los clientes rellenan, el navegador del cliente (su IP) 
// hace el POST, pero el control CORS de abajo garantiza que vengan desde tus dominios autorizados.

// Iniciar sesión de forma segura
if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'cookie_lifetime' => 0,
        'cookie_secure'   => true,   
        'cookie_httponly' => true,   
        'cookie_samesite' => 'Strict'
    ]);
}

// Bloqueo preventivo de inclusión remota (RFI)
if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'])) {
    // El script se está ejecutando directamente, lo cual es correcto para procesar el POST.
} else {
    http_response_code(403);
    exit('Acceso denegado: Inclusión no permitida.');
}

require 'incp/Exception.php';
require 'incp/PHPMailer.php';
require 'incp/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// ---------- CONFIGURACIÓN GENERAL ----------
$destinatario  = 'soporte@divalweb.com';
$from_email    = 'no-reply@igmacobchile.cl'; 
$from_nombre   = 'Igmacob Chile Web';
$gracias_url   = 'https://igmacobchile.cl/gracias/';

// ---------- CONFIGURACIÓN SMTP (cPanel) ----------
define('SMTP_HOST', 'mail.igmacobchile.cl');       
define('SMTP_USER', 'no-reply@igmacobchile.cl');   
define('SMTP_PASS', 'cqNzKn~GN+hyX_*(#');         
define('SMTP_PORT', 465);                          
define('SMTP_SECU', PHPMailer::ENCRYPTION_SMTPS);  

// ---------- CORS ESTRICTO (Solo tus dominios en el navegador pueden iniciar el POST) ----------
$allowed_origins = [
    'https://igmacobchile.cl',
    'https://www.igmacobchile.cl'
];
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';

if (in_array($origin, $allowed_origins, true)) {
    header('Access-Control-Allow-Origin: ' . $origin);
} else {
    // Si un servidor externo o bot intenta hacer un POST directo simulando el formulario, se deniega.
    http_response_code(403);
    exit('Origen no autorizado.');
}

// Solo POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Método no permitido');
}

// ---------- RATE LIMITING BÁSICO ----------
$ahora = time();
if (isset($_SESSION['ultimo_envio']) && ($ahora - $_SESSION['ultimo_envio']) < 30) {
    http_response_code(422); 
    header('Location: ' . $gracias_url . '?error=frecuencia');
    exit;
}

// ---------- HONEYPOT ANTI-SPAM ----------
if (!empty($_POST['website'])) {
    header('Location: ' . $gracias_url . '?ok=1');
    exit;
}

// ---------- SANITIZACIÓN ----------
function clean_secure($v, $max_len = 1000) {
    if ($v === null) return '';
    $v = substr(trim($v), 0, $max_len);
    $v = str_replace(["\r", "\n", "%0a", "%0d"], '', $v);
    $v = strip_tags($v);
    return htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
}

$nombre   = clean_secure($_POST['nombre']   ?? '', 80);
$email    = clean_secure($_POST['email']    ?? '', 100);
$telefono = clean_secure($_POST['telefono'] ?? '', 30);
$empresa  = clean_secure($_POST['empresa']  ?? '', 100);
$conoce   = clean_secure($_POST['conoce']   ?? '', 100);
$origen   = clean_secure($_POST['origen']   ?? 'sin-origen', 50);

$mensaje = $_POST['mensaje'] ?? '';
$mensaje = substr(trim($mensaje), 0, 3000);
$mensaje = htmlspecialchars(strip_tags($mensaje), ENT_QUOTES, 'UTF-8');

$referer  = clean_secure($_SERVER['HTTP_REFERER'] ?? 'desconocido', 250);
$ip       = filter_var($_SERVER['REMOTE_ADDR'] ?? '', FILTER_VALIDATE_IP) ? $_SERVER['REMOTE_ADDR'] : 'desconocida';

// ---------- VALIDACIÓN ----------
if (empty($nombre) || empty($email) || empty($mensaje)) {
    header('Location: ' . $gracias_url . '?error=campos');
    exit;
}

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

// ---------- PHPMAILER ----------
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
    $mail->SMTPDebug  = 0; 

    // Destinatarios
    $mail->setFrom($from_email, $from_nombre);
    $mail->addAddress($destinatario);
    
    if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $mail->addReplyTo($email, $nombre);
    }

    $mail->isHTML(false); 
    $mail->Subject = $subject;
    $mail->Body    = $body;

    $mail->send();
    
    $_SESSION['ultimo_envio'] = time();
    
    header('Location: ' . $gracias_url . '?ok=1');
    exit;

} catch (Exception $e) {
    header('Location: ' . $gracias_url . '?error=server');
    exit;
}*/
?>
