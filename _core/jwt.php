<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';

function b64url_encode(string $data): string {
  return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function b64url_decode(string $data): string {
  $data = strtr($data, '-_', '+/');
  $pad = strlen($data) % 4;
  if ($pad) $data .= str_repeat('=', 4 - $pad);
  return base64_decode($data) ?: '';
}

function jwt_sign(array $payload, int $ttlSeconds = 86400): string {
  $header = ['alg' => 'HS256', 'typ' => 'JWT'];
  $now = time();
  $payload['iat'] = $now;
  $payload['exp'] = $now + $ttlSeconds;

  $h = b64url_encode(json_encode($header));
  $p = b64url_encode(json_encode($payload));
  $sig = hash_hmac('sha256', "$h.$p", APP_JWT_SECRET, true);
  $s = b64url_encode($sig);
  return "$h.$p.$s";
}

function jwt_verify(string $token): ?array {
  $parts = explode('.', $token);
  if (count($parts) !== 3) return null;
  [$h, $p, $s] = $parts;

  $sig = b64url_decode($s);
  $calc = hash_hmac('sha256', "$h.$p", APP_JWT_SECRET, true);
  if (!hash_equals($calc, $sig)) return null;

  $payload = json_decode(b64url_decode($p), true);
  if (!is_array($payload)) return null;

  $exp = (int)($payload['exp'] ?? 0);
  if ($exp && time() > $exp) return null;

  return $payload;
}