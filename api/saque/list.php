<?php
declare(strict_types=1);

require_once __DIR__ . '/../_core/cors.php';
require_once __DIR__ . '/../_core/http.php';
require_once __DIR__ . '/../_core/auth.php';
require_once __DIR__ . '/../_core/supabase.php';

cors_handle();

$sb = new Supabase();
$user = auth_require_user($sb);
$uid = (string)($user['id'] ?? '');
if ($uid === '') json_out(['ok' => false, 'error' => 'unauthorized'], 401);

$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
if ($limit <= 0) $limit = 20;
if ($limit > 50) $limit = 50;

// via REST direto (tabela)
$q = "saques?select=id,amount_brl,status,requested_at,approved_at,paid_at,failed_at,created_at"
   . "&cliente_id=eq." . rawurlencode($uid)
   . "&order=created_at.desc"
   . "&limit=" . $limit;

$res = $sb->rest('GET', $q);

if (!$res['ok']) {
  json_out(['ok' => false, 'error' => 'list_failed', 'details' => $res['raw']], 500);
}

json_out(['ok' => true, 'items' => $res['data'] ?? []]);