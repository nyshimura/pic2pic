<?php
declare(strict_types=1);

// Oculta erros para não quebrar a resposta que o Mercado Pago espera
error_reporting(0);
ini_set('display_errors', '0');

// 1. Recebe a requisição (o "sussurro") do Mercado Pago
$input = json_decode(file_get_contents('php://input'), true);

// Verifica se o Mercado Pago enviou o ID da transação
if (!isset($input['data']['id'])) {
    http_response_code(200); // Dizemos "OK" para o MP parar de insistir se for outro tipo de notificação
    exit;
}

$idTransacaoMP = $input['data']['id'];

// A conexão com o banco fica na raiz, por isso precisa de ../../
require_once '../../configuracoes/conexao.php';

try {
    // 2. Detetive: Procura no NOSSO banco qual é o pedido e o fotógrafo dono deste pagamento
    $stmt = $pdo->prepare("
        SELECT p.id as pedido_id, p.status as status_pedido, p.email_comprador, p.nome_comprador, p.token_download,
               e.nome as evento_nome, f.mp_access_token, f.email as fotografo_email, f.nome as fotografo_nome
        FROM pedidos p
        JOIN eventos e ON p.evento_id = e.id
        JOIN fotografos f ON e.fotografo_id = f.id
        WHERE p.mp_preference_id = ?
    ");
    $stmt->execute([$idTransacaoMP]);
    $pedido = $stmt->fetch(PDO::FETCH_ASSOC);

    // Se não acharmos o pedido ou se já estiver aprovado, encerramos a chamada
    if (!$pedido || $pedido['status_pedido'] === 'aprovado') {
        http_response_code(200); 
        exit;
    }

    // 3. Desencripta a chave do Fotógrafo para perguntar ao MP sobre este pagamento
    $accessTokenMP_Encriptado = $pedido['mp_access_token'];
    $chaveSecreta = defined('CHAVE_MESTRA') ? CHAVE_MESTRA : 'Pic2Pic_Seguranca_2026';
    $key = hash('sha256', $chaveSecreta, true);
    
    $dadosBase64 = base64_decode($accessTokenMP_Encriptado);
    $ivLength = openssl_cipher_iv_length('aes-256-cbc');
    $iv = substr($dadosBase64, 0, $ivLength);
    $encrypted = substr($dadosBase64, $ivLength);
    $accessTokenMP = openssl_decrypt($encrypted, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);

    if (!$accessTokenMP) {
        http_response_code(200); exit;
    }

    // 4. BLINDAGEM MÁXIMA: Vamos bater na porta do MP para confirmar se o pagamento realmente existe
    $ch = curl_init("https://api.mercadopago.com/v1/payments/" . $idTransacaoMP);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $accessTokenMP]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $respostaMP = curl_exec($ch);
    curl_close($ch);

    $dadosMP = json_decode($respostaMP, true);

    // 5. O Veredito! Se estiver aprovado, libera o sistema
    if (isset($dadosMP['status']) && $dadosMP['status'] === 'approved') {
        
        // Atualiza a base de dados
        $pdo->prepare("UPDATE pedidos SET status = 'aprovado' WHERE id = ?")->execute([$pedido['pedido_id']]);

        // Prepara e dispara o E-mail de Entrega
        $linkEntrega = "https://" . $_SERVER['HTTP_HOST'] . "/fotos/entrega.php?token=" . $pedido['token_download'];
        $assunto = "Pagamento Aprovado! Suas fotos do catálogo: " . $pedido['evento_nome'];

        $mensagem = "Olá, " . htmlspecialchars($pedido['nome_comprador']) . "!\n\n";
        $mensagem .= "O seu pagamento via PIX foi aprovado com sucesso.\n";
        $mensagem .= "Para baixar suas fotos em alta resolução, clique no link abaixo:\n";
        $mensagem .= $linkEntrega . "\n\n";
        $mensagem .= "Obrigado,\n" . htmlspecialchars($pedido['fotografo_nome']);

        // AQUI ESTÁ A CORREÇÃO: Apenas um nível para trás para entrar na pasta configuracoes
        require_once '../configuracoes/motor_email.php';
        
        enviarEmailSMTP(
            $pdo, 
            $pedido['email_comprador'], 
            $pedido['nome_comprador'], 
            $assunto, 
            $mensagem, 
            $pedido['fotografo_email'], 
            $pedido['fotografo_nome']
        );
    }

    // O Mercado Pago exige receber o status 200 (OK) no final
    http_response_code(200);
    exit;

} catch (Throwable $e) {
    http_response_code(200); 
    exit;
}