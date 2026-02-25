<?php
declare(strict_types=1);

require_once __DIR__ . '/http.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/supabase.php';

function auth_require_admin(Supabase $sb): array {
  $user = auth_require_user($sb);

  $uid = (string)($user['id'] ?? '');
  if ($uid === '') {
    json_out(['ok' => false, 'error' => 'unauthorized'], 401);
  }

  // allowlist (tabela com RLS negando tudo — só service role lê)
  $res = $sb->rest('GET', 'admin_allowlist', [
    'select' => 'uid',
    'uid' => 'eq.' . $uid,
    'limit' => '1',
  ]);

  if (!$res['ok']) {
    json_out(['ok' => false, 'error' => 'admin_check_failed', 'details' => $res['raw']], 500);
  }

  $rows = $res['data'] ?? [];
  if (!is_array($rows) || count($rows) < 1) {
    json_out(['ok' => false, 'error' => 'forbidden'], 403);
  }

  return $user;
}