<?php
declare(strict_types=1);
session_start();
require_once '../../configuracoes/conexao.php';

// =========================================================
// ⚡ TURBO CACHE DO NAVEGADOR (30 DIAS)
// =========================================================
$tempoCache = 2592000; // 30 dias
header('Content-Type: image/jpeg');
header('Cache-Control: public, max-age=' . $tempoCache);
header('Expires: ' . gmdate('D, d M Y H:i:s', time() + $tempoCache) . ' GMT');
header('Pragma: cache');
// =========================================================

$idFoto = intval($_GET['f'] ?? 0);

if ($idFoto <= 0) {
    lerImagemPlaceholder("Erro ID");
    exit;
}


try {
    // 1. Busca os dados da foto, do evento e a marca d'água no banco
    $stmt = $pdo->prepare("
        SELECT fe.drive_file_id, f.marca_dagua 
        FROM fotos_eventos fe
        JOIN eventos e ON fe.evento_id = e.id
        JOIN fotografos f ON e.fotografo_id = f.id
        WHERE fe.id = ? AND e.ativo = 1
    ");
    $stmt->execute([$idFoto]);
    $dados = $stmt->fetch();

    if (!$dados) {
        lerImagemPlaceholder("Foto Indisponível");
        exit;
    }

    $fileId = $dados['drive_file_id'];
    $marcaDaguaCaminho = $dados['marca_dagua'];

    // 2. Autenticação JWT Inteligente (Usa Cache na Sessão para não travar o servidor)
    $accessToken = $_SESSION['google_drive_token'] ?? null;
    $tokenExpira = $_SESSION['google_drive_token_exp'] ?? 0;

    if (!$accessToken || time() >= $tokenExpira) {
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
        openssl_sign($base64UrlHeader . "." . $base64UrlPayload, $signature, $keyInfo['private_key'], "SHA256");
        $jwt = $base64UrlHeader . "." . $base64UrlPayload . "." . str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));

        $ch = curl_init($keyInfo['token_uri']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query(['grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer', 'assertion' => $jwt]));
        $resAuth = curl_exec($ch);
        curl_close($ch);
        
        $authData = json_decode($resAuth, true);
        $accessToken = $authData['access_token'] ?? null;

        if ($accessToken) {
            $_SESSION['google_drive_token'] = $accessToken;
            $_SESSION['google_drive_token_exp'] = $now + 3000; // Guarda por 50 minutos
        } else {
            lerImagemPlaceholder("Erro Auth");
            exit;
        }
    }

    // 3. Pede ao Google EXCLUSIVAMENTE a miniatura dessa foto
    $driveApiUrl = "https://www.googleapis.com/drive/v3/files/{$fileId}?fields=thumbnailLink";
    
    $ch2 = curl_init($driveApiUrl);
    curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch2, CURLOPT_HTTPHEADER, ["Authorization: Bearer $accessToken"]);
    $resFile = curl_exec($ch2);
    curl_close($ch2);

    $fileData = json_decode($resFile, true);

    if (empty($fileData['thumbnailLink'])) {
        lerImagemPlaceholder("Sem Permissão");
        exit;
    }

    // 4. Otimiza para baixa resolução (500px é perfeito para a galeria, leve e rápido)
    $thumbUrl = str_replace('=s220', '=s500', $fileData['thumbnailLink']);

    // Carrega a foto para a RAM (Zero Storage na Hostinger)
    $conteudoFoto = @file_get_contents($thumbUrl);
    if (!$conteudoFoto) {
        lerImagemPlaceholder("Erro Download");
        exit;
    }

    $imagemBase = imagecreatefromstring($conteudoFoto);

    // 5. MARCA D'ÁGUA EM COBERTURA TOTAL (Esticada 100%)
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

            // Estica a marca d'água para cobrir 100% da foto original em baixa
            imagecopyresampled($imagemBase, $marca, 0, 0, 0, 0, $imgW, $imgH, $marcaW, $marcaH);
            imagedestroy($marca);
        }
    }

    // 6. Imprime a imagem JPEG final na tela
    imagejpeg($imagemBase, null, 80); // Qualidade 80 para manter leveza
    imagedestroy($imagemBase);

} catch (Exception $e) {
    lerImagemPlaceholder("Erro Fatal");
}

function lerImagemPlaceholder(string $texto): void {
    $img = imagecreatetruecolor(400, 400);
    $bg = imagecolorallocate($img, 30, 30, 30);
    $txtColor = imagecolorallocate($img, 255, 60, 60);
    imagefill($img, 0, 0, $bg);
    imagestring($img, 5, 20, 190, $texto, $txtColor);
    imagejpeg($img);
    imagedestroy($img);
}