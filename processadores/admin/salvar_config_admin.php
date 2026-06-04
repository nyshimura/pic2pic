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
$emailRobo = trim(filter_input(INPUT_POST, 'email_robo_drive', FILTER_VALIDATE_EMAIL));

if (!$emailRobo) {
    http_response_code(400);
    echo json_encode(['sucesso' => false, 'erro' => 'Forneça um e-mail válido para o robô.']);
    exit;
}

try {
    // Trava de segurança extra: Verifica se o usuário que fez a requisição é realmente admin
    $stmt = $pdo->prepare("SELECT is_admin FROM fotografos WHERE id = ?");
    $stmt->execute([$idFotografo]);
    if (!$stmt->fetchColumn()) {
        http_response_code(403);
        echo json_encode(['sucesso' => false, 'erro' => 'Você não tem permissão de administrador.']);
        exit;
    }

    // Atualiza a linha mestre de configurações (ID 1 fixo)
    $stmt = $pdo->prepare("UPDATE configuracoes_sistema SET email_robo_drive = ? WHERE id = 1");
    $stmt->execute([$emailRobo]);

    echo json_encode(['sucesso' => true]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['sucesso' => false, 'erro' => 'Erro interno ao salvar configurações.']);
}