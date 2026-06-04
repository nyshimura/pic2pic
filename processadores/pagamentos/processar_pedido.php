<?php
declare(strict_types=1);

error_reporting(0);
ini_set('display_errors', '0');

session_start();
date_default_timezone_set('America/Sao_Paulo');
header('Content-Type: application/json');

require_once '../../configuracoes/conexao.php';

// 1. Recebe e decodifica o JSON enviado pelo JavaScript
$input = json_decode(file_get_contents('php://input'), true);
$tokenEvento = $input['token_evento'] ?? '';
$fotosIds = $input['fotos'] ?? [];
$clienteNome = trim($input['cliente_nome'] ?? '');
$clienteEmail = trim($input['cliente_email'] ?? '');
$clienteCpf = preg_replace('/[^0-9]/', '', $input['cliente_cpf'] ?? '');

if (empty($tokenEvento) || empty($fotosIds) || !is_array($fotosIds)) {
    echo json_encode(['sucesso' => false, 'erro' => 'Carrinho vazio ou dados inválidos.']);
    exit;
}

if (empty($clienteNome) || empty($clienteEmail) || empty($clienteCpf)) {
    echo json_encode(['sucesso' => false, 'erro' => 'Nome, E-mail e CPF são obrigatórios para a cobrança.']);
    exit;
}

try {
    // 2. Busca os dados do evento e puxa o NOME e E-MAIL do Fotógrafo
    $stmtEv = $pdo->prepare("
        SELECT e.id, e.nome, e.preco_foto, 
               f.mp_access_token, f.email as fotografo_email, f.nome as fotografo_nome 
        FROM eventos e
        JOIN fotografos f ON e.fotografo_id = f.id
        WHERE e.token_url = ? AND e.ativo = 1
    ");
    $stmtEv->execute([$tokenEvento]);
    $evento = $stmtEv->fetch(PDO::FETCH_ASSOC);

    if (!$evento) {
        echo json_encode(['sucesso' => false, 'erro' => 'Catálogo não encontrado ou expirado.']);
        exit;
    }

    $idEvento = $evento['id'];
    $precoUnitario = floatval($evento['preco_foto']);
    $qtdFotos = count($fotosIds);
    $valorTotal = $precoUnitario * $qtdFotos;

    // 3. ESTRUTURA DO PRÉ-CADASTRO (SHADOW ACCOUNT)
    $stmtVerifica = $pdo->prepare("SELECT id FROM compradores WHERE email = ?");
    $stmtVerifica->execute([$clienteEmail]);
    $clienteId = $stmtVerifica->fetchColumn();

    if (!$clienteId) {
        $stmtInsertCli = $pdo->prepare("INSERT INTO compradores (nome, email, cpf, senha) VALUES (?, ?, ?, NULL)");
        $stmtInsertCli->execute([$clienteNome, $clienteEmail, $clienteCpf]);
        $clienteId = $pdo->lastInsertId();
    } else {
        $pdo->prepare("UPDATE compradores SET cpf = ?, nome = ? WHERE id = ? AND (cpf IS NULL OR cpf = '')")->execute([$clienteCpf, $clienteNome, $clienteId]);
    }

    $tokenDownload = bin2hex(random_bytes(16));

    // ==========================================
    // ROTEAMENTO FÍSICO DOS FLUXOS
    // ==========================================
    if ($valorTotal == 0) {
        // Fluxo Seguro (Grátis)
        require_once 'fluxo_gratis.php';
    } else {
        // Fluxo PIX Transparente
        require_once 'fluxo_pix.php';
    }

} catch (Throwable $e) {
    echo json_encode(['sucesso' => false, 'erro' => 'Erro interno de servidor: ' . $e->getMessage()]);
}