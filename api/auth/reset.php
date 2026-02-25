<?php
declare(strict_types=1);

require_once __DIR__ . '/../_core/cors.php';
require_once __DIR__ . '/../_core/http.php';
require_once __DIR__ . '/../_core/supabase.php';

cors_handle();

$in = json_input();
$token = trim((string)($in['token'] ?? ''));
$newPass = (string)($in['password'] ?? '');

if ($token === '' || $newPass === '') json_out(['ok' => false, 'error' => 'missing_fields'], 400);
if (strlen($newPass) < 6) json_out(['ok' => false, 'error' => 'weak_password'], 400);

$sb = new Supabase();

$tokenHash = hash('sha256', $token);

// procura token válido
$res = $sb->rest('GET', 'password_resets', [
  'token_hash' => 'eq.' . $tokenHash,
  'used' => 'eq.false',
  'select' => 'id,cliente_id,expires_at,used',
  'limit' => '1'
]);

if (!$res['ok'] || !is_array($res['data']) || count($res['data']) < 1) {
  json_out(['ok' => false, 'error' => 'invalid_token'], 400);
}

$reset = $res['data'][0];
$expiresAt = strtotime((string)$reset['expires_at']);
if (!$expiresAt || time() > $expiresAt) {
  json_out(['ok' => false, 'error' => 'expired_token'], 400);
}

$hash = password_hash($newPass, PASSWORD_DEFAULT);

// atualiza senha
$up = $sb->rest('PATCH', 'clientes', [
  'id' => 'eq.' . $reset['cliente_id']
], [
  'senha_hash' => $hash,
], ['Prefer: return=representation']);

if (!$up['ok']) json_out(['ok' => false, 'error' => 'update_failed'], 500);

// marca token como usado
$sb->rest('PATCH', 'password_resets', [
  'id' => 'eq.' . $reset['id']
], [
  'used' => true
]);

json_out(['ok' => true]);