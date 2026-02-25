<?php
header('Content-Type: application/json; charset=utf-8');

echo json_encode([
  'SUPABASE_URL_getenv' => getenv('SUPABASE_URL') ?: null,
  'SUPABASE_KEY_getenv' => getenv('SUPABASE_KEY') ? 'SET' : null,
  'SUPABASE_URL__ENV'   => $_ENV['SUPABASE_URL'] ?? null,
  'SUPABASE_KEY__ENV'   => isset($_ENV['SUPABASE_KEY']) ? 'SET' : null,
], JSON_PRETTY_PRINT);