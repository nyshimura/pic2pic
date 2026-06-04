<?php
declare(strict_types=1);
require_once '../../configuracoes/conexao.php';

header('Content-Type: application/json');

$dados = json_decode(file_get_contents('php://input'), true);
$idEvento = intval($dados['evento_id'] ?? 0);
$fotoBase64 = $dados['selfie'] ?? '';

if ($idEvento <= 0 || empty($fotoBase64)) {
    echo json_encode(['sucesso' => false, 'erro' => 'Dados inválidos.']); exit;
}

try {
    $hfEndpoint = "https://nyshimura-api-fotos-eventos.hf.space/extrair_base64/"; 
    
    $ch = curl_init($hfEndpoint);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(["selfie_base64" => $fotoBase64]));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15); 
    $respostaHf = curl_exec($ch);
    curl_close($ch);

    $retornoHf = json_decode($respostaHf, true);
    // A selfie tem só 1 rosto, pegamos o índice 0 da nova resposta
    $selfieEncoding = $retornoHf['encodings'][0] ?? null;

    if (!$selfieEncoding || !is_array($selfieEncoding)) {
        echo json_encode(['sucesso' => false, 'erro' => 'A IA não conseguiu rastrear os pontos do seu rosto.']); exit;
    }

    $stmt = $pdo->prepare("SELECT id, encoding_facial FROM fotos_eventos WHERE evento_id = ? AND encoding_facial IS NOT NULL");
    $stmt->execute([$idEvento]);
    $fotosBanco = $stmt->fetchAll();

    $fotosEncontradas = [];
    
    // Podemos voltar para a porta de segurança padrão e confiável agora!
    $limiteDistancia = 0.60; 

    foreach ($fotosBanco as $fotoDb) {
        $rostosDaFoto = json_decode($fotoDb['encoding_facial'], true);
        
        // Verifica se é a nova estrutura (Array de Arrays)
        if (is_array($rostosDaFoto) && isset($rostosDaFoto[0]) && is_array($rostosDaFoto[0])) {
            
            // Varre TODOS os rostos presentes na foto
            foreach ($rostosDaFoto as $dbEncoding) {
                if (count($dbEncoding) === count($selfieEncoding)) {
                    $somaQuadrados = 0;
                    for ($i = 0; $i < count($selfieEncoding); $i++) {
                        $diferenca = $selfieEncoding[$i] - $dbEncoding[$i];
                        $somaQuadrados += ($diferenca * $diferenca);
                    }
                    $distancia = sqrt($somaQuadrados);

                    // Se encontrou você, guarda a foto e para de testar os outros rostos dessa imagem
                    if ($distancia <= $limiteDistancia) {
                        $fotosEncontradas[] = [
                            'id' => $fotoDb['id'],
                            'url' => 'processadores/imagens/proxy_imagem.php?f=' . $fotoDb['id']
                        ];
                        break; 
                    }
                }
            }
        }
    }

    if (count($fotosEncontradas) > 0) {
        echo json_encode(['sucesso' => true, 'fotos_encontradas' => $fotosEncontradas]);
    } else {
        echo json_encode(['sucesso' => false, 'erro' => 'Nenhuma foto sua foi encontrada.']);
    }

} catch (Exception $e) {
    echo json_encode(['sucesso' => false, 'erro' => 'Erro interno de cruzamento de dados.']);
}