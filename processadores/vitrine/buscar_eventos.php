<?php
declare(strict_types=1);

// Arrays que vão armazenar as prateleiras
$eventosLancamentos = [];
$eventosEmAlta = [];

try {
    // 1. Busca Lançamentos (Eventos criados nas últimas 48 horas)
    // Se a sua coluna de data for diferente de 'criado_em', altere abaixo.
    $stmtLancamentos = $pdo->query("
        SELECT e.id, e.nome, e.token_url, e.preco_foto, f.nome as fotografo_name 
        FROM eventos e 
        JOIN fotografos f ON e.fotografo_id = f.id 
        WHERE e.ativo = 1 AND e.expira_em > NOW() 
          AND e.criado_em >= DATE_SUB(NOW(), INTERVAL 48 HOUR)
        ORDER BY e.id DESC 
        LIMIT 10
    ");
    $eventosLancamentos = $stmtLancamentos->fetchAll(PDO::FETCH_ASSOC);

    // 2. Busca Em Alta (Mais visitados na plataforma)
    // Isolamos os IDs dos Lançamentos para que um evento novo não apareça repetido nas duas prateleiras
    $idsLancamentos = array_column($eventosLancamentos, 'id');
    $placeholders = '';
    $params = [];

    $sqlEmAlta = "
        SELECT e.id, e.nome, e.token_url, e.preco_foto, f.nome as fotografo_name 
        FROM eventos e 
        JOIN fotografos f ON e.fotografo_id = f.id 
        WHERE e.ativo = 1 AND e.expira_em > NOW()
    ";

    if (!empty($idsLancamentos)) {
        $placeholders = implode(',', array_fill(0, count($idsLancamentos), '?'));
        $sqlEmAlta .= " AND e.id NOT IN ($placeholders) ";
        $params = $idsLancamentos;
    }

    // Ordena pelo maior número de visitas. Desempate pelo ID (mais recente)
    $sqlEmAlta .= " ORDER BY e.visitas DESC, e.id DESC LIMIT 15";

    $stmtEmAlta = $pdo->prepare($sqlEmAlta);
    $stmtEmAlta->execute($params);
    $eventosEmAlta = $stmtEmAlta->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    // Falha silenciosa para não quebrar a home
}
?>