<?php
declare(strict_types=1);

require_once __DIR__ . '/../_core/cors.php';
require_once __DIR__ . '/../_core/http.php';
require_once __DIR__ . '/../_core/auth.php';
require_once __DIR__ . '/../_core/supabase.php';

cors_handle();

$in = json_input();

$amount      = (float)($in['amount'] ?? 0);
$pixKey      = trim((string)($in['pix_key'] ?? ''));
$pixKeyType  = trim((string)($in['pix_key_type'] ?? ''));
$phoneDigits = isset($in['phone_digits']) ? (string)$in['phone_digits'] : null;
$idemKey     = trim((string)($in['idem_key'] ?? ''));

// ✅ validações mínimas (UX fica no front; aqui é sanity-check)
if ($amount <= 0) {
  json_out(['ok' => false, 'error' => 'invalid_amount'], 400);
}
if ($pixKey === '' || mb_strlen($pixKey) < 5) {
  json_out(['ok' => false, 'error' => 'invalid_pix_key'], 400);
}
if ($pixKeyType === '') {
  // backend real pode ignorar o tipo e detectar sozinho,
  // mas mantendo seu contrato atual:
  json_out(['ok' => false, 'error' => 'invalid_pix_key'], 400);
}

// ✅ normaliza phone_digits (se vier)
if ($phoneDigits !== null) {
  $phoneDigits = preg_replace('/\D+/', '', $phoneDigits);
  if ($phoneDigits === '') $phoneDigits = null;
}

// ✅ idempotência: se o front não mandar, gera aqui
if ($idemKey === '') {
  // formato simples, suficiente pra idempotência do seu RPC
  $idemKey = 'wd_' . bin2hex(random_bytes(12));
}

$sb = new Supabase();
$user = auth_require_user($sb);

$uid = (string)($user['id'] ?? '');
if ($uid === '') {
  json_out(['ok' => false, 'error' => 'unauthorized'], 401);
}

// chama RPC atômica (ela deve: validar min/max, validar saldo,
// debitar/reservar, inserir em saques e retornar id/status/balance)
$rpc = $sb->rest('POST', 'rpc/create_withdraw_request', [], [
  'p_uid'          => $uid,
  'p_amount'       => $amount,
  'p_pix_key'      => $pixKey,
  'p_pix_key_type' => $pixKeyType,
  'p_phone_digits' => $phoneDigits,
  'p_idem_key'     => $idemKey,
]);

if (!$rpc['ok']) {
  $raw = (string)($rpc['raw'] ?? '');

  // normaliza mensagens da RPC (raise exception 'code')
  // (mantive seu jeito, só com pequenas proteções)
  $msg = $raw;

  if (stripos($msg, 'min_amount') !== false) json_out(['ok' => false, 'error' => 'min_amount'], 400);
  if (stripos($msg, 'max_amount') !== false) json_out(['ok' => false, 'error' => 'max_amount'], 400);
  if (stripos($msg, 'insufficient_balance') !== false) json_out(['ok' => false, 'error' => 'insufficient_balance'], 400);
  if (stripos($msg, 'invalid_pix_key') !== false) json_out(['ok' => false, 'error' => 'invalid_pix_key'], 400);
  if (stripos($msg, 'invalid_amount') !== false) json_out(['ok' => false, 'error' => 'invalid_amount'], 400);
  if (stripos($msg, 'invalid_idem_key') !== false) json_out(['ok' => false, 'error' => 'invalid_idem_key'], 400);

  json_out(['ok' => false, 'error' => 'withdraw_failed', 'details' => $raw], 500);
}

if (!is_array($rpc['data']) || count($rpc['data']) < 1) {
  json_out(['ok' => false, 'error' => 'withdraw_failed'], 500);
}

$row = $rpc['data'][0];

json_out([
  'ok'          => true,
  'id'          => (string)($row['id'] ?? ''),
  'status'      => (string)($row['status'] ?? 'requested'),
  'balance_brl' => (float)($row['balance_brl'] ?? 0),
]);