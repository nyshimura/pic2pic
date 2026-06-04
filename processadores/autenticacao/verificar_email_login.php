<?php
declare(strict_types=1);

error_reporting(0);
ini_set('display_errors', '0');
header('Content-Type: application/json');

require_once '../../configuracoes/conexao.php';

$email = trim($_POST['email'] ?? '');

if (empty($email)) {
    echo json_encode(['acao' => 'erro', 'erro' => 'Por favor, insira um e-mail válido.']);
    exit;
}

try {
    // 1. Verifica se o e-mail pertence a um FOTÓGRAFO
    $stmtFotografo = $pdo->prepare("SELECT id FROM fotografos WHERE email = ?");
    $stmtFotografo->execute([$email]);
    if ($stmtFotografo->fetch()) {
        // Fotógrafo obrigatoriamente tem senha
        echo json_encode(['acao' => 'pedir_senha']);
        exit;
    }

    // 2. Verifica se o e-mail pertence a um COMPRADOR
    $stmtComprador = $pdo->prepare("SELECT id, nome, senha FROM compradores WHERE email = ?");
    $stmtComprador->execute([$email]);
    $comprador = $stmtComprador->fetch(PDO::FETCH_ASSOC);

    if ($comprador) {
        
        // Cenário A: O comprador já tem uma senha configurada
        if (!empty($comprador['senha'])) {
            echo json_encode(['acao' => 'pedir_senha']);
            exit;
        } 
        
        // Cenário B: Criação do Token de 7 Horas (Shadow Account)
        else {
            // Gera um token criptográfico único
            $token = bin2hex(random_bytes(32));
            // Define a expiração para daqui a 7 horas
            $expira = date('Y-m-d H:i:s', strtotime('+7 hours'));
            
            // Grava o token e a validade na base de dados
            $pdo->prepare("UPDATE compradores SET token_recuperacao = ?, token_expira_em = ? WHERE id = ?")->execute([$token, $expira, $comprador['id']]);

            // Monta o Link Mágico (Ajuste a pasta /fotos/ se o seu sistema estiver na raiz)
            $linkReset = "https://" . $_SERVER['HTTP_HOST'] . "/fotos/redefinir_senha.php?token=" . $token;
            
            // Dispara o e-mail com o motor SMTP
            $assunto = "Crie a sua senha de acesso - Pic2Pic";
            
            $mensagem = "Olá, " . htmlspecialchars($comprador['nome']) . "!\n\n";
            $mensagem .= "Notamos que adquiriu as suas fotos connosco, mas ainda não definiu uma senha para o seu painel.\n\n";
            $mensagem .= "Para criar a sua senha e baixar às suas memórias, clique no link abaixo (válida por 7 horas):\n";
            $mensagem .= $linkReset . "\n\n";
            $mensagem .= "Se a ligação expirar, basta tentar fazer login novamente para receber um novo.\n\n";
            $mensagem .= "Obrigado,\nEquipa Pic2Pic";

            // AQUI ESTÁ A CORREÇÃO DO CAMINHO:
            require_once '../configuracoes/motor_email.php';
            enviarEmailSMTP($pdo, $email, $comprador['nome'], $assunto, $mensagem);

            echo json_encode(['acao' => 'email_enviado']);
            exit;
        }
    }

    // Cenário C: E-mail não consta no banco de dados de ninguém
    echo json_encode(['acao' => 'nao_encontrado']);

} catch (Throwable $e) {
    // Retorna o erro exato caso falhe alguma coisa na base de dados
    echo json_encode(['acao' => 'erro', 'erro' => 'ERRO: ' . $e->getMessage()]);
}