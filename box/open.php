<?php
declare(strict_types=1);

require_once __DIR__ . '/../_core/cors.php';
require_once __DIR__ . '/../_core/http.php';
require_once __DIR__ . '/../_core/supabase.php';
require_once __DIR__ . '/../_core/auth.php';

cors_handle();

$sb = new Supabase();
$user = auth_require_user($sb);

// chama RPC atômica
$rpc = $sb->rest('POST', 'rpc/open_box_reward', [], [
  'p_uid' => $user['id'],
]);

if (!$rpc['ok']) {
  $raw = (string)($rpc['raw'] ?? '');
  if (stripos($raw, 'no_credits') !== false) {
    json_out(['ok' => false, 'error' => 'no_credits'], 409);
  }
  json_out(['ok' => false, 'error' => 'open_failed', 'details' => $raw], 500);
}

if (!is_array($rpc['data']) || count($rpc['data']) < 1) {
  json_out(['ok' => false, 'error' => 'open_failed'], 500);
}

$row = $rpc['data'][0];

$kind   = (string)($row['kind'] ?? 'nothing');
$reward = (float)($row['reward'] ?? 0);

// ================================
// ✅ Registro do ganhador (BEST-EFFORT)
// ================================
if ($kind === 'cash' && $reward > 0) {

  // snapshot opcional (só se existir)
  $nome = null;
  if (!empty($user['nome']) && is_string($user['nome'])) $nome = $user['nome'];
  elseif (!empty($user['name']) && is_string($user['name'])) $nome = $user['name'];

  $telefone = null;
  if (!empty($user['telefone']) && is_string($user['telefone'])) $telefone = $user['telefone'];
  elseif (!empty($user['phone']) && is_string($user['phone'])) $telefone = $user['phone'];
  elseif (!empty($user['whatsapp']) && is_string($user['whatsapp'])) $telefone = $user['whatsapp'];

  $payload = [
    'cliente_id' => (string)$user['id'],
    // ✅ manda kind, e o banco também tem DEFAULT como rede de segurança
    'kind' => 'cash',
    'amount_brl' => $reward,
    'avatar' => '🎰',
  ];

  if ($nome) $payload['nome_snapshot'] = $nome;
  if ($telefone) $payload['telefone_snapshot'] = $telefone;

  try {
    // ✅ return=minimal = mais leve e evita parse/representation
    $ins = $sb->rest('POST', 'rewards', [], $payload, [
      'Prefer: return=minimal'
    ]);

    // Se falhar, NÃO quebra o open. Só loga (opcional).
    if (!$ins['ok']) {
      // opcional: salva log local pra depurar
      // @file_put_contents(__DIR__ . '/../_log/rewards_insert.log', date('c')." ".$ins['raw']."\n", FILE_APPEND);
    }

  } catch (\Throwable $e) {
    // opcional: log
    // @file_put_contents(__DIR__ . '/../_log/rewards_insert.log', date('c')." EX: ".$e->getMessage()."\n", FILE_APPEND);
  }
}

json_out([
  'ok' => true,
  'reward' => $reward,
  'credits' => (int)($row['credits'] ?? 0),
  'balance_brl' => (float)($row['balance_brl'] ?? 0),
  'bonus_credits' => (int)($row['bonus_credits'] ?? 0),
  'kind' => $kind,
  'bonus_awarded' => (int)($row['bonus_awarded'] ?? 0),
]);