<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

final class MercadoPago {
  private string $accessToken;

  public function __construct() {
    // ✅ usa o mesmo padrão do seu config.php (.env via env_get)
    $this->accessToken = defined('MP_ACCESS_TOKEN') ? (string)MP_ACCESS_TOKEN : '';
    if ($this->accessToken === '') {
      throw new RuntimeException('missing_mercadopago_access_token');
    }
  }

  private function request(string $method, string $url, ?array $body = null, array $extraHeaders = []): array {
    $ch = curl_init($url);

    $headers = array_merge([
      'Authorization: Bearer ' . $this->accessToken,
      'Content-Type: application/json',
    ], $extraHeaders);

    curl_setopt_array($ch, [
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_CUSTOMREQUEST => $method,
      CURLOPT_HTTPHEADER => $headers,
      CURLOPT_TIMEOUT => 30,
    ]);

    if ($body !== null) {
      curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    }

    $raw = curl_exec($ch);
    $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    $json = null;
    if (is_string($raw) && $raw !== '') {
      $json = json_decode($raw, true);
    }

    return [
      'ok' => ($http >= 200 && $http < 300),
      'http' => $http,
      'data' => $json,
      'raw' => $raw,
      'curl_error' => $err ?: null,
    ];
  }

  // ✅ mantém assinatura, só adiciona idempotencyKey como parâmetro
  public function createPixPayment(
    float $amount,
    string $payerEmail,
    string $description,
    string $notificationUrl,
    string $externalRef,
    string $idempotencyKey
  ): array {
    if ($idempotencyKey === '') {
      throw new RuntimeException('missing_idempotency_key');
    }

    $payload = [
      'transaction_amount' => (float)$amount,
      'description' => $description,
      'payment_method_id' => 'pix',
      'notification_url' => $notificationUrl,
      'external_reference' => $externalRef,
      'payer' => [
        'email' => $payerEmail,
      ],
    ];

    return $this->request('POST', 'https://api.mercadopago.com/v1/payments', $payload, [
      'X-Idempotency-Key: ' . $idempotencyKey, // ✅ obrigatório pra evitar duplicar cobrança
    ]);
  }

  public function getPayment(string $paymentId): array {
    return $this->request('GET', 'https://api.mercadopago.com/v1/payments/' . urlencode($paymentId));
  }
}