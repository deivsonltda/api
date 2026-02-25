<?php
declare(strict_types=1);

require_once __DIR__ . '/../_core/cors.php';
require_once __DIR__ . '/../_core/http.php';
require_once __DIR__ . '/../_core/supabase.php';

cors_handle();

$in = json_input();
$email = strtolower(trim((string)($in['email'] ?? '')));
if ($email === '') json_out(['ok' => false, 'error' => 'missing_email'], 400);

$sb = new Supabase();

// não vaza se existe ou não (sempre ok)
$res = $sb->rest('GET', 'clientes', [
  'email' => 'eq.' . $email,
  'select' => 'id,email',
  'limit' => '1'
]);

if (!$res['ok'] || !is_array($res['data']) || count($res['data']) < 1) {
  json_out(['ok' => true, 'message' => 'Se existir uma conta, enviaremos instruções por e-mail.']);
}

$row = $res['data'][0];
$token = bin2hex(random_bytes(24)); // 48 chars
$tokenHash = hash('sha256', $token);
$expires = gmdate('c', time() + 5 * 60); // 5 min

$ins = $sb->rest('POST', 'password_resets', [], [
  'cliente_id' => $row['id'],
  'token_hash' => $tokenHash,
  'expires_at' => $expires,
  'used' => false,
], ['Prefer: return=representation']);

$link = "http://localhost:8080/reset?token=" . urlencode($token);

if (APP_ENV === 'dev') {
  // DEV: devolve o link pra você testar sem SMTP
  json_out([
    'ok' => true,
    'message' => 'DEV: link gerado.',
    'resetLink' => $link,
    'expiresAt' => $expires,
  ]);
}

json_out(['ok' => true, 'message' => 'Se existir uma conta, enviaremos instruções por e-mail.']);