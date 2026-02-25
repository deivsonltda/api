<?php
declare(strict_types=1);

require_once __DIR__ . '/http.php';

// Lê ADMIN_ACTIONS_KEY do ambiente.
// Se você já tem env_get() no seu _core/http.php, usa ele.
function admin_require_key(): void {
  $key = '';
  if (function_exists('env_get')) {
    $key = (string) env_get('ADMIN_ACTIONS_KEY', '');
  } else {
    $key = (string) (getenv('ADMIN_ACTIONS_KEY') ?: '');
  }

  $key = trim($key);
  if ($key === '') {
    json_out(['ok' => false, 'error' => 'missing_admin_key_env'], 500);
  }

  $hdr = $_SERVER['HTTP_X_ADMIN_KEY'] ?? '';
  $hdr = trim((string)$hdr);

  if ($hdr === '' || !hash_equals($key, $hdr)) {
    json_out(['ok' => false, 'error' => 'unauthorized_admin'], 401);
  }
}