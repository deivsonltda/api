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
$reason  = trim((string)($in['reason'] ?? ''));

if ($saqueId === '') {
  json_out(['ok' => false, 'error' => 'missing_saque_id'], 400);
}

$rpc = $sb->rest('POST', 'rpc/admin_reject_saque', [], [
  'p_saque_id'  => $saqueId,
  'p_admin_uid' => (string)$admin['id'],
  'p_reason'    => $reason,
]);

if (!$rpc['ok'] || !is_array($rpc['data']) || count($rpc['data']) < 1) {
  json_out(['ok' => false, 'error' => 'reject_failed', 'details' => $rpc['raw']], 500);
}

$row = $rpc['data'][0];

$ok = (bool)($row['ok'] ?? false);
$status = (string)($row['status'] ?? '');

if (!$ok) {
  // se a RPC não achou, normalmente você vai retornar status "failed" (ou algo)
  // então aqui a gente tenta diferenciar not_found via mensagem crua também (se quiser)
  if ($status === 'failed') {
    json_out(['ok' => false, 'error' => 'not_found'], 404);
  }
  // conflito: status atual não permite rejeitar
  json_out(['ok' => false, 'error' => 'not_rejected', 'status' => $status], 409);
}

// sucesso => canceled
json_out([
  'ok' => true,
  'saque_id' => (string)($row['saque_id'] ?? $saqueId),
  'status' => $status ?: 'canceled',
]);