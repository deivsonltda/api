<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';

final class Supabase {
  private string $base;
  private string $key;

  public function __construct() {
    $this->base = SUPABASE_URL;
    $this->key = SUPABASE_SERVICE_ROLE_KEY;
    if (!$this->base || !$this->key) {
      throw new RuntimeException('Supabase URL/KEY não configurados no .env');
    }
  }

  public function rest(string $method, string $tablePath, array $query = [], ?array $body = null, array $headers = []): array {
    $url = $this->base . '/rest/v1/' . ltrim($tablePath, '/');

    if (!empty($query)) {
      $url .= '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    }

    $ch = curl_init($url);
    $defaultHeaders = [
      'apikey: ' . $this->key,
      'Authorization: Bearer ' . $this->key,
      'Content-Type: application/json',
    ];

    foreach ($headers as $h) $defaultHeaders[] = $h;

    curl_setopt_array($ch, [
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_CUSTOMREQUEST  => strtoupper($method),
      CURLOPT_HTTPHEADER     => $defaultHeaders,
    ]);

    if ($body !== null) {
      curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    }

    $resp = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    $json = null;
    if ($resp !== false && $resp !== '') {
      $json = json_decode($resp, true);
    }

    return [
      'ok' => ($status >= 200 && $status < 300),
      'status' => $status,
      'data' => $json,
      'raw' => $resp,
      'error' => $err ?: null,
    ];
  }
}