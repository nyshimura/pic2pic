<?php
declare(strict_types=1);

error_reporting(0);
ini_set('display_errors', '0');

session_start();
header('Content-Type: application/json');

require_once '../../configuracoes/conexao.php';

// Bloqueia acesso sem login
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_SESSION['fotografo_id'])) {
    http_response_code(401);
    echo json_encode(['sucesso' => false, 'erro' => 'Acesso negado.']);
    exit;
}

$idFotografo = $_SESSION['fotografo_id'];

try {
    // Verifica permissões de administrador
    $stmtAdmin = $pdo->prepare("SELECT is_admin FROM fotografos WHERE id = ?");
    $stmtAdmin->execute([$idFotografo]);
    $isAdmin = (bool) $stmtAdmin->fetchColumn();

    if (!$isAdmin) {
        echo json_encode(['sucesso' => false, 'erro' => 'Acesso restrito. Sem permissões de administrador.']);
        exit;
    }

    // Coleta os dados enviados
    $emailRobo = trim($_POST['email_robo'] ?? '');
    $smtpHost = trim($_POST['smtp_host'] ?? '');
    $smtpPort = intval($_POST['smtp_port'] ?? 0);
    $smtpUser = trim($_POST['smtp_user'] ?? '');
    $smtpPass = $_POST['smtp_pass'] ?? '';

    if (empty($emailRobo)) {
        echo json_encode(['sucesso' => false, 'erro' => 'O E-mail do Robô (Drive) é obrigatório.']);
        exit;
    }

    if (!empty($smtpPass)) {
        // ==========================================
        // CRIPTOGRAFIA DE DUPLA VIA (AES-256)
        // ==========================================
        
        // Verifique se a sua constante no conexao.php se chama mesmo CHAVE_MESTRA. 
        // Se for outro nome (ex: SECRET_KEY), altere na linha abaixo.
        $chaveSecreta = defined('CHAVE_MESTRA') ? CHAVE_MESTRA : 'Pic2Pic_Seguranca_2026';
        
        // Formata a chave para 256 bits (exigência do AES)
        $key = hash('sha256', $chaveSecreta, true);
        
        // Gera o Vetor de Inicialização (IV) de 16 bytes
        $iv = random_bytes(openssl_cipher_iv_length('aes-256-cbc'));
        
        // Encripta a palavra-passe
        $encrypted = openssl_encrypt($smtpPass, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);
        
        // Junta o IV com a palavra-passe encriptada e converte para texto base64 para guardar na base de dados
        $senhaSalvar = base64_encode($iv . $encrypted);

        $stmt = $pdo->prepare("UPDATE configuracoes_sistema SET email_robo_drive = ?, smtp_host = ?, smtp_port = ?, smtp_user = ?, smtp_pass = ? WHERE id = 1");
        $stmt->execute([$emailRobo, $smtpHost, $smtpPort, $smtpUser, $senhaSalvar]);
        
    } else {
        // Se o campo veio vazio, salva apenas os outros dados mantendo a palavra-passe atual segura
        $stmt = $pdo->prepare("UPDATE configuracoes_sistema SET email_robo_drive = ?, smtp_host = ?, smtp_port = ?, smtp_user = ? WHERE id = 1");
        $stmt->execute([$emailRobo, $smtpHost, $smtpPort, $smtpUser]);
    }

    echo json_encode(['sucesso' => true, 'msg' => 'Configurações de servidor guardadas com sucesso!']);

} catch (Throwable $e) {
    echo json_encode(['sucesso' => false, 'erro' => 'Falha no banco de dados: ' . $e->getMessage()]);
}