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

try {
    if (isset($_FILES['marca_dagua']) && $_FILES['marca_dagua']['error'] === UPLOAD_ERR_OK) {
        $extensao = strtolower(pathinfo($_FILES['marca_dagua']['name'], PATHINFO_EXTENSION));

        if ($extensao !== 'png') {
            echo json_encode(['sucesso' => false, 'erro' => 'A marca d\'água precisa ser obrigatoriamente um arquivo PNG.']);
            exit;
        }

        $diretorio = '../../ativos/imagens/marcas/';
        if (!is_dir($diretorio)) mkdir($diretorio, 0755, true);

        $nomeArquivo = 'marca_' . $idFotografo . '_' . time() . '.png';
        $caminhoFinal = $diretorio . $nomeArquivo;

        if (move_uploaded_file($_FILES['marca_dagua']['tmp_name'], $caminhoFinal)) {
            // Apaga a antiga do servidor para economizar espaço na Hostinger
            $stmt = $pdo->prepare("SELECT marca_dagua FROM fotografos WHERE id = ?");
            $stmt->execute([$idFotografo]);
            $marcaAntiga = $stmt->fetchColumn();
            if ($marcaAntiga && file_exists('../../' . $marcaAntiga)) unlink('../../' . $marcaAntiga);

            $caminhoMarca = 'ativos/imagens/marcas/' . $nomeArquivo;
            $stmt = $pdo->prepare("UPDATE fotografos SET marca_dagua = ? WHERE id = ?");
            $stmt->execute([$caminhoMarca, $idFotografo]);
            
            echo json_encode(['sucesso' => true, 'marca_dagua' => $caminhoMarca]);
            exit;
        }
    }
    
    echo json_encode(['sucesso' => false, 'erro' => 'Nenhum arquivo de imagem válido foi recebido.']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['sucesso' => false, 'erro' => 'Erro interno ao salvar a marca d\'água no servidor.']);
}