<?php
declare(strict_types=1);
session_start();

if (!isset($_SESSION['fotografo_id'])) {
    header("Location: index.php");
    exit;
}

require_once 'configuracoes/conexao.php';

$idFotografo = $_SESSION['fotografo_id'];
$nomeFotografo = htmlspecialchars($_SESSION['fotografo_nome']);
$tela = $_GET['tela'] ?? 'eventos'; 

// Busca os dados globais do fotógrafo para o Hub
$stmt = $pdo->prepare("SELECT email, mp_public_key, mp_access_token, foto_perfil, is_admin FROM fotografos WHERE id = ?");
$stmt->execute([$idFotografo]);
$dados = $stmt->fetch();

$fotoPerfil = $dados['foto_perfil'] ? $dados['foto_perfil'] : 'https://ui-avatars.com/api/?name=' . urlencode($nomeFotografo) . '&background=random';
$isAdmin = (bool) $dados['is_admin'];
$temMercadoPago = !empty($dados['mp_access_token']);

// Proteção para não carregar arquivos que não existem
$telasPermitidas = ['eventos', 'financeiro', 'marca', 'historico', 'admin'];
if (!in_array($tela, $telasPermitidas)) {
    $tela = 'eventos';
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Pic2Pic</title>
    <style>
        /* ESTILOS GLOBAIS DO HUB */
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background-color: #f4f6f8; color: #333; overflow-x: hidden; }
        .app-container { display: flex; min-height: 100vh; width: 100%; position: relative; }
        .sidebar-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 998; opacity: 0; visibility: hidden; transition: 0.3s ease; }
        .sidebar-overlay.active { opacity: 1; visibility: visible; }
        .sidebar { position: fixed; top: 0; left: -260px; width: 260px; height: 100vh; background-color: #111; color: #fff; display: flex; flex-direction: column; z-index: 999; transition: left 0.3s ease; overflow-y: auto; }
        .sidebar.open { left: 0; }
        .sidebar-header { padding: 25px 20px; font-size: 20px; font-weight: bold; border-bottom: 1px solid #222; text-align: center; letter-spacing: 1px; display: flex; justify-content: space-between; align-items: center; }
        .btn-fechar-menu { background: none; border: none; color: #fff; font-size: 24px; cursor: pointer; display: block; }
        .nav-links { list-style: none; padding: 20px 0; flex-grow: 1; }
        .nav-links li { margin-bottom: 5px; }
        .nav-links a { display: block; padding: 12px 20px; color: #aaa; text-decoration: none; transition: 0.2s; font-size: 15px; }
        .nav-links a:hover, .nav-links a.active { background-color: #222; color: #fff; border-left: 4px solid #007bff; }
        
        .main-content { flex-grow: 1; padding: 20px; width: 100%; max-width: 100vw; transition: margin-left 0.3s ease; }
        .topbar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; background: #fff; padding: 12px 15px; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.04); flex-wrap: wrap; gap: 10px; }
        .topbar-left { display: flex; align-items: center; gap: 15px; }
        .btn-menu { background: none; border: none; font-size: 24px; color: #333; cursor: pointer; padding: 5px; }
        .topbar h2 { font-size: 18px; font-weight: 600; margin: 0; }
        
        /* NOVO ESTILO DO MENU DE USUÁRIO NO TOPO */
        .user-menu-container { display: flex; align-items: center; gap: 15px; }
        .user-menu { display: flex; align-items: center; gap: 10px; cursor: pointer; padding: 5px 10px; border-radius: 8px; transition: 0.2s; }
        .user-menu:hover { background: #f1f3f4; }
        .avatar-img { width: 38px; height: 38px; border-radius: 50%; object-fit: cover; border: 2px solid #eee; }
        .user-info-text { text-align: right; line-height: 1.2; display: none; }
        .user-info-text span { display: block; font-size: 13px; font-weight: 700; }
        .user-info-text small { font-size: 11px; color: #1a73e8; font-weight: bold; }
        .btn-sair { background: #fce8e6; color: #c5221f; border: none; padding: 8px 15px; border-radius: 6px; font-size: 13px; font-weight: bold; cursor: pointer; text-decoration: none; transition: 0.2s; display: flex; align-items: center; }
        .btn-sair:hover { background: #fad2cf; }
        
        /* CLASSES REUTILIZADAS NOS MÓDULOS */
        .card { background: #fff; padding: 20px; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.02); margin-bottom: 20px; width: 100%; overflow: hidden; }
        .card-header { margin-bottom: 20px; }
        .card-header h3 { font-size: 17px; margin-bottom: 5px; }
        .card-header p { font-size: 13px; color: #666; }
        .card-header-flex { display: flex; flex-direction: column; gap: 15px; }
        .btn-acao { background-color: #007bff; color: #fff; border: none; padding: 12px 18px; border-radius: 8px; font-size: 14px; font-weight: 600; cursor: pointer; transition: 0.2s; width: 100%; }
        .btn-acao:hover { background-color: #0056b3; }
        .btn-cancelar { background: #e9ecef; color: #495057; border: none; padding: 12px 18px; border-radius: 8px; font-size: 14px; font-weight: 600; cursor: pointer; width: 100%; }
        .btn-danger { background-color: #dc3545; color: white; }
        .form-group { margin-bottom: 16px; }
        .form-group label { display: block; font-size: 13px; font-weight: 600; color: #444; margin-bottom: 6px; text-align: left; }
        .form-group input { width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 8px; font-size: 14px; background-color: #fafafa; }
        .alerta { padding: 12px; border-radius: 8px; margin-top: 15px; font-size: 13px; display: none; font-weight: 500; text-align: center; }
        .alerta.sucesso { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; display: block; }
        .alerta.erro { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; display: block; }
        .modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); display: flex; align-items: flex-end; justify-content: center; z-index: 2000; display: none; padding: 0; backdrop-filter: blur(3px); }
        .modal-box { background: #fff; padding: 30px 25px; border-radius: 16px 16px 0 0; width: 100%; max-width: 450px; max-height: 90vh; overflow-y: auto; box-shadow: 0 -4px 24px rgba(0,0,0,0.15); animation: slideUp 0.3s ease-out; position: relative; }
        .btn-close-modal { position: absolute; top: 15px; right: 15px; background: #f1f3f4; border: none; font-size: 20px; color: #333; cursor: pointer; width: 35px; height: 35px; border-radius: 50%; display: flex; align-items: center; justify-content: center; transition: 0.2s; }
        .btn-close-modal:hover { background: #e0e0e0; }
        .modal-footer { display: flex; flex-direction: column-reverse; gap: 10px; margin-top: 25px; }

        @keyframes slideUp { from { transform: translateY(100%); } to { transform: translateY(0); } }
        @media (min-width: 768px) {
            .btn-menu, .btn-fechar-menu { display: none; }
            .sidebar-overlay { display: none !important; }
            .sidebar { position: static; height: auto; min-height: 100vh; width: 250px; flex-shrink: 0; }
            .sidebar-header { justify-content: center; }
            .main-content { padding: 40px; }
            .topbar { padding: 15px 25px; margin-bottom: 40px; }
            .user-info-text { display: block; }
            .card-header-flex { flex-direction: row; justify-content: space-between; align-items: center; }
            .btn-acao, .btn-cancelar { width: auto; }
            .modal-overlay { align-items: center; padding: 15px; }
            .modal-box { border-radius: 16px; animation: fadeIn 0.2s ease-out; }
            .modal-footer { flex-direction: row; justify-content: flex-end; }
            .form-row { display: flex; gap: 15px; }
            .form-row .form-group { flex: 1; }
        }
        @keyframes fadeIn { from { opacity: 0; transform: scale(0.95); } to { opacity: 1; transform: scale(1); } }
    </style>
</head>
<body>

    <div class="app-container">
        <div class="sidebar-overlay" id="sidebar-overlay" onclick="toggleSidebar()"></div>

        <aside class="sidebar" id="sidebar">
            <div class="sidebar-header">📸 Pic2Pic <button class="btn-fechar-menu" onclick="toggleSidebar()">&times;</button></div>
            <ul class="nav-links">
                <li><a href="painel.php?tela=eventos" class="<?= $tela === 'eventos' ? 'active' : '' ?>">Meus Eventos</a></li>
                <li><a href="painel.php?tela=financeiro" class="<?= $tela === 'financeiro' ? 'active' : '' ?>">Pagamentos (MP)</a></li>
                <li><a href="painel.php?tela=marca" class="<?= $tela === 'marca' ? 'active' : '' ?>">Identidade Visual</a></li>
                <li><a href="painel.php?tela=historico" class="<?= $tela === 'historico' ? 'active' : '' ?>">Histórico de Logs</a></li>
                
                <li style="margin-top: 15px; border-top: 1px solid #333; padding-top: 15px;">
                    <a href="minhas_compras.php" style="color: #4caf50;">🛒 Minhas Compras</a>
                </li>

                <?php if ($isAdmin): ?>
                    <li style="margin-top: 5px;">
                        <a href="painel.php?tela=admin" class="<?= $tela === 'admin' ? 'active' : '' ?>" style="color: #ffc107;">⚙️ Painel Admin</a>
                    </li>
                <?php endif; ?>
            </ul>
        </aside>

        <main class="main-content">
            <header class="topbar">
                <div class="topbar-left">
                    <button class="btn-menu" onclick="toggleSidebar()">☰</button>
                    <h2>Dashboard</h2>
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

            <?php if (!$temMercadoPago && $tela !== 'financeiro'): ?>
                <div style="background-color: #fff3cd; color: #856404; padding: 15px 20px; margin-bottom: 25px; border-radius: 8px; border-left: 5px solid #ffeeba; display: flex; align-items: center; gap: 15px; box-shadow: 0 2px 4px rgba(0,0,0,0.05);">
                    <span style="font-size: 24px;">⚠️</span>
                    <div style="font-size: 14px; line-height: 1.4;">
                        <strong>Aviso de Faturamento:</strong> Você ainda não conectou as chaves do Mercado Pago. Até que isso seja feito, todos os seus catálogos <strong>serão gratuitos</strong>. Vá até a aba <a href="painel.php?tela=financeiro" style="color: #856404; text-decoration: underline; font-weight: bold;">Pagamentos (MP)</a> para resolver.
                    </div>
                </div>
            <?php endif; ?>

            <?php 
                $arquivo_modulo = "modulos/{$tela}.php";
                if (file_exists($arquivo_modulo)) {
                    include $arquivo_modulo;
                } else {
                    echo "<div class='card'><div class='empty-state'><h3>Módulo em construção...</h3></div></div>";
                }
            ?>

        </main>
    </div>

    <div id="modal-perfil" class="modal-overlay" onclick="fecharModalPerfil(event)">
        <div class="modal-box" onclick="event.stopPropagation()">
            <button class="btn-close-modal" onclick="fecharModalPerfil('forced')">&times;</button>
            <h3 style="color: #1a73e8; margin-bottom: 5px;">Dados do Fotógrafo</h3>
            <p style="font-size: 13px; color: #666; margin-bottom: 20px;">Atualize o nome do seu negócio e a sua senha.</p>
            
            <div id="msg-perfil" class="alerta"></div>
            
            <form id="form-perfil-fotografo">
                <div class="form-group">
                    <label>E-mail de Acesso</label>
                    <input type="email" value="<?= htmlspecialchars($dados['email']) ?>" disabled style="background: #e9ecef; color: #666; cursor: not-allowed;" title="Não é possível alterar o e-mail de login.">
                </div>
                <div class="form-group">
                    <label>Nome do Fotógrafo / Estúdio</label>
                    <input type="text" id="perf-nome" value="<?= $nomeFotografo ?>" required>
                </div>
                
                <hr style="margin: 20px 0; border: 0; border-top: 1px solid #eee;">
                <p style="font-size: 12px; color: #888; text-align: left; margin-bottom: 15px;">Para alterar a sua senha, preencha os campos abaixo. Deixe em branco para manter a atual.</p>

                <div class="form-group">
                    <label>Nova Senha</label>
                    <input type="password" id="perf-senha" placeholder="Deixe em branco para não alterar">
                </div>
                <div class="form-group">
                    <label>Confirmar Nova Senha</label>
                    <input type="password" id="perf-senha-confirma" placeholder="Repita a nova senha">
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn-cancelar" onclick="fecharModalPerfil('forced')">Cancelar</button>
                    <button type="submit" id="btn-salvar-perf" class="btn-acao">Salvar Alterações</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Scripts Globais do Hub
        function toggleSidebar() { document.getElementById('sidebar').classList.toggle('open'); document.getElementById('sidebar-overlay').classList.toggle('active'); }
        function abrirModalPerfil() { document.getElementById('modal-perfil').style.display = 'flex'; }
        function fecharModalPerfil(e) { if(!e || e.target === document.getElementById('modal-perfil') || e === 'forced') document.getElementById('modal-perfil').style.display = 'none'; }
        
        // Motor de Atualização do Perfil do Fotógrafo
        document.getElementById('form-perfil-fotografo').addEventListener('submit', async function(e) {
            e.preventDefault();
            const msg = document.getElementById('msg-perfil');
            const btn = document.getElementById('btn-salvar-perf');
            const nome = document.getElementById('perf-nome').value.trim();
            const senha = document.getElementById('perf-senha').value;
            const senhaConfirma = document.getElementById('perf-senha-confirma').value;

            msg.style.display = 'none';

            // Validação de senhas antes de enviar ao servidor
            if (senha || senhaConfirma) {
                if (senha !== senhaConfirma) {
                    msg.className = 'alerta erro'; msg.innerText = 'As senhas digitadas não coincidem.'; msg.style.display = 'block'; return;
                }
                if (senha.length < 6) {
                    msg.className = 'alerta erro'; msg.innerText = 'A nova senha deve ter pelo menos 6 caracteres.'; msg.style.display = 'block'; return;
                }
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
                    msg.className = 'alerta sucesso'; 
                    msg.innerText = 'Dados atualizados com sucesso!'; 
                    msg.style.display = 'block';
                    
                    document.getElementById('perf-senha').value = '';
                    document.getElementById('perf-senha-confirma').value = '';
                    
                    setTimeout(() => location.reload(), 1500);
                } else {
                    msg.className = 'alerta erro'; msg.innerText = res.erro || 'Falha ao atualizar dados.'; msg.style.display = 'block';
                }
            } catch(error) {
                msg.className = 'alerta erro'; msg.innerText = 'Erro de comunicação com o servidor.'; msg.style.display = 'block';
            }
            btn.innerText = 'Salvar Alterações'; btn.disabled = false;
        });
    </script>
</body>
</html>