<?php
declare(strict_types=1);

require_once __DIR__ . '/../../_core/cors.php';
require_once __DIR__ . '/../../_core/http.php';
require_once __DIR__ . '/../../_core/supabase.php';
require_once __DIR__ . '/../../_core/admin_auth.php';

cors_handle();

$sb = new Supabase();
$admin = auth_require_admin($sb);

// filtros
$status = isset($_GET['status']) ? trim((string)$_GET['status']) : 'requested'; // default do seu enum
$limit  = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
$offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;

if ($limit < 1) $limit = 50;
if ($limit > 200) $limit = 200;
if ($offset < 0) $offset = 0;

// valida status (aceita vazio => todos)
$allowed = ['requested','processing','paid','failed','canceled'];
if ($status !== '' && !in_array($status, $allowed, true)) {
  json_out(['ok' => false, 'error' => 'invalid_status'], 400);
}

// PostgREST range: offset..offset+limit-1
$rangeTo = $offset + $limit - 1;

$params = [
  // ✅ colunas (ajuste aqui caso sua tabela tenha nomes diferentes)
  'select' => 'id,cliente_id,amount_brl,pix_key,pix_key_type,idem_key,status,provider,provider_ref,provider_payload,created_at,updated_at',
  'order'  => 'created_at.desc',
];

if ($status !== '') {
  $params['status'] = 'eq.' . $status;
}

$res = $sb->rest('GET', 'saques', $params, null, [
  'Range: ' . $offset . '-' . $rangeTo,
  'Prefer: count=exact',
]);

if (!$res['ok']) {
  json_out(['ok' => false, 'error' => 'list_failed', 'details' => $res['raw']], 500);
}

$rows = is_array($res['data'] ?? null) ? $res['data'] : [];

json_out([
  'ok' => true,
  'items' => $rows,
  'limit' => $limit,
  'offset' => $offset,
]);