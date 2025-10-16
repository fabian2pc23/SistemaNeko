<?php
// verify.php
declare(strict_types=1);

require_once __DIR__ . '/includes/db.php';          // $pdo (PDO)
require_once __DIR__ . '/includes/mailer_smtp.php'; // sendAuthCode($to, $code)

if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

// Debe venir del login (guardamos idusuario en sesión para OTP)
$userId = $_SESSION['otp_uid'] ?? null;
if (!$userId) {
  header('Location: login.php');
  exit;
}

/* ======================== CONFIG ======================== */
$MAX_ATTEMPTS = 5;   // intentos máximos permitidos por OTP
$OTP_TTL_MIN  = 10;  // minutos de validez del OTP
/* ======================================================== */

$error   = '';
$success = '';

/* -------------- Reenviar OTP -------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'resend') {
  $stmt = $pdo->prepare('SELECT email, nombre, condicion FROM usuario WHERE idusuario = ? LIMIT 1');
  $stmt->execute([(int)$userId]);
  $u = $stmt->fetch();

  if (!$u || (int)$u['condicion'] !== 1) {
    $error = 'Usuario no válido o inactivo.';
  } else {
    $otp     = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $otpHash = hash('sha256', $otp);
    $expires = (new DateTime("+{$OTP_TTL_MIN} minutes"))->format('Y-m-d H:i:s');

    $pdo->prepare('DELETE FROM user_otp WHERE user_id = ?')->execute([(int)$userId]);
    $ins = $pdo->prepare('INSERT INTO user_otp (user_id, code_hash, expires_at, attempts) VALUES (?, ?, ?, 0)');
    $ins->execute([(int)$userId, $otpHash, $expires]);

    $mailOk = sendAuthCode((string)$u['email'], $otp);
    if ($mailOk) {
      $_SESSION['otp_sent'] = time();
      $success = 'Hemos enviado un nuevo código a tu correo.';
    } else {
      $error = 'No pudimos reenviar el código. Revisa tu configuración SMTP.';
    }
  }
}

/* -------------- Verificar OTP -------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'verify') {
  $code = preg_replace('/\D+/', '', $_POST['code'] ?? '');
  if ($code === '' || strlen($code) !== 6) {
    $error = 'Ingresa el código de 6 dígitos.';
  } else {
    $stmt = $pdo->prepare('
      SELECT id, code_hash, expires_at, COALESCE(attempts,0) AS attempts
      FROM user_otp
      WHERE user_id = ?
      ORDER BY id DESC
      LIMIT 1
    ');
    $stmt->execute([(int)$userId]);
    $otpRow = $stmt->fetch();

    if (!$otpRow) {
      $error = 'No hay un código activo. Vuelve a iniciar sesión.';
    } else {
      $now = new DateTimeImmutable('now');
      $exp = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', (string)$otpRow['expires_at']) ?: $now;

      if ($now > $exp) {
        $pdo->prepare('DELETE FROM user_otp WHERE id = ?')->execute([(int)$otpRow['id']]);
        $error = 'El código ha expirado. Reenvíalo o vuelve a iniciar sesión.';
      } elseif ((int)$otpRow['attempts'] >= $MAX_ATTEMPTS) {
        $pdo->prepare('DELETE FROM user_otp WHERE id = ?')->execute([(int)$otpRow['id']]);
        $error = 'Demasiados intentos. Vuelve a iniciar sesión.';
      } else {
        $inputHash = hash('sha256', $code);
        if (hash_equals(strtolower((string)$otpRow['code_hash']), strtolower($inputHash))) {
          // Código correcto: borrar OTP y autenticar
          $pdo->prepare('DELETE FROM user_otp WHERE id = ?')->execute([(int)$otpRow['id']]);

          // Traer datos del usuario
          $u = $pdo->prepare('SELECT idusuario, nombre, condicion, id_rol FROM usuario WHERE idusuario = ? LIMIT 1');
          $u->execute([(int)$userId]);
          $usr = $u->fetch();

          if (!$usr || (int)$usr['condicion'] !== 1) {
            $error = 'Usuario inactivo.';
          } else {
            // ======= SESIÓN AUTENTICADA =======
            session_regenerate_id(true);
            $_SESSION['idusuario']  = (int)$usr['idusuario'];
            $_SESSION['nombre']     = (string)$usr['nombre'];
            $_SESSION['imagen']     = $_SESSION['imagen'] ?? 'default.png';

            // ======= SETEO DE FLAGS DEL MENÚ SEGÚN PERMISOS =======
            $flags = [
              'escritorio'=>0,
              'almacen'   =>0,
              'compras'   =>0,
              'ventas'    =>0,
              'acceso'    =>0,
              'consultac' =>0,
              'consultav' =>0,
            ];

            // Intentar cargar permisos reales
            $map = [
              'ESCRITORIO'        => 'escritorio',
              'ALMACEN'           => 'almacen',
              'COMPRAS'           => 'compras',
              'VENTAS'            => 'ventas',
              'ACCESO'            => 'acceso',
              'CONSULTA COMPRAS'  => 'consultac',
              'CONSULTA VENTAS'   => 'consultav',
            ];

            try {
              // Permisos por rol
              $qRolPerm = $pdo->prepare("
                SELECT p.nombre
                FROM usuario u
                JOIN rol_permiso rp ON rp.id_rol = u.id_rol
                JOIN permiso p      ON p.idpermiso = rp.idpermiso
                WHERE u.idusuario = ?
              ");
              $qRolPerm->execute([(int)$usr['idusuario']]);
              $perms = $qRolPerm->fetchAll(PDO::FETCH_COLUMN);

              // Permisos directos por usuario
              $qUsrPerm = $pdo->prepare("
                SELECT p.nombre
                FROM usuario_permiso up
                JOIN permiso p ON p.idpermiso = up.idpermiso
                WHERE up.idusuario = ?
              ");
              $qUsrPerm->execute([(int)$usr['idusuario']]);
              $perms = array_merge($perms, $qUsrPerm->fetchAll(PDO::FETCH_COLUMN));

              foreach ($perms as $p) {
                $key = strtoupper(trim((string)$p));
                if (isset($map[$key])) {
                  $flags[$map[$key]] = 1;
                }
              }
            } catch (Throwable $e) {
              // Si falla carga de permisos, habilitamos TODO para pruebas:
              $flags = array_map(fn() => 1, $flags);
            }

            // Guardar flags
            foreach ($flags as $k => $v) $_SESSION[$k] = $v ? 1 : 0;

            // Limpiar OTP de sesión
            unset($_SESSION['otp_uid'], $_SESSION['otp_sent'], $_SESSION['otp_name'], $_SESSION['otp_email']);

            // Ir a escritorio
            header('Location: vistas/escritorio.php');
            exit;
          }
        } else {
          $pdo->prepare('UPDATE user_otp SET attempts = attempts + 1 WHERE id = ?')->execute([(int)$otpRow['id']]);
          $restantes = max(0, $MAX_ATTEMPTS - ((int)$otpRow['attempts'] + 1));
          $error = 'Código incorrecto. Intentos restantes: ' . $restantes . '.';
        }
      }
    }
  }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8" />
  <title>Verificación - Neko SAC</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <link rel="stylesheet" href="css/estilos.css?v=<?= time() ?>">
  <style>
    .otp-input{
      letter-spacing:.35em;text-align:center;
      font-family:ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,"Liberation Mono","Courier New",monospace;
      font-size:1.25rem
    }
    .btn-link{background:none;border:none;color:#6690ff;cursor:pointer;padding:0;font:inherit;text-decoration:underline}
    .auth-body{min-height:100vh;display:flex;align-items:center;justify-content:center;background:#0b1020}
    .auth-card{display:grid;grid-template-columns:1fr 1fr;max-width:920px;width:100%;background:#0e1530;color:#dce3ff;border-radius:18px;overflow:hidden;box-shadow:0 10px 35px rgba(0,0,0,.35)}
    .auth-left{padding:32px;background:linear-gradient(135deg,#0e1530 0,#0b1020 80%)}
    .brand-logo{height:48px;margin-bottom:12px}
    .brand-title{margin:6px 0 8px 0}
    .brand-sub{opacity:.75;margin:0 0 8px 0}
    .btn{display:inline-flex;align-items:center;justify-content:center;border-radius:10px;border:1px solid #3752d6;background:#2b3ed1;color:#fff;padding:10px 14px;cursor:pointer}
    .btn:hover{filter:brightness(1.05)}
    .btn-outline{background:transparent;border-color:#3c4ca8;color:#dce3ff}
    .auth-right{padding:32px;background:#0d1330}
    .auth-title{margin:0 0 12px 0}
    .auth-form .field{display:block;margin-bottom:14px}
    .field-label{display:block;margin-bottom:6px;font-size:.95rem;opacity:.85}
    .input{display:grid;grid-template-columns:1fr 40px;align-items:center;background:#0b1028;border:1px solid #2a3470;border-radius:10px;padding:4px 8px}
    .input input{background:transparent;border:none;color:#eaf0ff;outline:none;padding:10px 8px}
    .icon svg{width:20px;height:20px;opacity:.7}
    .alert{padding:10px 12px;border-radius:10px;margin:8px 0}
    .alert-error{background:#2a1120;color:#ffd8e4;border:1px solid #7a274a}
    .alert-success{background:#0f2a1a;color:#d4ffe6;border:1px solid #1f8a4b}
    .w-full{width:100%}
    .m-top{margin-top:.75rem}
    @media (max-width:840px){ .auth-card{grid-template-columns:1fr} .auth-left{display:none} }
  </style>
</head>
<body class="auth-body">
  <div class="auth-wrapper">
    <section class="auth-card">
      <div class="auth-left">
        <div class="brand-wrap">
          <img src="assets/logo.png" alt="Logo Empresa" class="brand-logo">
          <h1 class="brand-title">Verificación</h1>
          <p class="brand-sub">Te enviamos un código a tu correo.</p>
          <p class="brand-sub" style="opacity:.6">
            Usuario: <?= htmlspecialchars((string)($_SESSION['otp_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?><br>
            Correo: <?= htmlspecialchars((string)($_SESSION['otp_email'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
          </p>
        </div>
      </div>

      <div class="auth-right">
        <h2 class="auth-title">Ingresa tu código</h2>

        <?php if (!empty($error)): ?>
          <div class="alert alert-error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>
        <?php if (!empty($success)): ?>
          <div class="alert alert-success"><?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>

        <form method="post" class="auth-form" autocomplete="off" novalidate>
          <input type="hidden" name="action" value="verify">
          <label class="field">
            <span class="field-label">Código de 6 dígitos</span>
            <div class="input">
              <input class="otp-input" type="text" name="code" inputmode="numeric" pattern="\d{6}" maxlength="6" placeholder="••••••" required autofocus>
              <span class="icon">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 1a11 11 0 1 0 11 11A11.013 11.013 0 0 0 12 1Zm1 17h-2v-2h2Zm0-4h-2V6h2Z"/></svg>
              </span>
            </div>
          </label>
          <button type="submit" class="btn w-full">Verificar</button>
        </form>

        <form method="post" style="margin-top:.75rem;">
          <input type="hidden" name="action" value="resend">
          <button type="submit" class="btn-outline">Reenviar código</button>
        </form>

        <p class="small text-center m-top">
          ¿Problemas? <a href="login.php" class="btn-link" style="text-decoration:none;color:#9db1ff">Volver a iniciar sesión</a>
        </p>
      </div>
    </section>
  </div>
</body>
</html>
