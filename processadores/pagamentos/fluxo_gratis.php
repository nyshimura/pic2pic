<?php
// O Roteador já passou todas as variáveis necessárias para cá

$stmtPedido = $pdo->prepare("INSERT INTO pedidos (evento_id, email_comprador, nome_comprador, cpf_comprador, valor_total, status, token_download) VALUES (?, ?, ?, ?, ?, 'aprovado', ?)");
$stmtPedido->execute([$idEvento, $clienteEmail, $clienteNome, $clienteCpf, 0, $tokenDownload]);
$idPedido = $pdo->lastInsertId();

$stmtItem = $pdo->prepare("INSERT INTO pedidos_fotos (pedido_id, foto_id) VALUES (?, ?)");
foreach ($fotosIds as $idF) { 
    $stmtItem->execute([$idPedido, intval($idF)]); 
}

// --- DISPARO DO E-MAIL DE ENTREGA COM MÁSCARA (REPLY-TO) ---
$linkEntrega = "https://" . $_SERVER['HTTP_HOST'] . "/fotos/entrega.php?token=" . $tokenDownload;
$assunto = "As suas fotos do catálogo: " . $evento['nome'];

$mensagem = "Olá, " . htmlspecialchars($clienteNome) . "!\n\n";
$mensagem .= "O seu pedido foi processado com sucesso.\n";
$mensagem .= "Para baixar as suas fotos em alta resolução, clique no link abaixo:\n";
$mensagem .= $linkEntrega . "\n\n";
$mensagem .= "Obrigado,\n" . htmlspecialchars($evento['fotografo_nome']);

// Usa o motor SMTP profissional (Caminho corrigido para ../)
require_once '../configuracoes/motor_email.php';
enviarEmailSMTP(
    $pdo, 
    $clienteEmail, 
    $clienteNome, 
    $assunto, 
    $mensagem, 
    $evento['fotografo_email'], 
    $evento['fotografo_nome']
);
// ----------------------------------------------

echo json_encode(['sucesso' => true, 'is_gratis' => true, 'token_download' => $tokenDownload, 'msg' => 'Pedido grátis aprovado!']);
exit;