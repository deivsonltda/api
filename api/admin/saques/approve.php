<?php
declare(strict_types=1);

require_once __DIR__ . '/../../_core/cors.php';
require_once __DIR__ . '/../../_core/http.php';
require_once __DIR__ . '/../../_core/supabase.php';
require_once __DIR__ . '/../../_core/admin_auth.php';

cors_handle();

$sb = new Supabase();
$admin = auth_require_admin($sb);

$in = json_input();
$saqueId = trim((string)($in['saque_id'] ?? ''));

if ($saqueId === '') {
  json_out(['ok' => false, 'error' => 'missing_saque_id'], 400);
}

// chama RPC atômica (service role)
$rpc = $sb->rest('POST', 'rpc/admin_approve_saque', [], [
  'p_saque_id' => $saqueId,
  'p_admin_uid' => (string)$admin['id'],
]);

if (!$rpc['ok'] || !is_array($rpc['data']) || count($rpc['data']) < 1) {
  json_out(['ok' => false, 'error' => 'approve_failed', 'details' => $rpc['raw']], 500);
}

$row = $rpc['data'][0];

if (!($row['ok'] ?? false)) {
  $status = (string)($row['status'] ?? 'failed');
  if ($status === 'not_found') json_out(['ok' => false, 'error' => 'not_found'], 404);
  if ($status === 'pending') json_out(['ok' => false, 'error' => 'still_pending'], 409);
  if ($status === 'rejected') json_out(['ok' => false, 'error' => 'rejected'], 409);

  json_out(['ok' => false, 'error' => 'not_approved', 'status' => $status, 'row' => $row], 409);
}

json_out([
  'ok' => true,
  'saque_id' => (string)($row['saque_id'] ?? $saqueId),
  'cliente_id' => (string)($row['cliente_id'] ?? ''),
  'amount_brl' => (float)($row['amount_brl'] ?? 0),
  'new_balance_brl' => (float)($row['new_balance_brl'] ?? 0),
  'status' => (string)($row['status'] ?? 'approved'),
]);