<?php
declare(strict_types=1);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Termos de Uso - Pic2Pic</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
        body { background-color: #f4f6f8; color: #333; line-height: 1.6; padding: 40px 20px; display: flex; justify-content: center; }
        .container { background: #fff; width: 100%; max-width: 800px; padding: 40px; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.05); }
        .header-termos { text-align: center; margin-bottom: 40px; padding-bottom: 20px; border-bottom: 1px solid #eee; }
        .header-termos h1 { font-size: 28px; color: #111; margin-bottom: 10px; }
        .header-termos p { color: #666; font-size: 14px; }
        .section-termos { margin-bottom: 30px; }
        .section-termos h2 { font-size: 18px; color: #1a73e8; margin-bottom: 15px; display: flex; align-items: center; gap: 8px; }
        .section-termos p { font-size: 14px; color: #444; margin-bottom: 10px; text-align: justify; }
        .section-termos ul { margin-left: 20px; margin-bottom: 10px; font-size: 14px; color: #444; }
        .section-termos li { margin-bottom: 8px; }
        .btn-voltar { display: inline-block; margin-top: 30px; background: #e9ecef; color: #333; text-decoration: none; padding: 12px 24px; border-radius: 8px; font-weight: bold; transition: 0.2s; text-align: center; width: 100%; }
        .btn-voltar:hover { background: #dadce0; }
        @media (max-width: 600px) { .container { padding: 25px; } }
    </style>
</head>
<body>

    <div class="container">
        <div class="header-termos">
            <h1>Termos e Condições de Uso</h1>
            <p>Última atualização: <?= date('d/m/Y') ?></p>
            <p>Bem-vindo(a) à <strong>Pic2Pic</strong>.</p>
        </div>

        <div class="section-termos">
            <h2>1. Natureza do Serviço (Plataforma Intermediadora)</h2>
            <p>A Pic2Pic atua exclusivamente como uma plataforma tecnológica (marketplace) que fornece infraestrutura de software para fotógrafos profissionais exibirem e comercializarem seus catálogos de fotos.</p>
            <p><strong>A Pic2Pic não é a vendedora das fotografias.</strong> A relação de compra e venda de qualquer imagem ou serviço ocorre estrita e diretamente entre o Cliente (Comprador) e o Fotógrafo cadastrado na plataforma.</p>
        </div>

        <div class="section-termos">
            <h2>2. Processamento Financeiro e Pagamentos</h2>
            <p>A plataforma Pic2Pic não processa, não retém e não possui acesso a valores financeiros decorrentes das vendas. Todas as transações financeiras são realizadas e processadas pelo gateway de pagamento <strong>Mercado Pago</strong>, cujas chaves e credenciais pertencem exclusivamente ao Fotógrafo vendedor.</p>
            <ul>
                <li>Os pagamentos realizados via PIX são transferidos direta e instantaneamente para a conta bancária do Fotógrafo.</li>
                <li>A Pic2Pic não cobra taxas por transação e não tem autoridade legal ou técnica para realizar estornos, devoluções ou cancelamentos de pagamentos.</li>
            </ul>
        </div>

        <div class="section-termos">
            <h2>3. Responsabilidade por Entrega e Suporte</h2>
            <p>Qualquer solicitação de suporte, dúvidas sobre a qualidade das imagens, links expirados, ou problemas relativos ao recebimento do produto digital adquirido <strong>deve ser tratada diretamente com o Fotógrafo responsável pelo catálogo</strong>.</p>
            <p>A Pic2Pic isenta-se expressamente de qualquer responsabilidade civil, administrativa ou de consumo por falhas na entrega, insatisfação com o produto, violação de direitos autorais por parte dos fotógrafos ou atrasos no envio dos arquivos.</p>
        </div>

        <div class="section-termos">
            <h2>4. Uso de Inteligência Artificial e Biometria Facial (LGPD)</h2>
            <p>Para facilitar a localização das suas fotos, a Pic2Pic oferece um recurso opcional de busca por reconhecimento facial com Inteligência Artificial. Ao utilizar a opção "Encontrar meu rosto com IA":</p>
            <ul>
                <li>Você consente expressamente com o mapeamento temporário dos dados biométricos do seu rosto (selfie).</li>
                <li>Garantimos que a imagem capturada pela câmera do seu dispositivo é convertida em um código matemático temporário estritamente para cruzar com as fotos do evento e <strong>não é armazenada em nossos bancos de dados</strong> após a conclusão da busca.</li>
            </ul>
        </div>

        <div class="section-termos">
            <h2>5. Direitos de Imagem</h2>
            <p>A Pic2Pic não se responsabiliza pela captação, uso ou divulgação de imagens feitas pelos fotógrafos nos eventos. O Fotógrafo declara, ao utilizar a plataforma, possuir as devidas autorizações de uso de imagem das pessoas fotografadas, isentando a plataforma de qualquer litígio.</p>
        </div>

        <div class="section-termos">
            <h2>6. Modificações e Aceitação</h2>
            <p>A conclusão do cadastro como Fotógrafo ou a finalização de um pedido de compra como Cliente constitui a <strong>aceitação incondicional</strong> destes Termos de Uso.</p>
            <p>A Pic2Pic reserva-se o direito de alterar estes termos a qualquer momento, visando o aprimoramento da plataforma ou a adequação a novas legislações, sendo aplicável a versão vigente no momento do acesso ou da compra.</p>
        </div>

        <button onclick="fecharOuVoltar()" class="btn-voltar">← Fechar e Voltar</button>
        
        <script>
            function fecharOuVoltar() {
                // Tenta voltar no histórico se houver
                if (window.history.length > 1 && document.referrer !== "") {
                    window.history.back();
                } else {
                    // Se abriu numa aba nova, tenta fechar a aba
                    window.close();
                    // Fallback: se o navegador bloquear o fechamento, manda para a home
                    setTimeout(() => { window.location.href = 'index.php'; }, 300);
                }
            }
</script>    </div>

</body>
</html>