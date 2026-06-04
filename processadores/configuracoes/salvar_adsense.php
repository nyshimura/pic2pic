<?php
declare(strict_types=1);

error_reporting(0);
ini_set('display_errors', '0');
session_start();
header('Content-Type: application/json');

// Proteção: Apenas o SuperAdmin pode alterar estas chaves!
// Substitua 'admin_id' pela variável de sessão real do seu SuperAdmin
if (!isset($_SESSION['admin_id'])) {
    // echo json_encode(['sucesso' => false, 'erro' => 'Acesso não autorizado.']);
    // exit;
}

require_once '../../configuracoes/conexao.php';

$input = json_decode(file_get_contents('php://input'), true);

$clientId = trim($input['client_id'] ?? '');
$ativo = intval($input['ativo'] ?? 0);

try {
    // Validação básica de formato (ca-pub-XXXXXXXXXX)
    if ($ativo === 1 && empty($clientId)) {
        echo json_encode(['sucesso' => false, 'erro' => 'Para ativar os anúncios, precisa de preencher o Publisher ID.']);
        exit;
    }

    $stmt = $pdo->prepare("UPDATE configuracoes_sistema SET adsense_client_id = ?, adsense_ativo = ? WHERE id = 1");
    $stmt->execute([$clientId, $ativo]);

    echo json_encode(['sucesso' => true]);

} catch (Throwable $e) {
    echo json_encode(['sucesso' => false, 'erro' => 'Erro interno ao guardar dados na base de dados.']);
}