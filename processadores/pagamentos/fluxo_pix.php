<?php
// O Roteador já passou todas as variáveis necessárias para cá

$accessTokenMP_Encriptado = $evento['mp_access_token'];

if (empty($accessTokenMP_Encriptado)) {
    echo json_encode(['sucesso' => false, 'erro' => 'O fotógrafo responsável ainda não configurou uma chave válida do Mercado Pago.']);
    exit;
}

// ==========================================
// 1. DESENCRIPTAÇÃO DO COFRE AES-256
// ==========================================
$chaveSecreta = defined('CHAVE_MESTRA') ? CHAVE_MESTRA : 'Pic2Pic_Seguranca_2026';
$key = hash('sha256', $chaveSecreta, true);

$dadosBase64 = base64_decode($accessTokenMP_Encriptado);
$ivLength = openssl_cipher_iv_length('aes-256-cbc');
$iv = substr($dadosBase64, 0, $ivLength);
$encrypted = substr($dadosBase64, $ivLength);

$accessTokenMP = openssl_decrypt($encrypted, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);

if (!$accessTokenMP) {
    echo json_encode(['sucesso' => false, 'erro' => 'Erro interno de segurança. Não foi possível destrancar a chave do Mercado Pago.']);
    exit;
}

// ==========================================
// 2. GRAVA O PEDIDO COMO PENDENTE (AGUARDANDO PAGAMENTO)
// ==========================================
$stmtPedido = $pdo->prepare("INSERT INTO pedidos (evento_id, email_comprador, nome_comprador, cpf_comprador, valor_total, status, token_download) VALUES (?, ?, ?, ?, ?, 'pendente', ?)");
$stmtPedido->execute([$idEvento, $clienteEmail, $clienteNome, $clienteCpf, $valorTotal, $tokenDownload]);
$idPedido = $pdo->lastInsertId();

$stmtItem = $pdo->prepare("INSERT INTO pedidos_fotos (pedido_id, foto_id) VALUES (?, ?)");
foreach ($fotosIds as $idF) { 
    $stmtItem->execute([$idPedido, intval($idF)]); 
}

// ==========================================
// 3. INTEGRAÇÃO API MERCADO PAGO (GERAÇÃO DO PIX)
// ==========================================
$urlMP = 'https://api.mercadopago.com/v1/payments';

// A chave de idempotência garante que um clique duplo não gere duas cobranças
$idempotencyKey = "Pic2Pic_Pedido_" . $idPedido . "_" . time(); 

$dataMP = [
    "transaction_amount" => floatval($valorTotal),
    "description" => "Fotos do Catálogo: " . $evento['nome'],
    "payment_method_id" => "pix",
    "payer" => [
        "email" => $clienteEmail,
        "first_name" => $clienteNome,
        "identification" => [
            "type" => "CPF",
            "number" => $clienteCpf // O Roteador já removeu os pontos e traços
        ]
    ],
    // O external_reference é crucial! É com ele que o Webhook saberá qual pedido aprovar depois
    "external_reference" => (string)$idPedido 
];

$ch = curl_init($urlMP);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $accessTokenMP, 
    'Content-Type: application/json',
    'X-Idempotency-Key: ' . $idempotencyKey
]);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($dataMP));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

$responseMP = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$resMP = json_decode($responseMP, true);

// ==========================================
// 4. AVALIA A RESPOSTA E DEVOLVE O QR CODE AO CARRINHO
// ==========================================
if ($httpCode === 201 || $httpCode === 200) {
    // Extrai as "Joias" da resposta do Mercado Pago
    $idTransacaoMP = $resMP['id'];
    $qrCodeCopiaCola = $resMP['point_of_interaction']['transaction_data']['qr_code'];
    $qrCodeBase64 = $resMP['point_of_interaction']['transaction_data']['qr_code_base64'];

    // Grava o ID da Transação do MP no nosso banco para que o Webhook encontre depois
    $pdo->prepare("UPDATE pedidos SET mp_preference_id = ? WHERE id = ?")->execute([$idTransacaoMP, $idPedido]);
    
    // --- NOVO: DISPARO DO E-MAIL COM O PIX COPIA E COLA ---
    $assunto = "Aguardando Pagamento - Catálogo: " . $evento['nome'];

    $mensagem = "Olá, " . htmlspecialchars($clienteNome) . "!\n\n";
    $mensagem .= "Recebemos o seu pedido. Para liberar as suas fotos, realize o pagamento via PIX utilizando o código Copia e Cola abaixo:\n\n";
    $mensagem .= $qrCodeCopiaCola . "\n\n";
    $mensagem .= "Assim que o pagamento for confirmado, enviaremos um novo e-mail com a link para download as suas memórias em alta resolução.\n\n";
    $mensagem .= "Obrigado,\n" . htmlspecialchars($evento['fotografo_nome']);

    require_once '../configuracoes/motor_email.php';
    enviarEmailSMTP($pdo, $clienteEmail, $clienteNome, $assunto, $mensagem, $evento['fotografo_email'], $evento['fotografo_nome']);
    // ------------------------------------------------------
    
    // Entrega o JSON mastigado para o Javascript exibir a tela bonita
    echo json_encode([
        'sucesso' => true, 
        'is_gratis' => false, 
        'qr_code_copia_cola' => $qrCodeCopiaCola,
        'qr_code_base64' => $qrCodeBase64,
        'pedido_id' => $idPedido
    ]);
    exit;

} else {
    // Se a API recusar (ex: CPF inválido ou erro no servidor deles)
    $mensagemErro = $resMP['message'] ?? 'Falha de comunicação com os servidores do Mercado Pago.';
    echo json_encode(['sucesso' => false, 'erro' => "Aviso do Mercado Pago: " . $mensagemErro]);
    exit;
}