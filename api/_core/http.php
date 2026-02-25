<?php
declare(strict_types=1);

function json_input(): array {
  $raw = file_get_contents('php://input') ?: '';
  $data = json_decode($raw, true);
  return is_array($data) ? $data : [];
}

function json_out(array $data, int $status = 200): void {
  http_response_code($status);
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
  exit;
}

function header_get(string $name): ?string {
  $nameLower = strtolower($name);

  // 1) getallheaders()
  if (function_exists('getallheaders')) {
    $h = getallheaders();
    if (is_array($h)) {
      foreach ($h as $k => $v) {
        if (strtolower((string)$k) === $nameLower) {
          return is_string($v) ? $v : null;
        }
      }
    }
  }

  // 2) $_SERVER variantes comuns
  $map = [
    'authorization' => ['HTTP_AUTHORIZATION', 'REDIRECT_HTTP_AUTHORIZATION'],
  ];

  if (isset($map[$nameLower])) {
    foreach ($map[$nameLower] as $key) {
      if (!empty($_SERVER[$key])) return (string)$_SERVER[$key];
    }
  }

  // 3) fallback genérico
  $serverKey = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
  return !empty($_SERVER[$serverKey]) ? (string)$_SERVER[$serverKey] : null;
}

function bearer_token(): ?string {
  $auth = header_get('Authorization');
  if (!$auth) return null;

  // "Bearer xxxxx"
  if (preg_match('/^\s*Bearer\s+(.+)\s*$/i', $auth, $m)) {
    return trim($m[1]);
  }
  return null;
}