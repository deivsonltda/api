<?php
declare(strict_types=1);

require_once __DIR__ . '/../_core/cors.php';
require_once __DIR__ . '/../_core/http.php';
require_once __DIR__ . '/../_core/auth.php';
require_once __DIR__ . '/../_core/supabase.php';
require_once __DIR__ . '/../_core/mercadopago.php';

cors_handle();

$in = json_input();
$amount = (float)($in['amount'] ?? 0);

if ($amount <= 0) json_out(['ok' => false, 'error' => 'invalid_amount'], 400);

$sb = new Supabase();
$user = auth_require_user($sb); // pega cliente logado do seu JWT
$email = (string)($user['email'] ?? '');

if ($email === '') json_out(['ok' => false, 'error' => 'missing_email'], 400);

// ✅ sua referência externa (pode ser "uid:timestamp" ou id de uma tabela de pagamentos)
$externalRef = ($user['id'] ?? 'user') . ':' . time();

// ✅ idempotency key obrigatória
$idempotencyKey = bin2hex(random_bytes(16)) . '-' . $externalRef;

// notification_url: a URL pública do seu webhook
$notificationUrl = (string) env_get('MP_NOTIFICATION_URL', '');
$notificationUrl = trim($notificationUrl);

if ($notificationUrl === '') {
  json_out(['ok' => false, 'error' => 'missing_mp_notification_url'], 500);
}

$mp = new MercadoPago();

$resp = $mp->createPixPayment(
  $amount,
  $email,
  'Compra de créditos',
  $notificationUrl,
  $externalRef,
  $idempotencyKey
);

if (!$resp['ok']) {
  json_out([
    'ok' => false,
    'error' => 'mp_create_failed',
    'details' => $resp['raw'],
  ], 500);
}

// ✅ EMV do PIX vem dentro do QR data
$qrCode = $resp['data']['point_of_interaction']['transaction_data']['qr_code'] ?? null;
$paymentId = $resp['data']['id'] ?? null;

if (!$qrCode || !$paymentId) {
  json_out(['ok' => false, 'error' => 'mp_missing_qr'], 500);
}

// ==============================
// ✅ NOVO: registra transação pendente no Supabase (public.transacoes_pix)
// ==============================

$clientId = (string)($user['id'] ?? '');
if ($clientId === '') {
  json_out(['ok' => false, 'error' => 'missing_client_id'], 500);
}

// 1 crédito = R$ 1
$creditsQty = (int) floor($amount);

// status vindo do MP (geralmente "pending")
$mpStatus = (string)($resp['data']['status'] ?? 'pending');
$mpStatusDetail = (string)($resp['data']['status_detail'] ?? 'created');

// payload conforme sua tabela
$txInsertPayload = [
  // id (uuid) -> deixa o DEFAULT do banco gerar
  'cliente_id' => $clientId,
  'mp_payment_id' => (string)$paymentId,
  'external_reference' => (string)$externalRef,
  'amount_brl' => (float)$amount,
  'credits_qty' => $creditsQty,
  'status' => $mpStatus,
  'status_detail' => $mpStatusDetail,
  'pix_emv' => (string)$qrCode,
  // created_at/updated_at -> deixa o DEFAULT/triggers do banco (se tiver)
  'raw_mp' => $resp['data'], // jsonb
];

$txIns = $sb->rest('POST', 'transacoes_pix', [], $txInsertPayload, [
  'Prefer: return=representation'
]);

if (!$txIns['ok'] || !is_array($txIns['data']) || count($txIns['data']) < 1) {
  json_out([
    'ok' => false,
    'error' => 'tx_insert_failed',
    'details' => $txIns['raw'],
  ], 500);
}

$txRow = $txIns['data'][0];

// ==============================
// resposta final (mantendo o que já funciona)
// ==============================
json_out([
  'ok' => true,
  'payment_id' => (string)$paymentId,
  'external_ref' => (string)$externalRef,
  'pixCode' => (string)$qrCode, // ✅ seu EMV “copia e cola”
  'tx' => [
    'id' => (string)($txRow['id'] ?? ''),
    'status' => (string)($txRow['status'] ?? $mpStatus),
    'credits_qty' => (int)($txRow['credits_qty'] ?? $creditsQty),
    'amount_brl' => (float)($txRow['amount_brl'] ?? $amount),
  ],
]);