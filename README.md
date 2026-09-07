# 📸 Pic2Pic - Plataforma de Venda de Fotos para Eventos

Uma plataforma completa (SaaS) construída em PHP e MySQL para fotógrafos gerenciarem e venderem suas fotos de eventos diretamente para os clientes. 

O sistema conta com vitrine pública, painel exclusivo para fotógrafos (upload via Google Drive e pagamentos com Mercado Pago), e painel para clientes (para visualização e download após a compra).

## 🚀 Funcionalidades

- **Vitrine Estilo Netflix**: Eventos exibidos em prateleiras (Lançamentos, Em Alta, por Categorias).
- **Painel do Fotógrafo**:
  - Cadastro de eventos.
  - Sincronização de fotos diretamente pelo Google Drive.
  - Venda de fotos com pagamento automatizado via Mercado Pago (PIX/Cartão).
  - Acompanhamento de vendas.
- **Painel do Comprador**:
  - Galeria inteligente de fotos do evento.
  - Histórico de compras.
  - Links mágicos via e-mail para acesso e recuperação.
- **Segurança**: Prevenção avançada de sessão (AJAX-friendly), fotos com marca d'água e ocultação dos caminhos reais via proxies.

## 🛠️ Tecnologias Utilizadas

- **Backend**: PHP 7.4 / 8.0+
- **Banco de Dados**: MySQL (MariaDB)
- **Frontend**: HTML5, CSS3, JavaScript (Vanilla / Fetch API)
- **Integrações**: Mercado Pago API, Google Drive API, PHPMailer

## 📥 Como Implantar (Guia de Instalação)

### 1. Requisitos
- Servidor web com PHP (XAMPP, WAMP, cPanel, Hostinger, etc).
- Banco de Dados MySQL.
- Certificado SSL (HTTPS - Obrigatório para as integrações de pagamento e cookies seguros).

### 2. Configurando o Banco de Dados
1. Crie um banco de dados no seu servidor MySQL (ex: `pic2pic`).
2. Importe o arquivo estrutural `pic2pic.sql` que está na raiz do projeto.
3. Se estiver usando o terminal:
   ```bash
   mysql -u seu_usuario -p pic2pic < pic2pic.sql
   ```

### 3. Configuração do Sistema
1. Abra o arquivo `configuracoes/conexao.php`.
2. Altere as credenciais de acesso ao banco de dados:
   ```php
   define('DB_HOST', 'localhost');
   define('DB_NAME', 'pic2pic');
   define('DB_USER', 'root');
   define('DB_PASS', '');
   ```
3. No mesmo arquivo, ajuste o caminho do seu site na constante `APP_URL`. 
4. **Importante**: Altere a chave `APP_KEY` para uma chave segura e aleatória (mínimo de 32 caracteres) para garantir a criptografia interna.

#### Inteligência Artificial (Reconhecimento Facial)
- O sistema usa uma API externa em Python hospedada no **Hugging Face** para processar os rostos nas fotos.
- **Como publicar a sua própria API:**
  1. Crie uma conta gratuita no [Hugging Face](https://huggingface.co/).
  2. Crie um novo **Space** (Geralmente usando o SDK Docker ou Gradio/FastAPI).
  3. Faça o upload de **todos os arquivos** que estão dentro da pasta local `/api-fotos-eventos` deste projeto para dentro do seu novo Space.
  4. Aguarde o Space fazer o "Build" e ficar com o status *Running*.
- **Como conectar a API ao sistema:**
  1. Copie o link direto do seu Space em execução (ex: `https://seu-usuario-api-fotos-eventos.hf.space`).
  2. Abra o arquivo `configuracoes/conexao.php`.
  3. Procure pela constante `URL_API_IA` e insira o seu link ali (sem a barra `/` no final).
  4. Pronto! O sistema inteiro (scripts de filtro, sincronização e pings) já vai puxar essa URL automaticamente.

### 4. Permissões de Pastas
Certifique-se de que o servidor tenha permissão de escrita (`chmod 755` ou `775`) nas seguintes pastas:
- `/uploads` (se houver uploads locais de avatares)
- `/credenciais` (onde chaves sensíveis podem ser armazenadas)

### 5. Configurando as Integrações Essenciais

#### Pagamentos (Mercado Pago)
- O sistema funciona de forma multi-tenant. Cada fotógrafo pode inserir as suas próprias chaves (**Public Key** e **Access Token**) diretamente no **Painel do Fotógrafo** (aba "Financeiro"), para que os pagamentos caiam diretamente nas contas deles sem intermédio.
- **🚨 ATENÇÃO - Configuração do Webhook:** Para que o sistema saiba que o pagamento foi efetuado (e libere as fotos automaticamente), é obrigatório configurar a **URL de Notificação (Webhook)** no painel do Mercado Pago.
  - A URL que deve ser cadastrada lá é: `https://seuendereco.com.br/fotos/processadores/pagamentos/webhook_mp.php`
  - Evento a ser marcado lá: `Pagamentos (payment)`.

#### Armazenamento (Google Drive)
- As fotos pesadas dos eventos não ocupam o seu servidor, elas são carregadas diretamente do Google Drive.
- **Para o Fotógrafo (Usuário Final):** É super simples. Ele só precisa criar uma pasta no seu próprio Google Drive, colocar as fotos dentro e **compartilhar essa pasta** com o e-mail de serviço que aparecerá no painel do sistema.
- **Para o Administrador (Quem vai instalar o sistema):**
  1. Acesse o [Google Cloud Console](https://console.cloud.google.com/).
  2. Crie um projeto e ative a **Google Drive API**.
  3. Crie credenciais do tipo **Conta de Serviço (Service Account)**.
  4. O e-mail dessa conta de serviço é o que você deve exibir no painel para os fotógrafos. Você também precisará colocar o arquivo JSON de credenciais na pasta segura do seu servidor e referenciá-lo nos scripts (`processadores/imagens/` e `processadores/eventos/`).

#### Inteligência Artificial (Reconhecimento Facial)
- O sistema usa uma API externa em Python hospedada no **Hugging Face** para processar os rostos nas fotos.
- **Como publicar a sua própria API:**
  1. Crie uma conta gratuita no [Hugging Face](https://huggingface.co/).
  2. Crie um novo **Space** (Geralmente usando o SDK Docker ou Gradio/FastAPI).
  3. Faça o upload de **todos os arquivos** que estão dentro da pasta local `/api-fotos-eventos` deste projeto para dentro do seu novo Space.
  4. Aguarde o Space fazer o "Build" e ficar com o status *Running*.
- **Como conectar a API ao sistema:**
  1. Copie o link direto do seu Space em execução (ex: `https://seu-usuario-api-fotos-eventos.hf.space`).
  2. Abra o arquivo `configuracoes/conexao.php`.
  3. Procure pela constante `URL_API_IA` e insira o seu link ali (sem a barra `/` no final).
  4. Pronto! O sistema inteiro (scripts de filtro, sincronização e pings) já vai puxar essa URL automaticamente.

## 🛡️ Notas de Segurança e Performance
- Mantenha a pasta `/configuracoes` isolada e garanta que arquivos `.php` sensíveis não sejam impressos como texto (o `.htaccess` na raiz já ajuda na segurança).
- Para envio de e-mails, configure as credenciais SMTP no arquivo correspondente dentro de `/configuracoes/motor_email.php` ou similar para que os links de ativação cheguem perfeitamente aos clientes.

## 👨‍💻 Contribuindo
Sinta-se à vontade para fazer um **Fork** deste repositório, criar sua branch de feature e enviar um **Pull Request**. Toda melhoria será muito bem-vinda!

---
Feito com 💻 e ☕.
