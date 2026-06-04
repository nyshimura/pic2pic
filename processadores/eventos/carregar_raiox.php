<?php
declare(strict_types=1);

error_reporting(0);
ini_set('display_errors', '0');

session_start();
require_once '../../configuracoes/conexao.php';

if (!isset($_SESSION['fotografo_id']) || !isset($_POST['id_evento'])) {
    die("<div class='alerta erro' style='display:block;'>Acesso negado.</div>");
}

$idEvento = intval($_POST['id_evento']);
$offset = intval($_POST['offset'] ?? 0);
$limit = 30; // Carrega 30 fotos por vez
$idFotografo = $_SESSION['fotografo_id'];

// Confirma se o evento pertence ao fotógrafo logado
$stmtVerifica = $pdo->prepare("SELECT id FROM eventos WHERE id = ? AND fotografo_id = ?");
$stmtVerifica->execute([$idEvento, $idFotografo]);
if (!$stmtVerifica->fetch()) {
    die("<div class='alerta erro' style='display:block;'>Catálogo não encontrado.</div>");
}

// Busca as fotos usando paginação segura
$stmtFotos = $pdo->prepare("SELECT id, encoding_facial FROM fotos_eventos WHERE evento_id = :evento ORDER BY id DESC LIMIT :limite OFFSET :salto");
$stmtFotos->bindValue(':evento', $idEvento, PDO::PARAM_INT);
$stmtFotos->bindValue(':limite', $limit, PDO::PARAM_INT);
$stmtFotos->bindValue(':salto', $offset, PDO::PARAM_INT);
$stmtFotos->execute();
$fotos = $stmtFotos->fetchAll(PDO::FETCH_ASSOC);

if (empty($fotos)) {
    // Se for o primeiro carregamento e não houver nada, mostra o aviso.
    if ($offset === 0) {
        echo "<div class='alerta' style='display:block; background:#fff3cd; color:#856404; text-align:center; break-inside: avoid;'>Nenhuma foto lida pela IA ainda. Clique em Sincronizar.</div>";
    }
    // Se for scroll e bater no fim, não faz nada (o JS lida com isso)
    exit;
}

foreach ($fotos as $foto) {
    $encodingRaw = $foto['encoding_facial'];

    echo "<div class='card-raiox'>";
    
    // Utiliza o proxy seguro para não dar erro com links expirados do Drive
    echo "<img src='processadores/imagens/proxy_imagem.php?f=" . $foto['id'] . "' loading='lazy' alt='Foto Raio-X'>";
    
    // A CORREÇÃO ANTI-CRASH: Primeiro checa se é NULL
    if ($encodingRaw === null) {
        // Estado 1: Foto nova, ainda não sincronizada com a IA
        echo "<div class='status-tarja' style='background: rgba(255, 193, 7, 0.95); color: #000;'>⏳ NÃO INDEXADA</div>";
    } else {
        // Estado Seguro: Se não for NULL, podemos descodificar sem quebrar o PHP 8
        $encodings = json_decode($encodingRaw, true);
        $temRosto = is_array($encodings) && count($encodings) > 0;
        
        if ($temRosto) {
            // Estado 2: IA encontrou o rosto e registrou as coordenadas
            echo "<div class='status-tarja ok'>ROSTO MAPEADO</div>";
        } else {
            // Estado 3: IA processou a imagem, mas não encontrou rostos humanos
            echo "<div class='status-tarja erro'>SEM ROSTO</div>";
        }
    }
    
    echo "</div>";
}
?>