<form class="report-analysis module-card" data-section="<?= h($section) ?>">
<?php
$reportTitles = [
    'descripcion' => 'I. DESCRIPCIÓN ANALÍTICA',
    'velocidad' => 'A. DETERMINACIÓN DE VELOCIDAD DE LOS VEHICULOS PARTICIPANTES',
    'danos-lesiones' => 'A. ANÁLISIS COMPARATIVO DE DAÑOS Y LESIONES',
    'evidencias' => 'A. ANÁLISIS DE EVIDENCIAS.',
    'integral' => 'A. ANÁLISIS INTEGRAL',
    'causalidad' => 'I. CAUSALIDAD DEL ACCIDENTE',
    'infracciones' => 'INFRACCIONES ADMINISTRATIVAS',
];
?>
<h3><?= h($reportTitles[$section] ?? $title) ?></h3>
<?php if ($section === 'velocidad'): ?>
<p class="report-speed-introduction">Para el presente caso, la velocidad es obtenida de conformidad con el Texto Único Ordenado del Reglamento Nacional Código de Tránsito regulado con el D.S. Nº06-2009-MTC en su título IV capítulo II, sección IV en el artículo 162º que prescribe que cuando no exista riesgo y circunstancias señalados en los artículos anteriores, haciendo referencia a los artículos 160º y 161º solo en dicho supuesto se establecen límites cuantitativos de velocidad, por lo tanto para el presente análisis existieron situaciones que solo pueden ser estimadas de forma cualitativa en razón a los riesgos que presentaba las características y las condiciones señaladas, las cuales significan lo siguiente:</p>
<?php endif; ?>
<?php if ($reportAnalysisError): ?><p role="alert"><?= h($reportAnalysisError) ?></p><?php endif; ?>
<fieldset <?= $reportCanEdit ? '' : 'disabled' ?> style="border:0;padding:0;margin:0;min-width:0">
<div class="report-analysis-groups"></div>
<div class="report-analysis-footer">
<button class="report-analysis-save" type="submit" aria-label="Guardar <?= h(mb_strtolower($title)) ?>">
<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h12l4 4v12a2 2 0 0 1-2 2Z"/><path d="M7 3v6h10V3M7 21v-8h10v8"/></svg>
<span>Guardar análisis</span>
</button>
</div>
</fieldset>
<span class="report-analysis-status" role="status" aria-live="polite"></span>
</form>
