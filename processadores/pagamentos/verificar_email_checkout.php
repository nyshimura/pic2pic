<?php
declare(strict_types=1);
require_once '../../configuracoes/conexao.php';

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
$email = trim($input['email'] ?? '');

if (empty($email)) {
    echo json_encode(['encontrado' => false]);
    exit;
}

try {
    // 1. Procura primeiro se já é um Comprador existente
    $stmt = $pdo->prepare("SELECT nome, cpf FROM compradores WHERE email = ? LIMIT 1");
    $stmt->execute([$email]);
    $comprador = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($comprador) {
        echo json_encode(['encontrado' => true, 'nome' => $comprador['nome'], 'cpf' => $comprador['cpf']]);
        exit;
    }

    // 2. Procura se é um Fotógrafo (que está a tentar comprar)
    $stmtF = $pdo->prepare("SELECT nome FROM fotografos WHERE email = ? LIMIT 1");
    $stmtF->execute([$email]);
    $fotografo = $stmtF->fetch(PDO::FETCH_ASSOC);

    if ($fotografo) {
        echo json_encode(['encontrado' => true, 'nome' => $fotografo['nome'], 'cpf' => null]);
        exit;
    }

    echo json_encode(['encontrado' => false]);
} catch (Exception $e) {
    echo json_encode(['encontrado' => false]);
}