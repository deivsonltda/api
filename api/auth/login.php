<?php
declare(strict_types=1);

require_once __DIR__ . '/../_core/cors.php';
require_once __DIR__ . '/../_core/http.php';
require_once __DIR__ . '/../_core/supabase.php';
require_once __DIR__ . '/../_core/jwt.php';
require_once __DIR__ . '/../_core/auth.php';

cors_handle();

$in = json_input();
$identifier = trim((string)($in['identifier'] ?? ''));
$senha      = (string)($in['password'] ?? '');

if ($identifier === '' || $senha === '') {
  json_out(['ok' => false, 'error' => 'missing_fields'], 400);
}

$sb = new Supabase();

$identifierLower  = strtolower($identifier);
$identifierDigits = preg_replace('/\D+/', '', $identifier);

$select = 'id,nome,email,telefone,senha_hash,credits,balance_brl,accepted_terms,accepted_privacy,accepted_lgpd_cookies,accepted_truth,accepted_at,created_at';

// ✅ Estratégia robusta: tenta email -> telefone (raw) -> telefone (digits)
// (evita dependência de "or" e problemas com @/encoding)
$res = $sb->rest('GET', 'clientes', [
  'select' => $select,
  'email'  => 'eq.' . $identifierLower,
  'limit'  => '1'
]);

if (!$res['ok'] || !is_array($res['data']) || count($res['data']) < 1) {
  $res = $sb->rest('GET', 'clientes', [
    'select'   => $select,
    'telefone' => 'eq.' . $identifier,
    'limit'    => '1'
  ]);
}

if ((!$res['ok'] || !is_array($res['data']) || count($res['data']) < 1) && $identifierDigits !== '') {
  $res = $sb->rest('GET', 'clientes', [
    'select'   => $select,
    'telefone' => 'eq.' . $identifierDigits,
    'limit'    => '1'
  ]);
}

if (!$res['ok'] || !is_array($res['data']) || count($res['data']) < 1) {
  json_out(['ok' => false, 'error' => 'invalid_credentials'], 401);
}

$row  = $res['data'][0];
$hash = (string)($row['senha_hash'] ?? '');

if ($hash === '' || !password_verify($senha, $hash)) {
  json_out(['ok' => false, 'error' => 'invalid_credentials'], 401);
}

$token = jwt_sign(['uid' => $row['id']], 60 * 60 * 24 * 365 * 10);

// ✅ mantém cookie HttpOnly (não quebra o fluxo atual que usa token no JSON)
$secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
setcookie('auth_token', $token, [
  'expires'  => time() + (60 * 60 * 24 * 365 * 10),
  'path'     => '/',
  'secure'   => $secure,
  'httponly' => true,
  'samesite' => 'Lax',
]);

json_out([
  'ok'    => true,
  'token' => $token, // mantém exatamente como já funciona hoje
  'user'  => auth_public_user($row),
]);