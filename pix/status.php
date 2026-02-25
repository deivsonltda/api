<?php
declare(strict_types=1);

require_once __DIR__ . '/../_core/cors.php';
require_once __DIR__ . '/../_core/http.php';
require_once __DIR__ . '/../_core/supabase.php';
require_once __DIR__ . '/../_core/auth.php';

cors_handle();

$sb = new Supabase();
$user = auth_require_user($sb);

$txid = trim((string)($_GET['txid'] ?? ''));
if ($txid === '') json_out(['ok' => false, 'error' => 'missing_txid'], 400);

$userId = (string)($user['id'] ?? '');
if ($userId === '') json_out(['ok' => false, 'error' => 'unauthorized'], 401);

// procura por id OU external_reference, filtrando pelo cliente_id
$q = $sb->rest('GET', 'transacoes_pix', [
  'select' => 'id,cliente_id,amount_brl,credits_qty,status,mp_payment_id,created_at,updated_at,external_reference',
  'cliente_id' => 'eq.' . $userId,
  'or' => '(id.eq.' . $txid . ',external_reference.eq.' . $txid . ')',
  'limit' => '1'
]);

if (!$q['ok'] || !is_array($q['data']) || count($q['data']) < 1) {
  json_out(['ok' => false, 'error' => 'not_found'], 404);
}

$row = $q['data'][0];

json_out([
  'ok' => true,
  'status' => (string)($row['status'] ?? ''),
  'credits_qty' => (int)($row['credits_qty'] ?? 0),
  'amount_brl' => (float)($row['amount_brl'] ?? 0),
  'id' => (string)($row['id'] ?? ''),
]);