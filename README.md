# Pic2Pic Vitrine

Plataforma de vitrine fotográfica de alta performance com IA integrada.

## 🚀 Sobre o Projeto
Sistema estilo Netflix para catálogos fotográficos. Focado em velocidade, segurança e monetização, utilizando PHP nativo para máxima eficiência e baixo consumo de recursos.

## 🛠 Funcionalidades
* **Vitrine Inteligente:** Organização automática de eventos em prateleiras horizontais por categorias.
* **Filtro Facial IA:** Busca automatizada para que clientes encontrem suas fotos rapidamente.
* **Segurança:** Proteção de conteúdo com aplicação de marca d'água física (PHP GD Library).
* **Performance:** Cache de servidor (físico) e cache de navegador (30 dias).
* **Monetização:** Integração nativa com Google AdSense (anúncios não intrusivos).
* **Gestão:** Painel administrativo (Superadmin) para categorias, configurações SMTP e AdSense.
* **Conformidade:** Sistema de cookies nativo em conformidade com a LGPD.

## 💻 Tecnologias
* **Back-end:** PHP (Nativo)
* **Banco de Dados:** MySQL
* **Integração:** Google Drive API (JWT Service Account)
* **Front-end:** CSS/JS Vanilla (Responsivo)

---

## ⚙️ Como Instalar e Configurar (Guia de Replicação)

Para rodar este projeto no seu servidor local (XAMPP/WAMP) ou hospedagem (Hostinger, cPanel, etc.), siga os passos abaixo rigorosamente:

### 1. Banco de Dados
1. Crie um banco de dados MySQL vazio no seu servidor.
2. Importe o arquivo `database.sql` (disponível na raiz deste repositório) para criar todas as tabelas necessárias.
3. Abra o arquivo `configuracoes/conexao.php` e insira as credenciais do seu banco de dados (Host, Usuário, Senha e Nome do Banco).

### 2. Configuração da Chave Mestra (Segurança)
No arquivo `configuracoes/conexao.php` (ou arquivo de variáveis de ambiente do projeto), você precisará definir a **Chave Mestra** de criptografia. 
* **Importante:** Por questões de segurança, esta chave deve conter **no mínimo 32 caracteres**.
* Exemplo: `define('CHAVE_MESTRA', 'suachavemestradeveternominimo32caracteres');`

### 3. API do Google Drive (Acesso às Fotos)
O sistema puxa as imagens diretamente do Google Drive para poupar espaço na sua hospedagem.
1. Acesse o [Google Cloud Console](https://console.cloud.google.com/).
2. Crie um novo projeto e ative a **Google Drive API**.
3. Vá em "Credenciais" > "Criar Credenciais" > **Conta de Serviço (Service Account)**.
4. Gere uma nova chave no formato **JSON** e faça o download.
5. Renomeie o arquivo baixado exatamente para `google_drive.json`.
6. Coloque este arquivo dentro da pasta `credenciais/` do seu projeto.
> *Nota: O e-mail dessa Conta de Serviço é o que os fotógrafos deverão usar para compartilhar as pastas de fotos no Drive.*

### 4. Configurando o Primeiro Superadmin
Para acessar o painel de configurações do sistema (onde você configura o SMTP e AdSense), você precisa de uma conta de Administrador.
1. Acesse a página inicial do sistema no seu navegador.
2. Clique em **Fazer Login** > **Criar conta grátis**.
3. Preencha os seus dados e crie uma conta normalmente.
4. Acesse o seu `phpMyAdmin` (ou gerenciador de banco de dados).
5. Vá na tabela `fotografos`, encontre a conta que você acabou de criar e altere a coluna `is_admin` de `0` para `1`.
6. Pronto! Agora basta fazer login no site novamente e você terá acesso total ao módulo Administrativo.

---
*Desenvolvido por Rafael Nishimura Abreu*
