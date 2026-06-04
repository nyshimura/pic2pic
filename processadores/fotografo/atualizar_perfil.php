<?php
declare(strict_types=1);

error_reporting(0);
ini_set('display_errors', '0');

session_start();
header('Content-Type: application/json');

// Garante que só quem tem a sessão de fotógrafo edita a tabela fotografos
if (!isset($_SESSION['fotografo_id'])) {
    echo json_encode(['sucesso' => false, 'erro' => 'Acesso negado.']);
    exit;
}

require_once '../../configuracoes/conexao.php';

$idFotografo = $_SESSION['fotografo_id'];
$input = json_decode(file_get_contents('php://input'), true);

$nome = trim($input['nome'] ?? '');
$senha = $input['senha'] ?? '';

if (empty($nome)) {
    echo json_encode(['sucesso' => false, 'erro' => 'O nome do estúdio é obrigatório.']);
    exit;
}

try {
    // Atualiza apenas o nome se a senha estiver em branco
    if (empty($senha)) {
        $stmt = $pdo->prepare("UPDATE fotografos SET nome = ? WHERE id = ?");
        $stmt->execute([$nome, $idFotografo]);
    } else {
        // Se preencheu a senha, gera o Hash seguro e atualiza ambos
        $hashSenha = password_hash($senha, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE fotografos SET nome = ?, senha = ? WHERE id = ?");
        $stmt->execute([$nome, $hashSenha, $idFotografo]);
    }

    // Atualiza a sessão ativa para que o nome no topo do site mude na hora
    $_SESSION['fotografo_nome'] = $nome;

    echo json_encode(['sucesso' => true]);

} catch (Throwable $e) {
    echo json_encode(['sucesso' => false, 'erro' => 'Erro interno ao salvar no banco de dados.']);
}