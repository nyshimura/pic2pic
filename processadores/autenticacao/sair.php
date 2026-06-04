<?php
declare(strict_types=1);

// 1. Inicializa a sessão para poder manipulá-la
session_start();

// 2. Limpa todas as variáveis armazenadas na sessão atual
$_SESSION = [];

// 3. Destrói o cookie de sessão no navegador do usuário (Segurança extra)
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// 4. Destrói a sessão no servidor Hostinger
session_destroy();

// 5. Redireciona o usuário de volta para a tela de login na raiz
header("Location: ../../index.php");
exit;