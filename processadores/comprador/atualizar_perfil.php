<?php
declare(strict_types=1);

error_reporting(0);
ini_set('display_errors', '0');

session_start();
header('Content-Type: application/json');

// Validação de Segurança
if (!isset($_SESSION['comprador_id'])) {
    echo json_encode(['sucesso' => false, 'erro' => 'A sessão expirou. Faça login novamente.']);
    exit;
}

require_once '../../configuracoes/conexao.php';

$input = json_decode(file_get_contents('php://input'), true);
$idComprador = $_SESSION['comprador_id'];

// Sanitização de entradas
$nome = trim($input['nome'] ?? '');
// Limpa o CPF para guardar apenas números na base de dados
$cpf = preg_replace('/[^0-9]/', '', $input['cpf'] ?? '');

// Nova palavra-passe (Opcional)
$senhaNova = trim($input['senha'] ?? '');

if (empty($nome)) {
    echo json_encode(['sucesso' => false, 'erro' => 'O nome completo é obrigatório.']);
    exit;
}

try {
    // 1. Verifica se o cliente enviou uma palavra-passe nova
    if (!empty($senhaNova)) {
        // Encripta a palavra-passe antes de guardar (NUNCA guardar em texto limpo)
        $senhaHash = password_hash($senhaNova, PASSWORD_DEFAULT);
        
        $stmt = $pdo->prepare("UPDATE compradores SET nome = ?, cpf = ?, senha = ? WHERE id = ?");
        $stmt->execute([$nome, $cpf, $senhaHash, $idComprador]);
    } else {
        // Se a senha vier em branco, atualizamos apenas o Nome e o CPF
        $stmt = $pdo->prepare("UPDATE compradores SET nome = ?, cpf = ? WHERE id = ?");
        $stmt->execute([$nome, $cpf, $idComprador]);
    }
    
    // Atualiza a variável de sessão para que o nome novo apareça imediatamente após o refresh
    $_SESSION['comprador_nome'] = $nome;

    echo json_encode(['sucesso' => true]);

} catch (Throwable $e) {
    echo json_encode(['sucesso' => false, 'erro' => 'Ocorreu um erro interno ao atualizar o perfil.']);
}