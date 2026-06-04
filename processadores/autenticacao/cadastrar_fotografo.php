<?php
declare(strict_types=1);
session_start();

require_once '../../configuracoes/conexao.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['sucesso' => false, 'erro' => 'Método não permitido.']);
    exit;
}

$nome = trim(filter_input(INPUT_POST, 'nome', FILTER_SANITIZE_SPECIAL_CHARS));
$email = trim(filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL));
$senha = $_POST['senha'] ?? '';
$confirmarSenha = $_POST['confirmar_senha'] ?? '';

// Validação básica
if (!$nome || !$email || empty($senha)) {
    http_response_code(400);
    echo json_encode(['sucesso' => false, 'erro' => 'Preencha todos os campos obrigatórios com dados válidos.']);
    exit;
}

if ($senha !== $confirmarSenha) {
    http_response_code(400);
    echo json_encode(['sucesso' => false, 'erro' => 'As senhas não coincidem.']);
    exit;
}

if (strlen($senha) < 6) {
    http_response_code(400);
    echo json_encode(['sucesso' => false, 'erro' => 'A senha deve ter pelo menos 6 caracteres.']);
    exit;
}

try {
    // Verifica se o e-mail já está cadastrado
    $stmt = $pdo->prepare("SELECT id FROM fotografos WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        http_response_code(409); // Conflict
        echo json_encode(['sucesso' => false, 'erro' => 'Este e-mail já está em uso. Faça login.']);
        exit;
    }

    // Criptografa a senha usando o algoritmo BCRYPT padrão do PHP
    $senhaHash = password_hash($senha, PASSWORD_DEFAULT);

    // Insere o novo fotógrafo
    $stmt = $pdo->prepare("INSERT INTO fotografos (nome, email, senha) VALUES (?, ?, ?)");
    $stmt->execute([$nome, $email, $senhaHash]);
    $novoId = $pdo->lastInsertId();

    // Faz o login automático imediatamente após o cadastro
    session_regenerate_id(true);
    $_SESSION['fotografo_id'] = $novoId;
    $_SESSION['fotografo_nome'] = $nome;
    $_SESSION['ultima_atividade'] = time();

    echo json_encode(['sucesso' => true, 'redirecionar' => 'painel.php']);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['sucesso' => false, 'erro' => 'Erro interno ao criar a conta.']);
}