<?php
declare(strict_types=1);

require_once __DIR__ . '/../_core/cors.php';
require_once __DIR__ . '/../_core/http.php';
require_once __DIR__ . '/../_core/supabase.php';
require_once __DIR__ . '/../_core/auth.php';

cors_handle();

$sb = new Supabase();
$user = auth_require_user($sb);

$uid = (string)($user['id'] ?? '');
if ($uid === '') {
  json_out(['ok' => false, 'error' => 'unauthorized'], 401);
}

// paginação
$limit  = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
$offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;

if ($limit < 1) $limit = 50;
if ($limit > 200) $limit = 200;
if ($offset < 0) $offset = 0;

// PostgREST range: offset..offset+limit-1
$rangeTo = $offset + $limit - 1;

// select seguro (só o que o front precisa)
$params = [
  'select' => 'id,kind,amount_brl,avatar,created_at',
  'cliente_id' => 'eq.' . $uid,
  'order' => 'created_at.desc',
];

$res = $sb->rest('GET', 'rewards', $params, null, [
  'Range: ' . $offset . '-' . $rangeTo,
  'Prefer: count=exact',
]);

if (!$res['ok']) {
  json_out([
    'ok' => false,
    'error' => 'list_failed',
    'details' => $res['raw'] ?? null,
  ], 500);
}

$rows = is_array($res['data'] ?? null) ? $res['data'] : [];

json_out([
  'ok' => true,
  'items' => $rows,
  'limit' => $limit,
  'offset' => $offset,
]);