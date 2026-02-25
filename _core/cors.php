<?php
declare(strict_types=1);

/**
 * CORS simples para DEV (localhost) e PROD (streambrasil.online).
 * - Sempre envia Access-Control-Allow-Origin (origem exata quando possível)
 * - Responde OPTIONS
 * - Permite Authorization
 * - Suporta cookies (Allow-Credentials) — necessário porque login seta cookie
 */

function cors_handle(): void {
  $origin = $_SERVER['HTTP_ORIGIN'] ?? '';

  // ✅ origens permitidas (PROD + DEV)
  // Ajuste as portas conforme o seu front local (Vite geralmente 5173)
  $allowed = [
    'https://streambrasil.online',
    'https://www.streambrasil.online',

    'http://localhost:5173',
    'http://127.0.0.1:5173',

    'http://localhost:8080',
    'http://127.0.0.1:8080',

    // se você usa outra porta local, adicione aqui:
    // 'http://localhost:3000',
  ];

  if ($origin && in_array($origin, $allowed, true)) {
    header("Access-Control-Allow-Origin: {$origin}");
    header("Vary: Origin");
    header("Access-Control-Allow-Credentials: true");
  } else {
    // fallback mantém produção como antes
    header("Access-Control-Allow-Origin: https://streambrasil.online");
    header("Vary: Origin");
    header("Access-Control-Allow-Credentials: true");
  }

  header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
  header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
  header("Access-Control-Max-Age: 86400");

  if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(204);
    exit;
  }
}