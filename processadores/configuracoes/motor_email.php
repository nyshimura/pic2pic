<?php
declare(strict_types=1);

// Requer os ficheiros do PHPMailer
require_once __DIR__ . '/../../libs/PHPMailer/src/Exception.php';
require_once __DIR__ . '/../../libs/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/../../libs/PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function enviarEmailSMTP($pdo, $destinatarioEmail, $destinatarioNome, $assunto, $mensagem, $replyToEmail = null, $replyToNome = null) {
    // 1. Busca configurações na base de dados
    $stmt = $pdo->query("SELECT smtp_host, smtp_port, smtp_user, smtp_pass FROM configuracoes_sistema WHERE id = 1");
    $config = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$config || empty($config['smtp_host']) || empty($config['smtp_pass'])) {
        return false; // SMTP não foi configurado no painel
    }

    // 2. Desencripta a palavra-passe AES-256
    $chaveSecreta = defined('CHAVE_MESTRA') ? CHAVE_MESTRA : 'Pic2Pic_Seguranca_2026';
    $key = hash('sha256', $chaveSecreta, true);
    
    $dadosBase64 = base64_decode($config['smtp_pass']);
    $ivLength = openssl_cipher_iv_length('aes-256-cbc');
    $iv = substr($dadosBase64, 0, $ivLength);
    $encrypted = substr($dadosBase64, $ivLength);
    
    $senhaSmtp = openssl_decrypt($encrypted, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);

    // 3. Dispara o PHPMailer
    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host       = $config['smtp_host'];
        $mail->SMTPAuth   = true;
        $mail->Username   = $config['smtp_user'];
        $mail->Password   = $senhaSmtp;
        
        // Ajuste automático de segurança pela porta
        if ($config['smtp_port'] == 587) {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        } else {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; // Porta 465 (Padrão Hostinger)
        }
        $mail->Port = $config['smtp_port'];

        // Remetente (Obrigatoriamente o seu e-mail do sistema para não cair no SPAM)
        $mail->setFrom($config['smtp_user'], 'Pic2Pic (Sistema)');

        // Destinatário (Comprador)
        $mail->addAddress($destinatarioEmail, $destinatarioNome);

        // Responder Para (A Mágica do Fotógrafo!)
        if ($replyToEmail) {
            $nomeReply = $replyToNome ? $replyToNome : 'Fotógrafo';
            $mail->addReplyTo($replyToEmail, $nomeReply);
            // Máscara: O e-mail chega com o nome do Fotógrafo
            $mail->FromName = $nomeReply . ' (via Pic2Pic)';
        }

        // Conteúdo
        $mail->isHTML(false);
        $mail->Subject = $assunto;
        $mail->Body    = $mensagem;
        $mail->CharSet = 'UTF-8';

        $mail->send();
        return true;
    } catch (Exception $e) {
        // Em ambiente de desenvolvimento, pode fazer echo do erro com: echo $mail->ErrorInfo;
        return false;
    }
}