<?php
declare(strict_types=1);

require_once __DIR__ . '/../_core/cors.php';
require_once __DIR__ . '/../_core/http.php';
require_once __DIR__ . '/../_core/supabase.php';
require_once __DIR__ . '/../_core/mercadopago.php';

cors_handle();

// MercadoPago pode mandar querystring ou JSON.
$paymentId = null;

if (isset($_GET['data_id'])) $paymentId = (string)$_GET['data_id'];
if (isset($_GET['id'])) $paymentId = (string)$_GET['id'];

$in = json_input();
if (!$paymentId) {
  $paymentId = $in['data']['id'] ?? $in['data_id'] ?? $in['id'] ?? null;
}

$paymentId = $paymentId ? trim((string)$paymentId) : '';

if ($paymentId === '') {
  // responde 200 pra evitar retry infinito
  json_out(['ok' => true, 'ignored' => true, 'reason' => 'missing_payment_id']);
}

$mp = new MercadoPago();
$sb = new Supabase();

$pay = $mp->getPayment($paymentId);
if (!$pay['ok'] || !is_array($pay['data'])) {
  json_out(['ok' => true, 'ignored' => true, 'reason' => 'mp_get_failed']);
}

$p = $pay['data'];
$status = trim((string)($p['status'] ?? ''));
$externalRef = trim((string)($p['external_reference'] ?? ''));

// --------------------------
// helpers (tabela NOVA)
// --------------------------
function find_tx_new(Supabase $sb, string $paymentId, string $externalRef): ?array {
  $orParts = [];
  if ($externalRef !== '') $orParts[] = 'external_reference.eq.' . $externalRef;
  $orParts[] = 'mp_payment_id.eq.' . $paymentId;

  $q = $sb->rest('GET', 'transacoes_pix', [
    'select' => 'id,cliente_id,credits_qty,status,mp_payment_id,external_reference',
    'or' => '(' . implode(',', $orParts) . ')',
    'limit' => '1'
  ]);

  if ($q['ok'] && is_array($q['data']) && count($q['data']) > 0) return $q['data'][0];
  return null;
}

function patch_tx_new(Supabase $sb, string $id, string $newStatus, string $paymentId, array $rawMp): void {
  $sb->rest('PATCH', 'transacoes_pix', ['id' => 'eq.' . $id], [
    'status' => $newStatus,
    'mp_payment_id' => $paymentId,
    // opcional: atualiza raw_mp com o payload mais recente (sem perder o antigo)
    'raw_mp' => $rawMp,
    'updated_at' => gmdate('c'),
  ]);
}

function add_credits(Supabase $sb, string $clienteId, int $inc): void {
  if ($inc <= 0) return;

  // lê e soma (se quiser 100% atômico, faça uma RPC no banco)
  $u = $sb->rest('GET', 'clientes', [
    'select' => 'id,credits',
    'id' => 'eq.' . $clienteId,
    'limit' => '1'
  ]);

  if (!$u['ok'] || !is_array($u['data']) || count($u['data']) < 1) return;

  $current = (int)($u['data'][0]['credits'] ?? 0);

  $sb->rest('PATCH', 'clientes', ['id' => 'eq.' . $clienteId], [
    'credits' => $current + $inc,
    'updated_at' => gmdate('c'),
  ]);
}

// --------------------------
// fluxo principal
// --------------------------
$tx = find_tx_new($sb, $paymentId, $externalRef);

if (!$tx) {
  // fallback opcional (tabela antiga)
  json_out(['ok' => true, 'ignored' => true, 'reason' => 'tx_not_found']);
}

// idempotência: se já aprovado, não soma de novo
if ((string)($tx['status'] ?? '') === 'approved') {
  json_out(['ok' => true, 'already' => true]);
}

if ($status === 'approved') {
  patch_tx_new($sb, (string)$tx['id'], 'approved', $paymentId, $p);

  $clienteId = (string)($tx['cliente_id'] ?? '');
  $inc = (int)($tx['credits_qty'] ?? 0);

  if ($clienteId !== '' && $inc > 0) {
    add_credits($sb, $clienteId, $inc);
  }

  json_out(['ok' => true, 'credited' => true, 'credits_added' => $inc]);
}

// outros status: grava no banco pra acompanhar
// (mapeia cancelamentos / falhas pra algo legível)
$mapped = $status;
if (in_array($status, ['cancelled','rejected','refunded','charged_back'], true)) {
  $mapped = 'failed';
}
if ($mapped === '') $mapped = 'pending';

patch_tx_new($sb, (string)$tx['id'], $mapped, $paymentId, $p);

json_out(['ok' => true, 'updated' => true, 'status' => $mapped]);