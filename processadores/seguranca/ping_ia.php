<?php
// Ficheiro: /processadores/seguranca/ping_ia.php
require_once __DIR__ . '/../../configuracoes/conexao.php';

// URL direta do seu Space no Hugging Face (Carregada das configurações)
$url_ia = URL_API_IA; 

$ch = curl_init($url_ia);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 15); // Aumentei um pouco o timeout para garantir a resposta
curl_setopt($ch, CURLOPT_USERAGENT, 'Pic2Pic-KeepAlive-Bot/1.0');
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true); // Segue redirecionamentos caso necessário

curl_exec($ch);
curl_close($ch);

// Log para que você possa verificar no servidor se o ping está a rodar
file_put_contents('log_ping.txt', "[" . date('Y-m-d H:i:s') . "] Ping enviado para a API IA\n", FILE_APPEND);