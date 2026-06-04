<?php
declare(strict_types=1);
session_start();
require_once '../../configuracoes/conexao.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_SESSION['fotografo_id'])) {
    http_response_code(401);
    echo json_encode(['sucesso' => false, 'erro' => 'Acesso negado.']);
    exit;
}

$idFotografo = $_SESSION['fotografo_id'];
$publicKey = trim($_POST['public_key'] ?? ''); // Public Key não precisa de criptografia (já é pública)
$accessToken = trim($_POST['access_token'] ?? '');

if (empty($publicKey) || empty($accessToken)) {
    http_response_code(400);
    echo json_encode(['sucesso' => false, 'erro' => 'As chaves são obrigatórias.']);
    exit;
}

try {
    // 1. Gera um Vetor de Inicialização (IV) aleatório para a criptografia (Regra do AES)
    $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('aes-256-cbc'));
    
    // 2. Criptografa o Access Token usando a Chave Mestra do conexao.php
    $tokenCriptografado = openssl_encrypt($accessToken, 'aes-256-cbc', APP_KEY, 0, $iv);
    
    // 3. Junta o IV e o Token embaralhado (precisamos do IV salvo para conseguir reverter depois)
    $tokenFinal = base64_encode($iv . '::' . $tokenCriptografado);

    // Salva no banco
    $stmt = $pdo->prepare("UPDATE fotografos SET mp_public_key = ?, mp_access_token = ? WHERE id = ?");
    $stmt->execute([$publicKey, $tokenFinal, $idFotografo]);
    
    echo json_encode(['sucesso' => true]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['sucesso' => false, 'erro' => 'Erro interno ao salvar as chaves de forma segura.']);
}