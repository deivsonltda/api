<?php
declare(strict_types=1);

require_once __DIR__ . '/../_core/cors.php';
require_once __DIR__ . '/../_core/http.php';
require_once __DIR__ . '/../_core/supabase.php';
require_once __DIR__ . '/../_core/auth.php';

cors_handle();

$sb = new Supabase();
$userRow = auth_require_user($sb);

json_out([
  'ok' => true,
  'user' => auth_public_user($userRow),
]);