<?php
declare(strict_types=1);

require_once __DIR__ . '/../_core/cors.php';
require_once __DIR__ . '/../_core/http.php';
require_once __DIR__ . '/../_core/supabase.php';
require_once __DIR__ . '/../_core/jwt.php';
require_once __DIR__ . '/../_core/auth.php';

cors_handle();

$in = json_input();

$nome     = trim((string)($in['name'] ?? ''));
$email    = strtolower(trim((string)($in['email'] ?? '')));
$telefone = trim((string)($in['phone'] ?? ''));
$senha    = (string)($in['password'] ?? '');
$accepted = (bool)($in['accepted'] ?? false);

if ($nome === '' || $email === '' || $senha === '') {
  json_out(['ok' => false, 'error' => 'missing_fields'], 400);
}
if (strlen($senha) < 6) {
  json_out(['ok' => false, 'error' => 'weak_password'], 400);
}
if (!$accepted) {
  json_out(['ok' => false, 'error' => 'must_accept_terms'], 400);
}

$sb = new Supabase();

// checa email duplicado (mantém o mesmo erro/código pra não quebrar nada)
$check = $sb->rest('GET', 'clientes', [
  'email'  => 'eq.' . $email,
  'select' => 'id',
  'limit'  => '1'
]);

if ($check['ok'] && is_array($check['data']) && count($check['data']) > 0) {
  json_out(['ok' => false, 'error' => 'email_in_use'], 409);
}

// ✅ NOVO: checa telefone duplicado (somente se vier telefone)
if ($telefone !== '') {
  $checkPhone = $sb->rest('GET', 'clientes', [
    'telefone' => 'eq.' . $telefone,
    'select'   => 'id',
    'limit'    => '1'
  ]);

  if ($checkPhone['ok'] && is_array($checkPhone['data']) && count($checkPhone['data']) > 0) {
    json_out(['ok' => false, 'error' => 'phone_in_use'], 409);
  }
}

$hash = password_hash($senha, PASSWORD_DEFAULT);

/**
 * IMPORTANTE:
 * - NÃO enviar "id" (nem null)
 * - remover campos null do payload (ex: telefone vazio)
 */
$payload = [
  'nome' => $nome,
  'email' => $email,
  'telefone' => ($telefone !== '' ? $telefone : null),
  'senha_hash' => $hash,
  'credits' => 0,
  'balance_brl' => 0,
  'accepted_terms' => true,
  'accepted_privacy' => true,
  'accepted_lgpd_cookies' => true,
  'accepted_truth' => true,
  'accepted_at' => date('c'),
];

// remove chaves com valor null (pra não “atrapalhar” defaults)
$payload = array_filter($payload, fn($v) => $v !== null);

$insert = $sb->rest(
  'POST',
  'clientes',
  [],
  $payload,
  [
    // missing=default aplica defaults quando coluna não é enviada
    'Prefer: return=representation, missing=default'
  ]
);

if (!$insert['ok'] || !is_array($insert['data']) || count($insert['data']) < 1) {
  json_out(['ok' => false, 'error' => 'register_failed', 'details' => $insert['raw']], 500);
}

$row = $insert['data'][0];

// se ainda vier sem id, é prova de que o schema do banco tá errado
if (empty($row['id'])) {
  json_out([
    'ok' => false,
    'error' => 'db_schema_error',
    'details' => 'Tabela clientes.id sem default UUID (gen_random_uuid). Ajuste o SQL do schema.'
  ], 500);
}

$token = jwt_sign(['uid' => $row['id']], 60 * 60 * 24 * 30); // 30 dias

// ✅ Opcional e seguro: seta cookie HttpOnly sem quebrar o front atual
$secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
setcookie('auth_token', $token, [
  'expires'  => time() + (60 * 60 * 24 * 30),
  'path'     => '/',
  'secure'   => $secure,
  'httponly' => true,
  'samesite' => 'Lax',
]);

json_out([
  'ok' => true,
  'token' => $token,
  'user' => auth_public_user($row),
]);