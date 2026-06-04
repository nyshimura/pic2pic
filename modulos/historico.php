<?php
// Módulo de Histórico (Accordion & Timeline)
try {
    $stmtLogs = $pdo->prepare("
        SELECT l.id, l.descricao, l.criado_em, IFNULL(e.nome, 'Sistema / Geral') as evento_nome 
        FROM logs_eventos l 
        LEFT JOIN eventos e ON l.evento_id = e.id 
        WHERE l.fotografo_id = ? 
        ORDER BY l.criado_em DESC LIMIT 500
    ");
    $stmtLogs->execute([$idFotografo]);
    $todosLogs = $stmtLogs->fetchAll(PDO::FETCH_ASSOC);
    
    $logsAgrupados = [];
    foreach($todosLogs as $log) {
        $eventoNome = $log['evento_nome'];
        if(!isset($logsAgrupados[$eventoNome])) {
            $logsAgrupados[$eventoNome] = [];
        }
        $logsAgrupados[$eventoNome][] = $log;
    }
} catch (Exception $e) { 
    $logsAgrupados = []; 
}
?>

<style>
    .accordion-container { margin-top: 15px; }
    .accordion-item { background: #fff; border-radius: 8px; margin-bottom: 10px; border: 1px solid #e0e0e0; overflow: hidden; }
    .accordion-header { padding: 15px 20px; cursor: pointer; display: flex; justify-content: space-between; align-items: center; background: #f8f9fa; font-weight: 600; font-size: 14px; transition: background 0.2s; user-select: none; }
    .accordion-header:hover { background: #e9ecef; }
    .accordion-body { display: none; padding: 20px; border-top: 1px solid #e0e0e0; background: #fff; max-height: 400px; overflow-y: auto; }
    .accordion-item.active .accordion-body { display: block; }
    .accordion-item.active .accordion-header { background: #e9ecef; color: #1a73e8; }
    .timeline { border-left: 2px solid #e9ecef; padding-left: 15px; margin-left: 10px; }
    .timeline-item { margin-bottom: 15px; position: relative; }
    .timeline-item:last-child { margin-bottom: 0; }
    .timeline-item::before { content: ''; position: absolute; left: -21px; top: 5px; width: 10px; height: 10px; border-radius: 50%; background: #1a73e8; border: 2px solid #fff; }
    .timeline-date { font-size: 11px; color: #888; font-weight: bold; margin-bottom: 3px; }
    .timeline-content { font-size: 13px; color: #444; }
</style>

<section class="card" style="max-width: 1100px; margin: 0 auto;">
    <div class="card-header">
        <h3>📜 Histórico de Atividades</h3>
        <p style="font-size: 13px; color: #666;">Acompanhe as últimas alterações dos seus catálogos (sincronizações, edições e alertas da IA).</p>
    </div>

    <?php if(empty($logsAgrupados)): ?>
        <div style="text-align: center; padding: 40px; color: #888;">Nenhum registro no sistema ainda.</div>
    <?php else: ?>
        <div class="accordion-container">
            <?php foreach($logsAgrupados as $nomeEvento => $logs): ?>
                <div class="accordion-item">
                    <div class="accordion-header" onclick="this.parentElement.classList.toggle('active')">
                        <span>
                            📁 <?= htmlspecialchars($nomeEvento) ?> 
                            <span style="font-size: 12px; color: #888; font-weight: normal; margin-left: 10px;">(<?= count($logs) ?> registros)</span>
                        </span>
                        <span style="font-size: 12px;">▼</span>
                    </div>
                    <div class="accordion-body">
                        <div class="timeline">
                            <?php foreach($logs as $log): ?>
                                <div class="timeline-item">
                                    <div class="timeline-date"><?= date('d/m/Y H:i', strtotime($log['criado_em'])) ?></div>
                                    <div class="timeline-content"><?= htmlspecialchars($log['descricao']) ?></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>