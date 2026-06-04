<?php
declare(strict_types=1);
require_once '../../configuracoes/conexao.php';

header('Content-Type: application/json');

$token = $_GET['e'] ?? '';
$offset = intval($_GET['offset'] ?? 0);
$limit = 30; 

if (empty($token)) {
    echo json_encode(['sucesso' => false, 'erro' => 'Token inválido.']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT id FROM eventos WHERE token_url = ? AND ativo = 1");
    $stmt->execute([$token]);
    $evento = $stmt->fetch();

    if (!$evento) {
        echo json_encode(['sucesso' => false, 'erro' => 'Evento não encontrado.']);
        exit;
    }

    $stmtFotos = $pdo->prepare("SELECT id FROM fotos_eventos WHERE evento_id = ? ORDER BY id DESC LIMIT ? OFFSET ?");
    $stmtFotos->bindValue(1, $evento['id'], PDO::PARAM_INT);
    $stmtFotos->bindValue(2, $limit, PDO::PARAM_INT);
    $stmtFotos->bindValue(3, $offset, PDO::PARAM_INT);
    $stmtFotos->execute();
    $fotos = $stmtFotos->fetchAll(PDO::FETCH_ASSOC);

    $fotosOtimizadas = [];
    foreach ($fotos as $f) {
        $fotosOtimizadas[] = [
            'id' => $f['id'],
            'url' => 'processadores/imagens/proxy_imagem.php?f=' . $f['id']
        ];
    }

    echo json_encode(['sucesso' => true, 'fotos' => $fotosOtimizadas]);
} catch (Exception $e) {
    echo json_encode(['sucesso' => false, 'erro' => 'Erro interno de paginação.']);
}