<?php
// Ficheiro: /processadores/seguranca/ping_ia.php

// URL direta do seu Space no Hugging Face
$url_ia = "https://huggingface.co/spaces/nyshimura/api-fotos-eventos"; 

$ch = curl_init($url_ia);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 15); // Aumentei um pouco o timeout para garantir a resposta
curl_setopt($ch, CURLOPT_USERAGENT, 'Pic2Pic-KeepAlive-Bot/1.0');
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true); // Segue redirecionamentos caso necessário

curl_exec($ch);
curl_close($ch);

// Log para que você possa verificar no servidor se o ping está a rodar
file_put_contents('log_ping.txt', "[" . date('Y-m-d H:i:s') . "] Ping enviado para o Space nyshimura/api-fotos-eventos\n", FILE_APPEND);