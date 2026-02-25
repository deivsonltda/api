<?php
declare(strict_types=1);

require_once __DIR__ . '/http.php';
require_once __DIR__ . '/jwt.php';
require_once __DIR__ . '/supabase.php';

function auth_require_user(Supabase $sb): array {
  // 1) mantém seu fluxo atual (Bearer)
  $token = bearer_token();

  // 2) ✅ ADIÇÃO: se não veio Bearer, tenta cookie HttpOnly
  if (!$token && !empty($_COOKIE['auth_token'])) {
    $token = (string)$_COOKIE['auth_token'];
  }

  if (!$token) json_out(['ok' => false, 'error' => 'missing_token'], 401);

  $payload = jwt_verify($token);
  if (!$payload) json_out(['ok' => false, 'error' => 'invalid_token'], 401);

  $uid = $payload['uid'] ?? null;
  if (!$uid) json_out(['ok' => false, 'error' => 'invalid_token_payload'], 401);

  $res = $sb->rest('GET', 'clientes', [
    'id' => 'eq.' . $uid,
    'select' => 'id,nome,email,telefone,credits,balance_brl,accepted_terms,accepted_privacy,accepted_lgpd_cookies,accepted_truth,accepted_at,created_at'
  ]);

  if (!$res['ok'] || !is_array($res['data']) || count($res['data']) < 1) {
    json_out(['ok' => false, 'error' => 'user_not_found'], 401);
  }

  return $res['data'][0];
}

function auth_public_user(array $row): array {
  return [
    'id' => $row['id'],
    'name' => $row['nome'],
    'email' => $row['email'],
    'phone' => $row['telefone'],
    'credits' => (int)($row['credits'] ?? 0),
    'balanceBRL' => (float)($row['balance_brl'] ?? 0),
    'contracts' => [
      'terms' => (bool)($row['accepted_terms'] ?? false),
      'privacy' => (bool)($row['accepted_privacy'] ?? false),
      'lgpdCookies' => (bool)($row['accepted_lgpd_cookies'] ?? false),
      'truth' => (bool)($row['accepted_truth'] ?? false),
      'acceptedAt' => $row['accepted_at'] ?? null,
    ],
    'createdAt' => $row['created_at'] ?? null,
  ];
}