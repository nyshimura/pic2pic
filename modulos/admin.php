<?php
// Módulo Administrativo de Configuração Global
if (!$isAdmin) {
    echo "<div class='alerta erro' style='display:block;'>Acesso negado. Apenas administradores podem ver esta página.</div>";
    exit;
}

// =========================================================
// PROCESSAMENTO DE CATEGORIAS (ADICIONAR E EDITAR)
// =========================================================
$msgCatSucesso = null;
$msgCatErro = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao'])) {
    // 1. Adicionar Nova Categoria
    if ($_POST['acao'] === 'adicionar_categoria') {
        $novaCategoria = trim(filter_input(INPUT_POST, 'nome_categoria', FILTER_SANITIZE_SPECIAL_CHARS));
        if (!empty($novaCategoria)) {
            try {
                $stmt = $pdo->prepare("INSERT INTO categorias_eventos (nome) VALUES (?)");
                $stmt->execute([$novaCategoria]);
                $msgCatSucesso = "Categoria '$novaCategoria' adicionada com sucesso!";
            } catch (PDOException $e) {
                $msgCatErro = "Erro: A categoria '$novaCategoria' já existe no sistema.";
            }
        }
    }

    // 2. Editar Categoria Existente
    if ($_POST['acao'] === 'editar_categoria') {
        $idCat = intval($_POST['id_categoria']);
        $nomeAtualizado = trim(filter_input(INPUT_POST, 'nome_editado', FILTER_SANITIZE_SPECIAL_CHARS));
        
        if ($idCat > 0 && !empty($nomeAtualizado)) {
            try {
                $pdo->prepare("UPDATE categorias_eventos SET nome = ? WHERE id = ?")->execute([$nomeAtualizado, $idCat]);
                $msgCatSucesso = "Categoria atualizada com sucesso!";
            } catch (Exception $e) {
                $msgCatErro = "Erro ao atualizar a categoria. Verifique se o nome já existe.";
            }
        }
    }
}

// =========================================================
// BUSCA DOS DADOS DO BANCO PARA EXIBIR NA TELA
// =========================================================

// Busca as categorias ativas
$categoriasGerais = $pdo->query("SELECT * FROM categorias_eventos ORDER BY nome ASC")->fetchAll(PDO::FETCH_ASSOC);

// Busca todas as configurações globais (SMTP e AdSense)
$stmtConfig = $pdo->query("SELECT email_robo_drive, smtp_host, smtp_port, smtp_user, adsense_client_id, adsense_ativo FROM configuracoes_sistema WHERE id = 1");
$configGlobal = $stmtConfig->fetch();

$emailRoboDrive = $configGlobal['email_robo_drive'] ?? 'Configuração pendente';
$adsenseId = $configGlobal['adsense_client_id'] ?? '';
$adsenseAtivo = (int)($configGlobal['adsense_ativo'] ?? 0);
?>

<section class="card" style="max-width: 600px; margin: 0 auto 25px auto; border-top: 4px solid #ffc107;">
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
                <label>Usuário / E-mail Autenticado</label>
                <input type="email" id="admin_smtp_user" value="<?= htmlspecialchars($configGlobal['smtp_user'] ?? '') ?>" placeholder="nao-responda@ccrn.com.br">
            </div>
            <div class="form-group">
                <label>Senha do E-mail</label>
                <input type="password" id="admin_smtp_pass" autocomplete="new-password" placeholder="Apenas preencha para alterar a senha atual">
            </div>
        </div>

        <div id="msg-admin" class="alerta"></div>
        <button type="submit" class="btn-acao" style="background-color: #333; width: 100%;">Salvar Configurações</button>
    </form>
</section>

<section class="card" style="max-width: 600px; margin: 0 auto 25px auto; border-top: 4px solid #1a73e8;">
    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
        <div>
            <h3>📈 Google AdSense</h3>
            <p style="font-size: 13px; color: #666;">Monetize o tráfego do sistema com anúncios.</p>
        </div>
        <?php if ($adsenseAtivo === 1): ?>
            <span style="background-color: #d4edda; color: #155724; padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: bold;">✅ Ativo</span>
        <?php else: ?>
            <span style="background-color: #fce8e6; color: #c5221f; padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: bold;">❌ Desativado</span>
        <?php endif; ?>
    </div>
    <form id="form-adsense">
        <div class="form-group">
            <label>Publisher ID (Client ID)</label>
            <input type="text" id="adsense_id" value="<?= htmlspecialchars($adsenseId) ?>" placeholder="Ex: ca-pub-1234567890123456">
            <small style="color: #888; font-size: 11px; display: block; margin-top: 5px;">Encontra este código no topo do seu painel do Google AdSense.</small>
        </div>

        <div class="form-group">
            <label>Status do AdSense</label>
            <select id="adsense_status" style="width: 100%; padding: 12px; border: 1px solid #dadce0; border-radius: 6px; font-size: 14px; background: #fff;">
                <option value="1" <?= $adsenseAtivo === 1 ? 'selected' : '' ?>>Ativado (Exibir anúncios no sistema)</option>
                <option value="0" <?= $adsenseAtivo === 0 ? 'selected' : '' ?>>Desativado (Ocultar anúncios)</option>
            </select>
        </div>

        <div id="msg-adsense" class="alerta"></div>
        <button type="submit" class="btn-acao" style="background-color: #1a73e8; width: 100%;">Salvar AdSense</button>
    </form>
</section>

<section class="card" style="max-width: 600px; margin: 0 auto; border-top: 4px solid #28a745;">
    <div class="card-header">
        <h3>🏷️ Gestão de Categorias</h3>
        <p style="font-size: 13px; color: #666;">Estas categorias organizam os eventos na vitrine principal em formato de prateleiras.</p>
    </div>

    <?php if ($msgCatSucesso): ?>
        <div class="alerta sucesso" style="display:block;"><?= $msgCatSucesso ?></div>
    <?php endif; ?>
    <?php if ($msgCatErro): ?>
        <div class="alerta erro" style="display:block;"><?= $msgCatErro ?></div>
    <?php endif; ?>

    <form method="POST" style="display: flex; gap: 10px; margin-bottom: 25px; align-items: flex-end;">
        <input type="hidden" name="acao" value="adicionar_categoria">
        <div style="flex-grow: 1;">
            <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 5px; color: #444;">Nova Categoria</label>
            <input type="text" name="nome_categoria" placeholder="Ex: Casamentos" required style="width: 100%; padding: 10px; border: 1px solid #dadce0; border-radius: 6px; font-size: 14px;">
        </div>
        <button type="submit" class="btn-acao" style="background: #28a745; width: auto; padding: 11px 20px;">
            + Adicionar
        </button>
    </form>

    <div style="background: #f8f9fa; border: 1px solid #ddd; border-radius: 8px; overflow: hidden;">
        <table style="width: 100%; border-collapse: collapse;">
            <thead>
                <tr style="background: #e9ecef; border-bottom: 2px solid #ddd;">
                    <th style="padding: 12px; text-align: left; font-size: 13px; color: #444; width: 60px;">ID</th>
                    <th style="padding: 12px; text-align: left; font-size: 13px; color: #444;">Nome da Categoria</th>
                    <th style="padding: 12px; text-align: center; font-size: 13px; color: #444; width: 120px;">Ação</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($categoriasGerais)): ?>
                    <tr><td colspan="3" style="padding: 15px; text-align: center; color: #666; font-size: 14px;">Nenhuma categoria registada.</td></tr>
                <?php else: ?>
                    <?php foreach ($categoriasGerais as $cat): ?>
                        <tr style="border-bottom: 1px solid #eee; background: #fff;">
                            <td style="padding: 12px; font-size: 13px; color: #666; font-weight: bold;">#<?= $cat['id'] ?></td>
                            <td style="padding: 12px;">
                                <form method="POST" style="display: flex; align-items: center; margin: 0; width: 100%;">
                                    <input type="hidden" name="acao" value="editar_categoria">
                                    <input type="hidden" name="id_categoria" value="<?= $cat['id'] ?>">
                                    <input type="text" name="nome_editado" value="<?= htmlspecialchars($cat['nome']) ?>" required style="padding: 8px 10px; border: 1px solid #dadce0; border-radius: 4px; font-size: 14px; width: 100%;">
                            </td>
                            <td style="padding: 12px; text-align: center;">
                                    <button type="submit" style="background: #f1f3f4; color: #3c4043; border: 1px solid #dadce0; padding: 8px 12px; border-radius: 4px; font-size: 12px; font-weight: 600; cursor: pointer; transition: 0.2s;">
                                        Salvar
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <p style="font-size: 11px; color: #888; text-align: center; margin-top: 15px; line-height: 1.4;">A exclusão de categorias foi desativada por motivos de segurança, para garantir que eventos vinculados a elas não sejam "órfãos" na plataforma.</p>
</section>

<script>
    // ---------------------------------------------------------
    // MOTOR DE SUBMISSÃO: SMTP & DRIVE (CARD 1)
    // ---------------------------------------------------------
    if(document.getElementById('form-admin')) {
        document.getElementById('form-admin').addEventListener('submit', async function(e) {
            e.preventDefault();
            const btn = this.querySelector('button[type="submit"]');
            const msg = document.getElementById('msg-admin');
            const textoOriginal = btn.innerText;
            
            btn.innerText = 'Salvando...'; btn.disabled = true; msg.style.display = 'none';

            const formData = new FormData();
            formData.append('email_robo', document.getElementById('admin_email_robo').value);
            formData.append('smtp_host', document.getElementById('admin_smtp_host').value);
            formData.append('smtp_port', document.getElementById('admin_smtp_port').value);
            formData.append('smtp_user', document.getElementById('admin_smtp_user').value);
            formData.append('smtp_pass', document.getElementById('admin_smtp_pass').value);

            try {
                const resposta = await fetch('processadores/configuracoes/editar_admin.php', { method: 'POST', body: formData });
                const res = await resposta.json();
                if (res.sucesso) { msg.className = 'alerta sucesso'; msg.innerText = 'Configurações gravadas com sucesso!'; msg.style.display = 'block'; } 
                else { msg.className = 'alerta erro'; msg.innerText = res.erro || 'Falha.'; msg.style.display = 'block'; }
            } catch(err) { msg.className = 'alerta erro'; msg.innerText = 'Erro de comunicação.'; msg.style.display = 'block'; }
            
            btn.innerText = textoOriginal; btn.disabled = false;
        });
    }

    // ---------------------------------------------------------
    // MOTOR DE SUBMISSÃO: GOOGLE ADSENSE (CARD 2)
    // ---------------------------------------------------------
    if(document.getElementById('form-adsense')) {
        document.getElementById('form-adsense').addEventListener('submit', async function(e) {
            e.preventDefault();
            const btn = this.querySelector('button[type="submit"]');
            const msg = document.getElementById('msg-adsense');
            const textoOriginal = btn.innerText;
            
            btn.innerText = 'Salvando...'; btn.disabled = true; msg.style.display = 'none';

            const dados = {
                client_id: document.getElementById('adsense_id').value.trim(),
                ativo: document.getElementById('adsense_status').value
            };

            try {
                const response = await fetch('processadores/configuracoes/salvar_adsense.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(dados)
                });
                const res = await response.json();

                if (res.sucesso) {
                    msg.className = 'alerta sucesso'; 
                    msg.innerText = 'Configurações do AdSense gravadas!'; 
                    msg.style.display = 'block';
                    setTimeout(() => location.reload(), 1500); // Recarrega para atualizar a badge visual
                } else {
                    msg.className = 'alerta erro'; 
                    msg.innerText = res.erro || 'Falha ao salvar AdSense.'; 
                    msg.style.display = 'block';
                }
            } catch (error) {
                msg.className = 'alerta erro'; 
                msg.innerText = 'Erro de comunicação com o servidor.'; 
                msg.style.display = 'block';
            }
            
            btn.innerText = textoOriginal; btn.disabled = false;
        });
    }
</script>