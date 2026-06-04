<?php
declare(strict_types=1);
session_start();
require_once '../../configuracoes/conexao.php';

$token = isset($_GET['e']) ? trim(filter_input(INPUT_GET, 'e', FILTER_SANITIZE_SPECIAL_CHARS)) : '';

if (empty($token)) {
    lerImagemPlaceholder("Código inválido");
    exit;
}

try {
    // 1. Busca os dados do evento
    $stmt = $pdo->prepare("SELECT e.id as evento_id, e.drive_folder_id, f.marca_dagua 
                           FROM eventos e 
                           JOIN fotografos f ON e.fotografo_id = f.id 
                           WHERE e.token_url = ? AND e.ativo = 1");
    $stmt->execute([$token]);
    $dados = $stmt->fetch();

    if (!$dados) {
        lerImagemPlaceholder("Não encontrado");
        exit;
    }

    $eventoId = $dados['evento_id'];
    $folderId = $dados['drive_folder_id'];
    $marcaDaguaCaminho = $dados['marca_dagua'];

    // =========================================================
    // ⚡ MOTOR DE CACHE DE ALTA VELOCIDADE (ZERO API)
    // =========================================================
    $diretorioCache = '../../uploads/thumbs/';
    $arquivoCache = $diretorioCache . 'capa_evento_' . $eventoId . '.jpg';

    // Se a capa já foi processada antes, entrega IMEDIATAMENTE (Poupa CPU e Google API)
    if (file_exists($arquivoCache)) {
        header('Content-Type: image/jpeg');
        // Instrui o navegador do cliente a fazer cache local por 24 horas
        header('Cache-Control: public, max-age=86400');
        header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 86400) . ' GMT');
        readfile($arquivoCache);
        exit;
    }

    // Cria a pasta de cache se for a primeira vez rodando no servidor
    if (!is_dir($diretorioCache)) {
        mkdir($diretorioCache, 0755, true);
    }
    // =========================================================

    // 2. Só chega aqui se o Cache não existir (1ª vez). Autentica no Google:
    $jsonPath = '../../credenciais/google_drive.json';
    if (!file_exists($jsonPath)) {
        lerImagemPlaceholder("Chave ausente");
        exit;
    }

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
    $signature = '';
    openssl_sign($base64UrlHeader . "." . $base64UrlPayload, $signature, $keyInfo['private_key'], "SHA256");
    $base64UrlSignature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));
    $jwt = $base64UrlHeader . "." . $base64UrlPayload . "." . $base64UrlSignature;

    $ch = curl_init($keyInfo['token_uri']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
        'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
        'assertion' => $jwt
    ]));
    $resAuth = curl_exec($ch);
    curl_close($ch);
    
    $authData = json_decode($resAuth, true);
    $accessToken = $authData['access_token'] ?? null;

    if (!$accessToken) {
        lerImagemPlaceholder("Erro de Autenticação");
        exit;
    }

    // 3. Pede a 1ª foto para usar como capa
    $query = urlencode("'" . $folderId . "' in parents and mimeType contains 'image/'");
    $driveApiUrl = "https://www.googleapis.com/drive/v3/files?q={$query}&fields=files(id,thumbnailLink)&pageSize=1";

    $ch2 = curl_init($driveApiUrl);
    curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch2, CURLOPT_HTTPHEADER, ["Authorization: Bearer $accessToken"]);
    $resFiles = curl_exec($ch2);
    curl_close($ch2);

    $filesData = json_decode($resFiles, true);

    if (empty($filesData['files'][0]['thumbnailLink'])) {
        lerImagemPlaceholder("Pasta Vazia");
        exit;
    }

    $thumbUrl = str_replace('=s220', '=s800', $filesData['files'][0]['thumbnailLink']);

    $conteudoAmostra = file_get_contents($thumbUrl);
    
    if (!$conteudoAmostra) {
        lerImagemPlaceholder("Erro Download");
        exit;
    }

    $imagemBase = imagecreatefromstring($conteudoAmostra);

    // 4. APLICA A MARCA D'ÁGUA EM COBERTURA TOTAL (100%)
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

    // 5. SALVA NO DISCO (Gera o Cache) E ENTREGA
    imagejpeg($imagemBase, $arquivoCache, 85); // <-- Isso salva o arquivo na Hostinger
    
    header('Content-Type: image/jpeg');
    header('Cache-Control: public, max-age=86400'); // Avisa o navegador que pode guardar também
    imagejpeg($imagemBase, null, 85);
    imagedestroy($imagemBase);

} catch (Exception $e) {
    lerImagemPlaceholder("Erro Interno");
}

function lerImagemPlaceholder(string $texto): void {
    header('Content-Type: image/jpeg');
    $img = imagecreatetruecolor(600, 400);
    $bg = imagecolorallocate($img, 33, 33, 33);
    $txtColor = imagecolorallocate($img, 255, 193, 7);
    imagefill($img, 0, 0, $bg);
    imagestring($img, 5, 20, 20, $texto, $txtColor);
    imagejpeg($img);
    imagedestroy($img);
}