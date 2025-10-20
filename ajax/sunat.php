<?php
declare(strict_types=1);

// --- CONFIGURACIÓN DE ERRORES Y TIEMPO ---
set_time_limit(60);
ini_set('display_errors', '0'); // 👈 no mostrar errores HTML
ini_set('log_errors', '1');
error_reporting(E_ALL);
// --- FIN CONFIGURACIÓN ---

header('Content-Type: application/json; charset=utf-8');

/** Construye una dirección legible a partir de distintos formatos */
function build_address($dom): string {
  if (is_string($dom)) {
    return trim($dom);
  }
  if (!is_array($dom)) return '';

  foreach (['direccion','domicilio_fiscal','domicilio','calle','via'] as $k) {
    if (!empty($dom[$k]) && is_string($dom[$k])) {
      return trim((string)$dom[$k]);
    }
  }

  $parts = [];
  foreach ([
    'via','tipo_via','nombre_via','calle','avenida','jr',
    'mz','lote','numero','nro','km','interior','dpto','piso','referencia'
  ] as $k) {
    if (!empty($dom[$k])) $parts[] = trim((string)$dom[$k]);
  }
  $dir = trim(implode(' ', $parts));

  $geo = [];
  foreach (['distrito','provincia','departamento'] as $k) {
    if (!empty($dom[$k])) $geo[] = trim((string)$dom[$k]);
  }
  if ($geo) {
    $dir = $dir ? ($dir . ', ' . implode(' - ', $geo)) : implode(' - ', $geo);
  }
  return $dir;
}

// --- VALIDAR RUC ---
$ruc_raw = (string)($_GET['ruc'] ?? '');
$ruc     = preg_replace('/\D+/', '', $ruc_raw);
if (strlen($ruc) !== 11) {
  http_response_code(400);
  echo json_encode(['success'=>false,'message'=>'RUC inválido (11 dígitos).']);
  exit;
}

// --- VALIDAR QUE CURL ESTÉ INSTALADO ---
if (!function_exists('curl_init')) {
  http_response_code(500);
  echo json_encode(['success'=>false, 'message'=>'La extensión cURL de PHP no está instalada.']);
  exit;
}

// --- TOKEN DE AUTORIZACIÓN ---
$TOKEN = getenv('MIAPI_CLOUD_TOKEN')
  ?: 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJ1c2VyX2lkIjo0MDEsImV4cCI6MTc2MTU0MTQxMn0.5M179k5ws4tayquMwg_yfVdbybQCDkKaTPUu6Dibt_E';

$url = "https://miapi.cloud/v1/ruc/{$ruc}";
$ch  = curl_init($url);
curl_setopt_array($ch, [
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_TIMEOUT        => 20,
  CURLOPT_FOLLOWLOCATION => true,
  CURLOPT_SSL_VERIFYPEER => true,
  CURLOPT_HTTPHEADER     => [
    "Authorization: Bearer {$TOKEN}",
    "Accept: application/json",
    "Content-Type: application/json",
    "User-Agent: proveedor-app/1.0",
  ],
]);

$body = curl_exec($ch);
$errn = curl_errno($ch);
$err  = curl_error($ch);
$code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
file_put_contents('/tmp/debug_miapi.txt', "HTTP: {$code}\nErrNo: {$errn}\nError: {$err}\nBody:\n{$body}\n");
curl_close($ch);


// --- MANEJO DE ERRORES CURL / TOKEN / API ---

// 💥 Error de conexión CURL
if ($errn !== 0) {
  http_response_code(500);
  echo json_encode([
    'success' => false,
    'message' => 'Error de conexión con la API (CURL falló).',
    'details' => "cURL Error: {$err} (ErrNo: {$errn})",
  ]);
  exit;
}


// 💥 Si la API devolvió HTML (token inválido, error del servidor, etc.)
if (stripos($body, '<html') !== false || stripos($body, '<!DOCTYPE') !== false) {
  http_response_code(502);
  echo json_encode([
    'success' => false,
    'message' => 'El servidor remoto devolvió HTML en lugar de JSON. Posible token inválido o error del proveedor.',
    'body_preview' => substr($body, 0, 200),
  ]);
  exit;
}

// 💥 Código HTTP distinto de 2xx
if ($code < 200 || $code >= 300) {
  http_response_code($code);
  echo json_encode([
    'success' => false,
    'message' => "Error HTTP del servidor remoto.",
    'details' => [
      'codigo_http' => $code,
      'respuesta'   => substr($body, 0, 200),
    ]
  ]);
  exit;
}

// --- DECODIFICAR JSON ---
$j = json_decode($body, true);
if (json_last_error() !== JSON_ERROR_NONE) {
  http_response_code(500);
  echo json_encode([
    'success' => false,
    'message' => "El cuerpo recibido no es JSON válido.",
    'error_php_msg' => json_last_error_msg(),
    'body_raw' => substr($body, 0, 200),
  ]);
  exit;
}

// --- VALIDAR RESPUESTA ---
if (!is_array($j)) {
  http_response_code(502);
  echo json_encode(['success'=>false,'message'=>'Respuesta inválida del proveedor.']);
  exit;
}

if (!($j['success'] ?? false) || empty($j['datos']) || !is_array($j['datos'])) {
  http_response_code(404);
  echo json_encode(['success'=>false,'message'=>$j['message'] ?? 'RUC no encontrado.']);
  exit;
}

// --- PROCESAR DATOS ---
$d        = $j['datos'];
$razon    = trim((string)($d['razon_social'] ?? $d['razonSocial'] ?? $d['name'] ?? ''));
$direccion = '';
if (isset($d['direccion']))          $direccion = build_address($d['direccion']);
if (!$direccion && isset($d['domicilio_fiscal'])) $direccion = build_address($d['domicilio_fiscal']);
if (!$direccion && isset($d['domicilio']))        $direccion = build_address($d['domicilio']);

// --- RESPUESTA FINAL ---
echo json_encode([
  'success'       => true,
  'ruc'           => $ruc,
  'razon_social'  => $razon,
  'estado'        => $d['estado']       ?? null,
  'condicion'     => $d['condicion']    ?? null,
  'direccion'     => $direccion ?: null,
  'ubigeo'        => $d['ubigeo']       ?? null,
]);
exit;
