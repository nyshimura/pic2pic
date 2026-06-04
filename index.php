<?php
declare(strict_types=1);
session_start();

require_once 'configuracoes/conexao.php';

// 1. VERIFICA AUTENTICAÇÃO PARA O MENU DO TOPO
$logado = false;
$urlPainelDestino = '';
$nomeUsuarioLogado = '';

if (isset($_SESSION['fotografo_id'])) {
    $logado = true;
    $urlPainelDestino = 'painel.php';
    $nomeUsuarioLogado = $_SESSION['fotografo_nome'] ?? 'Fotógrafo';
} elseif (isset($_SESSION['comprador_id'])) {
    $logado = true;
    $urlPainelDestino = 'painel_comprador.php';
    $nomeUsuarioLogado = $_SESSION['comprador_nome'] ?? 'Cliente';
}

// 2. BUSCA EVENTOS PARA AS PRATELEIRAS (ESTILO NETFLIX)
$eventosLancamentos = [];
$eventosEmAlta = [];
$eventosPorCategoria = [];
$totalGeral = 0;

try {
    // A. Lançamentos (Eventos criados nas últimas 48 horas)
    $stmtLancamentos = $pdo->query("SELECT e.id, e.nome, e.token_url, e.preco_foto, f.nome as fotografo_name 
                                    FROM eventos e 
                                    JOIN fotografos f ON e.fotografo_id = f.id 
                                    WHERE e.ativo = 1 AND e.expira_em > NOW() 
                                      AND e.criado_em >= DATE_SUB(NOW(), INTERVAL 48 HOUR)
                                    ORDER BY e.id DESC LIMIT 15");
    $eventosLancamentos = $stmtLancamentos->fetchAll(PDO::FETCH_ASSOC);

    // B. Em Alta (Mais visitados. Exclui os que já estão em Lançamentos para não duplicar no topo)
    $idsLancamentos = array_column($eventosLancamentos, 'id');
    $notInClause = '';
    if (!empty($idsLancamentos)) {
        $notInClause = "AND e.id NOT IN (" . implode(',', $idsLancamentos) . ")";
    }
    
    $stmtEmAlta = $pdo->query("SELECT e.id, e.nome, e.token_url, e.preco_foto, f.nome as fotografo_name 
                               FROM eventos e 
                               JOIN fotografos f ON e.fotografo_id = f.id 
                               WHERE e.ativo = 1 AND e.expira_em > NOW() $notInClause
                               ORDER BY e.visitas DESC, e.id DESC LIMIT 15");
    $eventosEmAlta = $stmtEmAlta->fetchAll(PDO::FETCH_ASSOC);

    // C. Categorias (Puxa todos e agrupa. A última categoria a receber evento fica no topo)
    $stmtCat = $pdo->query("SELECT e.id, e.nome, e.token_url, e.preco_foto, e.categoria, f.nome as fotografo_name 
                            FROM eventos e 
                            JOIN fotografos f ON e.fotografo_id = f.id 
                            WHERE e.ativo = 1 AND e.expira_em > NOW() 
                            ORDER BY e.id DESC");
    $todosEventos = $stmtCat->fetchAll(PDO::FETCH_ASSOC);

    foreach ($todosEventos as $ev) {
        $cat = !empty($ev['categoria']) ? $ev['categoria'] : 'Outros';
        $eventosPorCategoria[$cat][] = $ev;
        $totalGeral++;
    }
} catch (Exception $e) {
    // Falha silenciosa para manter o layout no ar em caso de erro
}

// 3. BUSCA CONFIGURAÇÕES DO GOOGLE ADSENSE
$adsenseAtivo = 0;
$adsenseId = '';
try {
    $stmtAd = $pdo->query("SELECT adsense_client_id, adsense_ativo FROM configuracoes_sistema WHERE id = 1");
    $sysConfig = $stmtAd->fetch(PDO::FETCH_ASSOC);
    if ($sysConfig) {
        $adsenseAtivo = (int)($sysConfig['adsense_ativo'] ?? 0);
        $adsenseId = $sysConfig['adsense_client_id'] ?? '';
    }
} catch (Exception $e) {
    // Falha silenciosa
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Pic2Pic - Vitrine de Eventos</title>
    
    <?php if ($adsenseAtivo === 1 && !empty($adsenseId)): ?>
        <script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=<?= htmlspecialchars($adsenseId) ?>" crossorigin="anonymous"></script>
    <?php endif; ?>

    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
        body { background-color: #f4f6f8; color: #333; display: flex; flex-direction: column; min-height: 100vh; overflow-x: hidden; }
        
        .google-nav { width: 100%; display: flex; justify-content: space-between; align-items: center; padding: 15px 5%; background: #fff; border-bottom: 1px solid #eee; position: sticky; top: 0; z-index: 100; box-shadow: 0 2px 10px rgba(0,0,0,0.02); }
        .google-nav .brand { font-weight: 800; font-size: 18px; color: #111; text-decoration: none; display: flex; align-items: center; gap: 5px; }
        
        .btn-google-login { background-color: #1a73e8; color: #fff; border: none; padding: 10px 20px; border-radius: 6px; font-size: 14px; font-weight: 600; cursor: pointer; transition: 0.2s; text-decoration: none; }
        .btn-google-login:hover { background-color: #1557b0; box-shadow: 0 2px 6px rgba(26,115,232,0.3); }
        .btn-user-logged { background-color: #f1f3f4; color: #3c4043; border: 1px solid #dadce0; padding: 8px 16px; border-radius: 20px; font-size: 13px; font-weight: 600; text-decoration: none; display: flex; align-items: center; gap: 6px; transition: 0.2s; }
        .btn-user-logged:hover { background-color: #e8eaed; }

        .hero { text-align: center; padding: 50px 20px 30px; background: #fff; border-bottom: 1px solid #eee; margin-bottom: 30px; }
        .hero h1 { font-size: 28px; font-weight: 800; color: #111; margin-bottom: 10px; }
        .hero p { font-size: 15px; color: #666; max-width: 500px; margin: 0 auto; line-height: 1.5; }

        .vitrine-container { max-width: 1200px; margin: 0 auto; width: 100%; flex-grow: 1; padding-bottom: 50px; }
        .prateleira-section { margin-bottom: 40px; width: 100%; }
        .prateleira-title { font-size: 20px; font-weight: 800; color: #111; margin-bottom: 15px; padding: 0 5%; display: flex; align-items: center; gap: 8px; }
        
        .prateleira-row { 
            display: flex; gap: 20px; padding: 10px 5% 20px 5%; 
            overflow-x: auto; scroll-snap-type: x mandatory; 
            scroll-behavior: smooth; -webkit-overflow-scrolling: touch; 
        }
        .prateleira-row::-webkit-scrollbar { display: none; }
        
        .card-evento { flex: 0 0 290px; min-width: 290px; max-width: 290px; scroll-snap-align: start; background: #fff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.05); transition: transform 0.2s, box-shadow 0.2s; cursor: pointer; display: flex; flex-direction: column; text-decoration: none; color: inherit; height: 315px; }
        .card-evento:hover { transform: translateY(-5px); box-shadow: 0 8px 25px rgba(0,0,0,0.1); }
        .card-thumb { width: 100%; height: 180px; background-color: #ddd; object-fit: cover; }
        .card-body { padding: 20px; display: flex; flex-direction: column; flex-grow: 1; }
        .card-title { font-size: 18px; font-weight: 700; color: #111; margin-bottom: 5px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .card-fotografo { font-size: 13px; color: #666; margin-bottom: 15px; display: flex; align-items: center; gap: 5px; }
        .card-btn { margin-top: auto; background-color: #e8f0fe; color: #1a73e8; border: none; padding: 12px; border-radius: 6px; font-size: 14px; font-weight: 700; transition: 0.2s; text-align: center; }
        .card-evento:hover .card-btn { background-color: #1a73e8; color: #fff; }

        .card-ad { 
            flex: 0 0 290px; 
            width: 290px;
            min-width: 290px; 
            max-width: 290px;
            scroll-snap-align: start; 
            background: #fafafa; 
            border-radius: 12px; 
            border: 1px dashed #dadce0; 
            position: relative; 
            padding: 10px; 
            display: block; 
            height: 315px; 
            overflow: hidden; 
        }
        .ad-badge { position: absolute; top: 12px; right: 15px; font-size: 10px; color: #9aa0a6; text-transform: uppercase; font-weight: bold; letter-spacing: 1px; z-index: 10; }
        
        .ad-wrapper { 
            width: 268px; 
            min-width: 268px;
            height: 270px;
            min-height: 270px;
            margin: 20px auto 0 auto; 
            display: block; 
            overflow: hidden; 
        }

        .empty-state { text-align: center; padding: 50px 20px; color: #888; width: 100%; }

        .modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); display: none; justify-content: center; align-items: center; z-index: 2000; padding: 20px; backdrop-filter: blur(3px); }
        .modal-box { background: #fff; width: 100%; max-width: 420px; padding: 30px; border-radius: 16px; box-shadow: 0 10px 30px rgba(0,0,0,0.2); position: relative; animation: popIn 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275); max-height: 95vh; overflow-y: auto; }
        @keyframes popIn { from { opacity: 0; transform: scale(0.9); } to { opacity: 1; transform: scale(1); } }
        .btn-close-modal { position: absolute; top: 15px; right: 15px; background: #f1f3f4; border: none; font-size: 20px; color: #333; cursor: pointer; width: 35px; height: 35px; border-radius: 50%; display: flex; align-items: center; justify-content: center; transition: 0.2s; }
        .btn-close-modal:hover { background: #e0e0e0; }

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
    </style>
</head>
<body>

    <nav class="google-nav">
        <a href="index.php" class="brand">📸 Pic2Pic</a>
        <div>
            <?php if ($logado): ?>
                <a href="<?= $urlPainelDestino ?>" class="btn-user-logged">
                    <span>👤 <?= htmlspecialchars($nomeUsuarioLogado) ?></span>
                    <small style="color:#1a73e8;">(Painel)</small>
                </a>
            <?php else: ?>
                <button class="btn-google-login" onclick="abrirModalAuth()">Fazer Login</button>
            <?php endif; ?>
        </div>
    </nav>

    <section class="hero">
        <h1>Encontre suas melhores memórias</h1>
        <p>Selecione o seu evento abaixo, acesse a galeria completa e utilize nossa Inteligência Artificial para filtrar suas fotos.</p>
    </section>

    <main class="vitrine-container">
        <?php if ($totalGeral === 0): ?>
            <div class="empty-state">
                <h3 style="font-size: 20px; margin-bottom: 10px; color: #111;">Nenhum evento no momento.</h3>
                <p>Os fotógrafos ainda não publicaram novos catálogos na plataforma.</p>
            </div>
        <?php else: ?>

            <?php if (!empty($eventosLancamentos)): ?>
                <div class="prateleira-section">
                    <h2 class="prateleira-title">🔥 Lançamentos Recentes</h2>
                    <div class="prateleira-row">
                        <?php 
                        $qtdCards = count($eventosLancamentos);
                        foreach ($eventosLancamentos as $index => $ev): 
                        ?>
                            <a href="galeria.php?e=<?= $ev['token_url'] ?>" class="card-evento">
                                <img src="processadores/imagens/thumb_evento.php?e=<?= $ev['token_url'] ?>" alt="Capa" class="card-thumb" loading="lazy">
                                <div class="card-body">
                                    <h3 class="card-title" title="<?= htmlspecialchars($ev['nome']) ?>"><?= htmlspecialchars($ev['nome']) ?></h3>
                                    <div class="card-fotografo">📸 <?= htmlspecialchars($ev['fotografo_name']) ?></div>
                                    <div class="card-btn">Ver Fotos</div>
                                </div>
                            </a>

                            <?php if ($adsenseAtivo === 1 && !empty($adsenseId) && (($index + 1) % 4 === 0 || ($qtdCards < 4 && $index === $qtdCards - 1))): ?>
                                <div class="card-ad">
                                    <span class="ad-badge">Patrocinado</span>
                                    <div class="ad-wrapper">
                                        <ins class="adsbygoogle" style="display:block !important; width:268px !important; min-width:268px !important; height:270px !important;" data-ad-client="<?= htmlspecialchars($adsenseId) ?>"></ins>
                                    </div>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (!empty($eventosEmAlta)): ?>
                <div class="prateleira-section">
                    <h2 class="prateleira-title">⭐ Em Alta</h2>
                    <div class="prateleira-row">
                        <?php 
                        $qtdCards = count($eventosEmAlta);
                        foreach ($eventosEmAlta as $index => $ev): 
                        ?>
                            <a href="galeria.php?e=<?= $ev['token_url'] ?>" class="card-evento">
                                <img src="processadores/imagens/thumb_evento.php?e=<?= $ev['token_url'] ?>" alt="Capa" class="card-thumb" loading="lazy">
                                <div class="card-body">
                                    <h3 class="card-title" title="<?= htmlspecialchars($ev['nome']) ?>"><?= htmlspecialchars($ev['nome']) ?></h3>
                                    <div class="card-fotografo">📸 <?= htmlspecialchars($ev['fotografo_name']) ?></div>
                                    <div class="card-btn">Ver Fotos</div>
                                </div>
                            </a>

                            <?php if ($adsenseAtivo === 1 && !empty($adsenseId) && (($index + 1) % 4 === 0 || ($qtdCards < 4 && $index === $qtdCards - 1))): ?>
                                <div class="card-ad">
                                    <span class="ad-badge">Patrocinado</span>
                                    <div class="ad-wrapper">
                                        <ins class="adsbygoogle" style="display:block !important; width:268px !important; min-width:268px !important; height:270px !important;" data-ad-client="<?= htmlspecialchars($adsenseId) ?>"></ins>
                                    </div>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php foreach ($eventosPorCategoria as $categoriaNome => $eventosCategoria): ?>
                <div class="prateleira-section">
                    <h2 class="prateleira-title">📁 <?= htmlspecialchars($categoriaNome) ?></h2>
                    <div class="prateleira-row">
                        <?php 
                        $qtdCards = count($eventosCategoria);
                        foreach ($eventosCategoria as $index => $ev): 
                        ?>
                            <a href="galeria.php?e=<?= $ev['token_url'] ?>" class="card-evento">
                                <img src="processadores/imagens/thumb_evento.php?e=<?= $ev['token_url'] ?>" alt="Capa" class="card-thumb" loading="lazy">
                                <div class="card-body">
                                    <h3 class="card-title" title="<?= htmlspecialchars($ev['nome']) ?>"><?= htmlspecialchars($ev['nome']) ?></h3>
                                    <div class="card-fotografo">📸 <?= htmlspecialchars($ev['fotografo_name']) ?></div>
                                    <div class="card-btn">Ver Fotos</div>
                                </div>
                            </a>

                            <?php if ($adsenseAtivo === 1 && !empty($adsenseId) && (($index + 1) % 4 === 0 || ($qtdCards < 4 && $index === $qtdCards - 1))): ?>
                                <div class="card-ad">
                                    <span class="ad-badge">Patrocinado</span>
                                    <div class="ad-wrapper">
                                        <ins class="adsbygoogle" style="display:block !important; width:268px !important; min-width:268px !important; height:270px !important;" data-ad-client="<?= htmlspecialchars($adsenseId) ?>"></ins>
                                    </div>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>

        <?php endif; ?>
    </main>

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
                <p style="font-size: 13px; color: #5f6368; text-align: center; margin-bottom: 25px;">Cadastre-se como Fotógrafo Parceiro</p>
                <div id="msg-cadastro" class="alerta"></div>
                <form id="form-cadastro">
                    <div class="form-group"><label>Nome do Estúdio</label><input type="text" id="cad_nome" required autocomplete="name"></div>
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

    <div id="lgpd-cookie-banner" style="display: none; position: fixed; bottom: 20px; left: 50%; transform: translateX(-50%); width: 90%; max-width: 700px; background: #fff; padding: 20px; border-radius: 12px; box-shadow: 0 10px 40px rgba(0,0,0,0.15); z-index: 9999; border: 1px solid #dadce0; align-items: center; justify-content: space-between; gap: 20px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;">
        <div style="font-size: 13px; color: #444; line-height: 1.5; flex-grow: 1;">
            <strong style="color: #111; font-size: 15px;">Nós usamos cookies 🍪</strong><br>
            A Pic2Pic utiliza cookies para garantir a segurança, melhorar a sua experiência e personalizar a publicidade. Ao continuar a navegar, você concorda com a nossa política.
        </div>
        <button onclick="aceitarCookies()" style="background: #1a73e8; color: #fff; border: none; padding: 12px 24px; border-radius: 6px; font-weight: 600; font-size: 14px; cursor: pointer; white-space: nowrap; transition: background 0.2s; height: fit-content;">
            Entendi
        </button>
    </div>

    <script>
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

        document.addEventListener("DOMContentLoaded", function() {
            if (!localStorage.getItem('pic2pic_cookies_aceitos')) {
                document.getElementById('lgpd-cookie-banner').style.display = 'flex';
            }
        });

        function aceitarCookies() {
            localStorage.setItem('pic2pic_cookies_aceitos', 'sim');
            document.getElementById('lgpd-cookie-banner').style.opacity = '0';
            setTimeout(() => {
                document.getElementById('lgpd-cookie-banner').style.display = 'none';
            }, 300);
        }
        
        // ==========================================
        // SOLUÇÃO DEFINITIVA: ADSENSE OBSERVER (RAIO LONGO)
        // Acorda o anúncio 500px antes de ele entrar na tela!
        // ==========================================
        document.addEventListener("DOMContentLoaded", function() {
            const adCards = document.querySelectorAll('.card-ad');
            
            if ('IntersectionObserver' in window) {
                const adObserver = new IntersectionObserver((entries, observer) => {
                    entries.forEach(entry => {
                        if (entry.isIntersecting) {
                            const insElement = entry.target.querySelector('ins.adsbygoogle');
                            
                            // Verifica se o anúncio já não foi injetado pelo Google
                            if (insElement && !insElement.getAttribute('data-adsbygoogle-status')) {
                                try {
                                    (adsbygoogle = window.adsbygoogle || []).push({});
                                } catch (e) { }
                            }
                            // Desliga o radar para este card, pois o anúncio já carregou
                            observer.unobserve(entry.target);
                        }
                    });
                }, { 
                    root: null, // Olha para a janela inteira do telemóvel
                    rootMargin: '500px 500px 500px 500px', // Acorda o Google quando faltam 500px para o card aparecer
                    threshold: 0 
                });

                // Inicia o radar para todos os blocos de anúncio
                adCards.forEach(card => adObserver.observe(card));
            } else {
                // Fallback para navegadores muito antigos
                document.querySelectorAll('ins.adsbygoogle').forEach(() => {
                    try { (adsbygoogle = window.adsbygoogle || []).push({}); } catch(e){}
                });
            }
        });
    </script>
</body>
</html>
