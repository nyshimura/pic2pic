<?php
declare(strict_types=1);

// Configurações do Banco de Dados
define('DB_HOST', 'localhost');
define('DB_NAME', 'nomedabase');
define('DB_USER', 'seuusuario');
define('DB_PASS', 'suasenha');

// Configuração de Ambiente (URL Base do Sistema)
// Altere para o endereço real quando colocar no ar, com ou sem a pasta. Não inclua a barra no final (/).
// Exemplo: 'https://seusite.com.br/fotos' ou 'http://localhost/projeto_github'
define('APP_URL', 'https://seuendereco.com.br/fotos');

define('APP_KEY', 'suachavemestrade32caracteres'); // Chave mestra de criptografia (Mínimo 32 caracteres)

try {
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS, $options);
    
    $pdo->exec("SET time_zone = '-03:00'");
    
} catch (PDOException $e) {
    // Em produção, registre o erro em log oculto e exiba uma mensagem amigável
    die("Erro interno de configuração. Por favor, tente mais tarde.");
}