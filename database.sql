-- Banco de Dados Limpo e Sanitizado - Pic2Pic Vitrine
-- Estrutura otimizada para implantação inicial (Open Source)

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

-- --------------------------------------------------------
-- Estrutura para tabela `categorias_eventos`
-- --------------------------------------------------------
CREATE TABLE `categorias_eventos` (
  `id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `nome` varchar(50) NOT NULL,
  `criado_em` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Categorias padrão do sistema
INSERT INTO `categorias_eventos` (`nome`) VALUES
('Casamentos'),
('Aniversários'),
('Ensaios'),
('Shows e Baladas'),
('Formatura'),
('Outros');

-- --------------------------------------------------------
-- Estrutura para tabela `compradores`
-- --------------------------------------------------------
CREATE TABLE `compradores` (
  `id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `nome` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `cpf` varchar(20) DEFAULT NULL,
  `senha` varchar(255) DEFAULT NULL,
  `token_recuperacao` varchar(100) DEFAULT NULL,
  `token_expira_em` datetime DEFAULT NULL,
  `criado_em` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Estrutura para tabela `configuracoes_sistema`
-- --------------------------------------------------------
CREATE TABLE `configuracoes_sistema` (
  `id` int(11) NOT NULL DEFAULT 1 PRIMARY KEY,
  `email_robo_drive` varchar(150) NOT NULL DEFAULT 'configuracao-pendente@iam.gserviceaccount.com',
  `atualizado_em` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `smtp_host` varchar(255) DEFAULT NULL,
  `smtp_port` int(11) DEFAULT NULL,
  `smtp_user` varchar(255) DEFAULT NULL,
  `smtp_pass` varchar(255) DEFAULT NULL,
  `adsense_client_id` varchar(50) DEFAULT NULL,
  `adsense_ativo` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Linha de configuração global vazia
INSERT INTO `configuracoes_sistema` (`id`, `email_robo_drive`, `adsense_ativo`) VALUES
(1, 'SEU-ROBO-AQUI@app.iam.gserviceaccount.com', 0);

-- --------------------------------------------------------
-- Estrutura para tabela `fotografos` (Superadmins/Parceiros)
-- --------------------------------------------------------
CREATE TABLE `fotografos` (
  `id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `nome` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `senha` varchar(255) NOT NULL,
  `is_admin` tinyint(1) DEFAULT 0,
  `mp_public_key` varchar(255) DEFAULT NULL,
  `mp_access_token` varchar(255) DEFAULT NULL,
  `foto_perfil` varchar(255) DEFAULT NULL,
  `marca_dagua` varchar(255) DEFAULT NULL,
  `criado_em` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Usuário Administrador Mestre (Senha padrão: senha123)
-- Instrução: O usuário que instalar o sistema deve alterar este e-mail no banco para o e-mail pessoal dele.
INSERT INTO `fotografos` (`nome`, `email`, `senha`, `is_admin`) VALUES
('Administrador', 'admin@pic2pic.com', '$2y$10$8K1p/a0bLx2vC5yA.p0b3.1p1a2s3d4f5g6h7j8k9l0z1x2c3v4b5', 1);

-- --------------------------------------------------------
-- Estrutura para tabela `eventos`
-- --------------------------------------------------------
CREATE TABLE `eventos` (
  `id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `fotografo_id` int(11) NOT NULL,
  `nome` varchar(100) NOT NULL,
  `token_url` varchar(50) NOT NULL,
  `drive_folder_id` varchar(100) NOT NULL,
  `preco_foto` decimal(10,2) DEFAULT 0.00,
  `ativo` tinyint(1) DEFAULT 1,
  `expira_em` datetime NOT NULL,
  `criado_em` timestamp NULL DEFAULT current_timestamp(),
  `visitas` int(11) DEFAULT 0,
  `categoria` varchar(50) NOT NULL DEFAULT 'Outros',
  FOREIGN KEY (`fotografo_id`) REFERENCES `fotografos`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Estrutura para tabela `fotos_eventos`
-- --------------------------------------------------------
CREATE TABLE `fotos_eventos` (
  `id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `evento_id` int(11) NOT NULL,
  `drive_file_id` varchar(100) NOT NULL,
  `url_visualizacao` varchar(255) NOT NULL,
  `encoding_facial` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`encoding_facial`)),
  `criado_em` timestamp NULL DEFAULT current_timestamp(),
  FOREIGN KEY (`evento_id`) REFERENCES `eventos`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Estrutura para tabela `logs_eventos`
-- --------------------------------------------------------
CREATE TABLE `logs_eventos` (
  `id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `fotografo_id` int(11) NOT NULL,
  `evento_id` int(11) NOT NULL,
  `descricao` text NOT NULL,
  `criado_em` timestamp NULL DEFAULT current_timestamp(),
  FOREIGN KEY (`fotografo_id`) REFERENCES `fotografos`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`evento_id`) REFERENCES `eventos`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Estrutura para tabela `fotos_indexadas` e `pedidos` (Caso utilizes as lógicas extras no sistema)
-- --------------------------------------------------------
CREATE TABLE `fotos_indexadas` (
  `id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `evento_id` int(11) NOT NULL,
  FOREIGN KEY (`evento_id`) REFERENCES `eventos`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `pedidos` (
  `id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `comprador_id` int(11) NOT NULL,
  `evento_id` int(11) NOT NULL,
  `total` decimal(10,2) NOT NULL,
  `status` varchar(50) NOT NULL DEFAULT 'pendente',
  `criado_em` timestamp NULL DEFAULT current_timestamp(),
  FOREIGN KEY (`comprador_id`) REFERENCES `compradores`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`evento_id`) REFERENCES `eventos`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

COMMIT;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
