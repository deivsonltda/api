<?php

declare(strict_types=1);

function env_load(string $path): array
{
  if (!file_exists($path)) return [];
  $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
  $out = [];
  foreach ($lines as $line) {
    $line = trim($line);
    if ($line === '' || str_starts_with($line, '#')) continue;
    $pos = strpos($line, '=');
    if ($pos === false) continue;
    $k = trim(substr($line, 0, $pos));
    $v = trim(substr($line, $pos + 1));
    $v = trim($v, "\"'");
    $out[$k] = $v;
  }
  return $out;
}

$ENV = env_load(__DIR__ . '/../.env');

// expõe as variáveis do .env para getenv() / $_ENV
foreach ($ENV as $k => $v) {
  // evita sobrescrever vars já definidas pelo sistema/Apache
  if (getenv($k) === false) {
    putenv($k . '=' . $v);
  }
  if (!isset($_ENV[$k])) $_ENV[$k] = $v;
}

function env_get(string $key, ?string $default = null): ?string
{
  global $ENV;
  return $ENV[$key] ?? $default;
}

define('APP_ENV', env_get('APP_ENV', 'prod'));
define('APP_JWT_SECRET', env_get('APP_JWT_SECRET', ''));

define('SUPABASE_URL', rtrim(env_get('SUPABASE_URL', ''), '/'));
define('SUPABASE_SERVICE_ROLE_KEY', env_get('SUPABASE_SERVICE_ROLE_KEY', ''));

define('ALLOWED_ORIGINS', env_get('ALLOWED_ORIGINS', 'https://streambrasil.online'));

define('MP_ACCESS_TOKEN', env_get('MP_ACCESS_TOKEN', ''));
