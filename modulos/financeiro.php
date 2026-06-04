<?php
// Módulo de Integração com o Mercado Pago
$stmt = $pdo->prepare("SELECT mp_public_key, mp_access_token FROM fotografos WHERE id = ?");
$stmt->execute([$idFotografo]);
$dadosFinanceiro = $stmt->fetch();

$publicKeyAtual = $dadosFinanceiro['mp_public_key'] ?? '';
$temMercadoPago = !empty($dadosFinanceiro['mp_access_token']);

// Gera a URL exata do Webhook para o fotógrafo copiar
$urlWebhook = "https://" . $_SERVER['HTTP_HOST'] . "/fotos/processadores/pagamentos/webhook_mp.php";
?>

<style>
    /* Estilos para o Mini-Tutorial (Acordeão) */
    details.tutorial-mp {
        background-color: #f8f9fa;
        border: 1px solid #e0e0e0;
        border-radius: 8px;
        margin-bottom: 20px;
        padding: 12px 15px;
        transition: 0.3s;
    }
    details.tutorial-mp[open] {
        background-color: #fff;
        border-color: #1a73e8;
        box-shadow: 0 4px 12px rgba(26,115,232,0.1);
    }
    details.tutorial-mp summary {
        font-weight: 600;
        color: #1a73e8;
        cursor: pointer;
        outline: none;
        list-style: none;
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 14px;
    }
    details.tutorial-mp summary::-webkit-details-marker { display: none; }
    details.tutorial-mp summary::before { content: '📘'; font-size: 16px; }
    
    details.tutorial-mp .tutorial-content {
        margin-top: 15px;
        font-size: 13px;
        color: #444;
        line-height: 1.6;
        border-top: 1px solid #eee;
        padding-top: 15px;
    }
    details.tutorial-mp .tutorial-content a { color: #1a73e8; text-decoration: none; font-weight: bold; }
    details.tutorial-mp .tutorial-content a:hover { text-decoration: underline; }
    details.tutorial-mp .tutorial-content ol { padding-left: 20px; margin-bottom: 15px; }
    details.tutorial-mp .tutorial-content li { margin-bottom: 10px; }
    
    .box-webhook {
        background: #e9ecef;
        padding: 10px;
        border-radius: 6px;
        border: 1px dashed #adb5bd;
        margin-top: 5px;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .box-webhook input {
        flex: 1;
        padding: 8px;
        border: 1px solid #ccc;
        border-radius: 4px;
        font-size: 12px;
        color: #333;
        background: #fff;
    }
    .btn-copiar-wh {
        background: #343a40;
        color: #fff;
        border: none;
        padding: 8px 12px;
        border-radius: 4px;
        cursor: pointer;
        font-size: 12px;
        font-weight: bold;
    }
    .btn-copiar-wh:hover { background: #1d2124; }
</style>

<section class="card" style="max-width: 600px; margin: 0 auto;">
    <div class="card-header card-header-flex" style="flex-direction: row; justify-content: space-between;">
        <div>
            <h3>Mercado Pago</h3>
            <p style="font-size: 13px; color: #666;">Conecte as suas chaves para faturar.</p>
        </div>
        <?php if ($temMercadoPago): ?>
            <span class="badge-ativo" style="height: fit-content; background-color: #d4edda; color: #155724; padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: bold;">✅ Cofre Seguro Ativo</span>
        <?php endif; ?>
    </div>

    <details class="tutorial-mp">
        <summary>Não sabe como configurar? Veja o passo a passo aqui.</summary>
        <div class="tutorial-content">
            <ol>
                <li>Acesse o <a href="https://mercadopago.com.br/developers/panel/applications" target="_blank">Painel de Developers ↗</a> e faça login.</li>
                <li>Clique em <strong>"Criar aplicação"</strong> (Pagamentos on-line > Checkout Transparente).</li>
                <li>Abra a sua aplicação e copie as <strong>Credenciais de Produção</strong> (Public Key e Access Token) para os campos abaixo.</li>
                <li><strong>Obrigatório:</strong> No menu lateral, vá em <strong>Webhooks (Notificações)</strong>, cole a URL exata do seu sistema abaixo em <strong>modo de produção</strong> e marque a opção <strong>"Pagamentos"</strong>.
                    <div class="box-webhook">
                        <input type="text" id="url-wh" value="<?= $urlWebhook ?>" readonly>
                        <button type="button" class="btn-copiar-wh" onclick="copiarUrlWebhook(this)">Copiar URL</button>
                    </div>
                </li>
            </ol>
        </div>
    </details>

    <form id="form-mp">
        <div class="form-group">
            <label>Public Key (APP_USR-...)</label>
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

<script>
    function copiarUrlWebhook(btn) {
        const input = document.getElementById("url-wh");
        input.select();
        document.execCommand("copy");
        btn.innerText = "✅ Copiado!";
        btn.style.background = "#28a745";
        setTimeout(() => { btn.innerText = "Copiar URL"; btn.style.background = "#343a40"; }, 3000);
    }

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
            btn.innerText = 'Validando na API do Mercado Pago...'; 
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
        if(!confirm("Tem certeza que deseja remover o Mercado Pago? Todos os seus catálogos passarão a ser gratuitos imediatamente.")) return;
        
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