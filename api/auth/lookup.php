<?php
declare(strict_types=1);

require_once __DIR__ . '/../_core/cors.php';
require_once __DIR__ . '/../_core/http.php';
require_once __DIR__ . '/../_core/supabase.php';

cors_handle();

$in = json_input();
$identifier = trim((string)($in['identifier'] ?? ''));
$phoneDigits = trim((string)($in['phoneDigits'] ?? ''));

if ($identifier === '') {
  json_out(['ok' => false, 'error' => 'missing_identifier'], 400);
}

$sb = new Supabase();

$isEmail = (bool)preg_match('/^[^\s@]+@[^\s@]+\.[^\s@]+$/', $identifier);
$exists = false;
$kind = $isEmail ? 'email' : 'phone';

if ($isEmail) {
  $email = strtolower($identifier);
  $q = $sb->rest('GET', 'clientes', [
    'select' => 'id',
    'email' => 'eq.' . $email,
    'limit' => '1'
  ]);
  $exists = ($q['ok'] && is_array($q['data']) && count($q['data']) > 0);
} else {
  // tenta pelo telefone exatamente como está salvo
  $q1 = $sb->rest('GET', 'clientes', [
    'select' => 'id',
    'telefone' => 'eq.' . $identifier,
    'limit' => '1'
  ]);
  $exists = ($q1['ok'] && is_array($q1['data']) && count($q1['data']) > 0);

  // fallback: se você salvar só dígitos em algum lugar
  if (!$exists && $phoneDigits !== '') {
    $q2 = $sb->rest('GET', 'clientes', [
      'select' => 'id',
      'telefone' => 'eq.' . $phoneDigits,
      'limit' => '1'
    ]);
    $exists = ($q2['ok'] && is_array($q2['data']) && count($q2['data']) > 0);
  }
}

json_out([
  'ok' => true,
  'exists' => $exists,
  'kind' => $kind
]);