<?php
// src/includes/mailer_smtp.php
declare(strict_types=1);

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/**
 * Si NO usas Composer, coloca PHPMailer dentro de: src/PHPMailer/src/
 * Quedaría algo así:
 *   src/PHPMailer/src/PHPMailer.php
 *   src/PHPMailer/src/SMTP.php
 *   src/PHPMailer/src/Exception.php
 */
require_once __DIR__ . '/../PHPMailer/src/Exception.php';
require_once __DIR__ . '/../PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/../PHPMailer/src/SMTP.php';

/**
 * ============================
 * CONFIGURA EL PROVEEDOR SMTP
 * ============================
 * Opciones: 'gmail' | 'outlook' | 'sendgrid_smtp'
 */
const SMTP_PROVIDER = 'gmail';

/** GMAIL (requiere App Password de 16 chars con 2FA activo) */
const GMAIL_USERNAME     = 'nekosaccix@gmail.com';
const GMAIL_APP_PASSWORD = 'ittsmjryvyjpckxp';

/** OUTLOOK/HOTMAIL (habilita “Authenticated SMTP” o usa App Password si tienes MFA) */
const OUTLOOK_USERNAME = 'tu_correo@outlook.com';
const OUTLOOK_PASSWORD = 'TU_PASSWORD_O_APP_PASSWORD';

/** SENDGRID SMTP (usuario SIEMPRE 'apikey' y la contraseña es tu API key) */
const SENDGRID_SMTP_USER = 'apikey';
const SENDGRID_SMTP_PASS = 'SG.xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx';
const SENDGRID_FROM_MAIL = 'remitente_verificado@sendgrid'; // Single Sender o dominio autenticado
const SENDGRID_FROM_NAME = 'Neko SAC';

/** COMÚN: Nombre para remitente cuando no lo impone el proveedor */
const DEFAULT_FROM_NAME = 'Neko SAC';

/**
 * Envía un correo HTML vía SMTP (PHPMailer). Devuelve [ok, error].
 */
function send_mail_smtp(string $to, string $toName, string $subject, string $html): array {
    $mail = new PHPMailer(true);
    try {
        // Activa esto SOLO para depurar (verás el diálogo SMTP en error_log)
        // $mail->SMTPDebug  = 2;
        // $mail->Debugoutput = static function($s){ error_log("SMTP DEBUG: $s"); };

        $mail->isSMTP();
        $mail->CharSet = 'UTF-8';

        if (SMTP_PROVIDER === 'gmail') {
            $mail->Host       = gethostbyname('smtp.gmail.com'); // fuerza IPv4
            $mail->SMTPAuth   = true;
            $mail->Username   = GMAIL_USERNAME;
            $mail->Password   = GMAIL_APP_PASSWORD; // App Password
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = 587;
            $mail->setFrom(GMAIL_USERNAME, DEFAULT_FROM_NAME);

        } elseif (SMTP_PROVIDER === 'outlook') {
            $mail->Host       = gethostbyname('smtp.office365.com'); // o smtp-mail.outlook.com
            $mail->SMTPAuth   = true;
            $mail->Username   = OUTLOOK_USERNAME;
            $mail->Password   = OUTLOOK_PASSWORD; // normal o app password
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = 587;
            $mail->setFrom(OUTLOOK_USERNAME, DEFAULT_FROM_NAME);

        } elseif (SMTP_PROVIDER === 'sendgrid_smtp') {
            $mail->Host       = gethostbyname('smtp.sendgrid.net');
            $mail->SMTPAuth   = true;
            $mail->Username   = SENDGRID_SMTP_USER; // 'apikey'
            $mail->Password   = SENDGRID_SMTP_PASS; // tu API key SendGrid
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = 587;
            // Remitente debe ser verificado (Single Sender o dominio autenticado)
            $mail->setFrom(SENDGRID_FROM_MAIL, SENDGRID_FROM_NAME ?: DEFAULT_FROM_NAME);

        } else {
            throw new Exception('SMTP_PROVIDER inválido');
        }

        // Opciones TLS relajadas para dev en Windows (quítalas en producción)
        $mail->SMTPOptions = [
            'ssl' => [
                'verify_peer'       => false,
                'verify_peer_name'  => false,
                'allow_self_signed' => true,
            ],
        ];

        $mail->addAddress($to, $toName ?: $to);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $html;
        $mail->AltBody = strip_tags(preg_replace('/<br\s*\/?>/i', "\n", $html));

        $ok = $mail->send();
        return [$ok, $ok ? '' : $mail->ErrorInfo];

    } catch (Exception $e) {
        return [false, 'Mailer Exception: ' . $e->getMessage()];
    }
}

/**
 * Helper OTP: arma el correo y llama a send_mail_smtp(). Devuelve bool.
 */
function sendAuthCode(string $toEmail, string $otp): bool {
    $subject = 'Tu código de verificación (OTP)';
    $html = "
        <p>Hola,</p>
        <p>Tu código de verificación es:</p>
        <h2 style='font-family:monospace;letter-spacing:4px;text-align:center;margin:16px 0;'>{$otp}</h2>
        <p>Este código expira en 10 minutos.</p>
        <p>Si no fuiste tú, ignora este mensaje.</p>
    ";
    [$ok, $err] = send_mail_smtp($toEmail, '', $subject, $html);
    if (!$ok) { error_log('OTP MAIL ERROR: ' . $err); }
    return $ok;
}
