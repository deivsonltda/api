<?php
declare(strict_types=1);

/**
 * CORS simples para DEV (localhost:8080) e prod (afiliados.local).
 * - Sempre envia Access-Control-Allow-Origin
 * - Responde OPTIONS
 * - Permite Authorization
 */

function cors_handle(): void {
  $origin = $_SERVER['HTTP_ORIGIN'] ?? '';

  // ✅ origens permitidas (ajuste se quiser)
  $allowed = [
    'https://streambrasil.online'
  ];

  if ($origin && in_array($origin, $allowed, true)) {
    header("Access-Control-Allow-Origin: {$origin}");
    header("Vary: Origin");
  } else {
    // fallback DEV (se não vier Origin por algum motivo)
    header("Access-Control-Allow-Origin: https://streambrasil.online");
    header("Vary: Origin");
  }

  header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
  header("Access-Control-Allow-Headers: Content-Type, Authorization");
  header("Access-Control-Max-Age: 86400");

  // Se você NÃO usa cookies/sessão, não precisa disso:
  // header("Access-Control-Allow-Credentials: true");

  if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(204);
    exit;
  }
}