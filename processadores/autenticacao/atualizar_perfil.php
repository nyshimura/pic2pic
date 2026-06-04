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
$nome = trim(filter_input(INPUT_POST, 'nome', FILTER_SANITIZE_SPECIAL_CHARS));
$email = trim(filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL));
$senhaAtual = $_POST['senha_atual'] ?? '';
$novaSenha = $_POST['nova_senha'] ?? '';

try {
    // 1. Processamento da Foto de Perfil
    $caminhoFoto = null;
    if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
        $extensoesPermitidas = ['jpg', 'jpeg', 'png', 'webp'];
        $extensao = strtolower(pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION));
        
        if (!in_array($extensao, $extensoesPermitidas)) {
            echo json_encode(['sucesso' => false, 'erro' => 'Formato de foto de perfil inválido.']);
            exit;
        }

        $diretorioDestino = '../../ativos/imagens/perfis/';
        if (!is_dir($diretorioDestino)) mkdir($diretorioDestino, 0755, true);

        $nomeArquivo = 'perfil_' . $idFotografo . '_' . time() . '.' . $extensao;
        $caminhoFinal = $diretorioDestino . $nomeArquivo;

        if (move_uploaded_file($_FILES['foto']['tmp_name'], $caminhoFinal)) {
            $stmt = $pdo->prepare("SELECT foto_perfil FROM fotografos WHERE id = ?");
            $stmt->execute([$idFotografo]);
            $fotoAntiga = $stmt->fetchColumn();
            if ($fotoAntiga && file_exists('../../' . $fotoAntiga)) unlink('../../' . $fotoAntiga);
            
            $caminhoFoto = 'ativos/imagens/perfis/' . $nomeArquivo;
            $stmt = $pdo->prepare("UPDATE fotografos SET foto_perfil = ? WHERE id = ?");
            $stmt->execute([$caminhoFoto, $idFotografo]);
            $_SESSION['fotografo_foto'] = $caminhoFoto;
        }
    }

    // 2. Processamento da Marca D'água (NOVO)
    $caminhoMarca = null;
    if (isset($_FILES['marca_dagua']) && $_FILES['marca_dagua']['error'] === UPLOAD_ERR_OK) {
        $extensaoMarca = strtolower(pathinfo($_FILES['marca_dagua']['name'], PATHINFO_EXTENSION));
        
        // Bloqueio rigoroso: Marca d'água DEVE ser PNG para manter o fundo transparente sobre as fotos
        if ($extensaoMarca !== 'png') {
            echo json_encode(['sucesso' => false, 'erro' => 'A marca d\'água precisa obrigatoriamente ser no formato PNG.']);
            exit;
        }

        $diretorioDestinoMarca = '../../ativos/imagens/marcas/';
        if (!is_dir($diretorioDestinoMarca)) mkdir($diretorioDestinoMarca, 0755, true);

        $nomeArquivoMarca = 'marca_' . $idFotografo . '_' . time() . '.png';
        $caminhoFinalMarca = $diretorioDestinoMarca . $nomeArquivoMarca;

        if (move_uploaded_file($_FILES['marca_dagua']['tmp_name'], $caminhoFinalMarca)) {
            $stmt = $pdo->prepare("SELECT marca_dagua FROM fotografos WHERE id = ?");
            $stmt->execute([$idFotografo]);
            $marcaAntiga = $stmt->fetchColumn();
            if ($marcaAntiga && file_exists('../../' . $marcaAntiga)) unlink('../../' . $marcaAntiga);
            
            $caminhoMarca = 'ativos/imagens/marcas/' . $nomeArquivoMarca;
            $stmt = $pdo->prepare("UPDATE fotografos SET marca_dagua = ? WHERE id = ?");
            $stmt->execute([$caminhoMarca, $idFotografo]);
        }
    }

    // 3. Lógica de Senha e Dados Básicos
    if (!empty($novaSenha)) {
        $stmt = $pdo->prepare("SELECT senha FROM fotografos WHERE id = ?");
        $stmt->execute([$idFotografo]);
        $senhaBanco = $stmt->fetchColumn();

        if (!password_verify($senhaAtual, $senhaBanco)) {
            echo json_encode(['sucesso' => false, 'erro' => 'Senha atual incorreta.']);
            exit;
        }
        $novaSenhaHash = password_hash($novaSenha, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE fotografos SET nome = ?, email = ?, senha = ? WHERE id = ?");
        $stmt->execute([$nome, $email, $novaSenhaHash, $idFotografo]);
    } else {
        $stmt = $pdo->prepare("UPDATE fotografos SET nome = ?, email = ? WHERE id = ?");
        $stmt->execute([$nome, $email, $idFotografo]);
    }

    $_SESSION['fotografo_nome'] = $nome;
    echo json_encode([
        'sucesso' => true, 
        'foto' => $_SESSION['fotografo_foto'] ?? null,
        'marca_dagua' => $caminhoMarca
    ]);

} catch (Exception $e) {
    echo json_encode(['sucesso' => false, 'erro' => 'Erro interno ao salvar.']);
}