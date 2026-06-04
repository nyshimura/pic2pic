<?php
declare(strict_types=1);

error_reporting(0);
ini_set('display_errors', '0');

session_start();
header('Content-Type: application/json');

require_once '../../configuracoes/conexao.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_SESSION['fotografo_id'])) {
    http_response_code(401);
    echo json_encode(['sucesso' => false, 'erro' => 'Acesso negado.']);
    exit;
}

$idFotografo = $_SESSION['fotografo_id'];
$acao = $_POST['acao'] ?? 'salvar';

try {
    // ==========================================
    // MODO EXCLUSÃO
    // ==========================================
    if ($acao === 'excluir') {
        $stmt = $pdo->prepare("UPDATE fotografos SET mp_public_key = NULL, mp_access_token = NULL WHERE id = ?");
        $stmt->execute([$idFotografo]);
        echo json_encode(['sucesso' => true, 'msg' => '🗑️ Integração removida! Seus catálogos voltaram a ser gratuitos.']);
        exit;
    }

    // ==========================================
    // MODO SALVAR E VALIDAR
    // ==========================================
    $pk = trim($_POST['pk'] ?? '');
    $at = trim($_POST['at'] ?? '');

    if (empty($pk) || empty($at)) {
        echo json_encode(['sucesso' => false, 'erro' => 'Preencha ambas as chaves para conectar.']);
        exit;
    }

    // 1. BLINDAGEM FÍSICA (Garante que a chave é do padrão MP)
    if (!preg_match('/^(APP_USR|TEST)-/', $pk) || !preg_match('/^(APP_USR|TEST)-/', $at)) {
        echo json_encode(['sucesso' => false, 'erro' => 'Formato inválido! As chaves do Mercado Pago sempre começam com APP_USR- ou TEST-']);
        exit;
    }

    // 2. BLINDAGEM LÓGICA V2 - TESTE DO ACCESS TOKEN (Servidor)
    $chAT = curl_init('https://api.mercadopago.com/v1/payment_methods');
    curl_setopt($chAT, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $at]);
    curl_setopt($chAT, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($chAT, CURLOPT_SSL_VERIFYPEER, false); 
    
    $responseAT = curl_exec($chAT);
    $httpCodeAT = curl_getinfo($chAT, CURLINFO_HTTP_CODE);
    curl_close($chAT);

    $jsonAT = json_decode((string)$responseAT, true);

    if ($httpCodeAT !== 200 || isset($jsonAT['message']) || isset($jsonAT['error'])) {
        echo json_encode(['sucesso' => false, 'erro' => '❌ Access Token inválido ou revogado! O Mercado Pago recusou a chave.']);
        exit;
    }

    // 3. BLINDAGEM LÓGICA V2 - TESTE DA PUBLIC KEY (Frontend)
    // Batemos no endpoint passando a public_key na URL para ver se o MP a reconhece
    $chPK = curl_init('https://api.mercadopago.com/v1/payment_methods?public_key=' . $pk);
    curl_setopt($chPK, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($chPK, CURLOPT_SSL_VERIFYPEER, false);
    
    $responsePK = curl_exec($chPK);
    $httpCodePK = curl_getinfo($chPK, CURLINFO_HTTP_CODE);
    curl_close($chPK);

    $jsonPK = json_decode((string)$responsePK, true);

    if ($httpCodePK !== 200 || isset($jsonPK['message']) || isset($jsonPK['error'])) {
        echo json_encode(['sucesso' => false, 'erro' => '❌ Public Key inválida! O Mercado Pago não reconheceu esta chave pública.']);
        exit;
    }

    // ==========================================
    // CRIPTOGRAFIA AES-256 (O COFRE)
    // ==========================================
    $chaveSecreta = defined('CHAVE_MESTRA') ? CHAVE_MESTRA : 'Pic2Pic_Seguranca_2026';
    $key = hash('sha256', $chaveSecreta, true);
    $iv = random_bytes(openssl_cipher_iv_length('aes-256-cbc'));
    
    $encrypted = openssl_encrypt($at, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);
    $tokenSeguroSalvar = base64_encode($iv . $encrypted);

    $stmt = $pdo->prepare("UPDATE fotografos SET mp_public_key = ?, mp_access_token = ? WHERE id = ?");
    $stmt->execute([$pk, $tokenSeguroSalvar, $idFotografo]);

    echo json_encode(['sucesso' => true, 'msg' => '✅ Credenciais 100% Autênticas! Guardadas no Cofre Criptografado.']);

} catch (Throwable $e) {
    echo json_encode(['sucesso' => false, 'erro' => 'Falha interna: ' . $e->getMessage()]);
}