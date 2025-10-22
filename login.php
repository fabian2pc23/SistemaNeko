<?php
// src/login.php
declare(strict_types=1);

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/mailer_smtp.php'; // PHPMailer (SMTP)

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $identity = trim($_POST['email'] ?? '');
  $password = $_POST['password'] ?? '';

  if ($identity !== '' && $password !== '') {
    // === Ajustado al esquema de bd_ferreteria.usuario ===
    $stmt = $pdo->prepare(
      'SELECT 
          idusuario   AS id_usuario,
          nombre      AS nombre,
          email,
          login,
          clave,
          condicion   AS estado   -- 1 = activo
       FROM usuario
       WHERE (email = ? OR login = ?)
       LIMIT 1'
    );
    $stmt->execute([$identity, $identity]);
    $user = $stmt->fetch();

    if ($user && (int)$user['estado'] === 1) {
      // En tu tabla "clave" es un sha256 en hex
      $inputHash = hash('sha256', $password);
      if (hash_equals(strtolower((string)$user['clave']), strtolower($inputHash))) {

        // === Generar OTP ===
        $otp      = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $otpHash  = hash('sha256', $otp);
        $expires  = (new DateTime('+10 minutes'))->format('Y-m-d H:i:s');
        $userId   = (int)$user['id_usuario'];
        $fullName = trim((string)$user['nombre']); // nombre completo en esta BD

        // Limpiar y guardar OTP (asegúrate de tener la tabla user_otp en esta BD)
        $pdo->prepare('DELETE FROM user_otp WHERE user_id = ?')->execute([$userId]);
        $ins = $pdo->prepare('INSERT INTO user_otp (user_id, code_hash, expires_at) VALUES (?, ?, ?)');
        $ins->execute([$userId, $otpHash, $expires]);

        // Enviar correo (PHPMailer SMTP)
        $mailOk = sendAuthCode((string)$user['email'], $otp);

        if (!$mailOk) {
          $error = 'No se pudo enviar el correo: revisa tu configuración SMTP en includes/mailer_smtp.php';
        } else {
          if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
          $_SESSION['otp_uid']    = $userId;
          $_SESSION['otp_name']   = $fullName;
          $_SESSION['otp_email']  = (string)$user['email'];
          $_SESSION['otp_sent']   = time();
          header('Location: verify.php');
          exit;
        }
      }
    }
  }

  $error = $error ?: 'Usuario o contraseña incorrectos';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8" />
  <title>Login - Neko SAC</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <link rel="stylesheet" href="css/estilos.css?v=<?= time() ?>">
</head>
<body class="auth-body">
  <div class="auth-wrapper">
    <section class="auth-card">
      <div class="auth-left">
        <div class="brand-wrap">
          <img src="assets/logo.png" alt="Logo Empresa" class="brand-logo">
          <h1 class="brand-title">Hola, ¡bienvenido!</h1>
          <p class="brand-sub">¿No tienes una cuenta?</p>
          <a class="btn btn-outline" href="register.php">Register</a>
        </div>
      </div>

      <div class="auth-right">
        <h2 class="auth-title">Login</h2>

        <?php if ($error): ?>
          <div class="alert alert-error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>

        <form method="post" action="login.php" class="auth-form" autocomplete="off" novalidate>
          <label class="field">
            <span class="field-label">Email o usuario</span>
            <div class="input">
              <input type="text" name="email" placeholder="tucorreo@empresa.com o tu usuario" required autocomplete="username">
              <span class="icon">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 12a5 5 0 1 0-5-5 5 5 0 0 0 5 5Zm0 2c-4.08 0-8 2.06-8 5v1h16v-1c0-2.94-3.92-5-8-5Z"/></svg>
              </span>
            </div>
          </label>

          <label class="field">
            <span class="field-label">Contraseña</span>
            <div class="input">
              <input type="password" name="password" placeholder="••••••••" required autocomplete="current-password">
              <span class="icon">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M17 9h-1V7a4 4 0 0 0-8 0v2H7a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-8a2 2 0 0 0-2-2Zm-7-2a2 2 0 0 1 4 0v2H10Zm7 12H7v-8h10Z"/></svg>
              </span>
            </div>
          </label>

          <div class="row-between">
            <a class="link-muted" href="forgot_password.php">¿Olvidaste tu contraseña?</a>
          </div>

          <button type="submit" class="btn btn-primary w-full">Login</button>

          <p class="small text-center m-top">
            ¿No tienes cuenta? <a href="register.php" class="link-strong">Regístrate</a>
          </p>
        </form>
      </div>
    </section>
  </div>
</body>
</html>
