<?php
declare(strict_types=1);
require_once 'configuracoes/conexao.php';

$token = isset($_GET['token']) ? preg_replace('/[^a-zA-Z0-9]/', '', $_GET['token']) : '';
if (empty($token)) { header("Location: index.php"); exit; }

try {
    $stmt = $pdo->prepare("
        SELECT p.id, p.status, p.valor_total, e.nome as evento_nome, e.expira_em, f.nome as fotografo_nome,
               (SELECT COUNT(*) FROM pedidos_fotos WHERE pedido_id = p.id) as qtd_fotos
        FROM pedidos p
        JOIN eventos e ON p.evento_id = e.id
        JOIN fotografos f ON e.fotografo_id = f.id
        WHERE p.token_download = ?
    ");
    $stmt->execute([$token]);
    $pedido = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$pedido) {
        die("<h2 style='text-align:center; margin-top:50px; font-family:sans-serif;'>Link de download inválido ou expirado.</h2>");
    }

    // TRAVA DE SEGURANÇA: Verifica se a data de hoje é maior que a validade do evento
    $eventoExpirado = strtotime($pedido['expira_em']) < time();

} catch (Exception $e) {
    die("Erro ao carregar os dados de entrega.");
}

// ==========================================
// BUSCA CONFIGURAÇÕES DO GOOGLE ADSENSE
// ==========================================
$adsenseAtivo = 0;
$adsenseId = '';
try {
    $stmtAd = $pdo->query("SELECT adsense_client_id, adsense_ativo FROM configuracoes_sistema WHERE id = 1");
    $sysConfig = $stmtAd->fetch(PDO::FETCH_ASSOC);
    if ($sysConfig) {
        $adsenseAtivo = (int)($sysConfig['adsense_ativo'] ?? 0);
        $adsenseId = $sysConfig['adsense_client_id'] ?? '';
    }
} catch (Exception $e) {}
// ==========================================
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Suas Fotos - Pic2Pic</title>
    
    <?php if ($adsenseAtivo === 1 && !empty($adsenseId)): ?>
        <script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=<?= htmlspecialchars($adsenseId) ?>" crossorigin="anonymous"></script>
    <?php endif; ?>

    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
        body { background-color: #f4f6f8; color: #333; display: flex; flex-direction: column; min-height: 100vh; align-items: center; justify-content: center; padding: 20px; }
        
        .card-entrega { background: #fff; width: 100%; max-width: 500px; padding: 40px 30px; border-radius: 16px; box-shadow: 0 10px 30px rgba(0,0,0,0.05); text-align: center; }
        .icone-status { font-size: 60px; margin-bottom: 20px; }
        h1 { font-size: 24px; color: #111; margin-bottom: 10px; line-height: 1.2; }
        p { font-size: 15px; color: #666; margin-bottom: 30px; line-height: 1.5; }
        
        .info-box { background: #f8f9fa; padding: 15px; border-radius: 8px; margin-bottom: 30px; text-align: left; border: 1px solid #eee; }
        .info-box div { margin-bottom: 8px; font-size: 14px; }
        .info-box strong { color: #333; }
        
        .btn-download { background: #1a73e8; color: #fff; border: none; padding: 16px 30px; border-radius: 50px; font-size: 16px; font-weight: bold; cursor: pointer; text-decoration: none; display: inline-block; width: 100%; transition: 0.2s; }
        .btn-download:hover { background: #1557b0; }
        .btn-download.outline { background: #e9ecef; color: #333; }
        .btn-download.outline:hover { background: #dadce0; }
        
        .loader { display: none; margin-top: 15px; font-size: 14px; color: #1a73e8; font-weight: bold; }
        .aviso-expirado { background: #fff3cd; color: #856404; padding: 15px; border-radius: 8px; font-weight: bold; font-size: 14px; border: 1px solid #ffeeba; }
        
        .btn-voltar { display: inline-block; margin-top: 25px; font-size: 14px; color: #666; text-decoration: none; font-weight: 600; transition: 0.2s; }
        .btn-voltar:hover { color: #1a73e8; }

        /* Estilo do Outdoor de Publicidade */
        .ad-container { margin-top: 35px; width: 100%; min-height: 250px; background: #fafafa; border: 1px dashed #dadce0; border-radius: 12px; display: flex; align-items: center; justify-content: center; position: relative; overflow: hidden; }
        .ad-badge { position: absolute; top: 10px; right: 10px; font-size: 10px; color: #9aa0a6; text-transform: uppercase; font-weight: bold; letter-spacing: 1px; z-index: 10; }
    </style>
</head>
<body>

    <div class="card-entrega">
        <?php if ($pedido['status'] === 'aprovado'): ?>
            
            <div class="icone-status"><?= $eventoExpirado ? '🔒' : '🎉' ?></div>
            <h1><?= $eventoExpirado ? 'Catálogo Encerrado' : 'Tudo certo! Suas fotos estão prontas.' ?></h1>
            <p>
                <?= $eventoExpirado 
                    ? 'O prazo de validade deste evento chegou ao fim e os arquivos foram removidos da nuvem.' 
                    : 'Obrigado por utilizar o sistema do fotógrafo <b>' . htmlspecialchars($pedido['fotografo_nome']) . '</b>. Suas memórias já estão disponíveis para download.' ?>
            </p>
            
            <div class="info-box">
                <div><strong>Catálogo:</strong> <?= htmlspecialchars($pedido['evento_nome']) ?></div>
                <div><strong>Quantidade:</strong> <?= $pedido['qtd_fotos'] ?> foto(s)</div>
                <div><strong>Valor Pago:</strong> <?= floatval($pedido['valor_total']) == 0 ? '<span style="color:#28a745; font-weight:bold;">Grátis</span>' : 'R$ ' . number_format(floatval($pedido['valor_total']), 2, ',', '.') ?></div>
            </div>

            <?php if ($eventoExpirado): ?>
                <div class="aviso-expirado">O período para baixar estas imagens expirou.</div>
            <?php else: ?>
                <a href="#" id="btn-baixar" class="btn-download" onclick="iniciarDownload(event)">📥 Baixar Arquivo (.ZIP)</a>
                <div id="loader" class="loader">Empacotando suas fotos direto do Google Drive. Isso pode levar alguns segundos... ⏳</div>
            <?php endif; ?>

        <?php elseif ($pedido['status'] === 'pendente'): ?>
            
            <div class="icone-status">⏳</div>
            <h1>Aguardando Pagamento</h1>
            <p>O seu pedido foi recebido, mas ainda estamos aguardando a confirmação do pagamento pelo Mercado Pago.</p>
            
            <div class="info-box">
                <div><strong>Catálogo:</strong> <?= htmlspecialchars($pedido['evento_nome']) ?></div>
                <div><strong>Status:</strong> Pendente de aprovação PIX</div>
            </div>

            <p style="font-size: 13px;">Se você acabou de pagar, atualize esta página daqui a pouco para baixar as imagens.</p>
            <a href="" class="btn-download outline">🔄 Atualizar Página</a>
            
        <?php else: ?>
            
            <div class="icone-status">❌</div>
            <h1>Pedido Cancelado</h1>
            <p>Houve um problema com o seu pedido ou ele foi cancelado pelo sistema.</p>
            
        <?php endif; ?>

        <?php if ($adsenseAtivo === 1 && !empty($adsenseId)): ?>
            <div class="ad-container">
                <span class="ad-badge">Patrocinado</span>
                <ins class="adsbygoogle"
                     style="display:block; width:100%; height:100%;"
                     data-ad-client="<?= htmlspecialchars($adsenseId) ?>"
                     data-ad-format="auto"
                     data-full-width-responsive="true"></ins>
                <script>(adsbygoogle = window.adsbygoogle || []).push({});</script>
            </div>
        <?php endif; ?>

        <a href="painel_comprador.php" class="btn-voltar">← Voltar para Minhas Fotos</a>
    </div>

    <script>
        function iniciarDownload(e) {
            e.preventDefault();
            const btn = document.getElementById('btn-baixar');
            const loader = document.getElementById('loader');
            
            btn.style.display = 'none';
            loader.style.display = 'block';

            window.location.href = 'processadores/downloads/gerar_zip.php?token=<?= $token ?>';

            // Como a criação do ZIP pode demorar, mantemos a UI num estado de "Aguarde"
            setTimeout(() => {
                btn.style.display = 'inline-block';
                loader.style.display = 'none';
            }, 10000);
        }
    </script>
</body>
</html>