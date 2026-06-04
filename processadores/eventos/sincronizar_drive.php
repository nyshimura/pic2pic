<?php
declare(strict_types=1);

// Evita que erros nativos do PHP quebrem o AJAX do navegador
ini_set('display_errors', '0'); 
error_reporting(0);

session_start();

// ==========================================
// CONFIGURAÇÃO DO FUSO HORÁRIO PARA OS LOGS
// ==========================================
date_default_timezone_set('America/Sao_Paulo');

require_once '../../configuracoes/conexao.php';

// BLINDAGEM DE MEMÓRIA E TEMPO
set_time_limit(300);
ini_set('memory_limit', '512M');
$startTime = time(); 

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_SESSION['fotografo_id'])) {
    http_response_code(401);
    echo json_encode(['sucesso' => false, 'erro' => 'Acesso negado.']);
    exit;
}

$idFotografo = $_SESSION['fotografo_id'];
$idEvento = intval($_POST['id_evento'] ?? 0);

if ($idEvento <= 0) exit;

try {
    $stmt = $pdo->prepare("SELECT drive_folder_id FROM eventos WHERE id = ? AND fotografo_id = ?");
    $stmt->execute([$idEvento, $idFotografo]);
    $folderId = $stmt->fetchColumn();

    if (!$folderId) { echo json_encode(['sucesso' => false, 'erro' => 'Pasta não encontrada.']); exit; }

    $jsonPath = '../../credenciais/google_drive.json';
    if (!file_exists($jsonPath)) { echo json_encode(['sucesso' => false, 'erro' => 'Chave JSON ausente.']); exit; }

    $keyInfo = json_decode(file_get_contents($jsonPath), true);
    $header = json_encode(['alg' => 'RS256', 'typ' => 'JWT']);
    $payload = json_encode(['iss' => $keyInfo['client_email'], 'scope' => 'https://www.googleapis.com/auth/drive.readonly', 'aud' => $keyInfo['token_uri'], 'exp' => time() + 3600, 'iat' => time()]);

    $base64UrlHeader = str_replace(['+', '/', '=', "\n", "\r"], ['-', '_', '', '', ''], base64_encode($header));
    $base64UrlPayload = str_replace(['+', '/', '=', "\n", "\r"], ['-', '_', '', '', ''], base64_encode($payload));
    openssl_sign($base64UrlHeader . "." . $base64UrlPayload, $signature, $keyInfo['private_key'], "SHA256");
    $jwt = $base64UrlHeader . "." . $base64UrlPayload . "." . str_replace(['+', '/', '=', "\n", "\r"], ['-', '_', '', '', ''], base64_encode($signature));

    $ch = curl_init($keyInfo['token_uri']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query(['grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer', 'assertion' => $jwt]));
    $authData = json_decode(curl_exec($ch), true);
    curl_close($ch);
    $accessToken = $authData['access_token'] ?? null;

    if (!$accessToken) { echo json_encode(['sucesso' => false, 'erro' => 'Falha Google Cloud.']); exit; }

    $query = urlencode("'" . $folderId . "' in parents and mimeType contains 'image/'");
    $driveApiUrl = "https://www.googleapis.com/drive/v3/files?q={$query}&fields=files(id,thumbnailLink)&pageSize=1000";

    $ch2 = curl_init($driveApiUrl);
    curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch2, CURLOPT_HTTPHEADER, ["Authorization: Bearer $accessToken"]);
    $filesData = json_decode(curl_exec($ch2), true);
    curl_close($ch2);

    $fotosProcessadas = 0;
    $paradaForcada = false;
    
    $endpointHf = 'https://nyshimura-api-fotos-eventos.hf.space/extrair_base64/'; 

    $stmtInsert = $pdo->prepare("INSERT INTO fotos_eventos (evento_id, drive_file_id, url_visualizacao, encoding_facial) VALUES (?, ?, ?, ?)");
    $stmtUpdate = $pdo->prepare("UPDATE fotos_eventos SET encoding_facial = ? WHERE id = ?");
    $stmtLog = $pdo->prepare("INSERT INTO logs_eventos (fotografo_id, evento_id, descricao) VALUES (?, ?, ?)");

    if (empty($filesData['files'])) {
        echo json_encode(['sucesso' => true, 'msg' => 'Nenhuma foto encontrada no Drive.']);
        exit;
    }

    foreach ($filesData['files'] as $file) {
        
        if ((time() - $startTime > 20) || ($fotosProcessadas >= 10)) {
            $paradaForcada = true;
            break;
        }

        $fileId = $file['id'];
        $thumbUrl = str_replace('=s220', '=s1000', $file['thumbnailLink'] ?? '');
        if (!$thumbUrl) continue;

        $stmtCheck = $pdo->prepare("SELECT id, encoding_facial FROM fotos_eventos WHERE evento_id = ? AND drive_file_id = ?");
        $stmtCheck->execute([$idEvento, $fileId]);
        $fotoExistente = $stmtCheck->fetch();

        $precisaProcessar = false;
        $idParaUpdate = null;

        if ($fotoExistente) {
            if ($fotoExistente['encoding_facial'] === null) {
                $precisaProcessar = true;
                $idParaUpdate = $fotoExistente['id'];
            } else {
                $arr = json_decode($fotoExistente['encoding_facial'], true);
                if (empty($fotoExistente['encoding_facial']) && $fotoExistente['encoding_facial'] !== '[]') {
                    $precisaProcessar = true;
                    $idParaUpdate = $fotoExistente['id'];
                } elseif (is_array($arr) && isset($arr[0]) && !is_array($arr[0])) {
                    $precisaProcessar = true;
                    $idParaUpdate = $fotoExistente['id'];
                }
            }
        } else {
            $precisaProcessar = true;
        }

        if ($precisaProcessar) {
            $chImg = curl_init($thumbUrl);
            curl_setopt($chImg, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($chImg, CURLOPT_HTTPHEADER, ["Authorization: Bearer $accessToken"]);
            $imgData = curl_exec($chImg);
            curl_close($chImg);

            $encodingSalvar = null;

            if ($imgData) {
                
                // ==========================================
                // SANITIZADOR PARA MATAR TRANSPARÊNCIAS E PNGs
                // ==========================================
                $imgSanitizadaData = $imgData; 
                try {
                    $im = @imagecreatefromstring($imgData);
                    if ($im !== false) {
                        $width = imagesx($im);
                        $height = imagesy($im);
                        $bg = imagecreatetruecolor($width, $height);
                        $white = imagecolorallocate($bg, 255, 255, 255);
                        imagefilledrectangle($bg, 0, 0, $width, $height, $white);
                        imagecopy($bg, $im, 0, 0, 0, 0, $width, $height);
                        
                        ob_start();
                        imagejpeg($bg, null, 90);
                        $imgSanitizadaData = ob_get_clean();
                        
                        imagedestroy($im);
                        imagedestroy($bg);
                    }
                } catch(Throwable $t) { }
                // ==========================================

                $chHf = curl_init($endpointHf);
                curl_setopt($chHf, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($chHf, CURLOPT_POST, true);
                curl_setopt($chHf, CURLOPT_POSTFIELDS, json_encode(['selfie_base64' => base64_encode($imgSanitizadaData)])); 
                curl_setopt($chHf, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
                curl_setopt($chHf, CURLOPT_TIMEOUT, 35); 
                
                $respostaHf = curl_exec($chHf);
                $erroCurl = curl_error($chHf);
                curl_close($chHf);

                $dadosRosto = json_decode((string)$respostaHf, true);
                
                if ($dadosRosto && isset($dadosRosto['sucesso']) && $dadosRosto['sucesso'] === true) {
                    $encodingSalvar = json_encode($dadosRosto['encodings']);
                } else {
                    $motivo = $dadosRosto['erro'] ?? ($erroCurl ? "Falha de Conexão ($erroCurl)" : "Erro na IA");
                    $nomeIdentificador = substr($fileId, 0, 6) . "..."; 
                    
                    // ==========================================
                    // A GRANDE CORREÇÃO (ANTI-LOOP INFINITO)
                    // ==========================================
                    // Se der QUALQUER erro (falta de rosto ou crash da biblioteca dlib),
                    // gravamos '[]' vazio. Assim o PHP entende que a foto já foi processada!
                    $encodingSalvar = '[]'; 
                    
                    if (stripos(strtolower($motivo), 'rosto n') !== false || stripos(strtolower($motivo), 'detectado') !== false) {
                        $mensagemLog = "ℹ️ Foto [$nomeIdentificador] analisada: Ângulo complexo/reflexo. Marcada sem biometria.";
                    } else {
                        // Corta o erro gigante do Python para não poluir o painel
                        $erroCurto = substr($motivo, 0, 80) . "...";
                        $mensagemLog = "⚠️ Foto [$nomeIdentificador] ignorada para evitar travamento. Motivo: $erroCurto";
                    }
                    
                    $stmtLog->execute([$idFotografo, $idEvento, $mensagemLog]);
                }
            }

            if ($idParaUpdate) {
                $stmtUpdate->execute([$encodingSalvar, $idParaUpdate]);
                if ($encodingSalvar !== null) $fotosProcessadas++;
            } else {
                $stmtInsert->execute([$idEvento, $fileId, $thumbUrl, $encodingSalvar]);
                if ($stmtInsert->rowCount() > 0 && $encodingSalvar !== null) $fotosProcessadas++;
            }
        }
    }

    if ($paradaForcada) {
        echo json_encode(['sucesso' => true, 'msg' => "Lote processado ($fotosProcessadas fotos resolvidas). Clique em Sincronizar NOVAMENTE para continuar."]);
    } else {
        echo json_encode(['sucesso' => true, 'msg' => "Sincronização 100% concluída! Catálogo atualizado."]);
    }
    
} catch (Throwable $e) {
    echo json_encode(['sucesso' => false, 'erro' => 'Falha Interna: ' . $e->getMessage()]);
}