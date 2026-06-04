<?php
declare(strict_types=1);
session_start();
require_once '../../configuracoes/conexao.php';

header('Content-Type: application/json');

// Proteção de acesso
if (!isset($_SESSION['fotografo_id'])) {
    echo json_encode(['sucesso' => false, 'erro' => 'Acesso negado.']);
    exit;
}

$idEvento = intval($_GET['id_evento'] ?? 0);
$idFotografo = $_SESSION['fotografo_id'];

if ($idEvento <= 0) {
    echo json_encode(['sucesso' => false, 'erro' => 'ID inválido.']);
    exit;
}

try {
    // Verifica se o evento pertence a este fotógrafo por segurança
    $stmtCheck = $pdo->prepare("SELECT id FROM eventos WHERE id = ? AND fotografo_id = ?");
    $stmtCheck->execute([$idEvento, $idFotografo]);
    if (!$stmtCheck->fetch()) {
        echo json_encode(['sucesso' => false, 'erro' => 'Evento não autorizado.']);
        exit;
    }

    // Busca as fotos no banco
    $stmt = $pdo->prepare("SELECT id, encoding_facial FROM fotos_eventos WHERE evento_id = ? ORDER BY id DESC");
    $stmt->execute([$idEvento]);
    $fotos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $resultado = [];
    foreach ($fotos as $f) {
        // --- AS NOVAS MENSAGENS AMIGÁVEIS ---
        $status = 'erro';
        $motivo = 'Ambiente / Sem rosto'; // Substituiu o "Sem rosto (NULL)"
        
        // Avalia a saúde da matemática salva no banco
        if ($f['encoding_facial'] !== null) {
            $arr = json_decode($f['encoding_facial'], true);
            if (is_array($arr) && count($arr) > 0) {
                $status = 'ok';
                $motivo = count($arr) . ' Rosto(s) mapeado(s)'; // Ficou mais profissional
            } else {
                $motivo = 'Falha na leitura'; // Caso o JSON esteja corrompido
            }
        }

        $resultado[] = [
            'id' => $f['id'],
            'url' => 'processadores/imagens/proxy_imagem.php?f=' . $f['id'],
            'status' => $status,
            'motivo' => $motivo
        ];
    }

    echo json_encode(['sucesso' => true, 'fotos' => $resultado]);
} catch (Exception $e) {
    echo json_encode(['sucesso' => false, 'erro' => 'Erro ao buscar fotos.']);
}