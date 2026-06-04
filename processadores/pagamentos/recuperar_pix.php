<?php
declare(strict_types=1);

error_reporting(0);
ini_set('display_errors', '0');

session_start();
header('Content-Type: application/json');

// Aceita o pedido tanto se for um Cliente logado, quanto se for o Fotógrafo
if (!isset($_SESSION['comprador_id']) && !isset($_SESSION['fotografo_id'])) {
    echo json_encode(['sucesso' => false, 'erro' => 'Acesso negado.']);
    exit;
}

require_once '../../configuracoes/conexao.php';

$input = json_decode(file_get_contents('php://input'), true);
$pedidoId = intval($input['pedido_id'] ?? 0);

if ($pedidoId <= 0) {
    echo json_encode(['sucesso' => false, 'erro' => 'ID inválido.']);
    exit;
}

try {
    // 1. Descobre o e-mail exato do usuário logado baseado na sessão ativa
    $emailUsuario = '';
    if (isset($_SESSION['comprador_id'])) {
        $stmtUser = $pdo->prepare("SELECT email FROM compradores WHERE id = ?");
        $stmtUser->execute([$_SESSION['comprador_id']]);
        $emailUsuario = $stmtUser->fetchColumn();
    } else {
        $stmtUser = $pdo->prepare("SELECT email FROM fotografos WHERE id = ?");
        $stmtUser->execute([$_SESSION['fotografo_id']]);
        $emailUsuario = $stmtUser->fetchColumn();
    }

    // 2. Traz os dados do pedido apenas se o e-mail corresponder
    $stmtPedido = $pdo->prepare("
        SELECT p.status, p.mp_preference_id, p.token_download, p.nome_comprador, p.email_comprador,
               e.nome as evento_nome,
               f.mp_access_token, f.nome as fotografo_nome, f.email as fotografo_email 
        FROM pedidos p
        JOIN eventos e ON p.evento_id = e.id
        JOIN fotografos f ON e.fotografo_id = f.id
        WHERE p.id = ? AND p.email_comprador = ?
    ");
    $stmtPedido->execute([$pedidoId, $emailUsuario]);
    $pedido = $stmtPedido->fetch(PDO::FETCH_ASSOC);

    if (!$pedido || empty($pedido['mp_preference_id'])) {
        echo json_encode(['sucesso' => false, 'erro' => 'Pedido não encontrado ou sem integração.']);
        exit;
    }

    if ($pedido['status'] === 'aprovado') {
        echo json_encode(['sucesso' => true, 'status' => 'aprovado']);
        exit;
    }

    $accessTokenMP_Encriptado = $pedido['mp_access_token'];
    $chaveSecreta = defined('CHAVE_MESTRA') ? CHAVE_MESTRA : 'Pic2Pic_Seguranca_2026';
    $key = hash('sha256', $chaveSecreta, true);
    
    $dadosBase64 = base64_decode($accessTokenMP_Encriptado);
    $ivLength = openssl_cipher_iv_length('aes-256-cbc');
    $iv = substr($dadosBase64, 0, $ivLength);
    $encrypted = substr($dadosBase64, $ivLength);
    $accessTokenMP = openssl_decrypt($encrypted, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);

    if (!$accessTokenMP) {
        echo json_encode(['sucesso' => false, 'erro' => 'Falha de segurança ao aceder às chaves.']);
        exit;
    }

    $idTransacaoMP = $pedido['mp_preference_id'];
    $ch = curl_init("https://api.mercadopago.com/v1/payments/" . $idTransacaoMP);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $accessTokenMP]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $respostaMP = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $dadosMP = json_decode($respostaMP, true);

    if ($httpCode === 200 && isset($dadosMP['status'])) {
        
        if ($dadosMP['status'] === 'approved') {
            $pdo->prepare("UPDATE pedidos SET status = 'aprovado' WHERE id = ?")->execute([$pedidoId]);

            $linkEntrega = "https://" . $_SERVER['HTTP_HOST'] . "/fotos/entrega.php?token=" . $pedido['token_download'];
            $assunto = "Pagamento Aprovado! Suas fotos do catálogo: " . $pedido['evento_nome'];

            $mensagem = "Olá, " . htmlspecialchars($pedido['nome_comprador']) . "!\n\n";
            $mensagem .= "O seu pagamento via PIX foi aprovado com sucesso.\n";
            $mensagem .= "Para baixar suas fotos em alta resolução, clique no link abaixo:\n";
            $mensagem .= $linkEntrega . "\n\n";
            $mensagem .= "Obrigado,\n" . htmlspecialchars($pedido['fotografo_nome']);

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

            echo json_encode(['sucesso' => true, 'status' => 'aprovado']);
            exit;
        }

        $qrCodeCopiaCola = $dadosMP['point_of_interaction']['transaction_data']['qr_code'] ?? '';
        $qrCodeBase64 = $dadosMP['point_of_interaction']['transaction_data']['qr_code_base64'] ?? '';

        if (!empty($qrCodeBase64)) {
            echo json_encode([
                'sucesso' => true,
                'status' => 'pendente',
                'qr_code_copia_cola' => $qrCodeCopiaCola,
                'qr_code_base64' => $qrCodeBase64
            ]);
            exit;
        }
    }

    echo json_encode(['sucesso' => false, 'erro' => 'Não foi possível recuperar o PIX no Mercado Pago.']);

} catch (Throwable $e) {
    echo json_encode(['sucesso' => false, 'erro' => 'Falha interna: ' . $e->getMessage()]);
}