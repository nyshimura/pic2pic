# Pic2Pic Vitrine

Plataforma de vitrine fotográfica de alta performance com IA integrada.

## 🚀 Sobre o Projeto
O Pic2Pic é um sistema estilo *streaming* (Netflix) para catálogos fotográficos. Focado em velocidade, segurança e monetização, foi desenvolvido em PHP nativo para garantir máxima eficiência, alta performance de cache e baixo consumo de recursos no servidor.

## 🛠 Funcionalidades Principais
* **Vitrine Inteligente:** Organização automática de eventos em prateleiras horizontais por categorias.
* **Filtro Facial IA:** Busca automatizada para que clientes encontrem suas fotos rapidamente.
* **Segurança:** Proteção de conteúdo com aplicação de marca d'água física na imagem (PHP GD Library).
* **Performance Extrema:** Motor duplo de cache — servidor (arquivos físicos) e navegador (30 dias).
* **Monetização:** Integração nativa com Google AdSense (anúncios inseridos de forma orgânica no *scroll*).
* **Gestão (Superadmin):** Painel administrativo integrado para gestão de categorias, configurações SMTP e AdSense.
* **Conformidade Legal:** Sistema de consentimento de cookies nativo (LGPD).

## 💻 Tecnologias Utilizadas
* **Back-end:** PHP (Nativo)
* **Banco de Dados:** MySQL
* **Integração:** Google Drive API (via JWT / Service Account)
* **Front-end:** CSS Grid/Masonry e JS Vanilla (Totalmente Responsivo)

---

## ⚙️ Como Instalar e Configurar (Guia Rápido)

Para rodar este projeto no seu servidor local (XAMPP/WAMP) ou hospedagem (cPanel, Hostinger, etc.), siga os passos abaixo:

### 1. Banco de Dados
1. Crie um banco de dados MySQL vazio no seu servidor.
2. Importe o arquivo `database.sql` (disponível na raiz deste repositório). Ele criará a estrutura completa e as categorias padrão.

### 2. Conexão e Chave Mestra (Segurança)
1. Abra o arquivo `configuracoes/conexao.php`.
2. Insira as credenciais do seu banco de dados (Host, Usuário, Senha e Nome do Banco).
3. Localize a definição da **Chave Mestra** e altere-a para uma string segura (obrigatório **no mínimo 32 caracteres**).
   * Exemplo: `define('CHAVE_MESTRA', 'suachavemestradeveternominimo32caracteres');`
4. Localize a definição do **Endereço do seu Site** e altere-a para o endereço (exato **do seu projeto**).
   * Exemplo: `define('APP_URL', 'https://seuendereco.com.br/fotos');`

### 3. API do Google Drive (Hospedagem das Imagens)
O sistema não consome o disco da sua hospedagem; ele puxa as fotos diretamente do Drive.
1. Acesse o [Google Cloud Console](https://console.cloud.google.com/).
2. Crie um projeto e ative a **Google Drive API**.
3. Crie uma **Conta de Serviço (Service Account)** em "Credenciais".
4. Gere uma nova chave no formato **JSON** e faça o download.
5. Renomeie o arquivo exatamente para `google_drive.json` e coloque-o dentro da pasta `credenciais/` do seu projeto.
> **Importante:** Anote o e-mail gerado para essa Conta de Serviço. Os fotógrafos deverão compartilhar as pastas do Google Drive com este e-mail (como Leitor) para que o sistema consiga puxar as fotos.

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

### 4. Acesso Inicial (Superadmin)
O banco de dados já inclui um administrador padrão para você configurar o sistema.
* **URL de Login:** `seusite.com/index.php` (Clique em "Fazer Login")
* **E-mail:** `admin@pic2pic.com`
* **Senha:** `senha123`

🚨 **Atenção:** Assim que fizer o primeiro login, acesse o painel, altere o e-mail para o seu e-mail pessoal e cadastre uma nova senha forte! Após isso, utilize o painel para configurar as suas credenciais SMTP e a sua ID do Google AdSense.

---
*Desenvolvido por Rafael Nishimura Abreu*
