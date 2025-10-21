<?php

echo "✅ Debug activo<br>";

// src/register.php
declare(strict_types=1);

require_once __DIR__ . '/includes/db.php';

$error   = '';
$success = '';

function validar_dni(string $doc): bool { return (bool)preg_match('/^[0-9]{8}$/', $doc); }
function validar_ruc(string $doc): bool {
  if (!preg_match('/^[0-9]{11}$/', $doc)) return false;
  $factors = [5,4,3,2,7,6,5,4,3,2];
  $sum = 0;
  for ($i=0; $i<10; $i++) { $sum += ((int)$doc[$i]) * $factors[$i]; }
  $resto  = $sum % 11;
  $digito = 11 - $resto;
  if ($digito === 10) $digito = 0;
  elseif ($digito === 11) $digito = 1;
  return $digito === (int)$doc[10];
}
function validar_pasaporte(string $doc): bool { return (bool)preg_match('/^[A-Za-z0-9]{9,12}$/', $doc); }

/** Valida contraseña robusta. Devuelve null si todo OK, o un mensaje si falla. */
function validar_password_robusta(
  string $pwd,
  string $login = '',
  string $email = '',
  string $nombres = '',
  string $apellidos = ''
): ?string {
  if (strlen($pwd) < 10 || strlen($pwd) > 64) return 'La contraseña debe tener entre 10 y 64 caracteres.';
  if (preg_match('/\s/', $pwd)) return 'La contraseña no debe contener espacios.';
  if (!preg_match('/[A-Z]/', $pwd)) return 'Debe incluir al menos una letra mayúscula (A-Z).';
  if (!preg_match('/[a-z]/', $pwd)) return 'Debe incluir al menos una letra minúscula (a-z).';
  if (!preg_match('/[0-9]/', $pwd)) return 'Debe incluir al menos un dígito (0-9).';
  if (!preg_match('/[!@#$%^&*()_\+\=\-\[\]{};:,.?]/', $pwd)) return 'Debe incluir al menos un caracter especial: !@#$%^&*()_+=-[]{};:,.?';

  $lowerPwd = mb_strtolower($pwd, 'UTF-8');
  $prohibidos = [];
  if ($login) $prohibidos[] = mb_strtolower($login, 'UTF-8');
  if ($email) { $local = mb_strtolower((string)strtok($email, '@'), 'UTF-8'); if ($local) $prohibidos[] = $local; }
  foreach (preg_split('/\s+/', trim($nombres . ' ' . $apellidos)) as $pieza) {
    $pieza = mb_strtolower($pieza, 'UTF-8');
    if (mb_strlen($pieza, 'UTF-8') >= 4) $prohibidos[] = $pieza;
  }
  foreach ($prohibidos as $p) {
    if ($p !== '' && mb_strpos($lowerPwd, $p, 0, 'UTF-8') !== false) {
      return 'No debe contener partes de tu usuario, correo, nombres o apellidos.';
    }
  }

  $comunes = ['123456','123456789','12345678','12345','qwerty','password','111111','abc123','123123','iloveyou','admin','welcome','monkey','dragon','qwertyuiop','000000'];
  if (in_array(mb_strtolower($pwd, 'UTF-8'), $comunes, true)) return 'La contraseña es demasiado común. Elige otra.';
  return null;
}

// catálogos
$tiposDoc = $pdo->query('SELECT id_tipodoc, nombre FROM tipo_documento ORDER BY id_tipodoc')->fetchAll();
$roles    = $pdo->query('SELECT id_rol, nombre FROM rol_usuarios WHERE estado = 1 ORDER BY id_rol')->fetchAll();

// valores del form
$id_tipodoc    = (int)($_POST['id_tipodoc'] ?? 0);
$id_rol        = (int)($_POST['id_rol'] ?? 0);
$nro_documento = trim($_POST['nro_documento'] ?? '');

// Para personas
$nombres       = trim($_POST['nombres'] ?? '');
$apellidos     = trim($_POST['apellidos'] ?? '');

// Para empresas (RUC)
$empresa       = trim($_POST['empresa'] ?? '');

$email         = trim($_POST['email'] ?? '');
$loginU        = trim($_POST['login'] ?? '');

// NUEVOS CAMPOS
$telefono      = trim($_POST['telefono'] ?? '');
$direccion     = trim($_POST['direccion'] ?? '');
var_dump($_POST);
exit;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $password = $_POST['password'] ?? '';
  $confirm  = $_POST['confirm'] ?? '';

  if (!$id_tipodoc || !$id_rol || !$nro_documento || !$email || !$loginU || !$password || !$confirm) {
    $error = 'Todos los campos son obligatorios.';
  } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $error = 'El correo no es válido.';
  } elseif ($password !== $confirm) {
    $error = 'Las contraseñas no coinciden.';
  } else {
    // Validar doc por tipo
    $okDoc = false;
    if     ($id_tipodoc === 1) $okDoc = validar_dni($nro_documento);
    elseif ($id_tipodoc === 2) $okDoc = validar_ruc($nro_documento);
    elseif ($id_tipodoc === 3) $okDoc = validar_pasaporte($nro_documento);

    if (!$okDoc) {
      $error = 'Número de documento inválido para el tipo seleccionado.';
    } else {
      // Ajuste de nombres según tipo
      if ($id_tipodoc === 2) { // RUC
        if ($empresa === '') {
          $error = 'La razón social no fue completada. Usa el autocompletado por SUNAT.';
        } else {
          // para la tabla usuario (bd_ferreteria) usaremos un solo campo nombre
          $nombres   = $empresa; // razón social
          $apellidos = '';
        }
      } else {
        // DNI o Pasaporte: se espera nombres y apellidos
        if ($nombres === '' || $apellidos === '') {
          $error = 'Nombres y apellidos son obligatorios (usa el autocompletado).';
        }
      }

      // Validación ligera de teléfono/dirección (opcionales)
      if ($error === '') {
        if ($telefono !== '' && !preg_match('/^[0-9+\-\s]{6,20}$/', $telefono)) {
          $error = 'Teléfono no válido.';
        } elseif ($direccion !== '' && mb_strlen($direccion, 'UTF-8') > 70) {
          $error = 'Dirección demasiado larga (máx 70).';
        }
      }

      // Validación de contraseña robusta
      if ($error === '') {
        $errPwd = validar_password_robusta($password, $loginU, $email, $nombres, $apellidos);
        if ($errPwd !== null) {
          $error = $errPwd;
        }
      }

      if ($error === '') {
        // Duplicados (bd_ferreteria: usuario tiene num_documento e id_tipodoc)
        $dup = $pdo->prepare('
          SELECT 1
          FROM usuario
          WHERE email = ?
             OR login = ?
             OR (id_tipodoc = ? AND num_documento = ?)
          LIMIT 1
        ');
        $dup->execute([$email, $loginU, $id_tipodoc, $nro_documento]);
        if ($dup->fetch()) {
          $error = 'El email, usuario o documento ya está registrado.';
        } else {
          $hash = hash('sha256', $password); // (en producción recomendaría password_hash)
          $nombreFinal = ($id_tipodoc === 2) ? $empresa : trim($nombres . ' ' . $apellidos);

          // Inserción REAL en bd_ferreteria.usuario (agregado telefono y direccion)
          $ins = $pdo->prepare('
            INSERT INTO usuario
              (id_tipodoc, num_documento, id_rol, nombre, email, login, clave, telefono, direccion, condicion)
            VALUES
              (?,          ?,              ?,      ?,      ?,     ?,     ?,     ?,        ?,         1)
          ');
          if ($ins->execute([$id_tipodoc, $nro_documento, $id_rol, $nombreFinal, $email, $loginU, $hash, $telefono, $direccion])) {
            $success = 'Registro exitoso. Ahora puedes iniciar sesión.';
            // Limpiar
            $id_tipodoc = $id_rol = 0;
            $nro_documento = $nombres = $apellidos = $empresa = $email = $loginU = $telefono = $direccion = '';
          } else {
            $error = 'Error al registrar. Intenta nuevamente.';
          }
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
  <title>Registro - Sistema Ventas</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <link rel="stylesheet" href="css/estilos.css?v=<?= time() ?>">
  <style>
    .req { display:flex; align-items:center; gap:8px; font-size:.9rem; }
    .req i{ width:14px; text-align:center; }
    .req.bad{ color:#ef4444; }
    .req.ok{ color:#10b981; }
    .input-eye { position:absolute; right:10px; top:50%; transform:translateY(-50%); cursor:pointer; opacity:.75; }
    .input-wrap { position:relative; }
    .hidden { display:none !important; }
  </style>
</head>
<body class="auth-body">
  <div class="auth-wrapper">
    <section class="auth-card">
      <div class="auth-left">
        <div class="brand-wrap">
          <img src="assets/logo.png" alt="Logo Empresa" class="brand-logo">
          <h1 class="brand-title">Registro</h1>
          <p class="brand-sub">¿Ya tienes cuenta?</p>
          <a class="btn btn-outline" href="index.php?m=login">Login</a>
        </div>
      </div>

      <div class="auth-right">
        <h2 class="auth-title">Crear cuenta</h2>

        <?php if ($error): ?>
          <div class="alert alert-error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
          <div class="alert alert-success"><?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>

        <form method="post" action="register.php" class="auth-form" autocomplete="off" novalidate>
          <!-- Tipo doc -->
          <label class="field">
            <span class="field-label">Tipo de documento</span>
            <select id="tipodoc" name="id_tipodoc" required>
              <option value="">Seleccione…</option>
              <?php foreach ($tiposDoc as $td): ?>
                <option value="<?= (int)$td['id_tipodoc'] ?>" <?= ((int)$td['id_tipodoc']===$id_tipodoc?'selected':'') ?>>
                  <?= htmlspecialchars($td['nombre']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </label>

          <label class="field">
            <span class="field-label">Nro. de documento</span>
            <input id="nrodoc" type="text" name="nro_documento" value="<?= htmlspecialchars($nro_documento) ?>" required>
            <small id="hintdoc" class="hint"></small>
          </label>

          <!-- Empresa (solo RUC) -->
          <label class="field <?= $id_tipodoc===2 ? '' : 'hidden' ?>" id="wrap-empresa">
            <span class="field-label">Razón social / Nombre de la empresa</span>
            <input id="empresa" type="text" name="empresa" value="<?= htmlspecialchars($empresa) ?>" placeholder="Autocompletado por SUNAT" readonly>
          </label>

          <!-- Persona (DNI/Pasaporte) -->
          <label class="field <?= $id_tipodoc===2 ? 'hidden' : '' ?>" id="wrap-nombres">
            <span class="field-label">Nombres</span>
            <input id="nombres" type="text" name="nombres" value="<?= htmlspecialchars($nombres) ?>" placeholder="Autocompletado por RENIEC" readonly>
          </label>

          <label class="field <?= $id_tipodoc===2 ? 'hidden' : '' ?>" id="wrap-apellidos">
            <span class="field-label">Apellidos</span>
            <input id="apellidos" type="text" name="apellidos" value="<?= htmlspecialchars($apellidos) ?>" placeholder="Autocompletado por RENIEC" readonly>
          </label>

          <label class="field"><span class="field-label">Email</span>
            <input type="email" name="email" value="<?= htmlspecialchars($email) ?>" required>
          </label>
          <label class="field"><span class="field-label">Usuario</span>
            <input type="text" name="login" value="<?= htmlspecialchars($loginU) ?>" required>
          </label>

          <!-- NUEVOS CAMPOS -->
          <label class="field"><span class="field-label">Teléfono</span>
            <input type="text" name="telefono" value="<?= htmlspecialchars($telefono) ?>" placeholder="Opcional (6–20 caracteres)">
          </label>
          <label class="field"><span class="field-label">Dirección</span>
            <input type="text" name="direccion" value="<?= htmlspecialchars($direccion) ?>" placeholder="Opcional (máx. 70)">
          </label>

          <!-- Rol -->
          <label class="field">
            <span class="field-label">Selecciona tu rol</span>
            <div class="role-selector">
              <?php foreach ($roles as $rol): ?>
                <input type="radio" id="rol<?= (int)$rol['id_rol'] ?>" name="id_rol" value="<?= (int)$rol['id_rol'] ?>" <?= ((int)$rol['id_rol']===$id_rol?'checked':'') ?> required>
                <label for="rol<?= (int)$rol['id_rol'] ?>" class="role-btn"><?= htmlspecialchars($rol['nombre']) ?></label>
              <?php endforeach; ?>
            </div>
          </label>

          <label class="field">
            <span class="field-label">Contraseña</span>
            <div class="input-wrap">
              <input id="pwd" type="password" name="password" required aria-describedby="pwdHelp">
              <span class="input-eye" id="togglePwd" title="Ver/Ocultar">👁️</span>
            </div>
            <small id="pwdHelp" class="hint">Debe cumplir todos los requisitos:</small>
            <div id="rules" style="margin-top:6px">
              <div class="req bad" id="r-len"><i>•</i> 10–64 caracteres</div>
              <div class="req bad" id="r-up"><i>•</i> Al menos 1 mayúscula (A-Z)</div>
              <div class="req bad" id="r-low"><i>•</i> Al menos 1 minúscula (a-z)</div>
              <div class="req bad" id="r-num"><i>•</i> Al menos 1 número (0-9)</div>
              <div class="req bad" id="r-spe"><i>•</i> Al menos 1 especial (!@#$%^&*()_+=-[]{};:,.?)</div>
              <div class="req bad" id="r-spc"><i>•</i> Sin espacios</div>
              <div class="req bad" id="r-pii"><i>•</i> No contiene usuario/correo/nombres</div>
              <div class="req bad" id="r-common"><i>•</i> No es una contraseña común</div>
            </div>
          </label>

          <label class="field">
            <span class="field-label">Confirmar contraseña</span>
            <div class="input-wrap">
              <input id="pwd2" type="password" name="confirm" required>
              <span class="input-eye" id="togglePwd2" title="Ver/Ocultar">👁️</span>
            </div>
          </label>

          <button type="submit" class="btn btn-primary w-full">Crear cuenta</button>
          <p class="small text-center m-top">¿Ya tienes cuenta? <a href="index.php?m=login" class="link-strong">Inicia sesión</a></p>
        </form>
      </div>
    </section>
  </div>

<script>
// === Cambia máscara + visibilidad de campos según tipo de doc ===
const tipodoc=document.getElementById('tipodoc');
const nrodoc=document.getElementById('nrodoc');
const hint=document.getElementById('hintdoc');
const wrapEmp = document.getElementById('wrap-empresa');
const wrapNom = document.getElementById('wrap-nombres');
const wrapApe = document.getElementById('wrap-apellidos');
const nombres = document.getElementById('nombres');
const apellidos = document.getElementById('apellidos');
const empresa = document.getElementById('empresa');

function setupDocMask(){
  let t=parseInt(tipodoc.value||'0',10);
  nrodoc.removeAttribute('pattern');nrodoc.removeAttribute('maxlength');
  if(t===1){ // DNI
    nrodoc.setAttribute('pattern','^[0-9]{8}$');nrodoc.maxLength=8;hint.textContent='DNI: 8 dígitos';
    wrapEmp.classList.add('hidden'); wrapNom.classList.remove('hidden'); wrapApe.classList.remove('hidden');
  }
  else if(t===2){ // RUC
    nrodoc.setAttribute('pattern','^[0-9]{11}$');nrodoc.maxLength=11;hint.textContent='RUC: 11 dígitos';
    wrapEmp.classList.remove('hidden'); wrapNom.classList.add('hidden'); wrapApe.classList.add('hidden');
  }
  else if(t===3){ // Pasaporte
    nrodoc.setAttribute('pattern','^[A-Za-z0-9]{9,12}$');nrodoc.maxLength=12;hint.textContent='Pasaporte: 9-12 caracteres';
    wrapEmp.classList.add('hidden'); wrapNom.classList.remove('hidden'); wrapApe.classList.remove('hidden');
  } else {
    hint.textContent='';
    wrapEmp.classList.add('hidden'); wrapNom.classList.remove('hidden'); wrapApe.classList.remove('hidden');
  }
}
tipodoc.addEventListener('change',setupDocMask);
document.addEventListener('DOMContentLoaded',setupDocMask);

// === Ver/ocultar contraseñas ===
function togglePass(id, btnId){
  const input = document.getElementById(id);
  const btn = document.getElementById(btnId);
  btn.addEventListener('click', ()=>{ input.type = (input.type==='password'?'text':'password'); });
}
togglePass('pwd','togglePwd');
togglePass('pwd2','togglePwd2');

// === Checklist en vivo de contraseña (cliente) ===
(function(){
  const pwd = document.getElementById('pwd');
  const pwd2 = document.getElementById('pwd2');
  const login = document.querySelector('input[name="login"]');
  const email = document.querySelector('input[name="email"]');

  const common = new Set(['123456','123456789','12345678','12345','qwerty','password','111111','abc123','123123','iloveyou','admin','welcome','monkey','dragon','qwertyuiop','000000']);

  function mark(id, ok){ const el=document.getElementById(id); el.classList.toggle('ok', ok); el.classList.toggle('bad', !ok); }

  function strongCheck(v){
    const len = v.length>=10 && v.length<=64;
    const up  = /[A-Z]/.test(v);
    const low = /[a-z]/.test(v);
    const num = /[0-9]/.test(v);
    const spe = /[!@#$%^&*()_\+\=\-\[\]{};:,.?]/.test(v);
    const spc = !/\s/.test(v);

    const lowers = v.toLowerCase();
    let pii = true;
    const pieces = [];
    if (login && login.value) pieces.push(login.value.toLowerCase());
    if (email && email.value) pieces.push((email.value.split('@')[0]||'').toLowerCase());
    (nombres.value+' '+apellidos.value).split(/\s+/).forEach(p=>{ p=p.toLowerCase(); if(p.length>=4) pieces.push(p); });
    for (const p of pieces){ if(p && lowers.includes(p)){ pii=false; break; } }

    const notCommon = !common.has(lowers);

    mark('r-len', len); mark('r-up', up); mark('r-low', low);
    mark('r-num', num); mark('r-spe', spe); mark('r-spc', spc);
    mark('r-pii', pii); mark('r-common', notCommon);

    return len && up && low && num && spe && spc && pii && notCommon;
  }

  function syncValidity(){
    strongCheck(pwd.value);
    if (!strongCheck(pwd.value)) { pwd.setCustomValidity('La contraseña no cumple los requisitos mínimos.'); }
    else { pwd.setCustomValidity(''); }
    if (pwd2.value && pwd2.value !== pwd.value) { pwd2.setCustomValidity('Las contraseñas no coinciden.'); }
    else { pwd2.setCustomValidity(''); }
  }
  pwd.addEventListener('input', syncValidity);
  pwd2.addEventListener('input', syncValidity);
  login.addEventListener('input', syncValidity);
  email.addEventListener('input', syncValidity);
})();

// === RENIEC (DNI) con debounce + cancelación ===
(function(){
  const tip = document.getElementById('tipodoc');
  let t; let inflight; let lastQueried = '';

  function ready(){
    return parseInt(tip.value||'0',10)===1 && /^\d{8}$/.test(nrodoc.value);
  }

  async function consulta(){
    if(!ready()) return;
    if (nrodoc.value === lastQueried) return;
    if (inflight) inflight.abort();
    inflight = new AbortController();

    const prevN=nombres.value, prevA=apellidos.value;
    nombres.value='Consultando RENIEC...'; apellidos.value='Consultando RENIEC...';

    try{
      const res = await fetch(`ajax/reniec.php?dni=${encodeURIComponent(nrodoc.value)}`, {
        headers:{'X-Requested-With':'fetch'}, cache:'no-store', signal: inflight.signal
      });
      const data = await res.json();
      if(!res.ok || data.success===false) throw new Error(data.message || 'Fallo consultando proveedor');
      nombres.value = data.nombres || '';
      apellidos.value = data.apellidos || '';
      lastQueried = nrodoc.value;
    }catch(e){
      if (e.name === 'AbortError') return;
      nombres.value = prevN; apellidos.value = prevA;
      alert(e.message || 'Fallo consultando proveedor');
      lastQueried = '';
    }
  }

  function debounce(){ clearTimeout(t); t=setTimeout(()=>{ if(ready()) consulta(); }, 450); }
  tip.addEventListener('change', debounce);
  nrodoc.addEventListener('input', debounce);
  nrodoc.addEventListener('blur', ()=>{ if(ready()) consulta(); });
})();

// === SUNAT (RUC) con debounce + cancelación ===
(function(){
  const tip = document.getElementById('tipodoc');
  let t; let inflight; let lastRuc='';

  function ready(){
    return parseInt(tip.value||'0',10)===2 && /^\d{11}$/.test(nrodoc.value);
  }

  async function consulta(){
    if(!ready()) return;
    if (nrodoc.value === lastRuc) return;
    if (inflight) inflight.abort();
    inflight = new AbortController();

    const prev = empresa.value;
    empresa.value = 'Consultando SUNAT...';
    try{
      const res = await fetch(`ajax/sunat.php?ruc=${encodeURIComponent(nrodoc.value)}`, {
        headers:{'X-Requested-With':'fetch'}, cache:'no-store', signal: inflight.signal
      });
      const data = await res.json();
      if(!res.ok || data.success===false) throw new Error(data.message || 'Fallo consultando proveedor');
      empresa.value = data.razon_social || data.nombre_o_razon_social || '';
      lastRuc = nrodoc.value;
    }catch(e){
      if (e.name === 'AbortError') return;
      empresa.value = prev;
      alert(e.message || 'Fallo consultando proveedor');
      lastRuc='';
    }
  }

  function debounce(){ clearTimeout(t); t=setTimeout(()=>{ if(ready()) consulta(); }, 450); }
  tip.addEventListener('change', debounce);
  nrodoc.addEventListener('input', debounce);
  nrodoc.addEventListener('blur', ()=>{ if(ready()) consulta(); });
})();
</script>
</body>
</html>
