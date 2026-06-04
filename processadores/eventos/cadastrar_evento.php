<?php
declare(strict_types=1);
session_start();
require_once '../../configuracoes/conexao.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_SESSION['fotografo_id'])) {
    http_response_code(401);
    echo json_encode(['sucesso' => false, 'erro' => 'Acesso negado.']);
    exit;
}

$idFotografo = $_SESSION['fotografo_id'];

// Captura os dados (aceitando tanto o padrão novo do JS quanto o antigo)
$nomeEvento = trim(filter_input(INPUT_POST, 'nome', FILTER_SANITIZE_SPECIAL_CHARS) ?: filter_input(INPUT_POST, 'nome_evento', FILTER_SANITIZE_SPECIAL_CHARS) ?: '');
$categoria = trim(filter_input(INPUT_POST, 'categoria', FILTER_SANITIZE_SPECIAL_CHARS) ?? 'Outros'); // <-- NOVA COLUNA
$driveInput = trim($_POST['drive_folder'] ?? '');
$precoFoto = floatval($_POST['preco'] ?? $_POST['preco_foto'] ?? 0.00);
$expiraEm = $_POST['expira_em'] ?? '';

// Fallback de segurança para a categoria
if (empty($categoria)) {
    $categoria = 'Outros';
}

if (empty($nomeEvento) || empty($driveInput) || empty($expiraEm)) {
    http_response_code(400);
    echo json_encode(['sucesso' => false, 'erro' => 'Preencha todos os campos obrigatórios.']);
    exit;
}

// Extrator inteligente do ID do Google Drive via Regex
// Captura o padrão de hash do Google de dentro do link de compartilhamento
$folderId = $driveInput;
if (preg_match('/folders\/([a-zA-Z0-9-_]+)/', $driveInput, $matches)) {
    $folderId = $matches[1];
}

try {
    // Cria um token único alfanumérico aleatório de 8 caracteres para a URL do catálogo
    // Ficará algo como: seusite.com/fotos/index.php?e=a3f92b8d
    $tokenUrl = substr(md5(uniqid((string)rand(), true)), 0, 8);

    // ==========================================
    // INSERÇÃO NO BANCO DE DADOS (AGORA COM A CATEGORIA)
    // ==========================================
    $stmt = $pdo->prepare("INSERT INTO eventos (fotografo_id, nome, categoria, token_url, drive_folder_id, preco_foto, expira_em) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$idFotografo, $nomeEvento, $categoria, $tokenUrl, $folderId, $precoFoto, $expiraEm]);

    // Opcional: Grava no Log que o evento foi criado
    $idNovoEvento = $pdo->lastInsertId();
    if ($idNovoEvento) {
        $stmtLog = $pdo->prepare("INSERT INTO logs_eventos (fotografo_id, evento_id, descricao) VALUES (?, ?, ?)");
        $stmtLog->execute([$idFotografo, $idNovoEvento, "✨ Catálogo criado na categoria '{$categoria}'"]);
    }

    echo json_encode(['sucesso' => true]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['sucesso' => false, 'erro' => 'Erro ao criar evento. Verifique se os dados estão corretos.']);
}