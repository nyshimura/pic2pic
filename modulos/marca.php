<?php
// Módulo de Identidade Visual
$stmtMarca = $pdo->prepare("SELECT marca_dagua FROM fotografos WHERE id = ?");
$stmtMarca->execute([$idFotografo]);
$marcaDaguaAtual = $stmtMarca->fetchColumn() ?? '';
?>

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

<script>
    function previewImagem(event) {
        const input = event.target;
        const preview = document.getElementById('preview-marca');
        const txt = document.getElementById('txt-sem-marca');
        
        if (input.files && input.files[0]) {
            const reader = new FileReader();
            reader.onload = function(e) {
                preview.src = e.target.result;
                preview.style.display = 'inline-block';
                txt.style.display = 'none';
            }
            reader.readAsDataURL(input.files[0]);
        }
    }

    if(document.getElementById('form-marca-ajax')) {
        document.getElementById('form-marca-ajax').addEventListener('submit', async function(e) {
            e.preventDefault();
            const btn = this.querySelector('button[type="submit"]');
            const msg = document.getElementById('msg-marca');
            const fileInput = document.getElementById('input-marca-painel');
            
            if (!fileInput.files || fileInput.files.length === 0) {
                msg.className = 'alerta erro'; msg.innerText = 'Selecione uma imagem primeiro.'; msg.style.display = 'block';
                return;
            }

            const textoOriginal = btn.innerText;
            btn.innerText = 'Salvando...'; btn.disabled = true; msg.style.display = 'none';

            const formData = new FormData();
            formData.append('marca_dagua', fileInput.files[0]);

            try {
                const resposta = await fetch('processadores/configuracoes/salvar_marca.php', { method: 'POST', body: formData });
                const res = await resposta.json();
                if (res.sucesso) { 
                    msg.className = 'alerta sucesso'; msg.innerText = 'Marca d\'água atualizada com sucesso!'; msg.style.display = 'block'; 
                    setTimeout(() => location.reload(), 1500);
                } else { 
                    msg.className = 'alerta erro'; msg.innerText = res.erro || 'Falha ao salvar.'; msg.style.display = 'block'; 
                }
            } catch(err) { 
                msg.className = 'alerta erro'; msg.innerText = 'Erro de comunicação.'; msg.style.display = 'block'; 
            }
            btn.innerText = textoOriginal; btn.disabled = false;
        });
    }
</script>