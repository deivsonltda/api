<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

final class Supabase {
  private string $base;
  private string $key;

  public function __construct() {
    // 1) Mantém o que já funciona: tenta via constantes do config.php (se existirem)
    $baseConst = defined('SUPABASE_URL') ? (string) SUPABASE_URL : '';
    $keyConst  = defined('SUPABASE_SERVICE_ROLE_KEY') ? (string) SUPABASE_SERVICE_ROLE_KEY : '';

    // 2) Fallback robusto: lê do ambiente do container (EasyPanel)
    // Preferência: Service Role -> Anon -> Key genérica
    $baseEnv = (string) (getenv('SUPABASE_URL') ?: ($_ENV['SUPABASE_URL'] ?? ''));

    $serviceKeyEnv =
      (string) (getenv('SUPABASE_SERVICE_ROLE_KEY') ?: ($_ENV['SUPABASE_SERVICE_ROLE_KEY'] ?? ''));

    $anonKeyEnv =
      (string) (getenv('SUPABASE_ANON_KEY') ?: ($_ENV['SUPABASE_ANON_KEY'] ?? ''));

    $genericKeyEnv =
      (string) (getenv('SUPABASE_KEY') ?: ($_ENV['SUPABASE_KEY'] ?? ''));

    $this->base = trim($baseConst !== '' ? $baseConst : $baseEnv);

    // mantém seu comportamento atual (service role), mas não quebra se só tiver anon
    $candidateKey = trim($keyConst !== '' ? $keyConst : $serviceKeyEnv);
    if ($candidateKey === '') $candidateKey = trim($anonKeyEnv);
    if ($candidateKey === '') $candidateKey = trim($genericKeyEnv);

    $this->key = $candidateKey;

    if ($this->base === '' || $this->key === '') {
      throw new RuntimeException('Supabase URL/KEY não configurados no .env');
    }

    $this->base = rtrim($this->base, '/');
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

    foreach ($headers as $h) {
      $defaultHeaders[] = $h;
    }

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