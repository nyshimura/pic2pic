<?php
declare(strict_types=1);

error_reporting(0);
ini_set('display_errors', '0');

session_start();
header('Content-Type: application/json');
require_once '../../configuracoes/conexao.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_SESSION['fotografo_id'])) {
    http_response_code(401);
    echo json_encode(['sucesso' => false, 'erro' => 'Acesso negado.']);
    exit;
}

$id = intval($_POST['id'] ?? 0);
$novoNome = trim($_POST['nome'] ?? '');
$novaCategoria = trim($_POST['categoria'] ?? 'Outros'); // <-- ADICIONADO AQUI
$novoDriveFolder = trim($_POST['drive_folder'] ?? '');
$novoPreco = $_POST['preco'] ?? '';
$novoExpiraEm = $_POST['expira_em'] ?? '';
$idFotografo = $_SESSION['fotografo_id'];

// Usa strlen no preco para aceitar "0.00" sem acusar erro de vazio
if ($id <= 0 || empty($novoNome) || empty($novoDriveFolder) || strlen((string)$novoPreco) === 0 || empty($novoExpiraEm)) {
    echo json_encode(['sucesso' => false, 'erro' => 'Por favor, preencha todos os campos obrigatórios.']);
    exit;
}

// Fallback de segurança para a categoria
if (empty($novaCategoria)) {
    $novaCategoria = 'Outros';
}

try {
    // 1. BUSCAR OS DADOS ANTIGOS ANTES DE ATUALIZAR (Adicionado 'categoria' no SELECT)
    $stmtBusca = $pdo->prepare("SELECT nome, categoria, drive_folder_id, preco_foto, expira_em FROM eventos WHERE id = ? AND fotografo_id = ?");
    $stmtBusca->execute([$id, $idFotografo]);
    $eventoAntigo = $stmtBusca->fetch(PDO::FETCH_ASSOC);

    if (!$eventoAntigo) {
        echo json_encode(['sucesso' => false, 'erro' => 'Catálogo não encontrado.']);
        exit;
    }

    // 2. MÁQUINA DE COMPARAÇÃO (O QUE MUDOU?)
    $alteracoes = [];

    // Compara o Nome
    if ($eventoAntigo['nome'] !== $novoNome) {
        $alteracoes[] = "Nome: de '{$eventoAntigo['nome']}' para '{$novoNome}'";
    }

    // Compara a Categoria <-- ADICIONADO AQUI PARA O LOG
    $categoriaAntiga = $eventoAntigo['categoria'] ?? 'Outros';
    if ($categoriaAntiga !== $novaCategoria) {
        $alteracoes[] = "Categoria: de '{$categoriaAntiga}' para '{$novaCategoria}'";
    }

    // Compara o ID do Drive
    if ($eventoAntigo['drive_folder_id'] !== $novoDriveFolder) {
        $alteracoes[] = "ID do Drive alterado";
    }

    // Compara o Preço (usando conversão para decimais para evitar falsos positivos)
    $precoAntigoFloat = floatval($eventoAntigo['preco_foto']);
    $novoPrecoFloat = floatval($novoPreco);
    if (abs($precoAntigoFloat - $novoPrecoFloat) > 0.001) {
        $strPrecoAntigo = number_format($precoAntigoFloat, 2, ',', '.');
        $strPrecoNovo = number_format($novoPrecoFloat, 2, ',', '.');
        $alteracoes[] = "Preço: de R$ {$strPrecoAntigo} para R$ {$strPrecoNovo}";
    }

    // Compara a Data de Expiração (padronizando os formatos antes de comparar)
    $dataAntigaStr = date('Y-m-d H:i', strtotime($eventoAntigo['expira_em']));
    $dataNovaStr = date('Y-m-d H:i', strtotime($novoExpiraEm));
    if ($dataAntigaStr !== $dataNovaStr) {
        $strDataAntiga = date('d/m/Y H:i', strtotime($eventoAntigo['expira_em']));
        $strDataNova = date('d/m/Y H:i', strtotime($novoExpiraEm));
        $alteracoes[] = "Expiração: de {$strDataAntiga} para {$strDataNova}";
    }

    // 3. ATUALIZA A BASE DE DADOS (Adicionado 'categoria = ?' e '$novaCategoria')
    $stmt = $pdo->prepare("UPDATE eventos SET nome = ?, categoria = ?, drive_folder_id = ?, preco_foto = ?, expira_em = ? WHERE id = ? AND fotografo_id = ?");
    $stmt->execute([$novoNome, $novaCategoria, $novoDriveFolder, $novoPrecoFloat, $novoExpiraEm, $id, $idFotografo]);
    
    // 4. GRAVA O LOG APENAS SE HOUVERAM ALTERAÇÕES
    if (!empty($alteracoes)) {
        // Junta todas as alterações numa linha bonita separada por barras (|)
        $mensagemLog = "✏️ Catálogo editado ➔ " . implode(" | ", $alteracoes);
        
        $stmtLog = $pdo->prepare("INSERT INTO logs_eventos (fotografo_id, evento_id, descricao) VALUES (?, ?, ?)");
        $stmtLog->execute([$idFotografo, $id, $mensagemLog]);
    }
    
    echo json_encode(['sucesso' => true]);
} catch (Throwable $e) {
    echo json_encode(['sucesso' => false, 'erro' => 'Falha no banco de dados.']);
}