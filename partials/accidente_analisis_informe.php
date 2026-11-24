<?php
$reportAnalysis = [];
$reportAnalysisError = '';
try {
    $stmt = $pdo->prepare('SELECT seccion, contenido, version FROM accidente_analisis_informe WHERE accidente_id = ?');
    $stmt->execute([$accidente_id]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $reportAnalysis[$row['seccion']] = ['data' => json_decode($row['contenido'], true, 32, JSON_THROW_ON_ERROR), 'version' => (int) $row['version']];
    }
} catch (Throwable $e) {
    $reportAnalysisError = 'No se pudo cargar el análisis del informe. Verifica la conexión y la migración de la base de datos.';
}
$reportVehicles = [];
foreach ($participantVehicleRows as $vehicle) {
    $reportVehicles[] = ['id' => (int) $vehicle['veh_id'], 'label' => trim(implode(' · ', array_filter([$vehicle['orden_participacion'], $vehicle['veh_placa'] ?: 'Sin placa', $vehicle['veh_marca'], $vehicle['veh_modelo']])) )];
}
$reportPeople = [];
foreach ($personas as $person) {
    $reportPeople[] = ['id' => (int) $person['involucrado_id'], 'label' => trim(implode(' ', [$person['apellido_paterno'] ?? '', $person['apellido_materno'] ?? '', $person['nombres'] ?? ''])) . ' · ' . ($person['rol_nombre'] ?: 'Sin rol registrado')];
}
$reportCanEdit = $reportAnalysisError === '' && \App\Support\Access::canEdit($accidente_id);
?>
<style>
#analisis-tabs{flex-wrap:wrap!important;gap:5px}#analisis-tabs .nav-link{white-space:normal;flex:1 1 150px;text-align:left}
.report-analysis{padding:14px}.report-analysis h3{font-size:17px;margin:0 0 8px}.report-analysis h4{font-size:14px;margin:0}.report-analysis-group{margin:20px 0}.report-analysis-head{display:flex;align-items:center;justify-content:space-between;gap:10px;margin-bottom:10px}.report-analysis-row{border:1px solid var(--border-color,#cbd5e1);border-radius:10px;padding:12px;margin:10px 0;background:var(--bs-body-bg,#fff)}.report-analysis-fields{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}.report-analysis label{display:block;font-weight:600;font-size:12px}.report-analysis textarea,.report-analysis select,.report-analysis input{display:block;width:100%;margin-top:5px;padding:9px;border:1px solid #adbccf;border-radius:6px;color:inherit;background:transparent;font:inherit}.report-analysis textarea{min-height:95px;resize:vertical}.report-analysis-status{display:block;margin:10px 0;white-space:pre-wrap}.report-analysis-empty{color:#64748b;font-size:13px}.report-analysis-row-head{display:flex;justify-content:space-between;align-items:center;margin-bottom:10px}.report-analysis-full{grid-column:1/-1}@media(max-width:650px){.report-analysis-fields{grid-template-columns:1fr}}
</style>
<?php foreach (['velocidad' => 'Determinación de velocidad', 'evidencias' => 'Análisis de evidencias', 'integral' => 'Análisis integral', 'causalidad' => 'Causalidad del accidente'] as $section => $title): ?>
<div class="tab-pane fade <?= $section === 'velocidad' ? 'show active' : '' ?>" id="analisis-<?= h($section) ?>" role="tabpanel">
<form class="report-analysis module-card" data-section="<?= h($section) ?>">
<h3><?= h($title) ?></h3>
<p>Componentes del informe policial · información del expediente.</p>
<?php if ($reportAnalysisError): ?><p role="alert"><?= h($reportAnalysisError) ?></p><?php endif; ?>
<fieldset <?= $reportCanEdit ? '' : 'disabled' ?> style="border:0;padding:0;margin:0;min-width:0">
<div class="report-analysis-groups"></div>
<button class="btn-shell" type="submit">Guardar <?= h(mb_strtolower($title)) ?></button>
</fieldset>
<span class="report-analysis-status" role="status" aria-live="polite"></span>
</form>
</div>
<?php endforeach; ?>
<script type="application/json" id="report-analysis-context"><?= json_encode(['caseId' => $accidente_id, 'saved' => $reportAnalysis, 'vehicles' => $reportVehicles, 'people' => $reportPeople, 'editable' => $reportCanEdit, 'csrf' => \App\Support\Access::csrf()], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?></script>
<script src="assets/js/accidente-analisis-informe.js?v=<?= filemtime(__DIR__ . '/../assets/js/accidente-analisis-informe.js') ?>" defer></script>
