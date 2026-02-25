<?php
declare(strict_types=1);

require_once __DIR__ . '/../_core/cors.php';
require_once __DIR__ . '/../_core/http.php';
require_once __DIR__ . '/../_core/supabase.php';

cors_handle();

$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
if ($limit <= 0) $limit = 10;
if ($limit > 50) $limit = 50;

// helpers
function firstName(?string $full): string {
  $full = trim((string)$full);
  if ($full === '') return 'Anônimo';
  $parts = preg_split('/\s+/', $full);
  return $parts[0] ?: 'Anônimo';
}

$sb = new Supabase();

/**
 * Importante:
 * - Esse endpoint é público (não precisa auth) só pra ler ganhadores.
 * - Então ele deve depender de RLS no Supabase permitindo SELECT público
 *   (apenas em rewards e só campos não sensíveis).
 */

// PostgREST query:
// /rewards?select=id,amount_brl,avatar,nome_snapshot,created_at&kind=eq.cash&order=created_at.desc&limit=10
$q = [
  'select' => 'id,amount_brl,avatar,nome_snapshot,created_at',
  'kind'   => 'eq.cash',
  'order'  => 'created_at.desc',
  'limit'  => (string)$limit,
];

$resp = $sb->rest('GET', 'rewards', $q);

if (!$resp['ok']) {
  json_out([
    'ok' => false,
    'error' => 'rewards_fetch_failed',
    'details' => (string)($resp['raw'] ?? ''),
  ], 500);
}

$rows = $resp['data'];
if (!is_array($rows)) $rows = [];

$winners = [];
foreach ($rows as $r) {
  $winners[] = [
    'name' => firstName($r['nome_snapshot'] ?? null),
    'amount' => (float)($r['amount_brl'] ?? 0),
    'created_at' => $r['created_at'] ?? null,
  ];
}

json_out(['ok' => true, 'winners' => $winners]);