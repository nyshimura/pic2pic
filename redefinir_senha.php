<?php
declare(strict_types=1);
session_start();
require_once 'configuracoes/conexao.php';

$token = isset($_GET['token']) ? preg_replace('/[^a-zA-Z0-9]/', '', $_GET['token']) : '';
$tokenValido = false;
$idComprador = null;

if (!empty($token)) {
    // Procura o token e verifica se a data atual é MENOR que a data de expiração (CORRIGIDO PARA token_expira_em)
    $stmt = $pdo->prepare("SELECT id, nome FROM compradores WHERE token_recuperacao = ? AND token_expira_em > NOW()");
    $stmt->execute([$token]);
    $comprador = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($comprador) {
        $tokenValido = true;
        $idComprador = $comprador['id'];
        $nomeComprador = $comprador['nome'];
    }
}

// Se o formulário for submetido via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $tokenValido) {
    header('Content-Type: application/json');
    $senha = $_POST['senha'] ?? '';
    
    if (strlen($senha) < 6) {
        echo json_encode(['sucesso' => false, 'erro' => 'A senha deve ter pelo menos 6 caracteres.']);
        exit;
    }

    try {
        $senhaHash = password_hash($senha, PASSWORD_DEFAULT);
        
        // Atualiza a senha e ANULA o token para não ser usado 2 vezes (CORRIGIDO PARA token_expira_em)
        $stmtUpdate = $pdo->prepare("UPDATE compradores SET senha = ?, token_recuperacao = NULL, token_expira_em = NULL WHERE id = ?");
        $stmtUpdate->execute([$senhaHash, $idComprador]);

        // Faz o login automático do cliente para máxima fluidez
        $_SESSION['comprador_id'] = $idComprador;
        $_SESSION['comprador_nome'] = $nomeComprador;

        echo json_encode(['sucesso' => true]);
        exit;
    } catch (Throwable $e) {
        echo json_encode(['sucesso' => false, 'erro' => 'Erro interno ao salvar.']);
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="pt-PT">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Criar Palavra-passe - Pic2Pic</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
        body { background-color: #f4f6f8; color: #333; display: flex; justify-content: center; align-items: center; min-height: 100vh; padding: 20px; }
        .box-reset { background: #fff; width: 100%; max-width: 400px; padding: 40px 30px; border-radius: 16px; box-shadow: 0 10px 30px rgba(0,0,0,0.05); text-align: center; }
        h2 { font-size: 22px; color: #111; margin-bottom: 10px; }
        p { font-size: 14px; color: #666; margin-bottom: 25px; line-height: 1.5; }
        .form-group { margin-bottom: 15px; text-align: left; }
        .form-group label { display: block; font-size: 13px; font-weight: 600; margin-bottom: 5px; color: #444; }
        .form-group input { width: 100%; padding: 14px; border: 1px solid #dadce0; border-radius: 8px; font-size: 14px; transition: 0.2s; }
        .form-group input:focus { border-color: #1a73e8; outline: none; }
        .btn-submit { background: #1a73e8; color: #fff; border: none; padding: 14px; border-radius: 8px; font-size: 14px; font-weight: bold; cursor: pointer; width: 100%; transition: 0.2s; margin-top: 10px; }
        .btn-submit:hover { background: #1557b0; }
        .alerta { padding: 12px; border-radius: 8px; margin-bottom: 20px; font-size: 13px; display: none; text-align: center; font-weight: 500; }
        .alerta.erro { background: #fce8e6; color: #c5221f; border: 1px solid #fad2cf; display: block; }
        .aviso-erro { text-align: center; }
        .aviso-erro h1 { font-size: 50px; margin-bottom: 10px; }
        .btn-voltar { display: inline-block; margin-top: 20px; color: #1a73e8; text-decoration: none; font-weight: bold; font-size: 14px; }
    </style>
</head>
<body>

    <div class="box-reset">
        <?php if (!$tokenValido): ?>
            <div class="aviso-erro">
                <h1>⏳</h1>
                <h2>Link Expirado</h2>
                <p>Este link de segurança expirou ou já foi utilizada.</p>
                <a href="index.php" class="btn-voltar">← Voltar e tentar novamente</a>
            </div>
        <?php else: ?>
            <h2>Definir Senha</h2>
            <p>Olá, <b><?= htmlspecialchars(explode(' ', $nomeComprador)[0]) ?></b>! Crie uma senha segura para baixar às suas fotos.</p>
            
            <div id="msg-reset" class="alerta"></div>

            <form id="form-reset">
                <div class="form-group">
                    <label>Nova Senha</label>
                    <input type="password" id="senha1" required minlength="6" placeholder="Mínimo de 6 caracteres">
                </div>
                <div class="form-group">
                    <label>Confirmar Senha</label>
                    <input type="password" id="senha2" required minlength="6" placeholder="Repita a palavra-passe">
                </div>
                <button type="submit" id="btn-salvar" class="btn-submit">Guardar e logar</button>
            </form>
        <?php endif; ?>
    </div>

    <?php if ($tokenValido): ?>
    <script>
        document.getElementById('form-reset').addEventListener('submit', async function(e) {
            e.preventDefault();
            const senha1 = document.getElementById('senha1').value;
            const senha2 = document.getElementById('senha2').value;
            const msg = document.getElementById('msg-reset');
            const btn = document.getElementById('btn-salvar');

            if (senha1 !== senha2) {
                msg.className = 'alerta erro'; msg.innerText = 'As senhas não coincidem.'; return;
            }

            btn.innerText = 'A guardar...'; btn.disabled = true; msg.style.display = 'none';

            const formData = new FormData();
            formData.append('senha', senha1);

            try {
                // Envia para este mesmo ficheiro
                const r = await fetch(window.location.href, { method: 'POST', body: formData });
                const res = await r.json();

                if (res.sucesso) {
                    btn.innerText = 'Sucesso! Redirecionando...';
                    btn.style.background = '#137333';
                    // Como a sessão foi criada no backend, redireciona direto para o painel!
                    setTimeout(() => window.location.href = 'painel_comprador.php', 1500);
                } else {
                    msg.className = 'alerta erro'; msg.innerText = res.erro;
                    btn.innerText = 'Guardar e Aceder'; btn.disabled = false;
                }
            } catch(err) {
                msg.className = 'alerta erro'; msg.innerText = 'Erro de comunicação com o servidor.';
                btn.innerText = 'Guardar e Logar'; btn.disabled = false;
            }
        });
    </script>
    <?php endif; ?>

</body>
</html>