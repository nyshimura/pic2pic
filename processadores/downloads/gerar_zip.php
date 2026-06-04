<?php
declare(strict_types=1);

// Proteção máxima para não dar erro de "Tempo Limite" ou "Memória Cheia" ao gerar ZIPs gigantes
set_time_limit(0);
ini_set('memory_limit', '2048M');
error_reporting(0);
ini_set('display_errors', '0');

require_once '../../configuracoes/conexao.php';

// 1. Recebe e valida o Token do Pedido
$token = isset($_GET['token']) ? preg_replace('/[^a-zA-Z0-9]/', '', $_GET['token']) : '';

if (empty($token)) {
    die("Acesso inválido.");
}

try {
    // 2. Verifica o pedido e a expiração do evento
    $stmtPedido = $pdo->prepare("
        SELECT p.id, p.status, e.nome as evento_nome, e.expira_em 
        FROM pedidos p
        JOIN eventos e ON p.evento_id = e.id
        WHERE p.token_download = ?
    ");
    $stmtPedido->execute([$token]);
    $pedido = $stmtPedido->fetch(PDO::FETCH_ASSOC);

    if (!$pedido || $pedido['status'] !== 'aprovado') {
        die("Este pedido não existe ou ainda não teve o pagamento aprovado.");
    }

    if (strtotime($pedido['expira_em']) < time()) {
        die("O prazo de validade para descarregar os ficheiros deste catálogo expirou.");
    }

    $idPedido = $pedido['id'];
    $nomeEventoLimpo = preg_replace('/[^a-zA-Z0-9]/', '_', $pedido['evento_nome']);
    $nomeArquivoZip = "Pic2Pic_" . $nomeEventoLimpo . "_Pedido_" . $idPedido . ".zip";

    // 3. Busca a lista de fotos
    $stmtFotos = $pdo->prepare("
        SELECT fe.id, fe.drive_file_id 
        FROM pedidos_fotos pf
        JOIN fotos_eventos fe ON pf.foto_id = fe.id
        WHERE pf.pedido_id = ?
    ");
    $stmtFotos->execute([$idPedido]);
    $fotos = $stmtFotos->fetchAll(PDO::FETCH_ASSOC);

    if (empty($fotos)) {
        die("Nenhuma foto encontrada neste pedido.");
    }

    // ====================================================================
    // 4. AUTENTICAÇÃO GOOGLE DRIVE (A mesma arquitetura do seu sistema!)
    // ====================================================================
    $jsonPath = '../../credenciais/google_drive.json';
    if (!file_exists($jsonPath)) { 
        die("Erro Crítico: Ficheiro de credenciais JSON do Google ausente."); 
    }

    $keyInfo = json_decode(file_get_contents($jsonPath), true);
    $header = json_encode(['alg' => 'RS256', 'typ' => 'JWT']);
    $payload = json_encode([
        'iss' => $keyInfo['client_email'], 
        'scope' => 'https://www.googleapis.com/auth/drive.readonly', 
        'aud' => $keyInfo['token_uri'], 
        'exp' => time() + 3600, 
        'iat' => time()
    ]);

    $base64UrlHeader = str_replace(['+', '/', '=', "\n", "\r"], ['-', '_', '', '', ''], base64_encode($header));
    $base64UrlPayload = str_replace(['+', '/', '=', "\n", "\r"], ['-', '_', '', '', ''], base64_encode($payload));
    openssl_sign($base64UrlHeader . "." . $base64UrlPayload, $signature, $keyInfo['private_key'], "SHA256");
    $jwt = $base64UrlHeader . "." . $base64UrlPayload . "." . str_replace(['+', '/', '=', "\n", "\r"], ['-', '_', '', '', ''], base64_encode($signature));

    $chAuth = curl_init($keyInfo['token_uri']);
    curl_setopt($chAuth, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($chAuth, CURLOPT_POST, true);
    curl_setopt($chAuth, CURLOPT_POSTFIELDS, http_build_query(['grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer', 'assertion' => $jwt]));
    $authData = json_decode(curl_exec($chAuth), true);
    curl_close($chAuth);
    
    $accessToken = $authData['access_token'] ?? null;

    if (!$accessToken) { 
        die("Falha na comunicação de segurança com o Google Cloud."); 
    }

    // ====================================================================
    // 5. MOTOR DE DOWNLOAD E EMPACOTAMENTO
    // ====================================================================
    $caminhoZipTemp = tempnam(sys_get_temp_dir(), 'pic2pic_zip_');
    $zip = new ZipArchive();
    
    if ($zip->open($caminhoZipTemp, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        die("Erro fatal: Não foi possível iniciar a criação do ZIP no servidor.");
    }

    $ficheirosTemporarios = []; // Guarda o rastro para apagarmos depois

    foreach ($fotos as $foto) {
        $fileId = $foto['drive_file_id'];
        $nomeFinalDaFoto = "Foto_" . str_pad((string)$foto['id'], 4, "0", STR_PAD_LEFT) . ".jpg"; // Ex: Foto_0012.jpg
        
        // Ficheiro temporário local para proteger a memória RAM
        $tmpImg = tempnam(sys_get_temp_dir(), 'p2p_img_');
        $ficheirosTemporarios[] = $tmpImg; 
        
        $fp = fopen($tmpImg, 'w+');

        // Pede a foto original em alta resolução (alt=media)
        $driveApiUrl = "https://www.googleapis.com/drive/v3/files/{$fileId}?alt=media";
        
        $chFile = curl_init($driveApiUrl);
        curl_setopt($chFile, CURLOPT_FILE, $fp); // Despeja o download direto no ficheiro temporário
        curl_setopt($chFile, CURLOPT_HTTPHEADER, ["Authorization: Bearer $accessToken"]);
        curl_setopt($chFile, CURLOPT_FOLLOWLOCATION, true); // Vital para a Google (redirecionamentos de download)
        
        curl_exec($chFile);
        $httpCode = curl_getinfo($chFile, CURLINFO_HTTP_CODE);
        curl_close($chFile);
        fclose($fp);

        // Injeta no ZIP
        if ($httpCode === 200 && filesize($tmpImg) > 0) {
            $zip->addFile($tmpImg, $nomeFinalDaFoto);
        } else {
            // Se a foto foi apagada do Drive, envia um aviso no lugar dela
            $zip->addFromString("ERRO_" . $nomeFinalDaFoto . ".txt", "Aviso: Nao foi possivel baixar esta foto do Google Drive. Ela pode ter sido removida da nuvem.");
        }
    }

    // Fechar o ZIP consolida-o no disco e liberta os ficheiros anexados
    $zip->close();

    // Limpeza de primavera: Apaga todas as fotos originais soltas do disco
    foreach ($ficheirosTemporarios as $t) {
        @unlink($t);
    }

    // ====================================================================
    // 6. ENTREGA FINAL AO CLIENTE
    // ====================================================================
    if (file_exists($caminhoZipTemp)) {
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . $nomeArquivoZip . '"');
        header('Content-Length: ' . filesize($caminhoZipTemp));
        header('Pragma: no-cache');
        header('Expires: 0');

        // Empurra o ZIP para a máquina do cliente
        readfile($caminhoZipTemp);

        // Apaga o ZIP do servidor Hostinger após a entrega para poupar espaço
        @unlink($caminhoZipTemp);
        exit;
    } else {
        die("Falha na compactação dos ficheiros.");
    }

} catch (Exception $e) {
    die("Erro interno do servidor: " . $e->getMessage());
}