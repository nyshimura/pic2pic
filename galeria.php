<?php
declare(strict_types=1);
session_start();
require_once 'configuracoes/conexao.php';

$logado = isset($_SESSION['fotografo_id']) || isset($_SESSION['comprador_id']);
$urlPainelDestino = isset($_SESSION['fotografo_id']) ? 'painel.php' : 'painel_comprador.php';
$nomeUsuarioLogado = $_SESSION['fotografo_nome'] ?? ($_SESSION['comprador_nome'] ?? 'Usuário');

$token = isset($_GET['e']) ? trim(filter_input(INPUT_GET, 'e', FILTER_SANITIZE_SPECIAL_CHARS)) : '';
if (empty($token)) { header("Location: index.php"); exit; }

$evento = null;
$expirado = false;
$fotosIniciais = [];
$isGratis = false;

try {
    $stmt = $pdo->prepare("SELECT e.id as evento_id, e.nome, e.preco_foto, e.expira_em, e.ativo, f.nome as fotografo_name, f.marca_dagua 
                           FROM eventos e 
                           JOIN fotografos f ON e.fotografo_id = f.id 
                           WHERE e.token_url = ? AND e.ativo = 1");
    $stmt->execute([$token]);
    $evento = $stmt->fetch();

    if ($evento) {
        $expirado = strtotime($evento['expira_em']) < time();
        $isGratis = (floatval($evento['preco_foto']) == 0); 
        
        // ==========================================
        // SOMA +1 VISITA COM PROTEÇÃO ANTI-SPAM (COOKIES)
        // ==========================================
        $idEventoAtual = $evento['evento_id'];
        $nomeCookie = "visitou_evento_" . $idEventoAtual;
        
        if (!isset($_COOKIE[$nomeCookie])) {
            $pdo->prepare("UPDATE eventos SET visitas = visitas + 1 WHERE id = ?")->execute([$idEventoAtual]);
            $tempoBloqueio = time() + (6 * 3600); 
            setcookie($nomeCookie, 'sim', $tempoBloqueio, '/');
        }
        
        $stmtFotos = $pdo->prepare("SELECT id FROM fotos_eventos WHERE evento_id = ? ORDER BY id DESC LIMIT 30");
        $stmtFotos->execute([$evento['evento_id']]);
        $fotosIniciais = $stmtFotos->fetchAll();
    } else {
        die("<h2 style='text-align:center; margin-top:50px; font-family:sans-serif;'>Catálogo indisponível.</h2>");
    }
} catch (Exception $e) { die("Erro ao carregar a galeria."); }

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
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?= htmlspecialchars($evento['nome']) ?> - Galeria Pic2Pic</title>
    
    <?php if ($adsenseAtivo === 1 && !empty($adsenseId)): ?>
        <script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=<?= htmlspecialchars($adsenseId) ?>" crossorigin="anonymous"></script>
    <?php endif; ?>

    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
        body { background-color: #f4f6f8; color: #333; display: flex; flex-direction: column; min-height: 100vh; padding-bottom: 80px; }
        
        .google-nav { width: 100%; display: flex; justify-content: space-between; align-items: center; padding: 15px 5%; background: #fff; border-bottom: 1px solid #eee; position: sticky; top: 0; z-index: 100; box-shadow: 0 2px 10px rgba(0,0,0,0.02); }
        .google-nav .brand { font-weight: 800; font-size: 18px; color: #111; text-decoration: none; display: flex; align-items: center; gap: 5px; }
        .btn-google-login { background-color: #1a73e8; color: #fff; border: none; padding: 10px 20px; border-radius: 6px; font-size: 14px; font-weight: 600; cursor: pointer; text-decoration: none; }
        .btn-user-logged { background-color: #f1f3f4; color: #3c4043; border: 1px solid #dadce0; padding: 8px 16px; border-radius: 20px; font-size: 13px; font-weight: 600; text-decoration: none; display: flex; align-items: center; gap: 6px; }

        .hero { text-align: center; padding: 40px 20px; background: #111; color: #fff; border-bottom: 4px solid #1a73e8; }
        .hero h1 { font-size: 26px; font-weight: 800; margin-bottom: 10px; }
        .hero p { font-size: 14px; color: #aaa; }
        
        .toolbar-galeria { display: flex; justify-content: center; margin: 30px 0; padding: 0 20px; gap: 10px; flex-wrap: wrap; }
        .btn-ia-search { background: linear-gradient(135deg, #1a73e8 0%, #8ab4f8 100%); color: #fff; border: none; padding: 16px 30px; border-radius: 50px; font-size: 16px; font-weight: bold; cursor: pointer; box-shadow: 0 6px 20px rgba(26,115,232,0.3); display: flex; align-items: center; gap: 10px; transition: 0.2s; }
        .btn-clear-filter { background: #e9ecef; color: #495057; border: none; padding: 16px 30px; border-radius: 50px; font-size: 16px; font-weight: bold; cursor: pointer; display: none; }

        .masonry-grid { column-count: 1; column-gap: 15px; padding: 0 5%; max-width: 1400px; margin: 0 auto; width: 100%; }
        @media (min-width: 500px) { .masonry-grid { column-count: 2; } }
        @media (min-width: 900px) { .masonry-grid { column-count: 3; } }
        @media (min-width: 1200px) { .masonry-grid { column-count: 4; } }

        .masonry-item { break-inside: avoid; margin-bottom: 15px; position: relative; border-radius: 12px; overflow: hidden; background: #e9ecef; cursor: pointer; border: 3px solid transparent; transition: transform 0.2s, border-color 0.2s; }
        
        /* ESTILO DO ANÚNCIO BLINDADO NA GALERIA */
        .masonry-ad { background: #fff; border: 1px dashed #dadce0; cursor: default; display: block; position: relative; padding: 10px; overflow: hidden; min-height: 270px; }
        .ad-badge { position: absolute; top: 10px; right: 10px; font-size: 10px; color: #9aa0a6; text-transform: uppercase; font-weight: bold; letter-spacing: 1px; z-index: 10; }

        .masonry-item img { width: 100%; display: block; border-radius: 9px; min-height: 150px; object-fit: cover; z-index: 1; position: relative; }
        
        .masonry-item.selected { border-color: #1a73e8; transform: scale(0.96); }
        .check-icon { position: absolute; top: 10px; right: 10px; background: #1a73e8; color: #fff; width: 26px; height: 26px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 13px; font-weight: bold; opacity: 0; transition: 0.2s; border: 2px solid #fff; z-index: 3; box-shadow: 0 2px 5px rgba(0,0,0,0.2); }
        .masonry-item.selected .check-icon { opacity: 1; }
        
        .preco-tag { position: absolute; bottom: 10px; left: 10px; background: rgba(0,0,0,0.75); color: #fff; font-size: 12px; padding: 5px 10px; border-radius: 6px; font-weight: bold; z-index: 3; pointer-events: none; backdrop-filter: blur(2px); }
        .preco-tag.gratis { background: rgba(40, 167, 69, 0.95); color: #fff; }

        .scroll-loading { text-align: center; padding: 20px; font-size: 14px; font-weight: bold; color: #666; display: none; width: 100%; }

        .cart-bar { position: fixed; bottom: -100px; left: 0; width: 100%; background: #fff; padding: 15px 5%; box-shadow: 0 -4px 20px rgba(0,0,0,0.1); display: flex; justify-content: space-between; align-items: center; transition: bottom 0.3s ease; z-index: 1000; border-top: 1px solid #eee; }
        .cart-bar.active { bottom: 0; }
        .cart-info { display: flex; flex-direction: column; }
        .cart-info span { font-size: 13px; color: #666; }
        .cart-info strong { font-size: 18px; color: #1a73e8; font-weight: 800; }
        .btn-checkout { background: #1a73e8; color: #fff; border: none; padding: 12px 25px; border-radius: 6px; font-size: 15px; font-weight: bold; cursor: pointer; transition: 0.2s; }

        /* MODAIS DE AUTENTICAÇÃO ATUALIZADOS */
        .modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); display: none; justify-content: center; align-items: center; z-index: 2000; padding: 20px; backdrop-filter: blur(5px); }
        .modal-box { background: #fff; width: 100%; max-width: 420px; padding: 30px; border-radius: 16px; position: relative; max-height: 95vh; overflow-y: auto; }
        .btn-close-modal { position: absolute; top: 15px; right: 15px; background: #f1f3f4; border: none; font-size: 20px; color: #333; cursor: pointer; width: 35px; height: 35px; border-radius: 50%; display: flex; align-items: center; justify-content: center; transition: 0.2s; }
        .btn-close-modal:hover { background: #e0e0e0; }
        
        .camera-viewport { width: 100%; max-width: 250px; height: 250px; background: #000; border-radius: 50%; overflow: hidden; margin: 0 auto 20px; border: 4px solid #1a73e8; }
        #video-preview { width: 100%; height: 100%; object-fit: cover; transform: scaleX(-1); }
        #canvas-capture { display: none; }
        .lgpd-container { background: #f8f9fa; padding: 12px; border-radius: 8px; margin-bottom: 20px; display: flex; gap: 10px; align-items: flex-start; }
        .btn-scan { background-color: #1a73e8; color: #fff; border: none; padding: 15px; border-radius: 50px; font-size: 15px; font-weight: bold; width: 100%; cursor: pointer; }
        .btn-scan:disabled { background-color: #ccc; cursor: not-allowed; }

        .form-group { margin-bottom: 16px; text-align: left; }
        .form-group label { display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px; color: #444; }
        .form-group input { width: 100%; padding: 12px; border: 1px solid #dadce0; border-radius: 6px; font-size: 14px; transition: 0.2s; }
        .form-group input:focus { border-color: #1a73e8; outline: none; }
        .btn-modal-submit { width: 100%; background: #1a73e8; color: #fff; border: none; padding: 14px; border-radius: 6px; font-size: 14px; font-weight: 600; cursor: pointer; margin-top: 10px; transition: 0.2s; }
        .btn-modal-submit:hover { background: #1557b0; }
        .btn-secundario { background: #f1f3f4; color: #333; margin-top: 8px; }
        .btn-secundario:hover { background: #e8eaed; }
        
        .toggle-text { text-align: center; margin-top: 20px; font-size: 13px; color: #666; }
        .toggle-text a { color: #1a73e8; text-decoration: none; font-weight: 600; cursor: pointer; }
        #panel-cadastro { display: none; }
        .alerta { padding: 12px; border-radius: 6px; margin-bottom: 15px; font-size: 13px; display: none; text-align: center; font-weight: 500; }
        .alerta.erro { background: #fce8e6; color: #c5221f; border: 1px solid #fad2cf; display: block; }
        .alerta.sucesso { background: #e6f4ea; color: #137333; border: 1px solid #ceead6; display: block; }
        
        .loading-screen { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(255,255,255,0.98); z-index: 3000; display: none; flex-direction: column; justify-content: center; align-items: center; text-align: center; padding: 20px; }
        .spinner-ia { border: 4px solid #f3f3f3; border-top: 4px solid #1a73e8; border-radius: 50%; width: 50px; height: 50px; animation: rodar 1s linear infinite; margin-bottom: 20px; }
        @keyframes rodar { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
    </style>
</head>
<body>

    <nav class="google-nav">
        <a href="index.php" class="brand">📸 Pic2Pic</a>
        <div>
            <?php if ($logado): ?>
                <a href="<?= $urlPainelDestino ?>" class="btn-user-logged">👤 <?= htmlspecialchars($nomeUsuarioLogado) ?></a>
            <?php else: ?>
                <button class="btn-google-login" onclick="abrirModalAuth()">Fazer Login</button>
            <?php endif; ?>
        </div>
    </nav>

    <section class="hero">
        <h1><?= htmlspecialchars($evento['nome']) ?></h1>
        <p>Catálogo por <?= htmlspecialchars($evento['fotografo_name']) ?></p>
    </section>

    <?php if ($expirado): ?>
        <div style="background: #f8d7da; color: #721c24; padding: 20px; margin: 30px auto; max-width: 800px; text-align: center; border-radius: 8px; font-weight: bold;">
            ⏰ Este catálogo foi encerrado.
        </div>
    <?php else: ?>
        
        <div class="toolbar-galeria">
            <button class="btn-ia-search" id="btn-open-cam" onclick="abrirModalCamera()">
                <span style="font-size: 20px;">🤖</span> Encontrar meu rosto com IA
            </button>
            <button class="btn-clear-filter" id="btn-clear-filter" onclick="limparFiltro()">Remover Filtro</button>
        </div>

        <div id="status-filtro" style="text-align:center; margin-bottom: 30px; font-size: 14px; color: #666; padding: 0 15px;">
            Ou selecione manualmente as fotos que deseja comprar abaixo:
        </div>

        <div class="masonry-grid" id="container-fotos">
            <?php if (empty($fotosIniciais)): ?>
                <div style="text-align: center; padding: 50px; width: 100%; color: #888;">
                    Nenhuma foto sincronizada ainda.
                </div>
            <?php else: ?>
                <?php foreach ($fotosIniciais as $index => $f): ?>
                    
                    <div class="masonry-item foto-galeria" data-id="<?= $f['id'] ?>" onclick="toggleFoto(this, <?= $f['id'] ?>)">
                        <img src="processadores/imagens/proxy_imagem.php?f=<?= $f['id'] ?>" loading="lazy" alt="Foto Evento">
                        <div class="check-icon">✓</div>
                        <?php if($isGratis): ?>
                            <div class="preco-tag gratis">Grátis</div>
                        <?php else: ?>
                            <div class="preco-tag">R$ <?= number_format(floatval($evento['preco_foto']), 2, ',', '.') ?></div>
                        <?php endif; ?>
                    </div>

                    <?php if ($adsenseAtivo === 1 && !empty($adsenseId) && ($index + 1) % 6 === 0): ?>
                        <div class="masonry-item masonry-ad">
                            <span class="ad-badge">Patrocinado</span>
                            <ins class="adsbygoogle"
                                 style="display:block !important; width:100% !important; min-width:250px !important; height:250px !important; margin-top:20px;"
                                 data-ad-client="<?= htmlspecialchars($adsenseId) ?>"
                                 data-ad-format="fluid"
                                 data-ad-layout="in-article"></ins>
                        </div>
                    <?php endif; ?>

                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div id="loading-scroll" class="scroll-loading">⏳ Carregando mais fotos...</div>

        <div class="cart-bar" id="cart-bar">
            <div class="cart-info">
                <span id="cart-count">0 fotos selecionadas</span>
                <strong id="cart-total">R$ 0,00</strong>
            </div>
            <button class="btn-checkout" onclick="enviarParaCheckout()">
                <?= $isGratis ? 'Avançar para Download' : 'Finalizar Pedido' ?>
            </button>
        </div>

    <?php endif; ?>

    <div id="modal-auth" class="modal-overlay" onclick="fecharModalAuth(event)">
        <div class="modal-box" onclick="event.stopPropagation()">
            <button class="btn-close-modal" onclick="fecharModalAuth('forced')">&times;</button>
            
            <div id="panel-login">
                <h3 style="margin-bottom: 5px; font-size: 22px; text-align: center;">Fazer login</h3>
                <p style="font-size: 13px; color: #5f6368; text-align: center; margin-bottom: 25px;">Acessar o Painel de Controle</p>
                <div id="msg-login" class="alerta"></div>
                <form id="form-login">
                    <div class="form-group"><label>E-mail</label><input type="email" id="login_email" required autocomplete="username" placeholder="Seu e-mail cadastrado"></div>
                    <div class="form-group" id="div-senha" style="display: none;"><label>Senha</label><input type="password" id="login_senha" autocomplete="current-password" placeholder="Sua senha de acesso"></div>
                    <button type="submit" id="btn-submit-login" class="btn-modal-submit">Continuar</button>
                    <button type="button" id="btn-voltar-email" class="btn-modal-submit btn-secundario" style="display: none;" onclick="voltarParaEmail()">Alterar E-mail</button>
                </form>
                <div class="toggle-text" id="footer-login">Novo por aqui? <a onclick="alternarAbasAuth('cadastro')">Criar conta grátis</a></div>
            </div>

            <div id="panel-cadastro">
                <h3 style="margin-bottom: 5px; font-size: 22px; text-align: center;">Criar sua conta</h3>
                <p style="font-size: 13px; color: #5f6368; text-align: center; margin-bottom: 25px;">Cadastre-se como Parceiro</p>
                <div id="msg-cadastro" class="alerta"></div>
                <form id="form-cadastro">
                    <div class="form-group"><label>Nome</label><input type="text" id="cad_nome" required autocomplete="name"></div>
                    <div class="form-group"><label>E-mail</label><input type="email" id="cad_email" required autocomplete="email"></div>
                    <div class="form-group"><label>Senha</label><input type="password" id="cad_senha" required minlength="6" autocomplete="new-password"></div>
                    <p style="font-size: 11px; color: #666; text-align: center; margin-top: 15px; line-height: 1.4;">
                        Ao clicar em "Criar Conta", você declara que leu e concorda com os nossos <a href="termos.php" target="_blank" style="color: #1a73e8; text-decoration: underline;">Termos de Uso</a>.
                    </p>
                    <button type="submit" class="btn-modal-submit">Criar Conta</button>
                </form>
                <div class="toggle-text">Já possui conta? <a onclick="alternarAbasAuth('login')">Fazer login</a></div>
            </div>
        </div>
    </div>

    <div id="modal-camera" class="modal-overlay" onclick="fecharModalCamera(event)">
        <div class="modal-box" onclick="event.stopPropagation()">
            <button class="btn-close-modal" onclick="fecharModalCamera('forced')">&times;</button>
            <div style="text-align: center; margin-bottom: 20px;">
                <h3 style="font-size: 20px;">Filtro Facial</h3>
            </div>
            <div class="camera-viewport">
                <video id="video-preview" autoplay playsinline></video>
                <canvas id="canvas-capture"></canvas>
            </div>
            <div class="lgpd-container">
                <input type="checkbox" id="lgpd-chk" onchange="document.getElementById('btn-scan').disabled = !this.checked;">
                <label for="lgpd-chk" style="font-size:12px; margin-top:2px;">Permito envio temporário para IA.</label>
            </div>
            <button class="btn-scan" id="btn-scan" disabled onclick="executarFiltroIA(<?= $evento['evento_id'] ?>)">📸 Mapear Meu Rosto</button>
        </div>
    </div>

    <div class="loading-screen" id="loading-screen">
        <div class="spinner-ia"></div>
        <h3 id="loading-text" style="margin-bottom: 8px; font-size: 22px; color: #1a73e8;">Cruzando dados matemáticos...</h3>
        <p style="font-size: 14px; color: #666;">A nossa Inteligência Artificial está a procurar o seu rosto.</p>
    </div>

    <script>
        const precoUnitario = <?= (float)$evento['preco_foto'] ?>;
        const precoFormatado = '<?= number_format(floatval($evento['preco_foto']), 2, ',', '.') ?>';
        let fotosSelecionadas = new Set();
        
        const isAdsenseAtivo = <?= $adsenseAtivo ?>;
        const clientAdsenseId = '<?= htmlspecialchars($adsenseId) ?>';
        let contadorFotosJS = <?= count($fotosIniciais) ?>; 
        
        let offset = 30; 
        let carregando = false;
        let temMaisFotos = true;
        let modoFiltroAtivo = false;
        let adObserver = null; 

        window.addEventListener('scroll', () => {
            if (carregando || !temMaisFotos || modoFiltroAtivo) return;
            if (window.innerHeight + window.scrollY >= document.body.offsetHeight - 500) {
                carregarMaisFotos();
            }
        });

        async function carregarMaisFotos() {
            carregando = true;
            document.getElementById('loading-scroll').style.display = 'block';

            try {
                const url = `processadores/eventos/carregar_fotos.php?e=<?= $token ?>&offset=${offset}`;
                const response = await fetch(url);
                const res = await response.json();

                if (res.sucesso) {
                    if (res.fotos.length === 0) {
                        temMaisFotos = false;
                        document.getElementById('loading-scroll').innerText = "Fim da galeria.";
                    } else {
                        renderizarFotosNoDOM(res.fotos);
                        offset += 30;
                    }
                }
            } catch(e) {}
            carregando = false;
            if (temMaisFotos) document.getElementById('loading-scroll').style.display = 'none';
        }

        function renderizarFotosNoDOM(fotos) {
            const container = document.getElementById('container-fotos');
            const htmlTagPreco = (precoUnitario === 0) ? '<div class="preco-tag gratis">Grátis</div>' : `<div class="preco-tag">R$ ${precoFormatado}</div>`;

            fotos.forEach(f => {
                const isSelected = fotosSelecionadas.has(f.id) ? ' selected' : '';
                const div = document.createElement('div');
                div.className = 'masonry-item foto-galeria' + isSelected;
                div.setAttribute('data-id', f.id);
                div.onclick = function() { toggleFoto(this, f.id); };
                div.innerHTML = `
                    <img src="${f.url}" loading="lazy" alt="Foto">
                    <div class="check-icon">✓</div>
                    ${htmlTagPreco}
                `;
                container.appendChild(div);

                contadorFotosJS++;
                if (isAdsenseAtivo === 1 && clientAdsenseId !== '' && contadorFotosJS % 6 === 0) {
                    const divAd = document.createElement('div');
                    divAd.className = 'masonry-item masonry-ad';
                    divAd.innerHTML = `
                        <span class="ad-badge">Patrocinado</span>
                        <ins class="adsbygoogle"
                             style="display:block !important; width:100% !important; min-width:250px !important; height:250px !important; margin-top:20px;"
                             data-ad-client="${clientAdsenseId}"
                             data-ad-format="fluid"
                             data-ad-layout="in-article"></ins>
                    `;
                    container.appendChild(divAd);
                    
                    if (adObserver) {
                        adObserver.observe(divAd);
                    } else {
                        try { (adsbygoogle = window.adsbygoogle || []).push({}); } catch(e){}
                    }
                }
            });
        }

        function toggleFoto(elemento, idFoto) {
            elemento.classList.toggle('selected');
            if (fotosSelecionadas.has(idFoto)) fotosSelecionadas.delete(idFoto);
            else fotosSelecionadas.add(idFoto);
            atualizarCarrinho();
        }

        function atualizarCarrinho() {
            const qtd = fotosSelecionadas.size;
            document.getElementById('cart-count').innerText = qtd + (qtd === 1 ? ' foto' : ' fotos');
            
            if (precoUnitario === 0) {
                document.getElementById('cart-total').innerText = 'Grátis';
                document.getElementById('cart-total').style.color = '#28a745';
            } else {
                document.getElementById('cart-total').innerText = 'R$ ' + (qtd * precoUnitario).toFixed(2).replace('.', ',');
                document.getElementById('cart-total').style.color = '#1a73e8';
            }
            if (qtd > 0) document.getElementById('cart-bar').classList.add('active');
            else document.getElementById('cart-bar').classList.remove('active');
        }

        function enviarParaCheckout() {
            if (fotosSelecionadas.size === 0) return;
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'checkout.php?e=<?= $token ?>';
            
            const inputFotos = document.createElement('input');
            inputFotos.type = 'hidden';
            inputFotos.name = 'fotos_selecionadas';
            inputFotos.value = JSON.stringify(Array.from(fotosSelecionadas));
            
            form.appendChild(inputFotos);
            document.body.appendChild(form);
            form.submit();
        }

        // ==========================================
        // SISTEMA DE AUTENTICAÇÃO (Sincronizado com index.php)
        // ==========================================
        let etapaLogin = 1;
        function voltarParaEmail() {
            etapaLogin = 1;
            document.getElementById('div-senha').style.display = 'none';
            document.getElementById('btn-voltar-email').style.display = 'none';
            document.getElementById('footer-login').style.display = 'block';
            document.getElementById('btn-submit-login').innerText = 'Continuar';
            document.getElementById('login_senha').required = false; document.getElementById('login_senha').value = '';
            document.getElementById('msg-login').style.display = 'none';
            const emailInput = document.getElementById('login_email');
            emailInput.readOnly = false; emailInput.style.backgroundColor = '#fff'; emailInput.focus();
        }

        document.getElementById('form-login').addEventListener('submit', async function(e) {
            e.preventDefault();
            const msg = document.getElementById('msg-login'); const btn = document.getElementById('btn-submit-login'); const emailInput = document.getElementById('login_email');
            msg.style.display = 'none';

            if (etapaLogin === 1) {
                btn.innerText = 'Verificando...'; btn.disabled = true;
                const formData = new FormData(); formData.append('email', emailInput.value);

                try {
                    const r = await fetch('processadores/autenticacao/verificar_email_login.php', { method: 'POST', body: formData });
                    const res = await r.json();

                    if (res.acao === 'pedir_senha' || res.acao === 'email_enviado') {
                        etapaLogin = 2;
                        document.getElementById('div-senha').style.display = 'block'; document.getElementById('login_senha').required = true; document.getElementById('login_senha').focus();
                        document.getElementById('btn-voltar-email').style.display = 'block'; document.getElementById('footer-login').style.display = 'none';
                        emailInput.readOnly = true; emailInput.style.backgroundColor = '#f1f3f4'; btn.innerText = 'Entrar';
                        
                        if(res.acao === 'email_enviado') {
                            msg.className = 'alerta sucesso'; msg.innerHTML = 'Enviamos uma <b>senha temporária</b> para o seu e-mail!'; msg.style.display = 'block';
                        }
                    } else { msg.className = 'alerta erro'; msg.innerText = res.erro || 'E-mail não encontrado. Crie uma conta grátis abaixo.'; msg.style.display = 'block'; }
                } catch(err) { msg.className = 'alerta erro'; msg.innerText = 'Erro ao conectar com o servidor.'; msg.style.display = 'block'; }
                btn.disabled = false; if (etapaLogin === 1) btn.innerText = 'Continuar';

            } else {
                btn.innerText = 'Entrando...'; btn.disabled = true;
                const formData = new FormData(); formData.append('email', emailInput.value); formData.append('senha', document.getElementById('login_senha').value);

                try {
                    const r = await fetch('processadores/autenticacao/validar_login.php', { method: 'POST', body: formData });
                    const res = await r.json();
                    if (res.sucesso) window.location.href = res.redirecionar || 'painel.php';
                    else { msg.className = 'alerta erro'; msg.innerText = res.erro; msg.style.display = 'block'; btn.innerText = 'Entrar'; btn.disabled = false; }
                } catch(err) { msg.className = 'alerta erro'; msg.innerText = 'Erro ao conectar.'; msg.style.display = 'block'; btn.innerText = 'Entrar'; btn.disabled = false; }
            }
        });

        function abrirModalAuth() { document.getElementById('modal-auth').style.display = 'flex'; voltarParaEmail(); }
        function fecharModalAuth(e) { if(!e || e.target === document.getElementById('modal-auth') || e === 'forced') document.getElementById('modal-auth').style.display = 'none'; }
        
        function alternarAbasAuth(aba) {
            document.getElementById('msg-login').style.display = 'none'; document.getElementById('msg-cadastro').style.display = 'none';
            if (aba === 'cadastro') { document.getElementById('panel-login').style.display = 'none'; document.getElementById('panel-cadastro').style.display = 'block'; } 
            else { document.getElementById('panel-cadastro').style.display = 'none'; document.getElementById('panel-login').style.display = 'block'; }
        }

        document.getElementById('form-cadastro').addEventListener('submit', async function(e) {
            e.preventDefault();
            const msg = document.getElementById('msg-cadastro'); const btn = e.target.querySelector('button');
            msg.style.display = 'none'; btn.innerText = 'Processando...';

            const formData = new FormData();
            formData.append('nome', document.getElementById('cad_nome').value);
            formData.append('email', document.getElementById('cad_email').value);
            formData.append('senha', document.getElementById('cad_senha').value);
            formData.append('confirmar_senha', document.getElementById('cad_senha').value);

            try {
                const r = await fetch('processadores/autenticacao/cadastrar_fotografo.php', { method: 'POST', body: formData });
                const res = await r.json();
                if (res.sucesso) { msg.className = 'alerta sucesso'; msg.innerText = 'Conta criada com sucesso!'; setTimeout(() => { window.location.href = 'painel.php'; }, 1000); } 
                else { msg.className = 'alerta erro'; msg.innerText = res.erro || 'Erro ao cadastrar.'; btn.innerText = 'Criar Conta'; }
            } catch(err) { msg.className = 'alerta erro'; msg.innerText = 'Erro de comunicação com o servidor.'; btn.innerText = 'Criar Conta'; }
        });

        // ==========================================
        // CÂMERA E INTELIGÊNCIA ARTIFICIAL
        // ==========================================
        let videoStream = null;
        async function abrirModalCamera() { document.getElementById('modal-camera').style.display = 'flex'; try { videoStream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: "user" }, audio: false }); document.getElementById('video-preview').srcObject = videoStream; } catch (err) { alert("Câmera bloqueada."); } }
        function fecharModalCamera(e) { if(!e || e.target === document.getElementById('modal-camera') || e === 'forced') { document.getElementById('modal-camera').style.display = 'none'; if (videoStream) videoStream.getTracks().forEach(track => track.stop()); } }

        async function executarFiltroIA(eventoId) {
            const video = document.getElementById('video-preview'); const canvas = document.getElementById('canvas-capture');
            canvas.width = video.videoWidth; canvas.height = video.videoHeight;
            canvas.getContext('2d').drawImage(video, 0, 0, canvas.width, canvas.height);
            const fotoBase64 = canvas.toDataURL('image/jpeg', 0.80);
            
            fecharModalCamera('forced'); 
            document.getElementById('loading-screen').style.display = 'flex'; 
            
            try {
                const response = await fetch('processadores/eventos/filtrar_rostos.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ evento_id: eventoId, selfie: fotoBase64 }) });
                const res = await response.json();
                
                setTimeout(() => {
                    if (res.sucesso) { 
                        aplicarFiltroVisual(res.fotos_encontradas); 
                        document.getElementById('loading-screen').style.display = 'none'; 
                    } else { 
                        alert("Aviso IA: " + res.erro); 
                        document.getElementById('loading-screen').style.display = 'none'; 
                    }
                }, 1500);

            } catch(e) { 
                alert("Erro ao conectar com IA."); 
                document.getElementById('loading-screen').style.display = 'none'; 
            }
        }

        function aplicarFiltroVisual(fotosEncontradas) {
            modoFiltroAtivo = true; const container = document.getElementById('container-fotos'); container.innerHTML = ''; 
            if (fotosEncontradas.length === 0) { document.getElementById('status-filtro').innerHTML = `<strong style="color:#dc3545;">Nenhuma foto sua encontrada.</strong>`; } 
            else { renderizarFotosNoDOM(fotosEncontradas); document.getElementById('status-filtro').innerHTML = `<strong style="color:#1a73e8;">Filtro IA Ativado:</strong> Encontramos você em ${fotosEncontradas.length} foto(s)!`; }
            document.getElementById('btn-open-cam').style.display = 'none'; document.getElementById('btn-clear-filter').style.display = 'inline-block'; document.getElementById('loading-scroll').style.display = 'none';
        }
        
        function limparFiltro() { location.reload(); }

        // ==========================================
        // ADSENSE OBSERVER (GALERIA) E LGPD
        // ==========================================
        document.addEventListener("DOMContentLoaded", function() {
            if ('IntersectionObserver' in window) {
                adObserver = new IntersectionObserver((entries, observer) => {
                    entries.forEach(entry => {
                        if (entry.isIntersecting) {
                            const insElement = entry.target.querySelector('ins.adsbygoogle');
                            if (insElement && !insElement.getAttribute('data-adsbygoogle-status')) {
                                try { (adsbygoogle = window.adsbygoogle || []).push({}); } catch (e) { }
                            }
                            observer.unobserve(entry.target);
                        }
                    });
                }, { root: null, rootMargin: '500px', threshold: 0 });

                document.querySelectorAll('.masonry-ad').forEach(card => adObserver.observe(card));
            } else {
                document.querySelectorAll('ins.adsbygoogle').forEach(() => {
                    try { (adsbygoogle = window.adsbygoogle || []).push({}); } catch(e){}
                });
            }
            
            if (!localStorage.getItem('pic2pic_cookies_aceitos')) { document.getElementById('lgpd-cookie-banner').style.display = 'flex'; }
        });

        function aceitarCookies() {
            localStorage.setItem('pic2pic_cookies_aceitos', 'sim');
            document.getElementById('lgpd-cookie-banner').style.opacity = '0';
            setTimeout(() => { document.getElementById('lgpd-cookie-banner').style.display = 'none'; }, 300);
        }
    </script>
</body>
</html>
