<?php
// Força o navegador a interpretar como UTF-8
header('Content-Type: text/html; charset=utf-8');

// Headers de segurança que já configuramos
header('X-Frame-Options: ALLOW-FROM https://aymoresembalagens.bitrix24.com.br');
header('Content-Security-Policy: frame-ancestors https://aymoresembalagens.bitrix24.com.br https://*.bitrix24.com.br');

include('index.html'); 
?>