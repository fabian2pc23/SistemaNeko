<?php
// ajax/sunat.php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

/** Construye una dirección legible a partir de distintos formatos */
function build_address($dom): string {
  if (is_string($dom)) {
    return trim($dom);
  }
  if (!is_array($dom)) return '';

  // Claves directas más comunes
  foreach (['direccion','domicilio_fiscal','domicilio','calle','via'] as $k) {
    if (!empty($dom[$k]) && is_string($dom[$k])) {
      return trim((string)$dom[$k]);
    }
  }

  // Composición por partes si llega un objeto desglosado
  $parts = [];
  foreach ([
    'via','tipo_via','nombre_via','calle','avenida','jr',
    'mz','lote','numero','nro','km','interior','dpto','piso','referencia'
  ] as $k) {
    if (!empty($dom[$k])) $parts[] = trim((string)$dom[$k]);
  }
  $dir = trim(implode(' ', $parts));

  // Ubigeo desglosado
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
$ruc     = preg_replace('/\D+/', '', $ruc_raw);
if (strlen($ruc) !== 11) {
  http_response_code(400);
  echo json_encode(['success'=>false,'message'=>'RUC inválido (11 dígitos).']); exit;
}

/* ===== MODO DEMO SI NO HAY CURL (para probar UI sin red/token) ===== */
if (!function_exists('curl_init')) {
  echo json_encode([
    'success'        => true,
    'ruc'            => $ruc,
    'razon_social'   => 'EMPRESA DEMO S.A.C.',
    'estado'         => 'ACTIVO',
    'condicion'      => 'HABIDO',
    'direccion'      => 'Av. Siempre Viva 123, Lima',
    'ubigeo'         => '150101',
  ]);
  exit;
}

$TOKEN = getenv('MIAPI_CLOUD_TOKEN')
  ?: 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJ1c2VyX2lkIjo0MDEsImV4cCI6MTc2MTU0MTQxMn0.5M179k5ws4tayquMwg_yfVdbybQCDkKaTPUu6Dibt_E';

$url = "https://miapi.cloud/v1/ruc/{$ruc}";
$ch  = curl_init($url);
curl_setopt_array($ch, [
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_TIMEOUT        => 15,
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
curl_close($ch);


$j = json_decode($body, true);

// *** CÓDIGO TEMPORAL DE DEPURACIÓN AGREGADO ***
if (json_last_error() !== JSON_ERROR_NONE) {
  http_response_code(500);
  echo json_encode([
    'success' => false, 
    'message' => "ERROR FATAL: El cuerpo no es JSON válido o el script falló.",
    'error_php_msg' => json_last_error_msg(), // Muestra el error de JSON
    'body_raw' => substr($body, 0, 100) // Muestra el inicio del cuerpo recibido
  ]);
  exit;
}


if (!is_array($j)) {
  http_response_code(502);
  echo json_encode(['success'=>false,'message'=>'Respuesta inválida del proveedor.']); exit;
}

// ... el resto del código ...

/* Formato típico miapi.cloud:
{
  "success": true,
  "datos": {
    "ruc": "20xxxxxxxx",
    "razon_social": "EMPRESA S.A.C.",
    "estado": "ACTIVO",
    "condicion": "HABIDO",
    "direccion": "...",           // a veces string
    "domicilio_fiscal": {...},    // a veces objeto
    "ubigeo": "150101"
  }
}
*/
if (!($j['success'] ?? false) || empty($j['datos']) || !is_array($j['datos'])) {
  http_response_code(404);
  echo json_encode(['success'=>false,'message'=>$j['message'] ?? 'RUC no encontrado.']); exit;
}

$d     = $j['datos'];
$razon = trim((string)($d['razon_social'] ?? $d['razonSocial'] ?? $d['name'] ?? ''));

// Dirección: intenta 'direccion' (string), luego 'domicilio_fiscal' (objeto) o 'domicilio'
$direccion = '';
if (isset($d['direccion']))        $direccion = build_address($d['direccion']);
if (!$direccion && isset($d['domicilio_fiscal'])) $direccion = build_address($d['domicilio_fiscal']);
if (!$direccion && isset($d['domicilio']))        $direccion = build_address($d['domicilio']);

echo json_encode([
  'success'        => true,
  'ruc'            => $ruc,
  'razon_social'   => $razon,
  'estado'         => $d['estado']     ?? null,
  'condicion'      => $d['condicion']  ?? null,
  'direccion'      => $direccion ?: null,
  'ubigeo'         => $d['ubigeo']     ?? null,
]);
