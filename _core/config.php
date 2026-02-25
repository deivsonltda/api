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

// expõe as variáveis do .env para getenv() / $_ENV (sem sobrescrever as do sistema)
foreach ($ENV as $k => $v) {
  if (getenv($k) === false) {
    putenv($k . '=' . $v);
  }
  if (!isset($_ENV[$k])) $_ENV[$k] = $v;
}

/**
 * Busca variável de ambiente de forma robusta:
 * 1) getenv() (EasyPanel/Docker)
 * 2) $_ENV (EasyPanel/Docker)
 * 3) arquivo .env carregado (dev/local)
 */
function env_get(string $key, ?string $default = null): ?string
{
  global $ENV;

  $v = getenv($key);
  if ($v !== false && $v !== '') return $v;

  if (isset($_ENV[$key]) && $_ENV[$key] !== '') return (string)$_ENV[$key];

  if (isset($ENV[$key]) && $ENV[$key] !== '') return (string)$ENV[$key];

  return $default;
}

define('APP_ENV', env_get('APP_ENV', 'prod'));
define('APP_JWT_SECRET', env_get('APP_JWT_SECRET', ''));

// Supabase
define('SUPABASE_URL', rtrim((string)env_get('SUPABASE_URL', ''), '/'));
define('SUPABASE_SERVICE_ROLE_KEY', (string)env_get('SUPABASE_SERVICE_ROLE_KEY', ''));

// CORS: aceita ALLOWED_ORIGINS (plural) e ALLOWED_ORIGIN (singular) do painel
$allowed = env_get('ALLOWED_ORIGINS', null);
if ($allowed === null || $allowed === '') {
  $allowed = env_get('ALLOWED_ORIGIN', 'https://streambrasil.online');
}
define('ALLOWED_ORIGINS', (string)$allowed);

// MercadoPago
define('MP_ACCESS_TOKEN', (string)env_get('MP_ACCESS_TOKEN', ''));