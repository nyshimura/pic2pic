<?php
declare(strict_types=1);
session_start();

// O caminho volta duas pastas para acessar as configurações na raiz
require_once '../../configuracoes/conexao.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['sucesso' => false, 'erro' => 'Método não permitido.']);
    exit;
}

// Coleta e limpa as entradas de dados
$email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
$senha = $_POST['senha'] ?? '';

if (!$email || empty($senha)) {
    http_response_code(400);
    echo json_encode(['sucesso' => false, 'erro' => 'Preencha todos os campos corretamente.']);
    exit;
}

try {
    // 1. TENTA LOGAR COMO FOTÓGRAFO (Prioridade Máxima)
    // Fotógrafos também são clientes, a sessão de fotógrafo já permitirá que ele compre na galeria.
    $stmtFoto = $pdo->prepare("SELECT id, nome, senha FROM fotografos WHERE email = ? LIMIT 1");
    $stmtFoto->execute([$email]);
    $fotografo = $stmtFoto->fetch();

    if ($fotografo && password_verify($senha, $fotografo['senha'])) {
        // Regenera o ID da sessão para prevenir Session Fixation
        session_regenerate_id(true);
        
        $_SESSION['fotografo_id'] = $fotografo['id'];
        $_SESSION['fotografo_nome'] = $fotografo['nome'];
        $_SESSION['ultima_atividade'] = time();

        // Aponta o redirecionamento de sucesso para o painel na raiz
        echo json_encode(['sucesso' => true, 'redirecionar' => 'painel.php']);
        exit;
    }

    // 2. TENTA LOGAR COMO COMPRADOR FINAL (Cliente)
    $stmtComp = $pdo->prepare("SELECT id, nome, senha FROM compradores WHERE email = ? LIMIT 1");
    $stmtComp->execute([$email]);
    $comprador = $stmtComp->fetch();

    if ($comprador) {
        
        // --- A MÁGICA DO PRÉ-CADASTRO (Conta sem senha definida) ---
        if ($comprador['senha'] === null) {
            
            // Gera um token seguro e define a validade para 7 horas
            $token = bin2hex(random_bytes(20));
            $expiraEm = date('Y-m-d H:i:s', strtotime('+7 hours'));
            
            $stmtUpdate = $pdo->prepare("UPDATE compradores SET token_recuperacao = ?, token_expira_em = ? WHERE id = ?");
            $stmtUpdate->execute([$token, $expiraEm, $comprador['id']]);
            
            // Lógica de disparo de E-mail de Ativação
            $linkRedefinir = "https://" . $_SERVER['HTTP_HOST'] . "/fotos/nova_senha.php?t=" . $token;
            $assunto = "Ative sua conta e veja suas fotos";
            
            $mensagem = "Olá, " . htmlspecialchars($comprador['nome']) . "!\n\n";
            $mensagem .= "Notamos que você adquiriu fotos em nosso sistema recentemente, mas ainda não ativou o seu painel de acesso pessoal.\n\n";
            $mensagem .= "Clique no link abaixo para criar sua senha. (Este link expira em 7 horas):\n";
            $mensagem .= $linkRedefinir . "\n\n";
            $mensagem .= "Atenciosamente,\nEquipe Pic2Pic";

            $headers = "From: sistema@" . $_SERVER['HTTP_HOST'] . "\r\n";
            @mail($email, $assunto, $mensagem, $headers);

            // Retorna um falso controlado para não logar, mas informa o usuário
            echo json_encode([
                'sucesso' => false, 
                'erro' => 'Você tem fotos salvas! Enviamos um link para o seu e-mail (' . htmlspecialchars($email) . ') para você criar sua senha.'
            ]);
            exit;
        }

        // --- CLIENTE NORMAL (Já definiu a senha antes) ---
        if (password_verify($senha, $comprador['senha'])) {
            session_regenerate_id(true);
            
            $_SESSION['comprador_id'] = $comprador['id'];
            $_SESSION['comprador_nome'] = $comprador['nome'];
            $_SESSION['ultima_atividade'] = time();

            echo json_encode(['sucesso' => true, 'redirecionar' => 'painel_comprador.php']);
            exit;
        }
    }

    // 3. SE NADA BATER (Nem fotógrafo, nem comprador)
    http_response_code(401);
    echo json_encode(['sucesso' => false, 'erro' => 'E-mail ou senha inválidos.']);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['sucesso' => false, 'erro' => 'Falha crítica no servidor.']);
}