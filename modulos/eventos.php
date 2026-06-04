<?php
// Este arquivo é carregado dinamicamente dentro do painel.php

// 1. Busca as categorias ativas no banco de dados para os dropdowns
try {
    $stmtCategorias = $pdo->query("SELECT nome FROM categorias_eventos ORDER BY nome ASC");
    $listaCategorias = $stmtCategorias->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {
    $listaCategorias = ['Outros']; // Fallback de segurança caso a tabela ainda não exista
}

// 2. Busca os eventos e a contagem de fotos lidas (agora puxando a categoria também)
$stmtEventos = $pdo->prepare("
    SELECT e.id, e.nome, e.token_url, e.drive_folder_id, e.preco_foto, e.ativo, e.expira_em, e.categoria,
           (SELECT COUNT(*) FROM fotos_eventos WHERE evento_id = e.id) as qtd_fotos
    FROM eventos e 
    WHERE e.fotografo_id = ? AND e.ativo = 1 
    ORDER BY e.id DESC
");
$stmtEventos->execute([$idFotografo]);
$meusEventos = $stmtEventos->fetchAll();

// 3. Busca as configurações globais (Drive) para o modal
$stmtConfig = $pdo->query("SELECT email_robo_drive FROM configuracoes_sistema WHERE id = 1");
$emailRoboDrive = $stmtConfig->fetchColumn() ?: 'Configuração pendente';
?>

<style>
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
    .badge-categoria { background-color: #e9ecef; color: #495057; padding: 3px 6px; border-radius: 4px; font-size: 10px; font-weight: 600; display: inline-block; margin-top: 4px; }
    .btn-link { font-size: 13px; color: #007bff; text-decoration: none; font-weight: 500; word-break: break-all; }
    
    .badge-fotos-clicavel { cursor: pointer; background: #e9ecef; color: #1a73e8; padding: 6px 12px; border-radius: 20px; font-weight: 700; font-size: 13px; transition: 0.2s; display: inline-flex; align-items: center; gap: 6px; border: 1px solid #dadce0; }
    .badge-fotos-clicavel:hover { background: #1a73e8; color: #fff; border-color: #1a73e8; }
    
    /* MODAL DO RAIO X DA IA */
    .modal-raiox-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); display: none; justify-content: center; align-items: center; z-index: 9999; backdrop-filter: blur(4px); }
    .modal-raiox-box { background: #fff; width: 95%; max-width: 1000px; height: 85vh; border-radius: 12px; display: flex; flex-direction: column; overflow: hidden; box-shadow: 0 10px 30px rgba(0,0,0,0.3); }
    .modal-raiox-header { padding: 15px 20px; background: #f8f9fa; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; align-items: center; }
    .modal-raiox-header h3 { margin: 0; font-size: 18px; color: #333; }
    .btn-fechar-raiox { background: #e9ecef; border: none; width: 32px; height: 32px; border-radius: 50%; font-size: 18px; font-weight: bold; cursor: pointer; color: #555; display: flex; align-items: center; justify-content: center; }
    .btn-fechar-raiox:hover { background: #dc3545; color: white; }
    .modal-raiox-body { padding: 20px; overflow-y: auto; flex: 1; background: #f0f2f5; }
    .loader-raiox { text-align: center; padding: 40px; font-size: 16px; color: #666; font-weight: bold; }
    
    /* ESTILO MASONRY (TIPO PINTEREST) PARA O RAIO-X */
    .grid-raiox { column-count: 2; column-gap: 15px; }
    @media (min-width: 768px) { .grid-raiox { column-count: 3; } }
    @media (min-width: 1000px) { .grid-raiox { column-count: 4; } }
    
    .card-raiox { break-inside: avoid; margin-bottom: 15px; position: relative; border-radius: 8px; overflow: hidden; background: #e9ecef; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
    .card-raiox img { width: 100%; height: auto; display: block; }
    
    .status-tarja { position: absolute; bottom: 0; left: 0; width: 100%; padding: 8px 5px; text-align: center; font-size: 12px; font-weight: 800; color: #fff; text-transform: uppercase; letter-spacing: 0.5px; }
    .status-tarja.ok { background: rgba(25, 135, 84, 0.95); }
    .status-tarja.erro { background: rgba(220, 53, 69, 0.95); }

    /* ESTILO PARA AS SETINHAS DO PREÇO */
    .spinner-wrapper { display: flex; align-items: center; border: 1px solid #ddd; border-radius: 8px; background-color: #fafafa; overflow: hidden; height: 42px; }
    .spinner-wrapper input { border: none !important; border-radius: 0 !important; margin-bottom: 0 !important; text-align: center; height: 100%; box-shadow: none !important; flex: 1; min-width: 0; font-weight: bold; }
    .spinner-btn { background: #e9ecef; border: none; padding: 0 15px; height: 100%; cursor: pointer; font-size: 18px; font-weight: bold; color: #555; transition: 0.2s; display: flex; align-items: center; justify-content: center; user-select: none; }
    .spinner-btn:hover { background: #dadce0; color: #111; }
    
    /* Dropdown personalizado */
    .form-select { width: 100%; padding: 12px; border: 1px solid #dadce0; border-radius: 6px; font-size: 14px; background: #fff; }
</style>

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
                            <td>
                                <strong><?= htmlspecialchars($evento['nome']) ?></strong><br>
                                <span class="badge-categoria"><?= htmlspecialchars($evento['categoria'] ?? 'Outros') ?></span><br>
                                <small>R$ <?= number_format(floatval($evento['preco_foto']), 2, ',', '.') ?></small>
                            </td>
                            <td><a href="<?= $urlCatalogo ?>" target="_blank" class="btn-link">Acessar Vitrine</a></td>
                            <td><span class="<?= $expirado ? 'badge-expirado' : 'badge-ativo' ?>"><?= $expirado ? 'Expirado' : 'Ativo' ?></span></td>
                            
                            <td>
                                <span class="badge-fotos-clicavel" onclick="abrirModalRaioX(<?= $evento['id'] ?>)" title="Ver status da Inteligência Artificial">
                                    👁️ <?= $evento['qtd_fotos'] ?> fotos na IA
                                </span>
                            </td>

                            <td style="white-space: nowrap;">
                                <button class="btn-tbl btn-tbl-sync" onclick="sincronizarDrive(<?= $evento['id'] ?>, this)" title="Puxar fotos do Drive">🔄 Sincronizar</button>
                                <button class="btn-tbl btn-tbl-edit" onclick="abrirModalEditar(<?= $evento['id'] ?>, '<?= addslashes($evento['nome']) ?>', '<?= addslashes($evento['drive_folder_id']) ?>', '<?= number_format(floatval($evento['preco_foto']), 2, '.', '') ?>', '<?= date('Y-m-d\TH:i', strtotime($evento['expira_em'])) ?>', '<?= addslashes($evento['categoria'] ?? 'Outros') ?>')">✏️</button>
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
            <span style="display: block; font-size: 13px; font-weight: 600; color: #0056b3; margin-bottom: 5px;">⚠️ Obrigatório: Compartilhar a pasta com:</span>
            <code style="display: block; background: #fff; padding: 8px; border-radius: 6px; border: 1px dashed #007bff; font-size: 12px; font-weight: bold; user-select: all; text-align: center;"><?= htmlspecialchars($emailRoboDrive) ?></code>
        </div>
        <form id="form-evento">
            <div class="form-group"><label>Nome do Evento</label><input type="text" id="nome_evento" required></div>
            
            <div class="form-group">
                <label>Categoria na Vitrine</label>
                <select id="categoria_evento" class="form-select" required>
                    <option value="" disabled selected>Selecione a categoria...</option>
                    <?php foreach ($listaCategorias as $cat): ?>
                        <option value="<?= htmlspecialchars($cat) ?>"><?= htmlspecialchars($cat) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group"><label>Link da Pasta</label><input type="text" id="drive_folder" required></div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Preço (R$)</label>
                    <div class="spinner-wrapper">
                        <button type="button" class="spinner-btn" onclick="ajustarPreco('preco_foto', -10)">-</button>
                        <input type="text" id="preco_foto" value="0,00" oninput="mascaraMoeda(this)" required>
                        <button type="button" class="spinner-btn" onclick="ajustarPreco('preco_foto', 10)">+</button>
                    </div>
                </div>
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
            
            <div class="form-group">
                <label>Categoria na Vitrine</label>
                <select id="edit_categoria_evento" class="form-select" required>
                    <option value="" disabled>Selecione a categoria...</option>
                    <?php foreach ($listaCategorias as $cat): ?>
                        <option value="<?= htmlspecialchars($cat) ?>"><?= htmlspecialchars($cat) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group"><label>ID da Pasta</label><input type="text" id="edit_drive_folder" required></div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Preço (R$)</label>
                    <div class="spinner-wrapper">
                        <button type="button" class="spinner-btn" onclick="ajustarPreco('edit_preco_foto', -10)">-</button>
                        <input type="text" id="edit_preco_foto" oninput="mascaraMoeda(this)" required>
                        <button type="button" class="spinner-btn" onclick="ajustarPreco('edit_preco_foto', 10)">+</button>
                    </div>
                </div>
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
    // A MÁSCARA INTELIGENTE DE DINHEIRO
    function mascaraMoeda(input) {
        let v = input.value.replace(/\D/g, ''); 
        if (v === '') v = '0';
        v = (parseInt(v) / 100).toFixed(2) + ''; 
        v = v.replace(".", ","); 
        v = v.replace(/(\d)(\d{3})(\d{3}),/g, "$1.$2.$3,"); 
        v = v.replace(/(\d)(\d{3}),/g, "$1.$2,");
        input.value = v;
    }

    // FUNÇÃO PARA AS SETINHAS CUSTOMIZADAS
    function ajustarPreco(idInput, variacaoCentavos) {
        const input = document.getElementById(idInput);
        let v = input.value.replace(/\D/g, '');
        if (v === '') v = '0';
        
        let atualCentavos = parseInt(v);
        let novoCentavos = atualCentavos + variacaoCentavos;
        if (novoCentavos < 0) novoCentavos = 0; 
        
        input.value = novoCentavos.toString();
        mascaraMoeda(input);
    }

    // CONTROLES DE MODAL
    function abrirModalEvento() { document.getElementById('modal-evento').style.display = 'flex'; }
    function fecharModalEvento(e) { if(!e || e.target === document.getElementById('modal-evento') || e === 'forced') document.getElementById('modal-evento').style.display = 'none'; }
    
    // Adicionado o parâmetro 'categoria'
    function abrirModalEditar(id, nome, drive, preco, expira, categoria) { 
        document.getElementById('edit_id_evento').value = id; 
        document.getElementById('edit_nome_evento').value = nome; 
        document.getElementById('edit_categoria_evento').value = categoria || 'Outros'; 
        document.getElementById('edit_drive_folder').value = drive; 
        document.getElementById('edit_preco_foto').value = parseFloat(preco).toFixed(2).replace('.', ','); 
        document.getElementById('edit_expira_em').value = expira; 
        document.getElementById('modal-editar-evento').style.display = 'flex'; 
    }
    function fecharModalEditar(e) { if(!e || e.target === document.getElementById('modal-editar-evento') || e === 'forced') document.getElementById('modal-editar-evento').style.display = 'none'; }
    
    function abrirModalExcluir(id, nome) { document.getElementById('excluir_id_evento').value = id; document.getElementById('modal-excluir-evento').style.display = 'flex'; }
    function fecharModalExcluir(e) { if(!e || e.target === document.getElementById('modal-excluir-evento') || e === 'forced') document.getElementById('modal-excluir-evento').style.display = 'none'; }

    // ==========================================
    // SCRIPT DO RAIO-X (LAZY LOAD INTELIGENTE)
    // ==========================================
    let raioxOffset = 0;
    let raioxCarregando = false;
    let raioxTemMais = true;
    let raioxEventoAtual = 0;

    async function abrirModalRaioX(idEvento) {
        document.getElementById('modal-raiox').style.display = 'flex';
        
        document.getElementById('corpo-raiox').innerHTML = `
            <div class="grid-raiox" id="grid-raiox-container"></div>
            <div id="raiox-loading" class="loader-raiox">⏳ Carregando imagens da IA...</div>
        `;

        raioxOffset = 0;
        raioxTemMais = true;
        raioxCarregando = false;
        raioxEventoAtual = idEvento;

        await carregarMaisRaiox();

        const modalBody = document.getElementById('corpo-raiox');
        modalBody.onscroll = function() {
            if (raioxCarregando || !raioxTemMais) return;
            
            // AUMENTEI A MARGEM PARA 800px PARA DISPARAR MAIS CEDO
            if (modalBody.scrollTop + modalBody.clientHeight >= modalBody.scrollHeight - 800) {
                carregarMaisRaiox();
            }
        };
    }

    async function carregarMaisRaiox() {
        raioxCarregando = true;
        const loading = document.getElementById('raiox-loading');
        if (loading) loading.style.display = 'block';

        try {
            const formData = new FormData();
            formData.append('id_evento', raioxEventoAtual);
            formData.append('offset', raioxOffset);
            
            const response = await fetch('processadores/eventos/carregar_raiox.php', { method: 'POST', body: formData });
            const html = await response.text();

            if (html.trim() === '') {
                raioxTemMais = false;
                if (loading) loading.innerHTML = "Fim da galeria analisada.";
            } else {
                document.getElementById('grid-raiox-container').insertAdjacentHTML('beforeend', html);
                raioxOffset += 30; // Pula as 30 fotos já carregadas
                if (loading) loading.style.display = 'none';
            }
        } catch(err) {
            if (loading) loading.innerHTML = '<div class="alerta erro" style="display:block;">Erro de conexão ao carregar o Raio-X.</div>';
        }
        
        raioxCarregando = false;
    }

    function fecharModalRaioX(e) {
        if(!e || e.target === document.getElementById('modal-raiox') || e === 'forced') {
            document.getElementById('modal-raiox').style.display = 'none';
            document.getElementById('corpo-raiox').innerHTML = ''; 
            document.getElementById('corpo-raiox').onscroll = null; 
        }
    }
    // ==========================================

    if(document.getElementById('form-evento')) {
        document.getElementById('form-evento').addEventListener('submit', async function(e) {
            e.preventDefault();
            const btn = this.querySelector('button[type="submit"]');
            const msg = document.getElementById('msg-evento');
            const textoOriginal = btn.innerText;
            
            btn.innerText = 'Criando...'; btn.disabled = true; msg.style.display = 'none';

            let precoLimpo = document.getElementById('preco_foto').value.replace(/\./g, '').replace(',', '.');

            const formData = new FormData();
            formData.append('nome', document.getElementById('nome_evento').value);
            formData.append('categoria', document.getElementById('categoria_evento').value); // <-- NOVO
            formData.append('drive_folder', document.getElementById('drive_folder').value);
            formData.append('preco', precoLimpo);
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
            
            btn.innerText = 'Salvando...'; btn.disabled = true; msg.style.display = 'none';

            let precoLimpo = document.getElementById('edit_preco_foto').value.replace(/\./g, '').replace(',', '.');

            const formData = new FormData();
            formData.append('id', document.getElementById('edit_id_evento').value);
            formData.append('nome', document.getElementById('edit_nome_evento').value);
            formData.append('categoria', document.getElementById('edit_categoria_evento').value); // <-- NOVO
            formData.append('drive_folder', document.getElementById('edit_drive_folder').value);
            formData.append('preco', precoLimpo);
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
            
            btn.innerText = 'Processando...'; btn.disabled = true;

            const formData = new FormData();
            formData.append('id', document.getElementById('excluir_id_evento').value);

            try {
                const resposta = await fetch('processadores/eventos/excluir_evento.php', { method: 'POST', body: formData });
                const res = await resposta.json();
                if (res.sucesso) { location.reload(); } else { alert(res.erro || 'Falha ao desativar.'); }
            } catch(err) { alert('Erro de comunicação.'); }
            btn.innerText = textoOriginal; btn.disabled = false;
        });
    }

    async function sincronizarDrive(id, btn) {
        const textoOriginal = btn.innerText;
        btn.innerText = 'Sincronizando...'; btn.disabled = true;
        
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
</script>