<?php
declare(strict_types=1);
session_start();
require_once 'configuracoes/conexao.php';

// Valida a URL e verifica se recebeu as fotos via POST da galeria
$token = isset($_GET['e']) ? trim(filter_input(INPUT_GET, 'e', FILTER_SANITIZE_SPECIAL_CHARS)) : '';

if (empty($token) || $_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['fotos_selecionadas'])) {
    header("Location: index.php");
    exit;
}

// Descodifica o pacote de fotos (JSON para Array PHP)
$fotosIds = json_decode($_POST['fotos_selecionadas'], true);
if (!is_array($fotosIds) || empty($fotosIds)) {
    die("O carrinho está vazio. Volte e selecione algumas fotos.");
}

try {
    // Busca os dados oficiais do evento para garantir o preço e a validade
    $stmt = $pdo->prepare("SELECT id, nome, preco_foto, expira_em, ativo, fotografo_id FROM eventos WHERE token_url = ? AND ativo = 1");
    $stmt->execute([$token]);
    $evento = $stmt->fetch();

    if (!$evento || strtotime($evento['expira_em']) < time()) {
        die("Este catálogo está indisponível ou foi encerrado.");
    }

    $qtdFotos = count($fotosIds);
    $precoUnitario = floatval($evento['preco_foto']);
    $valorTotal = $qtdFotos * $precoUnitario;
    $isGratis = ($valorTotal == 0);

} catch (Exception $e) {
    die("Erro ao carregar os dados de checkout.");
}
?>
<!DOCTYPE html>
<html lang="pt-PT">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout - <?= htmlspecialchars($evento['nome']) ?></title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
        body { background-color: #f4f6f8; color: #333; display: flex; justify-content: center; align-items: center; min-height: 100vh; padding: 20px; }
        
        .checkout-box { background: #fff; width: 100%; max-width: 500px; padding: 40px 30px; border-radius: 16px; box-shadow: 0 10px 30px rgba(0,0,0,0.05); }
        .header-chk { text-align: center; margin-bottom: 25px; border-bottom: 1px solid #eee; padding-bottom: 20px; }
        .header-chk h1 { font-size: 22px; color: #111; margin-bottom: 5px; }
        .header-chk p { font-size: 14px; color: #666; }
        
        .resumo { background: #f8f9fa; padding: 15px; border-radius: 8px; margin-bottom: 25px; display: flex; justify-content: space-between; align-items: center; border: 1px solid #eee; }
        .resumo div { font-size: 14px; color: #555; }
        .resumo strong { display: block; font-size: 20px; color: <?= $isGratis ? '#137333' : '#1a73e8' ?>; }
        
        .form-group { margin-bottom: 20px; text-align: left; }
        .form-group label { display: block; font-size: 13px; font-weight: 600; margin-bottom: 8px; color: #444; }
        .form-group input { width: 100%; padding: 14px; border: 1px solid #dadce0; border-radius: 8px; font-size: 15px; transition: 0.2s; }
        .form-group input:focus { border-color: #1a73e8; outline: none; box-shadow: 0 0 0 3px rgba(26,115,232,0.1); }
        
        .input-btn-group { display: flex; gap: 0; }
        .input-btn-group input { border-radius: 8px 0 0 8px; border-right: none; }
        .input-btn-group button { background: #1a73e8; color: #fff; border: none; padding: 0 20px; border-radius: 0 8px 8px 0; font-weight: bold; cursor: pointer; transition: 0.2s; }
        .input-btn-group button:hover { background: #1557b0; }
        
        .btn-submit { background: #1a73e8; color: #fff; border: none; padding: 16px; border-radius: 8px; font-size: 16px; font-weight: bold; cursor: pointer; width: 100%; transition: 0.2s; margin-top: 10px; }
        .btn-submit:hover { background: #1557b0; }
        .btn-submit:disabled { background: #ccc; cursor: not-allowed; }

        .alerta { padding: 12px; border-radius: 8px; margin-bottom: 20px; font-size: 13px; display: none; text-align: center; font-weight: 600; }
        .alerta.erro { background: #fce8e6; color: #c5221f; border: 1px solid #fad2cf; display: block; }
        
        .btn-voltar { display: block; text-align: center; margin-top: 20px; font-size: 13px; color: #666; text-decoration: none; font-weight: 600; }
        .btn-voltar:hover { color: #111; }
    </style>
</head>
<body>

    <div class="checkout-box" id="caixa-principal">
        <div class="header-chk">
            <h1>Dados da Entrega</h1>
            <p>Para onde enviamos o ficheiro ZIP?</p>
        </div>

        <div class="resumo">
            <div>
                <strong><?= $qtdFotos ?> foto(s)</strong>
                <?= htmlspecialchars($evento['nome']) ?>
            </div>
            <div>
                <?= $isGratis ? '<strong>Grátis</strong>' : '<strong>R$ ' . number_format($valorTotal, 2, ',', '.') . '</strong>' ?>
            </div>
        </div>
        
        <div id="msg-checkout" class="alerta"></div>

        <form id="form-checkout">
            <div class="form-group">
                <label>O seu E-mail principal</label>
                <div class="input-btn-group">
                    <input type="email" id="chk_email" required placeholder="joao@email.com" onkeypress="handleEnter(event)">
                    <button type="button" id="btn-verificar-email" onclick="verificarEmailCheckout()">Validar</button>
                </div>
            </div>
            
            <div id="campos-ocultos" style="display: none;">
                <div class="form-group">
                    <label>Nome Completo</label>
                    <input type="text" id="chk_nome" placeholder="Como gosta de ser chamado?">
                </div>
                <div class="form-group">
                    <label>CPF (Necessário para faturar)</label>
                    <input type="text" id="chk_cpf" maxlength="14" placeholder="000.000.000-00" oninput="mascaraCPF(this)">
                </div>
                <div style="background: #f8f9fa; padding: 12px; border-radius: 8px; border: 1px solid #eee; margin: 20px 0;">
    <p style="font-size: 11px; color: #555; text-align: center; line-height: 1.5; margin: 0;">
        Ao prosseguir, você concorda com os <a href="termos.php" target="_blank" style="color: #1a73e8; font-weight: bold;">Termos de Uso</a> 
        e reconhece que a Pic2Pic é uma infraestrutura de vendas. O valor do pagamento será creditado diretamente na conta do Fotógrafo, que é o único responsável pela entrega e suporte das imagens.
    </p>
</div>
                <button type="submit" class="btn-submit" id="btn-submit-checkout">
                    <?= $isGratis ? 'Confirmar e Transferir Fotos' : 'Gerar PIX de Pagamento' ?>
                </button>
            </div>
        </form>

        <a href="galeria.php?e=<?= $token ?>" class="btn-voltar">← Voltar para a Galeria</a>
    </div>

    <script>
        const fotosSelecionadas = <?= json_encode($fotosIds) ?>;

        function mascaraCPF(input) {
            let v = input.value.replace(/\D/g, ''); 
            if (v.length > 11) v = v.slice(0, 11);
            if (v.length > 9) v = v.replace(/(\d{3})(\d{3})(\d{3})(\d{2})/, "$1.$2.$3-$4");
            else if (v.length > 6) v = v.replace(/(\d{3})(\d{3})(\d{1,3})/, "$1.$2.$3");
            else if (v.length > 3) v = v.replace(/(\d{3})(\d{1,3})/, "$1.$2");
            input.value = v;
        }

        function handleEnter(e) {
            if(e.key === 'Enter') {
                e.preventDefault();
                verificarEmailCheckout();
            }
        }

        async function verificarEmailCheckout() {
            const emailInput = document.getElementById('chk_email');
            const email = emailInput.value.trim();
            if(!email || !email.includes('@')) {
                alert("Por favor, introduza um e-mail válido.");
                return;
            }

            const btn = document.getElementById('btn-verificar-email');
            btn.innerText = '...'; 
            btn.disabled = true;

            try {
                const r = await fetch('processadores/pagamentos/verificar_email_checkout.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({email: email})
                });
                const res = await r.json();

                document.getElementById('campos-ocultos').style.display = 'block';
                emailInput.style.backgroundColor = '#f1f3f4';
                emailInput.readOnly = true; 
                btn.style.display = 'none'; 

                if(res.encontrado) {
                    if(res.nome) document.getElementById('chk_nome').value = res.nome;
                    if(res.cpf) {
                        document.getElementById('chk_cpf').value = res.cpf;
                        document.getElementById('btn-submit-checkout').focus(); 
                    } else {
                        document.getElementById('chk_cpf').focus(); 
                    }
                } else {
                    document.getElementById('chk_nome').focus(); 
                }
            } catch(e) {
                console.error(e); 
                document.getElementById('campos-ocultos').style.display = 'block'; 
                btn.innerText = 'Validar'; 
                btn.disabled = false;
            }
        }

        // Nova função para copiar o PIX
        function copiarPix() {
            const texto = document.getElementById("texto-pix");
            texto.select();
            document.execCommand("copy");
            
            const btn = document.getElementById("btn-copiar");
            btn.innerText = "✅ Código Copiado!";
            btn.style.background = "#28a745";
            
            setTimeout(() => {
                btn.innerText = "📋 Copiar Código PIX";
                btn.style.background = "#1a73e8";
            }, 3000);
        }

        // Submissão final do pedido
        document.getElementById('form-checkout').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const btn = document.getElementById('btn-submit-checkout');
            const msg = document.getElementById('msg-checkout');
            const textoOriginal = btn.innerText;
            
            btn.innerText = 'Processando o pedido...'; 
            btn.disabled = true; 
            msg.style.display = 'none';

            const dados = {
                token_evento: '<?= $token ?>',
                fotos: fotosSelecionadas,
                cliente_nome: document.getElementById('chk_nome').value,
                cliente_email: document.getElementById('chk_email').value,
                cliente_cpf: document.getElementById('chk_cpf').value
            };

            try {
                const response = await fetch('processadores/pagamentos/processar_pedido.php', {
                    method: 'POST', 
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(dados)
                });
                
                let res;
                try {
                    res = await response.json();
                } catch (parseError) {
                    throw new Error("Erro interno do servidor. Pressione F12 e olhe a aba 'Rede' para ver o motivo real.");
                }
                
                if (res.sucesso) {
                    if (res.is_gratis) {
                        window.location.href = 'entrega.php?token=' + res.token_download;
                    } else {
                        // AQUI ESTÁ A MÁGICA: Substitui o conteúdo da caixa pelo PIX gerado E adiciona o botão de voltar!
                        const caixa = document.getElementById('caixa-principal');
                        caixa.innerHTML = `
                            <div style="text-align: center;">
                                <h2 style="color: #1a73e8; margin-bottom: 15px;">Pedido Gerado!</h2>
                                <p style="color: #555; margin-bottom: 20px; font-size: 14px;">Escaneie o QR Code abaixo com a app do seu banco para pagar.</p>
                                
                                <img src="data:image/jpeg;base64,${res.qr_code_base64}" style="width: 220px; height: 220px; border: 2px solid #eee; border-radius: 8px; padding: 10px; margin-bottom: 20px;">
                                
                                <p style="font-size: 14px; font-weight: bold; color: #444; margin-bottom: 10px;">Ou use o PIX Copia e Cola:</p>
                                <textarea id="texto-pix" style="width: 100%; height: 75px; font-size: 12px; padding: 10px; border-radius: 6px; border: 1px solid #ccc; resize: none; margin-bottom: 15px; color: #333;" readonly>${res.qr_code_copia_cola}</textarea>
                                
                                <button id="btn-copiar" onclick="copiarPix()" class="btn-submit">
                                    📋 Copiar Código PIX
                                </button>
                                
                                <p style="margin-top: 25px; font-size: 13px; color: #666; line-height: 1.5;">
                                    O pagamento será aprovado em segundos.<br>
                                    Assim que pagar, pode fechar esta página e acessar <strong>Minhas Compras</strong>.
                                </p>

                                <a href="galeria.php?e=<?= $token ?>" class="btn-voltar" style="margin-top: 25px;">← Voltar para a Galeria</a>
                            </div>
                        `;
                    }
                } else {
                    msg.className = 'alerta erro'; 
                    msg.innerText = res.erro || 'Falha ao processar o pagamento.';
                    msg.style.display = 'block'; 
                    btn.innerText = textoOriginal; 
                    btn.disabled = false;
                }
            } catch (error) {
                msg.className = 'alerta erro'; 
                msg.innerText = error.message || "Falha de comunicação com o servidor.";
                msg.style.display = 'block'; 
                btn.innerText = textoOriginal; 
                btn.disabled = false;
            }
        });
    </script>
</body>
</html>