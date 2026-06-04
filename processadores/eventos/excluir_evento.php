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
$idEvento = intval($_POST['id_evento'] ?? 0);

if ($idEvento <= 0) {
    http_response_code(400);
    echo json_encode(['sucesso' => false, 'erro' => 'Identificador do evento inválido.']);
    exit;
}

try {
    $pdo->beginTransaction();

    // Desativa o evento na tabela principal (ativo = 0)
    $stmt = $pdo->prepare("UPDATE eventos SET ativo = 0 WHERE id = ? AND fotografo_id = ?");
    $stmt->execute([$idEvento, $idFotografo]);

    if ($stmt->rowCount() > 0) {
        // Grava a trilha de desativação permanente/exclusão lógica
        $stmtLog = $pdo->prepare("INSERT INTO logs_eventos (evento_id, fotografo_id, coluna_alterada, valor_antigo, valor_novo, descricao) VALUES (?, ?, 'status', '1', '0', 'Catálogo arquivado/desativado pelo fotógrafo')");
        $stmtLog->execute([$idEvento, $idFotografo]);
        
        $pdo->commit();
        echo json_encode(['sucesso' => true]);
    } else {
        $pdo->rollBack();
        echo json_encode(['sucesso' => false, 'erro' => 'Evento não encontrado ou já desativado.']);
    }
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['sucesso' => false, 'erro' => 'Erro ao processar arquivamento.']);
}