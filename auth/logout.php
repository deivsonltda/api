<?php
declare(strict_types=1);

require_once __DIR__ . '/../_core/cors.php';
require_once __DIR__ . '/../_core/http.php';

cors_handle();

// ✅ ADIÇÃO: limpa cookie HttpOnly (não altera o comportamento atual)
$secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
setcookie('auth_token', '', [
  'expires'  => time() - 3600,
  'path'     => '/',
  'secure'   => $secure,
  'httponly' => true,
  'samesite' => 'Lax',
]);

// Com JWT no front, logout é só o front apagar o token.
// Esse endpoint fica pra padronizar UX.
json_out(['ok' => true]);