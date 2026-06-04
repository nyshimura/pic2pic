<?php
declare(strict_types=1);
session_start();

// Proteção: Apenas fotógrafos logados podem aceder a este ambiente específico
if (!isset($_SESSION['fotografo_id'])) {
    header("Location: index.php");
    exit;
}

require_once 'configuracoes/conexao.php';

$idFotografo = $_SESSION['fotografo_id'];
$nomeFotografo = htmlspecialchars($_SESSION['fotografo_nome']);

try {
    // 1. Busca o e-mail e foto do fotógrafo para o menu e para buscar os pedidos
    $stmtUser = $pdo->prepare("SELECT email, foto_perfil FROM fotografos WHERE id = ?");
    $stmtUser->execute([$idFotografo]);
    $dadosFotografo = $stmtUser->fetch(PDO::FETCH_ASSOC);
    
    $emailFotografo = $dadosFotografo['email'];
    $fotoPerfil = !empty($dadosFotografo['foto_perfil']) ? $dadosFotografo['foto_perfil'] : 'https://ui-avatars.com/api/?name=' . urlencode($nomeFotografo) . '&background=random';

    // 2. Busca o histórico de compras onde o fotógrafo foi o comprador
    $stmtPedidos = $pdo->prepare("
        SELECT p.id as pedido_id, p.valor_total, p.status, p.token_download, p.criado_em,
               e.nome as evento_nome, e.expira_em, e.token_url,
               f.nome as fotografo_nome,
               (SELECT COUNT(*) FROM pedidos_fotos pf WHERE pf.pedido_id = p.id) as qtd_fotos
        FROM pedidos p
        JOIN eventos e ON p.evento_id = e.id
        JOIN fotografos f ON e.fotografo_id = f.id
        WHERE p.email_comprador = ?
        ORDER BY p.id DESC
    ");
    $stmtPedidos->execute([$emailFotografo]);
    $minhasCompras = $stmtPedidos->fetchAll(PDO::FETCH_ASSOC);

    // 3. Busca Configurações do AdSense
    $adsenseAtivo = 0;
    $adsenseId = '';
    $stmtAd = $pdo->query("SELECT adsense_client_id, adsense_ativo FROM configuracoes_sistema WHERE id = 1");
    $sysConfig = $stmtAd->fetch(PDO::FETCH_ASSOC);
    if ($sysConfig) {
        $adsenseAtivo = (int)($sysConfig['adsense_ativo'] ?? 0);
        $adsenseId = $sysConfig['adsense_client_id'] ?? '';
    }

} catch (Exception $e) {
    die("Erro ao carregar os dados da sua conta de comprador.");
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Minhas Compras - Pic2Pic</title>
    
    <?php if ($adsenseAtivo === 1 && !empty($adsenseId)): ?>
        <script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=<?= htmlspecialchars($adsenseId) ?>" crossorigin="anonymous"></script>
    <?php endif; ?>

    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background-color: #f4f6f8; color: #333; }
        
        .topbar { background: #fff; padding: 15px 5%; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #eee; box-shadow: 0 2px 10px rgba(0,0,0,0.02); position: sticky; top: 0; z-index: 100; flex-wrap: wrap; gap: 10px; }
        .brand { font-weight: 800; font-size: 18px; color: #111; text-decoration: none; }
        .btn-voltar-admin { background: #e9ecef; color: #333; border: none; padding: 8px 15px; border-radius: 6px; font-size: 13px; font-weight: bold; cursor: pointer; text-decoration: none; transition: 0.2s; display: flex; align-items: center; gap: 5px; }
        .btn-voltar-admin:hover { background: #dadce0; }

        /* MENU DE USUÁRIO NO TOPO */
        .user-menu-container { display: flex; align-items: center; gap: 15px; }
        .user-menu { display: flex; align-items: center; gap: 10px; cursor: pointer; padding: 5px 10px; border-radius: 8px; transition: 0.2s; }
        .user-menu:hover { background: #f1f3f4; }
        .avatar-img { width: 38px; height: 38px; border-radius: 50%; object-fit: cover; border: 2px solid #eee; }
        .user-info-text { text-align: right; line-height: 1.2; display: none; }
        .user-info-text span { display: block; font-size: 13px; font-weight: 700; }
        .user-info-text small { font-size: 11px; color: #1a73e8; font-weight: bold; }
        .btn-sair { background: #fce8e6; color: #c5221f; border: none; padding: 8px 15px; border-radius: 6px; font-size: 13px; font-weight: bold; cursor: pointer; text-decoration: none; transition: 0.2s; display: flex; align-items: center; }
        .btn-sair:hover { background: #fad2cf; }

        @media (min-width: 768px) {
            .user-info-text { display: block; }
        }

        .container { max-width: 1000px; margin: 40px auto; padding: 0 20px; }
        .saudacao { margin-bottom: 30px; }
        .saudacao h1 { font-size: 24px; color: #111; }
        .saudacao p { color: #666; font-size: 15px; margin-top: 5px; }

        .card { background: #fff; border-radius: 12px; padding: 25px; box-shadow: 0 4px 20px rgba(0,0,0,0.04); }
        
        .table-responsive { width: 100%; overflow-x: auto; }
        .table-pedidos { width: 100%; min-width: 700px; border-collapse: collapse; text-align: left; font-size: 14px; }
        .table-pedidos th { padding: 15px 10px; border-bottom: 2px solid #eee; color: #666; font-weight: 600; }
        .table-pedidos td { padding: 15px 10px; border-bottom: 1px solid #eee; color: #333; vertical-align: middle; }
        
        .badge { padding: 5px 10px; border-radius: 6px; font-size: 12px; font-weight: bold; }
        .badge.aprovado { background: #e6f4ea; color: #137333; }
        .badge.pendente { background: #fef7e0; color: #b08d00; }
        .badge.expirado { background: #fce8e6; color: #c5221f; }

        .btn-acao { background: #1a73e8; color: #fff; text-decoration: none; padding: 8px 16px; border-radius: 6px; font-size: 13px; font-weight: bold; display: inline-block; transition: 0.2s; border: none; cursor: pointer; }
        .btn-acao:hover { background: #1557b0; }
        .btn-acao.outline { background: #fff; color: #1a73e8; border: 1px solid #1a73e8; }
        .btn-acao.outline:hover { background: #f8f9fa; }
        .btn-desativado { background: #e9ecef; color: #888; text-decoration: none; padding: 8px 16px; border-radius: 6px; font-size: 13px; font-weight: bold; display: inline-block; cursor: not-allowed; }

        .empty-state { text-align: center; padding: 60px 20px; }
        .empty-state span { font-size: 50px; display: block; margin-bottom: 15px; }
        .empty-state h3 { font-size: 18px; margin-bottom: 5px; }
        .empty-state p { color: #666; font-size: 14px; }

        /* Estilos dos Modais */
        .modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); display: none; justify-content: center; align-items: center; z-index: 1000; backdrop-filter: blur(4px); }
        .modal-box { background: #fff; width: 100%; max-width: 400px; padding: 30px; border-radius: 16px; position: relative; text-align: center; box-shadow: 0 10px 30px rgba(0,0,0,0.2); max-height: 90vh; overflow-y: auto; }
        .btn-close-modal { position: absolute; top: 15px; right: 15px; background: #f1f3f4; border: none; font-size: 20px; color: #333; cursor: pointer; width: 35px; height: 35px; border-radius: 50%; display: flex; align-items: center; justify-content: center; transition: 0.2s; }
        .btn-close-modal:hover { background: #e2e2e2; }
        
        .modal-box img { width: 220px; border: 2px solid #eee; padding: 5px; border-radius: 8px; margin: 15px 0; }
        .modal-box textarea { width: 100%; height: 80px; font-size: 12px; padding: 10px; border-radius: 6px; border: 1px solid #ccc; resize: none; margin-bottom: 15px; color: #333; }
        .btn-copiar { background: #1a73e8; color: white; padding: 12px 20px; border: none; border-radius: 8px; cursor: pointer; font-size: 14px; font-weight: bold; width: 100%; transition: 0.2s; margin-bottom: 10px;}

        /* Estilos do Sistema Toast (Notificações) */
        #toast-container { position: fixed; bottom: 20px; right: 20px; z-index: 9999; display: flex; flex-direction: column; gap: 10px; }
        .toast { min-width: 250px; color: #fff; padding: 15px 20px; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.15); display: flex; align-items: center; justify-content: space-between; font-size: 14px; font-weight: 500; animation: slideIn 0.3s ease-out forwards; }
        .toast.success { background: #137333; }
        .toast.error { background: #c5221f; }
        .toast.info { background: #1a73e8; }
        @keyframes slideIn { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
        @keyframes fadeOut { from { opacity: 1; } to { opacity: 0; } }
    </style>
</head>
<body>

    <div id="toast-container"></div>

    <header class="topbar">
        <div style="display: flex; align-items: center; gap: 15px;">
            <a href="painel.php" class="brand">📸 Pic2Pic</a>
            <a href="painel.php" class="btn-voltar-admin">← Voltar</a>
        </div>
        
        <div class="user-menu-container">
            <div class="user-menu" onclick="abrirModalPerfil()" title="Editar dados do Estúdio">
                <div class="user-info-text">
                    <span><?= $nomeFotografo ?> ✏️</span><small>Meu Estúdio</small>
                </div>
                <img src="<?= $fotoPerfil ?>" id="avatar-topo" class="avatar-img" alt="Avatar">
            </div>
            <a href="processadores/autenticacao/sair.php" class="btn-sair">Sair</a>
        </div>
    </header>

    <main class="container">
        <div class="saudacao">
            <h1>O seu Perfil de Comprador 🛒</h1>
            <p>Veja o histórico de todas as fotos que você adquiriu na plataforma.</p>
        </div>

        <?php if ($adsenseAtivo === 1 && !empty($adsenseId)): ?>
            <div class="card" style="padding: 0; margin-bottom: 30px; border: 1px dashed #dadce0; display: flex; align-items: center; justify-content: center; position: relative; min-height: 120px; background: #fafafa; box-shadow: none;">
                <span style="position: absolute; top: 10px; right: 10px; font-size: 10px; color: #9aa0a6; text-transform: uppercase; font-weight: bold; letter-spacing: 1px; z-index: 10;">Patrocinado</span>
                <ins class="adsbygoogle"
                     style="display:block; width:100%; height:100%;"
                     data-ad-client="<?= htmlspecialchars($adsenseId) ?>"
                     data-ad-format="auto"
                     data-full-width-responsive="true"></ins>
                <script>(adsbygoogle = window.adsbygoogle || []).push({});</script>
            </div>
        <?php endif; ?>

        <div class="card">
            <?php if (empty($minhasCompras)): ?>
                <div class="empty-state">
                    <span>🖼️</span>
                    <h3>Ainda não possui compras</h3>
                    <p>O seu histórico de compras aparecerá aqui automaticamente.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table-pedidos">
                        <thead>
                            <tr>
                                <th>Data do Pedido</th>
                                <th>Fotógrafo / Catálogo</th>
                                <th>Quantidade</th>
                                <th>Valor</th>
                                <th>Status</th>
                                <th>Ação</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($minhasCompras as $pedido): 
                                $dataPedido = date('d/m/Y H:i', strtotime($pedido['criado_em']));
                                $eventoExpirado = strtotime($pedido['expira_em']) < time();
                                $urlEntrega = "entrega.php?token=" . $pedido['token_download'];
                                $urlGaleria = "galeria.php?e=" . $pedido['token_url'];
                            ?>
                                <tr>
                                    <td><?= $dataPedido ?></td>
                                    <td>
                                        <strong><?= htmlspecialchars($pedido['fotografo_nome']) ?></strong><br>
                                        <a href="<?= $urlGaleria ?>" target="_blank" style="color: #1a73e8; text-decoration: none; font-size: 12px;">
                                            <?= htmlspecialchars($pedido['evento_nome']) ?>
                                        </a>
                                    </td>
                                    <td><strong><?= $pedido['qtd_fotos'] ?></strong> fotos</td>
                                    <td><?= floatval($pedido['valor_total']) == 0 ? '<span style="color:#137333; font-weight:bold;">Grátis</span>' : 'R$ ' . number_format(floatval($pedido['valor_total']), 2, ',', '.') ?></td>
                                    
                                    <td>
                                        <?php if ($eventoExpirado): ?>
                                            <span class="badge expirado">Encerrado</span>
                                        <?php elseif ($pedido['status'] === 'aprovado'): ?>
                                            <span class="badge aprovado">Liberado</span>
                                        <?php else: ?>
                                            <span class="badge pendente">Pendente</span>
                                        <?php endif; ?>
                                    </td>

                                    <td>
                                        <?php if ($eventoExpirado): ?>
                                            <span class="btn-desativado" title="O prazo para transferir os ficheiros expirou.">Não disponível</span>
                                        <?php elseif ($pedido['status'] === 'aprovado'): ?>
                                            <a href="<?= $urlEntrega ?>" class="btn-acao">📥 Transferir ZIP</a>
                                        <?php else: ?>
                                            <button onclick="recuperarPix(<?= $pedido['pedido_id'] ?>)" class="btn-acao outline">Pagar PIX</button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <div id="modal-perfil" class="modal-overlay" onclick="fecharModalPerfil(event)">
        <div class="modal-box" onclick="event.stopPropagation()">
            <button class="btn-close-modal" onclick="fecharModalPerfil('forced')">&times;</button>
            <h3 style="color: #1a73e8; margin-bottom: 5px;">Dados do Fotógrafo</h3>
            <p style="font-size: 13px; color: #666; margin-bottom: 20px;">Atualize o nome do seu negócio e a sua senha.</p>
            
            <form id="form-perfil-fotografo" style="text-align: left;">
                <label style="display:block; font-size:13px; font-weight:bold; color:#555; margin-top:15px;">E-mail de Acesso</label>
                <input type="email" value="<?= htmlspecialchars($emailFotografo) ?>" disabled style="width:100%; padding:12px; border:1px solid #ccc; border-radius:8px; margin-top:5px; font-size:14px; background: #e9ecef; color: #666; cursor: not-allowed;" title="Não é possível alterar o e-mail de login.">
                
                <label style="display:block; font-size:13px; font-weight:bold; color:#555; margin-top:15px;">Nome do Fotógrafo / Estúdio</label>
                <input type="text" id="perf-nome" value="<?= $nomeFotografo ?>" required style="width:100%; padding:12px; border:1px solid #ccc; border-radius:8px; margin-top:5px; font-size:14px;">
                
                <hr style="margin: 20px 0; border: 0; border-top: 1px solid #eee;">
                <p style="font-size: 12px; color: #888; margin-bottom: 15px;">Para alterar a sua senha, preencha os campos abaixo. Deixe em branco para manter a atual.</p>

                <label style="display:block; font-size:13px; font-weight:bold; color:#555; margin-top:15px;">Nova Senha</label>
                <input type="password" id="perf-senha" placeholder="Deixe em branco para não alterar" style="width:100%; padding:12px; border:1px solid #ccc; border-radius:8px; margin-top:5px; font-size:14px;">
                
                <label style="display:block; font-size:13px; font-weight:bold; color:#555; margin-top:15px;">Confirmar Nova Senha</label>
                <input type="password" id="perf-senha-confirma" placeholder="Repita a nova senha" style="width:100%; padding:12px; border:1px solid #ccc; border-radius:8px; margin-top:5px; font-size:14px;">
                
                <button type="submit" id="btn-salvar-perf" class="btn-acao" style="width: 100%; margin-top: 25px; padding: 14px;">Salvar Alterações</button>
            </form>
        </div>
    </div>

    <div id="modal-pix" class="modal-overlay" onclick="fecharModalPix(event)">
        <div class="modal-box" onclick="event.stopPropagation()">
            <button class="btn-close-modal" onclick="fecharModalPix('forced')">&times;</button>
            
            <div id="loading-pix">
                <h3 style="color: #666; margin-top: 20px;">Consultando Mercado Pago...</h3>
                <p style="font-size: 13px; color: #999; margin-top: 10px;">Por favor, aguarde.</p>
            </div>
            
            <div id="conteudo-pix" style="display: none;">
                <h3 style="color: #1a73e8;">Pedido Pendente</h3>
                <p style="font-size: 13px; color: #555; margin-top: 5px;">Escaneie ou copie o código abaixo para liberar suas fotos.</p>
                <img id="img-qrcode" src="" alt="QR Code PIX">
                <textarea id="texto-pix" readonly></textarea>
                <button id="btn-copiar" onclick="copiarPixModal()" class="btn-copiar">📋 Copiar Código PIX</button>
                <button onclick="location.reload()" class="btn-acao outline" style="width: 100%;">Já Paguei! Atualizar</button>
            </div>
        </div>
    </div>

    <script>
        // Sistema Global de Notificações Toast
        function showToast(mensagem, tipo = 'success') {
            const container = document.getElementById('toast-container');
            const toast = document.createElement('div');
            toast.className = `toast ${tipo}`;
            toast.innerText = mensagem;
            container.appendChild(toast);
            setTimeout(() => {
                toast.style.animation = 'fadeOut 0.3s ease-out forwards';
                setTimeout(() => toast.remove(), 300);
            }, 4000);
        }

        // Funções do Modal de Perfil (Fotógrafo)
        function abrirModalPerfil() { document.getElementById('modal-perfil').style.display = 'flex'; }
        function fecharModalPerfil(e) { if(!e || e.target === document.getElementById('modal-perfil') || e === 'forced') document.getElementById('modal-perfil').style.display = 'none'; }
        
        document.getElementById('form-perfil-fotografo').addEventListener('submit', async function(e) {
            e.preventDefault();
            const btn = document.getElementById('btn-salvar-perf');
            const nome = document.getElementById('perf-nome').value.trim();
            const senha = document.getElementById('perf-senha').value;
            const senhaConfirma = document.getElementById('perf-senha-confirma').value;

            if (senha || senhaConfirma) {
                if (senha !== senhaConfirma) { showToast('As senhas digitadas não coincidem.', 'error'); return; }
                if (senha.length < 6) { showToast('A nova senha deve ter pelo menos 6 caracteres.', 'error'); return; }
            }

            btn.innerText = 'Salvando...'; btn.disabled = true; 

            try {
                const response = await fetch('processadores/fotografo/atualizar_perfil.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({ nome: nome, senha: senha })
                });
                const res = await response.json();
                
                if (res.sucesso) {
                    showToast('Dados atualizados com sucesso!', 'success');
                    document.getElementById('perf-senha').value = '';
                    document.getElementById('perf-senha-confirma').value = '';
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showToast(res.erro || 'Falha ao atualizar dados.', 'error');
                }
            } catch(error) {
                showToast('Erro de comunicação com o servidor.', 'error');
            }
            btn.innerText = 'Salvar Alterações'; btn.disabled = false;
        });

        // Funções do Modal do PIX
        function fecharModalPix(e) {
            if(!e || e.target === document.getElementById('modal-pix') || e === 'forced') {
                document.getElementById('modal-pix').style.display = 'none';
            }
        }

        async function recuperarPix(pedidoId) {
            document.getElementById('modal-pix').style.display = 'flex';
            document.getElementById('loading-pix').style.display = 'block';
            document.getElementById('conteudo-pix').style.display = 'none';

            try {
                const response = await fetch('processadores/pagamentos/recuperar_pix.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ pedido_id: pedidoId })
                });
                const res = await response.json();

                if (res.sucesso) {
                    if (res.status === 'aprovado') {
                        fecharModalPix('forced');
                        showToast("Pagamento aprovado! Atualizando a página...", "success");
                        setTimeout(() => location.reload(), 2000);
                    } else {
                        document.getElementById('img-qrcode').src = "data:image/jpeg;base64," + res.qr_code_base64;
                        document.getElementById('texto-pix').value = res.qr_code_copia_cola;
                        document.getElementById('loading-pix').style.display = 'none';
                        document.getElementById('conteudo-pix').style.display = 'block';
                    }
                } else {
                    fecharModalPix('forced');
                    showToast(res.erro, "error");
                }
            } catch (err) {
                fecharModalPix('forced');
                showToast("Erro de comunicação com o servidor.", "error");
            }
        }

        function copiarPixModal() {
            const texto = document.getElementById("texto-pix");
            texto.select();
            document.execCommand("copy");
            const btn = document.getElementById("btn-copiar");
            btn.innerText = "✅ Código Copiado!";
            btn.style.background = "#28a745";
            showToast("Código Pix copiado com sucesso!", "info");
            setTimeout(() => { btn.innerText = "📋 Copiar Código PIX"; btn.style.background = "#1a73e8"; }, 3000);
        }
    </script>
</body>
</html>