<?php
declare(strict_types=1);

// --- CONFIGURACIÓN DE ERRORES Y TIEMPO ---
set_time_limit(60); 
ini_set('display_errors', '1');
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

$ruc_raw = (string)($_GET['ruc'] ?? '');
$ruc      = preg_replace('/\D+/', '', $ruc_raw);
if (strlen($ruc) !== 11) {
  http_response_code(400);
  echo json_encode(['success'=>false,'message'=>'RUC inválido (11 dígitos).']); exit;
}

/* ===== MODO DEMO SI NO HAY CURL (para probar UI sin red/token) ===== */
if (!function_exists('curl_init')) {
  http_response_code(500); // Dar un error 500 si falta CURL
  echo json_encode(['success'=>false, 'message'=>'La extensión cURL de PHP no está instalada.']); 
  exit;
}

// TOKEN VÁLIDO
$TOKEN = getenv('MIAPI_CLOUD_TOKEN')
  ?: 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJ1c2VyX2lkIjo0MDEsImV4cCI6MTc2MTU0MTQxMn0.5M179k5ws4tayquMwg_yfVdbybQCDkKaTPUu6Dibt_E';

$url = "https://miapi.cloud/v1/ruc/{$ruc}";
$ch  = curl_init($url);
curl_setopt_array($ch, [
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_TIMEOUT          => 20, // Aumentado a 20 segundos
  CURLOPT_FOLLOWLOCATION => true,
  CURLOPT_SSL_VERIFYPEER => true,
  CURLOPT_HTTPHEADER       => [
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
curl_close($ch);

// Manejo de fallos de red o errores de la API (incluye el 401 del token viejo, si fuera el caso)
if ($errn || $code < 200 || $code >= 300 || !$body) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => "ERROR de cURL/API: Falló la conexión o la API rechazó la solicitud.",
        'details' => "cURL Error: {$err} (Cod. HTTP: {$code}, ErrNo: {$errn})",
        'body_received' => substr($body, 0, 100)
    ]);
    exit;
}


$j = json_decode($body, true);

// Manejo de fallos de JSON (si la API devolvió algo que no es JSON)
if (json_last_error() !== JSON_ERROR_NONE) {
  http_response_code(500);
  echo json_encode([
    'success' => false,
    'message' => "ERROR FATAL: El cuerpo no es JSON válido.",
    'error_php_msg' => json_last_error_msg(), 
    'body_raw' => substr($body, 0, 100)
  ]);
  exit;
}


if (!is_array($j)) {
  http_response_code(502);
  echo json_encode(['success'=>false,'message'=>'Respuesta inválida del proveedor.']); exit;
}


if (!($j['success'] ?? false) || empty($j['datos']) || !is_array($j['datos'])) {
  http_response_code(404);
  echo json_encode(['success'=>false,'message'=>$j['message'] ?? 'RUC no encontrado.']); exit;
}

$d      = $j['datos'];
$razon = trim((string)($d['razon_social'] ?? $d['razonSocial'] ?? $d['name'] ?? ''));

// Dirección: intenta 'direccion' (string), luego 'domicilio_fiscal' (objeto) o 'domicilio'
$direccion = '';
if (isset($d['direccion']))         $direccion = build_address($d['direccion']);
if (!$direccion && isset($d['domicilio_fiscal'])) $direccion = build_address($d['domicilio_fiscal']);
if (!$direccion && isset($d['domicilio']))         $direccion = build_address($d['domicilio']);

echo json_encode([
  'success'         => true,
  'ruc'             => $ruc,
  'razon_social'    => $razon,
  'estado'          => $d['estado']       ?? null,
  'condicion'       => $d['condicion']    ?? null,
  'direccion'       => $direccion ?: null,
  'ubigeo'          => $d['ubigeo']       ?? null,
]);
