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

$stmt = $pdo->prepare("SELECT email, mp_public_key, mp_access_token, foto_perfil, marca_dagua, is_admin FROM fotografos WHERE id = ?");
$stmt->execute([$idFotografo]);
$dados = $stmt->fetch();

$emailFotografo = $dados['email'];
$publicKeyAtual = $dados['mp_public_key'] ?? '';
$fotoPerfil = $dados['foto_perfil'] ? $dados['foto_perfil'] : 'https://ui-avatars.com/api/?name=' . urlencode($nomeFotografo) . '&background=random';
$marcaDaguaAtual = $dados['marca_dagua'] ?? '';
$isAdmin = (bool) $dados['is_admin'];

// Verifica se o Fotógrafo já conectou o Mercado Pago
$temMercadoPago = !empty($dados['mp_access_token']);

// Busca eventos e conta quantas fotos já foram indexadas pelo motor
$stmtEventos = $pdo->prepare("
    SELECT e.id, e.nome, e.token_url, e.drive_folder_id, e.preco_foto, e.ativo, e.expira_em,
           (SELECT COUNT(*) FROM fotos_eventos WHERE evento_id = e.id) as qtd_fotos
    FROM eventos e 
    WHERE e.fotografo_id = ? AND e.ativo = 1 
    ORDER BY e.id DESC
");
$stmtEventos->execute([$idFotografo]);
$meusEventos = $stmtEventos->fetchAll();

// Busca as configurações globais (Drive + SMTP)
$stmtConfig = $pdo->query("SELECT email_robo_drive, smtp_host, smtp_port, smtp_user, smtp_pass FROM configuracoes_sistema WHERE id = 1");
$configGlobal = $stmtConfig->fetch();
$emailRoboDrive = $configGlobal['email_robo_drive'] ?? 'Configuração pendente';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Pic2Pic</title>
    <style>
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
        .sidebar-footer { padding: 20px; border-top: 1px solid #222; font-size: 13px; text-align: center; }
        .sidebar-footer a { color: #ff4d4d; text-decoration: none; font-weight: bold; }
        .main-content { flex-grow: 1; padding: 20px; width: 100%; max-width: 100vw; transition: margin-left 0.3s ease; }
        .topbar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; background: #fff; padding: 12px 15px; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.04); flex-wrap: wrap; gap: 10px; }
        .topbar-left { display: flex; align-items: center; gap: 15px; }
        .btn-menu { background: none; border: none; font-size: 24px; color: #333; cursor: pointer; padding: 5px; }
        .topbar h2 { font-size: 18px; font-weight: 600; margin: 0; }
        .user-menu { display: flex; align-items: center; gap: 10px; cursor: pointer; padding: 5px; border-radius: 50px; }
        .avatar-img { width: 38px; height: 38px; border-radius: 50%; object-fit: cover; border: 2px solid #eee; }
        .user-info-text { text-align: right; line-height: 1.2; display: none; }
        .user-info-text span { display: block; font-size: 13px; font-weight: 700; }
        .user-info-text small { font-size: 11px; color: #888; }
        .card { background: #fff; padding: 20px; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.02); margin-bottom: 20px; width: 100%; overflow: hidden; }
        .card-header { margin-bottom: 20px; }
        .card-header h3 { font-size: 17px; margin-bottom: 5px; }
        .card-header p { font-size: 13px; color: #666; }
        .card-header-flex { display: flex; flex-direction: column; gap: 15px; }
        .btn-acao { background-color: #007bff; color: #fff; border: none; padding: 12px 18px; border-radius: 8px; font-size: 14px; font-weight: 600; cursor: pointer; transition: 0.2s; width: 100%; }
        .btn-acao:hover { background-color: #0056b3; }
        .btn-cancelar { background: #e9ecef; color: #495057; border: none; padding: 12px 18px; border-radius: 8px; font-size: 14px; font-weight: 600; cursor: pointer; width: 100%; }
        .btn-danger { background-color: #dc3545; color: white; }
        .btn-link { font-size: 13px; color: #007bff; text-decoration: none; font-weight: 500; word-break: break-all; }
        .form-group { margin-bottom: 16px; }
        .form-group label { display: block; font-size: 13px; font-weight: 600; color: #444; margin-bottom: 6px; }
        .form-group input { width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 8px; font-size: 14px; background-color: #fafafa; }
        .table-responsive { width: 100%; overflow-x: auto; -webkit-overflow-scrolling: touch; }
        .table-eventos { width: 100%; min-width: 800px; border-collapse: collapse; text-align: left; font-size: 13px; }
        .table-eventos th { padding: 12px 10px; border-bottom: 2px solid #eee; color: #555; }
        .table-eventos td { padding: 15px 10px; border-bottom: 1px solid #eee; color: #333; }
        .btn-tbl { border: none; padding: 6px 10px; border-radius: 4px; font-size: 12px; font-weight: bold; cursor: pointer; margin-right: 5px; color: #fff; }
        .btn-tbl-edit { background-color: #ffc107; color: #212529; }
        .btn-tbl-del { background-color: #dc3545; }
        .btn-tbl-sync { background-color: #28a745; }
        .badge-ativo { background-color: #d4edda; color: #155724; padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: bold; }
        .badge-expirado { background-color: #fff3cd; color: #856404; padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: bold; }
        .modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); display: flex; align-items: flex-end; justify-content: center; z-index: 2000; display: none; padding: 0; }
        .modal-box { background: #fff; padding: 25px 20px; border-radius: 16px 16px 0 0; width: 100%; max-width: 500px; max-height: 90vh; overflow-y: auto; box-shadow: 0 -4px 24px rgba(0,0,0,0.15); animation: slideUp 0.3s ease-out; }
        .modal-footer { display: flex; flex-direction: column-reverse; gap: 10px; margin-top: 20px; }
        .alerta { padding: 12px; border-radius: 8px; margin-top: 15px; font-size: 13px; display: none; font-weight: 500; }
        .alerta.sucesso { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; display: block; }
        .alerta.erro { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; display: block; }
        
        .badge-fotos-clicavel { cursor: pointer; background: #e9ecef; color: #1a73e8; padding: 6px 12px; border-radius: 20px; font-weight: 700; font-size: 13px; transition: 0.2s; display: inline-flex; align-items: center; gap: 6px; border: 1px solid #dadce0; }
        .badge-fotos-clicavel:hover { background: #1a73e8; color: #fff; border-color: #1a73e8; }
        .modal-raiox-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); display: none; justify-content: center; align-items: center; z-index: 9999; backdrop-filter: blur(4px); }
        .modal-raiox-box { background: #fff; width: 95%; max-width: 1000px; height: 85vh; border-radius: 12px; display: flex; flex-direction: column; overflow: hidden; box-shadow: 0 10px 30px rgba(0,0,0,0.3); }
        .modal-raiox-header { padding: 15px 20px; background: #f8f9fa; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; align-items: center; }
        .modal-raiox-header h3 { margin: 0; font-size: 18px; color: #333; }
        .btn-fechar-raiox { background: #e9ecef; border: none; width: 32px; height: 32px; border-radius: 50%; font-size: 18px; font-weight: bold; cursor: pointer; color: #555; display: flex; align-items: center; justify-content: center; }
        .btn-fechar-raiox:hover { background: #dc3545; color: white; }
        .modal-raiox-body { padding: 20px; overflow-y: auto; flex: 1; background: #f0f2f5; }
        .grid-raiox { display: grid; grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); gap: 15px; }
        .card-raiox { position: relative; border-radius: 8px; overflow: hidden; background: #fff; box-shadow: 0 2px 5px rgba(0,0,0,0.05); }
        .card-raiox img { width: 100%; height: 160px; object-fit: cover; display: block; }
        .status-tarja { position: absolute; bottom: 0; left: 0; width: 100%; padding: 8px 5px; text-align: center; font-size: 12px; font-weight: 800; color: #fff; text-transform: uppercase; letter-spacing: 0.5px; }
        .status-tarja.ok { background: rgba(25, 135, 84, 0.95); }
        .status-tarja.erro { background: rgba(220, 53, 69, 0.95); }
        .loader-raiox { text-align: center; padding: 40px; font-size: 16px; color: #666; font-weight: bold; }

        .accordion-container { margin-top: 15px; }
        .accordion-item { background: #fff; border-radius: 8px; margin-bottom: 10px; border: 1px solid #e0e0e0; overflow: hidden; }
        .accordion-header { padding: 15px 20px; cursor: pointer; display: flex; justify-content: space-between; align-items: center; background: #f8f9fa; font-weight: 600; font-size: 14px; transition: background 0.2s; user-select: none; }
        .accordion-header:hover { background: #e9ecef; }
        .accordion-body { display: none; padding: 20px; border-top: 1px solid #e0e0e0; background: #fff; max-height: 400px; overflow-y: auto; }
        .accordion-item.active .accordion-body { display: block; }
        .accordion-item.active .accordion-header { background: #e9ecef; color: #1a73e8; }
        .timeline { border-left: 2px solid #e9ecef; padding-left: 15px; margin-left: 10px; }
        .timeline-item { margin-bottom: 15px; position: relative; }
        .timeline-item:last-child { margin-bottom: 0; }
        .timeline-item::before { content: ''; position: absolute; left: -21px; top: 5px; width: 10px; height: 10px; border-radius: 50%; background: #1a73e8; border: 2px solid #fff; }
        .timeline-date { font-size: 11px; color: #888; font-weight: bold; margin-bottom: 3px; }
        .timeline-content { font-size: 13px; color: #444; }

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
                <li><a href="painel.php?tela=historico" class="<?= strpos($tela, 'historico') !== false ? 'active' : '' ?>">Histórico de Logs</a></li>
                
                <li style="margin-top: 15px; border-top: 1px solid #333; padding-top: 15px;">
                    <a href="minhas_compras.php" style="color: #4caf50;">🛒 Minhas Compras</a>
                </li>

                <?php if ($isAdmin): ?>
                    <li style="margin-top: 5px;">
                        <a href="painel.php?tela=admin" class="<?= $tela === 'admin' ? 'active' : '' ?>" style="color: #ffc107;">⚙️ Painel Admin</a>
                    </li>
                <?php endif; ?>
            </ul>
            <div class="sidebar-footer"><a href="processadores/autenticacao/sair.php">Sair do Sistema</a></div>
        </aside>

        <main class="main-content">
            <header class="topbar">
                <div class="topbar-left">
                    <button class="btn-menu" onclick="toggleSidebar()">☰</button>
                    <h2>Dashboard</h2>
                </div>
                <div class="user-menu" onclick="abrirModalPerfil()">
                    <div class="user-info-text">
                        <span><?= $nomeFotografo ?></span><small>Meu Perfil</small>
                    </div>
                    <img src="<?= $fotoPerfil ?>" id="avatar-topo" class="avatar-img" alt="Avatar">
                </div>
            </header>

            <?php if (!$temMercadoPago): ?>
                <div style="background-color: #fff3cd; color: #856404; padding: 15px 20px; margin-bottom: 25px; border-radius: 8px; border-left: 5px solid #ffeeba; display: flex; align-items: center; gap: 15px; box-shadow: 0 2px 4px rgba(0,0,0,0.05);">
                    <span style="font-size: 24px;">⚠️</span>
                    <div style="font-size: 14px; line-height: 1.4;">
                        <strong>Aviso de Faturamento:</strong> Você ainda não configurou as credenciais do Mercado Pago. Até que os tokens sejam inseridos na aba <a href="painel.php?tela=financeiro" style="color: #856404; text-decoration: underline; font-weight: bold;">Pagamentos (MP)</a>, todos os seus catálogos <strong>serão tratados como gratuitos</strong> pelo sistema, mesmo que configure um preço nas fotos!
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($tela === 'eventos'): ?>
                <section class="card" style="max-width: 1100px; margin: 0 auto;">
                    <div class="card-header card-header-flex">
                        <div>
                            <h3>Lista de Catálogos</h3>
                            <p>Suas lojas conectadas ao Drive.</p>
                        </div>
                        <button class="btn-acao" onclick="abrirModalEvento()">+ Novo Evento</button>
                    </div>

                    <div id="msg-sync-global" class="alerta" style="margin-bottom: 20px;"></div>

                    <div class="table-responsive">
                        <table class="table-eventos">
                            <thead>
                                <tr>
                                    <th>Nome</th>
                                    <th>Link Público</th>
                                    <th>Status</th>
                                    <th>Fotos Lidas</th>
                                    <th>Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($meusEventos)): ?>
                                    <tr><td colspan="5" style="text-align: center; color: #888; padding: 40px 0;">Você não possui eventos ativos.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($meusEventos as $evento): 
                                        $expirado = strtotime($evento['expira_em']) < time();
                                        $urlCatalogo = APP_URL . "/index.php?e=" . $evento['token_url'];
                                    ?>
                                        <tr>
                                            <td><strong><?= htmlspecialchars($evento['nome']) ?></strong><br><small>R$ <?= number_format(floatval($evento['preco_foto']), 2, ',', '.') ?></small></td>
                                            <td><a href="<?= $urlCatalogo ?>" target="_blank" class="btn-link">Acessar Vitrine</a></td>
                                            <td><span class="<?= $expirado ? 'badge-expirado' : 'badge-ativo' ?>"><?= $expirado ? 'Expirado' : 'Ativo' ?></span></td>
                                            
                                            <td>
                                                <span class="badge-fotos-clicavel" onclick="abrirModalRaioX(<?= $evento['id'] ?>)" title="Ver status da Inteligência Artificial">
                                                    👁️ <?= $evento['qtd_fotos'] ?> fotos na IA
                                                </span>
                                            </td>

                                            <td style="white-space: nowrap;">
                                                <button class="btn-tbl btn-tbl-sync" onclick="sincronizarDrive(<?= $evento['id'] ?>, this)" title="Puxar fotos do Drive">🔄 Sincronizar</button>
                                                <button class="btn-tbl btn-tbl-edit" onclick="abrirModalEditar(<?= $evento['id'] ?>, '<?= addslashes($evento['nome']) ?>', '<?= addslashes($evento['drive_folder_id']) ?>', '<?= number_format(floatval($evento['preco_foto']), 2, '.', '') ?>', '<?= date('Y-m-d\TH:i', strtotime($evento['expira_em'])) ?>')">✏️</button>
                                                <button class="btn-tbl btn-tbl-del" onclick="abrirModalExcluir(<?= $evento['id'] ?>, '<?= addslashes($evento['nome']) ?>')">🗑️</button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </section>

                <div id="modal-evento" class="modal-overlay" onclick="fecharModalEvento(event)">
                    <div class="modal-box" onclick="event.stopPropagation()">
                        <div class="card-header">
                            <h3>Criar Novo Catálogo</h3>
                            <p>Aponte para a pasta de fotos do Drive.</p>
                        </div>
                        <div style="background-color: #e8f4fd; border: 1px solid #b8daff; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                            <span style="display: block; font-size: 13px; font-weight: 600; color: #0056b3; margin-bottom: 5px;">⚠️ Obrigatório: Compartilhar com:</span>
                            <code style="display: block; background: #fff; padding: 8px; border-radius: 6px; border: 1px dashed #007bff; font-size: 12px; font-weight: bold; user-select: all; text-align: center;"><?= htmlspecialchars($emailRoboDrive) ?></code>
                        </div>
                        <form id="form-evento">
                            <div class="form-group"><label>Nome do Evento</label><input type="text" id="nome_evento" required></div>
                            <div class="form-group"><label>Link da Pasta</label><input type="text" id="drive_folder" required></div>
                            <div class="form-row">
                                <div class="form-group"><label>Preço (R$)</label><input type="number" id="preco_foto" step="0.01" min="0" value="0.00" required></div>
                                <div class="form-group"><label>Expiração</label><input type="datetime-local" id="expira_em" required></div>
                            </div>
                            <div id="msg-evento" class="alerta"></div>
                            <div class="modal-footer"><button type="button" class="btn-cancelar" onclick="fecharModalEvento('forced')">Cancelar</button><button type="submit" class="btn-acao">Criar Catálogo</button></div>
                        </form>
                    </div>
                </div>

                <div id="modal-editar-evento" class="modal-overlay" onclick="fecharModalEditar(event)">
                    <div class="modal-box" onclick="event.stopPropagation()">
                        <div class="card-header"><h3>Editar Catálogo</h3></div>
                        <form id="form-editar-evento">
                            <input type="hidden" id="edit_id_evento">
                            <div class="form-group"><label>Nome</label><input type="text" id="edit_nome_evento" required></div>
                            <div class="form-group"><label>ID da Pasta</label><input type="text" id="edit_drive_folder" required></div>
                            <div class="form-row">
                                <div class="form-group"><label>Preço (R$)</label><input type="number" id="edit_preco_foto" step="0.01" min="0" required></div>
                                <div class="form-group"><label>Expiração</label><input type="datetime-local" id="edit_expira_em" required></div>
                            </div>
                            <div id="msg-edit-evento" class="alerta"></div>
                            <div class="modal-footer"><button type="button" class="btn-cancelar" onclick="fecharModalEditar('forced')">Cancelar</button><button type="submit" class="btn-acao">Salvar</button></div>
                        </form>
                    </div>
                </div>

                <div id="modal-excluir-evento" class="modal-overlay" onclick="fecharModalExcluir(event)">
                    <div class="modal-box" onclick="event.stopPropagation()">
                        <div class="card-header"><h3 style="color:#dc3545;">Desativar Catálogo?</h3></div>
                        <form id="form-excluir-evento">
                            <input type="hidden" id="excluir_id_evento">
                            <div class="modal-footer"><button type="button" class="btn-cancelar" onclick="fecharModalExcluir('forced')">Voltar</button><button type="submit" class="btn-acao btn-danger">Confirmar Desativação</button></div>
                        </form>
                    </div>
                </div>

            <?php elseif ($tela === 'financeiro'): ?>
                <section class="card" style="max-width: 600px; margin: 0 auto;">
                    <div class="card-header card-header-flex" style="flex-direction: row; justify-content: space-between;">
                        <div>
                            <h3>Mercado Pago</h3>
                            <p style="font-size: 13px; color: #666;">Conecte as suas chaves para faturar.</p>
                        </div>
                        <?php if ($temMercadoPago): ?>
                            <span class="badge-ativo" style="height: fit-content;">✅ Cofre Seguro Ativo</span>
                        <?php endif; ?>
                    </div>
                    <form id="form-mp">
                        <div class="form-group">
                            <label>Public Key</label>
                            <input type="text" id="pk" value="<?= htmlspecialchars($publicKeyAtual) ?>" <?= $temMercadoPago ? '' : 'required' ?> placeholder="APP_USR-...">
                        </div>
                        <div class="form-group">
                            <label>Access Token (Oculto)</label>
                            <input type="password" id="at" autocomplete="new-password" placeholder="<?= $temMercadoPago ? 'Criptografado. Preencha apenas para alterar.' : 'APP_USR-...' ?>" <?= $temMercadoPago ? '' : 'required' ?>>
                        </div>
                        <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                            <button type="submit" class="btn-acao" style="flex: 1;">Salvar Chaves</button>
                            <?php if ($temMercadoPago): ?>
                                <button type="button" class="btn-cancelar" style="flex: 1; background-color: #f8d7da; color: #721c24;" onclick="removerMP()">🗑️ Remover Integração</button>
                            <?php endif; ?>
                        </div>
                    </form>
                    <div id="msg-mp" class="alerta"></div>
                </section>
                
            <?php elseif ($tela === 'marca'): ?>
                <section class="card" style="max-width: 600px; margin: 0 auto;">
                    <div class="card-header">
                        <h3>Identidade Visual</h3>
                        <p style="font-size: 13px; color: #666;">Defina a marca d'água que será aplicada sobre as fotos da sua vitrine pública.</p>
                    </div>

                    <div style="text-align: center; margin-bottom: 20px; padding: 20px; background: #f8f9fa; border-radius: 8px; border: 1px dashed #ccc;">
                        <p style="font-size: 13px; font-weight: bold; color: #444; margin-bottom: 15px;">Visualização Atual:</p>
                        
                        <?php if (!empty($marcaDaguaAtual)): ?>
                            <img id="preview-marca" src="<?= htmlspecialchars($marcaDaguaAtual) ?>" style="max-width: 100%; max-height: 200px; object-fit: contain;">
                            <span id="txt-sem-marca" style="display: none; font-size: 14px; color: #999;">Nenhuma imagem selecionada.</span>
                        <?php else: ?>
                            <img id="preview-marca" src="" style="max-width: 100%; max-height: 200px; object-fit: contain; display: none;">
                            <span id="txt-sem-marca" style="display: block; font-size: 14px; color: #999;">Nenhuma marca d'água configurada no sistema.</span>
                        <?php endif; ?>
                    </div>

                    <form id="form-marca-ajax">
                        <div class="form-group">
                            <label for="input-marca-painel">Escolher Nova Imagem (PNG transparente)</label>
                            <input type="file" id="input-marca-painel" accept="image/png" required onchange="previewImagem(event)">
                        </div>
                        <button type="submit" class="btn-acao">Salvar Marca D'água</button>
                    </form>
                    <div id="msg-marca" class="alerta"></div>
                </section>

            <?php elseif ($tela === 'historico'): 
                try {
                    $stmtLogs = $pdo->prepare("
                        SELECT l.id, l.descricao, l.criado_em, IFNULL(e.nome, 'Sistema / Geral') as evento_nome 
                        FROM logs_eventos l 
                        LEFT JOIN eventos e ON l.evento_id = e.id 
                        WHERE l.fotografo_id = ? 
                        ORDER BY l.criado_em DESC LIMIT 500
                    ");
                    $stmtLogs->execute([$idFotografo]);
                    $todosLogs = $stmtLogs->fetchAll(PDO::FETCH_ASSOC);
                    
                    $logsAgrupados = [];
                    foreach($todosLogs as $log) {
                        $eventoNome = $log['evento_nome'];
                        if(!isset($logsAgrupados[$eventoNome])) {
                            $logsAgrupados[$eventoNome] = [];
                        }
                        $logsAgrupados[$eventoNome][] = $log;
                    }
                } catch (Exception $e) { 
                    $logsAgrupados = []; 
                }
            ?>
                <section class="card" style="max-width: 1100px; margin: 0 auto;">
                    <div class="card-header">
                        <h3>📜 Histórico de Atividades</h3>
                        <p style="font-size: 13px; color: #666;">Acompanhe as últimas 500 alterações dos seus catálogos (sincronizações, edições e alertas da IA).</p>
                    </div>

                    <?php if(empty($logsAgrupados)): ?>
                        <div style="text-align: center; padding: 40px; color: #888;">Nenhum log registrado no sistema ainda.</div>
                    <?php else: ?>
                        <div class="accordion-container">
                            <?php foreach($logsAgrupados as $nomeEvento => $logs): ?>
                                <div class="accordion-item">
                                    <div class="accordion-header" onclick="this.parentElement.classList.toggle('active')">
                                        <span>
                                            📁 <?= htmlspecialchars($nomeEvento) ?> 
                                            <span style="font-size: 12px; color: #888; font-weight: normal; margin-left: 10px;">(<?= count($logs) ?> registros)</span>
                                        </span>
                                        <span style="font-size: 12px;">▼</span>
                                    </div>
                                    <div class="accordion-body">
                                        <div class="timeline">
                                            <?php foreach($logs as $log): ?>
                                                <div class="timeline-item">
                                                    <div class="timeline-date"><?= date('d/m/Y H:i', strtotime($log['criado_em'])) ?></div>
                                                    <div class="timeline-content"><?= htmlspecialchars($log['descricao']) ?></div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </section>

            <?php elseif ($tela === 'admin' && $isAdmin): ?>
                <section class="card" style="max-width: 600px; margin: 0 auto; border-top: 4px solid #ffc107;">
                    <div class="card-header">
                        <h3>⚙️ Configuração do Sistema & SMTP</h3>
                        <p style="font-size: 13px; color: #666;">Defina o e-mail de disparo para garantir que os clientes recebam na Caixa de Entrada.</p>
                    </div>
                    <form id="form-admin">
                        <div class="form-group">
                            <label>E-mail do Robô (Drive API)</label>
                            <input type="email" id="admin_email_robo" value="<?= htmlspecialchars($emailRoboDrive) ?>" required>
                        </div>
                        
                        <div style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin-bottom: 20px; border: 1px solid #ddd;">
                            <h4 style="font-size: 14px; margin-bottom: 10px; color: #333;">Definições de Envio (SMTP)</h4>
                            <div class="form-row">
                                <div class="form-group"><label>Host SMTP (Ex: smtp.hostinger.com)</label><input type="text" id="admin_smtp_host" value="<?= htmlspecialchars($configGlobal['smtp_host'] ?? '') ?>"></div>
                                <div class="form-group"><label>Porta (Ex: 465)</label><input type="number" id="admin_smtp_port" value="<?= htmlspecialchars((string)($configGlobal['smtp_port'] ?? '')) ?>"></div>
                            </div>
                            <div class="form-group">
                                <label>Utilizador / E-mail Autenticado</label>
                                <input type="email" id="admin_smtp_user" value="<?= htmlspecialchars($configGlobal['smtp_user'] ?? '') ?>" placeholder="nao-responda@ccrn.com.br">
                            </div>
                            <div class="form-group">
                                <label>Palavra-passe do E-mail</label>
                                <input type="password" id="admin_smtp_pass" autocomplete="new-password" placeholder="Apenas preencha para alterar a senha atual">
                            </div>
                        </div>

                        <div id="msg-admin" class="alerta"></div>
                        <button type="submit" class="btn-acao" style="background-color: #333;">Gravar Configurações</button>
                    </form>
                </section>
            <?php endif; ?>
        </main>
    </div>

    <div id="modal-perfil" class="modal-overlay" onclick="fecharModalPerfil(event)">
        <div class="modal-box" onclick="event.stopPropagation()">
            <form id="form-perfil-ajax">
                <div class="form-group"><label>Seu Nome</label><input type="text" id="perf-nome" value="<?= $nomeFotografo ?>" required></div>
                <div class="modal-footer"><button type="button" class="btn-cancelar" onclick="fecharModalPerfil('forced')">Fechar</button><button type="submit" class="btn-acao">Salvar</button></div>
            </form>
        </div>
    </div>

    <div id="modal-raiox" class="modal-raiox-overlay" onclick="fecharModalRaioX(event)">
        <div class="modal-raiox-box" onclick="event.stopPropagation()">
            <div class="modal-raiox-header">
                <h3>🔍 Raio-X da Inteligência Artificial</h3>
                <button class="btn-fechar-raiox" onclick="fecharModalRaioX('forced')">&times;</button>
            </div>
            <div class="modal-raiox-body" id="corpo-raiox"></div>
        </div>
    </div>

    <script>
        function toggleSidebar() { document.getElementById('sidebar').classList.toggle('open'); document.getElementById('sidebar-overlay').classList.toggle('active'); }
        function abrirModalEvento() { document.getElementById('modal-evento').style.display = 'flex'; }
        function fecharModalEvento(e) { if(!e || e.target === document.getElementById('modal-evento') || e === 'forced') document.getElementById('modal-evento').style.display = 'none'; }
        
        function abrirModalEditar(id, nome, drive, preco, expira) { 
            document.getElementById('edit_id_evento').value = id; 
            document.getElementById('edit_nome_evento').value = nome; 
            document.getElementById('edit_drive_folder').value = drive; 
            
            // Força a formatação de 2 casas decimais no formulário (ex: 0.10)
            document.getElementById('edit_preco_foto').value = parseFloat(preco).toFixed(2); 
            
            document.getElementById('edit_expira_em').value = expira; 
            document.getElementById('modal-editar-evento').style.display = 'flex'; 
        }

        function fecharModalEditar(e) { if(!e || e.target === document.getElementById('modal-editar-evento') || e === 'forced') document.getElementById('modal-editar-evento').style.display = 'none'; }
        function abrirModalExcluir(id, nome) { document.getElementById('excluir_id_evento').value = id; document.getElementById('modal-excluir-evento').style.display = 'flex'; }
        function fecharModalExcluir(e) { if(!e || e.target === document.getElementById('modal-excluir-evento') || e === 'forced') document.getElementById('modal-excluir-evento').style.display = 'none'; }
        function abrirModalPerfil() { document.getElementById('modal-perfil').style.display = 'flex'; }
        function fecharModalPerfil(e) { if(!e || e.target === document.getElementById('modal-perfil') || e === 'forced') document.getElementById('modal-perfil').style.display = 'none'; }

        // --- SCRIPTS DE EVENTOS (CRIAR, EDITAR, EXCLUIR, SINCRONIZAR) ---
        if(document.getElementById('form-evento')) {
            document.getElementById('form-evento').addEventListener('submit', async function(e) {
                e.preventDefault();
                const btn = this.querySelector('button[type="submit"]');
                const msg = document.getElementById('msg-evento');
                const textoOriginal = btn.innerText;
                btn.innerText = 'A criar...'; btn.disabled = true; msg.style.display = 'none';

                const formData = new FormData();
                formData.append('nome', document.getElementById('nome_evento').value);
                formData.append('drive_folder', document.getElementById('drive_folder').value);
                formData.append('preco', document.getElementById('preco_foto').value);
                formData.append('expira_em', document.getElementById('expira_em').value);

                try {
                    const resposta = await fetch('processadores/eventos/criar_evento.php', { method: 'POST', body: formData });
                    const res = await resposta.json();
                    if (res.sucesso) { 
                        msg.className = 'alerta sucesso'; msg.innerText = 'Catálogo criado com sucesso!'; msg.style.display = 'block'; 
                        setTimeout(() => location.reload(), 1500);
                    } else { msg.className = 'alerta erro'; msg.innerText = res.erro || 'Falha ao criar.'; msg.style.display = 'block'; }
                } catch(err) { msg.className = 'alerta erro'; msg.innerText = 'Erro de comunicação.'; msg.style.display = 'block'; }
                btn.innerText = textoOriginal; btn.disabled = false;
            });
        }

        if(document.getElementById('form-editar-evento')) {
            document.getElementById('form-editar-evento').addEventListener('submit', async function(e) {
                e.preventDefault();
                const btn = this.querySelector('button[type="submit"]');
                const msg = document.getElementById('msg-edit-evento');
                const textoOriginal = btn.innerText;
                btn.innerText = 'A guardar...'; btn.disabled = true; msg.style.display = 'none';

                const formData = new FormData();
                formData.append('id', document.getElementById('edit_id_evento').value);
                formData.append('nome', document.getElementById('edit_nome_evento').value);
                formData.append('drive_folder', document.getElementById('edit_drive_folder').value);
                formData.append('preco', document.getElementById('edit_preco_foto').value);
                formData.append('expira_em', document.getElementById('edit_expira_em').value);

                try {
                    const resposta = await fetch('processadores/eventos/editar_evento.php', { method: 'POST', body: formData });
                    const res = await resposta.json();
                    if (res.sucesso) { 
                        msg.className = 'alerta sucesso'; msg.innerText = 'Atualizado com sucesso!'; msg.style.display = 'block'; 
                        setTimeout(() => location.reload(), 1500);
                    } else { msg.className = 'alerta erro'; msg.innerText = res.erro || 'Falha ao atualizar.'; msg.style.display = 'block'; }
                } catch(err) { msg.className = 'alerta erro'; msg.innerText = 'Erro de comunicação.'; msg.style.display = 'block'; }
                btn.innerText = textoOriginal; btn.disabled = false;
            });
        }

        if(document.getElementById('form-excluir-evento')) {
            document.getElementById('form-excluir-evento').addEventListener('submit', async function(e) {
                e.preventDefault();
                const btn = this.querySelector('button[type="submit"]');
                const textoOriginal = btn.innerText;
                btn.innerText = 'A processar...'; btn.disabled = true;

                const formData = new FormData();
                formData.append('id', document.getElementById('excluir_id_evento').value);

                try {
                    const resposta = await fetch('processadores/eventos/excluir_evento.php', { method: 'POST', body: formData });
                    const res = await resposta.json();
                    if (res.sucesso) { location.reload(); } else { alert(res.erro || 'Falha ao excluir.'); }
                } catch(err) { alert('Erro de comunicação.'); }
                btn.innerText = textoOriginal; btn.disabled = false;
            });
        }

        async function sincronizarDrive(id, btn) {
            const textoOriginal = btn.innerText;
            btn.innerText = 'A sincronizar...'; btn.disabled = true;
            
            const msgGlobal = document.getElementById('msg-sync-global');
            msgGlobal.style.display = 'none';

            const formData = new FormData();
            formData.append('id_evento', id);

            try {
                const resposta = await fetch('processadores/eventos/sincronizar_drive.php', { method: 'POST', body: formData });
                const res = await resposta.json();
                if (res.sucesso) { 
                    msgGlobal.className = 'alerta sucesso'; msgGlobal.innerText = res.msg; msgGlobal.style.display = 'block'; 
                    if(res.msg.includes('100%')) {
                        setTimeout(() => location.reload(), 2000);
                    } else {
                        btn.innerText = textoOriginal; btn.disabled = false;
                    }
                } else { 
                    msgGlobal.className = 'alerta erro'; msgGlobal.innerText = res.erro || 'Falha ao sincronizar.'; msgGlobal.style.display = 'block'; 
                    btn.innerText = textoOriginal; btn.disabled = false;
                }
            } catch(err) { 
                msgGlobal.className = 'alerta erro'; msgGlobal.innerText = 'Erro de comunicação.'; msgGlobal.style.display = 'block'; 
                btn.innerText = textoOriginal; btn.disabled = false;
            }
        }

        // --- SCRIPTS DO ADMIN (Envio de SMTP) ---
        if(document.getElementById('form-admin')) {
            document.getElementById('form-admin').addEventListener('submit', async function(e) {
                e.preventDefault();
                const btn = this.querySelector('button[type="submit"]');
                const msg = document.getElementById('msg-admin');
                const textoOriginal = btn.innerText;
                
                btn.innerText = 'A gravar...'; btn.disabled = true; msg.style.display = 'none';

                const formData = new FormData();
                formData.append('email_robo', document.getElementById('admin_email_robo').value);
                formData.append('smtp_host', document.getElementById('admin_smtp_host').value);
                formData.append('smtp_port', document.getElementById('admin_smtp_port').value);
                formData.append('smtp_user', document.getElementById('admin_smtp_user').value);
                formData.append('smtp_pass', document.getElementById('admin_smtp_pass').value);

                try {
                    const resposta = await fetch('processadores/configuracoes/editar_admin.php', { method: 'POST', body: formData });
                    const res = await resposta.json();
                    if (res.sucesso) { msg.className = 'alerta sucesso'; msg.innerText = 'Configurações gravadas!'; msg.style.display = 'block'; } 
                    else { msg.className = 'alerta erro'; msg.innerText = res.erro || 'Falha.'; msg.style.display = 'block'; }
                } catch(err) { msg.className = 'alerta erro'; msg.innerText = 'Erro de comunicação.'; msg.style.display = 'block'; }
                
                btn.innerText = textoOriginal; btn.disabled = false;
            });
        }
        
        // --- SCRIPT DO MERCADO PAGO (Validação e Exclusão) ---
        if(document.getElementById('form-mp')) {
            document.getElementById('form-mp').addEventListener('submit', async function(e) {
                e.preventDefault();
                const btn = this.querySelector('button[type="submit"]');
                const msg = document.getElementById('msg-mp');
                const pkInput = document.getElementById('pk').value;
                const atInput = document.getElementById('at').value;

                if("<?= $temMercadoPago ?>" && (!pkInput || !atInput)) {
                    msg.className = 'alerta erro'; msg.innerText = 'Para atualizar, digite as DUAS chaves novas.'; msg.style.display = 'block';
                    return;
                }

                const textoOriginal = btn.innerText;
                btn.innerText = 'A validar na API do Mercado Pago...'; 
                btn.disabled = true; 
                msg.style.display = 'none';

                const formData = new FormData();
                formData.append('acao', 'salvar');
                formData.append('pk', pkInput);
                formData.append('at', atInput);

                try {
                    const resposta = await fetch('processadores/financeiro/salvar_mp.php', { method: 'POST', body: formData });
                    const res = await resposta.json();
                    if (res.sucesso) { 
                        msg.className = 'alerta sucesso'; 
                        msg.innerText = res.msg; 
                        msg.style.display = 'block'; 
                        setTimeout(() => location.reload(), 2000);
                    } 
                    else { 
                        msg.className = 'alerta erro'; 
                        msg.innerText = res.erro || 'Falha na validação.'; 
                        msg.style.display = 'block'; 
                    }
                } catch(err) { 
                    msg.className = 'alerta erro'; 
                    msg.innerText = 'Erro de comunicação com o servidor local.'; 
                    msg.style.display = 'block'; 
                }
                btn.innerText = textoOriginal; btn.disabled = false;
            });
        }

        async function removerMP() {
            if(!confirm("Tem a certeza que deseja remover o Mercado Pago? Todos os seus catálogos passarão a ser gratuitos imediatamente.")) return;
            
            const msg = document.getElementById('msg-mp');
            const formData = new FormData();
            formData.append('acao', 'excluir');

            try {
                const resposta = await fetch('processadores/financeiro/salvar_mp.php', { method: 'POST', body: formData });
                const res = await resposta.json();
                if (res.sucesso) { 
                    msg.className = 'alerta sucesso'; msg.innerText = res.msg; msg.style.display = 'block'; 
                    setTimeout(() => location.reload(), 2000);
                }
            } catch(err) { alert("Falha ao remover integração."); }
        }
    </script>
</body>
</html>