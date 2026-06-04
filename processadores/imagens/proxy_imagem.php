<?php
declare(strict_types=1);
session_start();
require_once '../../configuracoes/conexao.php';

$idFoto = intval($_GET['f'] ?? 0);

if ($idFoto <= 0) exit;

try {
    // 1. Busca o ID do arquivo no Drive e o caminho da Marca de Água do Fotógrafo
    $stmt = $pdo->prepare("
        SELECT fe.drive_file_id, f.marca_dagua 
        FROM fotos_eventos fe
        JOIN eventos e ON fe.evento_id = e.id
        JOIN fotografos f ON e.fotografo_id = f.id
        WHERE fe.id = ? AND e.ativo = 1
    ");
    $stmt->execute([$idFoto]);
    $dados = $stmt->fetch();

    if (!$dados) exit;

    $fileId = $dados['drive_file_id'];
    $marcaDaguaCaminho = $dados['marca_dagua'];

    // =========================================================
    // ⚡ MOTOR DE CACHE FÍSICO + CACHE DO NAVEGADOR (30 DIAS)
    // =========================================================
    $diretorioCache = '../../uploads/cache_fotos/';
    $arquivoCache = $diretorioCache . 'foto_' . $idFoto . '.jpg';
    $tempoCache = 2592000; // 30 dias em segundos

    // Se a foto já foi carimbada antes, entrega instantaneamente do disco!
    if (file_exists($arquivoCache)) {
        header('Content-Type: image/jpeg');
        header('Cache-Control: public, max-age=' . $tempoCache);
        header('Expires: ' . gmdate('D, d M Y H:i:s', time() + $tempoCache) . ' GMT');
        header('Pragma: cache');
        readfile($arquivoCache);
        exit;
    }

    // Cria a pasta de cache se for a primeira vez
    if (!is_dir($diretorioCache)) {
        mkdir($diretorioCache, 0755, true);
    }
    // =========================================================

    // 2. Autenticação na API do Google Drive
    $accessToken = $_SESSION['google_drive_token'] ?? null;
    $tokenExpira = $_SESSION['google_drive_token_exp'] ?? 0;

    if (!$accessToken || time() >= $tokenExpira) {
        $jsonPath = '../../credenciais/google_drive.json';
        if (!file_exists($jsonPath)) exit;

        $keyInfo = json_decode(file_get_contents($jsonPath), true);
        $header = json_encode(['alg' => 'RS256', 'typ' => 'JWT']);
        $now = time();
        $payload = json_encode([
            'iss' => $keyInfo['client_email'],
            'scope' => 'https://www.googleapis.com/auth/drive.readonly',
            'aud' => $keyInfo['token_uri'],
            'exp' => $now + 3600,
            'iat' => $now
        ]);

        $base64UrlHeader = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($header));
        $base64UrlPayload = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($payload));
        openssl_sign($base64UrlHeader . "." . $base64UrlPayload, $signature, $keyInfo['private_key'], "SHA256");
        $jwt = $base64UrlHeader . "." . $base64UrlPayload . "." . str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));

        $ch = curl_init($keyInfo['token_uri']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query(['grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer', 'assertion' => $jwt]));
        $authData = json_decode(curl_exec($ch), true);
        curl_close($ch);
        
        $accessToken = $authData['access_token'] ?? null;

        if ($accessToken) {
            $_SESSION['google_drive_token'] = $accessToken;
            $_SESSION['google_drive_token_exp'] = $now + 3000;
        } else {
            exit;
        }
    }

    // 3. Pede link da miniatura
    $driveApiUrl = "https://www.googleapis.com/drive/v3/files/{$fileId}?fields=thumbnailLink";
    
    $ch2 = curl_init($driveApiUrl);
    curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch2, CURLOPT_HTTPHEADER, ["Authorization: Bearer $accessToken"]);
    $resFile = curl_exec($ch2);
    curl_close($ch2);

    $fileData = json_decode($resFile, true);
    if (empty($fileData['thumbnailLink'])) exit;

    $thumbUrl = str_replace('=s220', '=s500', $fileData['thumbnailLink']);
    $conteudoFoto = @file_get_contents($thumbUrl);
    
    if (!$conteudoFoto) exit;
    $imagemBase = imagecreatefromstring($conteudoFoto);

    // 4. APLICA A MARCA D'ÁGUA DIRETAMENTE NOS PIXELS
    if ($marcaDaguaCaminho && file_exists('../../' . $marcaDaguaCaminho)) {
        $marca = @imagecreatefrompng('../../' . $marcaDaguaCaminho);
        
        if ($marca) {
            imagealphablending($imagemBase, true);
            imagealphablending($marca, true);
            imagesavealpha($marca, true);

            $imgW = imagesx($imagemBase);
            $imgH = imagesy($imagemBase);
            $marcaW = imagesx($marca);
            $marcaH = imagesy($marca);

            imagecopyresampled($imagemBase, $marca, 0, 0, 0, 0, $imgW, $imgH, $marcaW, $marcaH);
            imagedestroy($marca);
        }
    }

    // 5. SALVA O CACHE NO DISCO E ENTREGA A IMAGEM SEGURA
    imagejpeg($imagemBase, $arquivoCache, 80); // Salva no disco (Qualidade 80 é excelente e leve)

    header('Content-Type: image/jpeg');
    header('Cache-Control: public, max-age=' . $tempoCache);
    header('Expires: ' . gmdate('D, d M Y H:i:s', time() + $tempoCache) . ' GMT');
    header('Pragma: cache');
    
    imagejpeg($imagemBase, null, 80); // Imprime na tela
    imagedestroy($imagemBase);

} catch (Exception $e) {
    exit;
}