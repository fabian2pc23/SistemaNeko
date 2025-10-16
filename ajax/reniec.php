<?php
// ajax/reniec.php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

$dni_raw = (string)($_GET['dni'] ?? '');
$dni     = preg_replace('/\D+/', '', $dni_raw);
if (strlen($dni) !== 8) {
  http_response_code(400);
  echo json_encode(['success'=>false,'message'=>'DNI inválido (8 dígitos).']); exit;
}

/** Arma una dirección legible a partir de diferentes formatos posibles */
function build_address($dom): string {
  if (is_string($dom)) {
    return trim($dom);
  }
  if (!is_array($dom)) return '';

  // claves comunes
  $candidatas = [
    'direccion', 'domicilio', 'domicilio_fiscal', 'calle', 'via'
  ];
  foreach ($candidatas as $k) {
    if (!empty($dom[$k]) && is_string($dom[$k])) {
      return trim((string)$dom[$k]);
    }
  }

  // composición por partes
  $parts = [];
  foreach (['via','tipo_via','nombre_via','calle','avenida','jr','mz','lote','numero','nro','interior','dpto','piso','km','referencia'] as $k) {
    if (!empty($dom[$k])) $parts[] = trim((string)$dom[$k]);
  }
  $dir = trim(implode(' ', $parts));

  // agrega ubigeo si viene desglosado
  $geo = [];
  foreach (['distrito','provincia','departamento'] as $k) {
    if (!empty($dom[$k])) $geo[] = trim((string)$dom[$k]);
  }
  if ($geo) {
    $dir = $dir ? ($dir . ', ' . implode(' - ', $geo)) : implode(' - ', $geo);
  }
  return $dir;
}

/* ===== MODO DEMO SI NO HAY CURL (para probar UI) ===== */
if (!function_exists('curl_init')) {
  echo json_encode([
    'success'   => true,
    'dni'       => $dni,
    'nombres'   => 'JUAN CARLOS',
    'apellidos' => 'PEREZ LOPEZ',
    'direccion' => 'Jr. Las Flores 123, Lima',
  ]);
  exit;
}

$TOKEN = getenv('MIAPI_CLOUD_TOKEN')
  ?: 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJ1c2VyX2lkIjozODAsImV4cCI6MTc2MDkzMjQ5Nn0.41qo3RAG_3TvRdU8Dtqf9rzL2QbSAGF8PU_8ueKfIDc';

$url = "https://miapi.cloud/v1/dni/{$dni}";
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

/* DEMO si falla la red/proveedor (para que puedas probar) */
if ($errn || $code < 200 || $code >= 300 || !$body) {
  echo json_encode([
    'success'   => true,
    'dni'       => $dni,
    'nombres'   => 'JUAN CARLOS',
    'apellidos' => 'PEREZ LOPEZ',
    'direccion' => 'Jr. Las Flores 123, Lima',
  ]);
  exit;
}

$j = json_decode($body, true);
if (!is_array($j) || !($j['success'] ?? false) || empty($j['datos']) || !is_array($j['datos'])) {
  http_response_code(404);
  echo json_encode(['success'=>false,'message'=>$j['message'] ?? 'DNI no encontrado.']); exit;
}

$d         = $j['datos'];
$nombres   = trim((string)($d['nombres'] ?? $d['name'] ?? ''));
$apepat    = trim((string)($d['ape_paterno'] ?? $d['apellido_paterno'] ?? ''));
$apemat    = trim((string)($d['ape_materno'] ?? $d['apellido_materno'] ?? ''));
$apellidos = trim(implode(' ', array_filter([$apepat, $apemat])));

/* domicilio puede venir:
   - como string en $d['domicilio'] o $d['direccion']
   - como objeto en $d['domicilio'] con partes (via, numero, etc.)
*/
$direccion = '';
if (isset($d['domicilio'])) $direccion = build_address($d['domicilio']);
if (!$direccion && isset($d['direccion'])) $direccion = build_address($d['direccion']);

echo json_encode([
  'success'   => true,
  'dni'       => $dni,
  'nombres'   => $nombres,
  'apellidos' => $apellidos,
  'direccion' => $direccion ?: null,
]);
