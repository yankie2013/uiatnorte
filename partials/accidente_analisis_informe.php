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
// Compatibilidad: las infracciones previas se muestran en su nueva pestaña sin alterar el original.
if (!isset($reportAnalysis['infracciones']) && !empty($reportAnalysis['causalidad']['data']['infracciones'])) {
    $reportAnalysis['infracciones'] = ['version' => 0, 'data' => ['infracciones' => array_map(static fn(array $row): array => $row + ['vehiculo_id' => '', 'introduccion' => '', 'norma' => ''], $reportAnalysis['causalidad']['data']['infracciones']), 'informacion' => [], 'anexos' => []]];
}
// Reutiliza únicamente detalles de expedientes del espacio de trabajo autorizado.
$reportEvidenceDetails = [];
$reportEvidenceTemplates = [];
try {
    $scope = \App\Support\Access::workspacePredicate('a');
    $catalog = $pdo->prepare("SELECT ai.contenido FROM accidente_analisis_informe ai JOIN accidentes_activos a ON a.id = ai.accidente_id WHERE ai.seccion = 'evidencias' AND ($scope) ORDER BY ai.actualizado_en ASC, ai.accidente_id ASC");
    $catalog->execute();
    while ($saved = $catalog->fetch(PDO::FETCH_ASSOC)) {
        foreach ((json_decode($saved['contenido'], true)['evidencias'] ?? []) as $evidence) {
            $detail = trim((string) ($evidence['detalle'] ?? ''));
            if ($detail !== '') {
                $reportEvidenceDetails[mb_strtolower($detail)] = $detail;
                $reportEvidenceTemplates[mb_strtolower($detail)] = ['detalle' => $detail, 'caracteristicas' => (string) ($evidence['caracteristicas'] ?? '')];
            }
        }
    }
} catch (Throwable $e) {
    // El formulario sigue disponible si no se pudieron cargar las sugerencias.
}
foreach (($reportAnalysis['evidencias']['data']['evidencias'] ?? []) as $evidence) {
    $detail = trim((string) ($evidence['detalle'] ?? ''));
    if ($detail !== '') {
        $reportEvidenceDetails[mb_strtolower($detail)] = $detail;
        $reportEvidenceTemplates[mb_strtolower($detail)] ??= ['detalle' => $detail, 'caracteristicas' => (string) ($evidence['caracteristicas'] ?? '')];
    }
}
natcasesort($reportEvidenceDetails);
$reportEvidenceDetails = array_values($reportEvidenceDetails);
// Catálogo reutilizable a partir de artículos guardados en los expedientes autorizados.
$reportArticleTemplates = [];
$collectReportArticles = static function (array $data) use (&$reportArticleTemplates): void {
    foreach (($data['infracciones'] ?? []) as $row) {
        $code = trim((string) ($row['codigo'] ?? ''));
        if ($code === '') continue;
        preg_match('/^(?:art[íi]culo\s*)?([\d]+(?:\.[\d]+)*[A-Za-z]?)[.°º\s:–-]*(.*)$/iu', $code, $match);
        $number = $match[1] ?? $code;
        $reportArticleTemplates[mb_strtolower($number)] = ['numero' => $number, 'titulo' => ($match[2] ?? '') ?: $code, 'contenido' => (string) ($row['descripcion'] ?? '')];
    }
    foreach (($data['evaluaciones'] ?? []) as $evaluation) {
        foreach (($evaluation['articulos'] ?? []) as $article) {
            if (!empty($article['numero'])) $reportArticleTemplates[mb_strtolower(trim((string) $article['numero']))] = $article;
        }
    }
};
try {
    $scope = \App\Support\Access::workspacePredicate('a');
    $catalog = $pdo->prepare("SELECT ai.contenido FROM accidente_analisis_informe ai JOIN accidentes_activos a ON a.id = ai.accidente_id WHERE ai.seccion IN ('infracciones', 'causalidad') AND ($scope) ORDER BY ai.actualizado_en ASC, ai.accidente_id ASC");
    $catalog->execute();
    while ($row = $catalog->fetch(PDO::FETCH_ASSOC)) {
        $data = json_decode($row['contenido'], true);
        if (is_array($data)) $collectReportArticles($data);
    }
} catch (Throwable $e) {
    // La edición del expediente no depende de la disponibilidad del catálogo.
}
if (isset($reportAnalysis['infracciones']['data'])) $collectReportArticles($reportAnalysis['infracciones']['data']);
$reportVehicles = [];
foreach ($participantVehicleRows as $vehicle) {
    $reportVehicles[] = ['id' => (int) $vehicle['veh_id'], 'placa' => $vehicle['veh_placa'] ?: 'Sin placa', 'tipo' => (string) ($vehicle['veh_tipo'] ?? 'vehículo'), 'label' => trim(implode(' · ', array_filter([$vehicle['orden_participacion'], $vehicle['veh_placa'] ?: 'Sin placa', $vehicle['veh_marca'], $vehicle['veh_modelo']])) )];
}
$reportDamageReferences = [];
foreach ($participantVehicleRows as $vehicle) {
    $invId = (int) ($vehicle['inv_vehiculo_id'] ?? 0);
    $record = $analysisDamageByInvId[$invId] ?? [];
    if (!$record) {
        foreach (($docVehiculoTodosPorInvolucrado[$invId] ?? []) as $document) {
            if (trim((string) ($document['danos_peritaje'] ?? '')) !== '') {
                $record = $document;
                break;
            }
        }
    }
    $details = [];
    foreach (['danos_peritaje' => 'Daños constatados', 'sistema_electrico_peritaje' => 'Sistema eléctrico', 'sistema_frenos_peritaje' => 'Sistema de frenos', 'sistema_direccion_peritaje' => 'Sistema de dirección', 'sistema_transmision_peritaje' => 'Sistema de transmisión', 'sistema_suspension_peritaje' => 'Sistema de suspensión', 'planta_motriz_peritaje' => 'Planta motriz', 'otros_peritaje' => 'Otros'] as $field => $label) {
        $value = trim((string) ($record[$field] ?? ''));
        if ($value !== '') $details[] = $label . ":\n" . $value;
    }
    $reportDamageReferences[] = [
        'label' => trim(implode(' · ', array_filter([$vehicle['orden_participacion'] ?? '', $vehicle['veh_placa'] ?: 'Sin placa', $vehicle['veh_marca'] ?? '', $vehicle['veh_modelo'] ?? '']))),
        'detail' => $details ? implode("\n\n", $details) : 'Sin daños registrados para este vehículo.',
    ];
}
$reportPeople = [];
foreach ($personas as $person) {
    $reportPeople[] = ['id' => (int) $person['involucrado_id'], 'rol' => (string) ($person['rol_nombre'] ?: 'involucrado'), 'vehiculo_id' => (int) ($person['vehiculo_id'] ?? 0), 'nombre' => trim(implode(' ', [$person['apellido_paterno'] ?? '', $person['apellido_materno'] ?? '', $person['nombres'] ?? ''])), 'label' => trim(implode(' ', [$person['apellido_paterno'] ?? '', $person['apellido_materno'] ?? '', $person['nombres'] ?? ''])) . ' · ' . ($person['rol_nombre'] ?: 'Sin rol registrado')];
}
$reportCanEdit = $reportAnalysisError === '' && \App\Support\Access::canEdit($accidente_id);
?>
<style>
.report-risk-entry>[hidden]{display:none!important}
.report-draft-item-actions{display:flex;align-items:center;justify-content:flex-end;gap:8px;flex-wrap:wrap;flex-shrink:0}
.report-word-download{display:inline-flex;align-items:center;gap:6px;text-decoration:none;border-color:#b8cde5;color:#245b9b}
#analisis-tabs{flex-wrap:wrap!important;gap:5px}#analisis-tabs .nav-link{white-space:normal;flex:1 1 150px;text-align:left}
.report-analysis{padding:14px}.report-analysis h3{font-size:17px;margin:0 0 8px}.report-analysis h4{font-size:14px;margin:0}.report-analysis-group{margin:14px 0}.report-analysis-head{display:flex;align-items:center;justify-content:space-between;gap:10px;margin-bottom:10px}.report-analysis-row{border:1px solid var(--border-color,#cbd5e1);border-radius:10px;padding:9px 12px;margin:8px 0;background:var(--bs-body-bg,#fff)}.report-analysis-fields{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px}.report-analysis label{display:block;font-weight:600;font-size:12px}.report-analysis textarea,.report-analysis select,.report-analysis input{display:block;width:100%;margin-top:4px;padding:6px 9px;border:1px solid #adbccf;border-radius:6px;color:inherit;background:transparent;font:inherit}.report-analysis textarea{height:52px;min-height:52px;resize:vertical}.report-analysis-status{display:block;margin:10px 0;white-space:pre-wrap}.report-analysis-empty{color:#64748b;font-size:13px}.report-analysis-row-head{display:flex;justify-content:space-between;align-items:center;margin-bottom:5px}.report-analysis-full{grid-column:1/-1}@media(max-width:650px){.report-analysis-fields{grid-template-columns:1fr}}
.report-risk-item{display:flex;gap:8px;margin:6px 0;font-size:13px;white-space:pre-wrap;overflow-wrap:anywhere}.report-risk-item::before{content:"–"}.report-risk-dialog{width:min(620px,calc(100vw - 32px));max-height:80vh;overflow:auto;border:1px solid #cbd5e1;border-radius:14px;padding:20px;color:#26364d;background:#fff;box-shadow:0 20px 70px #0003}.report-risk-dialog::backdrop{background:#0f172a66}.report-risk-dialog h3{margin:0 0 16px;font-size:18px}.report-risk-entry{display:flex;gap:8px;position:sticky;top:0;background:#fff;padding:4px 0 10px}.report-risk-entry input{flex:1;min-width:0;border:1px solid #adbccf;border-radius:6px;padding:9px;font:inherit}.report-risk-draft{padding:0;list-style:none;margin:10px 0}.report-risk-draft li{display:flex;align-items:center;gap:10px;padding:8px 0;border-bottom:1px solid #e2e8f0}.report-risk-draft li::before{content:"–"}.report-risk-draft span{flex:1;white-space:pre-wrap;overflow-wrap:anywhere;min-width:0}.report-risk-actions{display:flex;justify-content:flex-end;gap:8px;margin-top:16px}
.report-paragraph-item,.report-estimation-item{margin:10px 0;font-size:13px;white-space:pre-wrap;overflow-wrap:anywhere}.report-risk-entry textarea,.report-risk-entry select{min-width:0;width:100%;border:1px solid #adbccf;border-radius:6px;padding:9px;font:inherit}.report-risk-entry textarea{flex:1;resize:vertical}.report-estimation-entry{flex-wrap:wrap}.report-estimation-entry textarea{flex-basis:75%}.report-draft-plain li::before{content:none}
.report-analysis[data-section="danos-lesiones"] .report-considerations h4{color:inherit;font-weight:700;font-size:13px}.report-considerations .report-risk-list{padding-left:20px;color:inherit;font-weight:700}
.report-risk-dialog.report-damage-dialog{width:min(960px,calc(100vw - 32px))}.report-damage-references{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px;margin-bottom:16px}.report-damage-reference{border:1px solid #cbd5e1;border-radius:10px;padding:12px;min-width:0;background:#f6f8fb}.report-damage-reference h4{font-size:14px;margin:0 0 10px}.report-damage-reference select{width:100%;padding:8px;border:1px solid #adbccf;border-radius:6px;background:#fff;font:inherit;margin-bottom:8px}.report-damage-reference p{margin:6px 0;font-size:13px}.report-damage-detail{white-space:pre-wrap;overflow-wrap:anywhere;max-height:230px;overflow:auto;line-height:1.5}@media(max-width:650px){.report-damage-references{grid-template-columns:1fr}}

.report-damage-dialog{background:linear-gradient(145deg,#edf5ff 0%,#fff 52%,#f3f8ff 100%);border-color:#bad3f5;box-shadow:0 24px 80px #153b7038}
.report-damage-dialog>h3{color:#164780;padding:0 0 14px;border-bottom:2px solid #d6e7fc}
.report-damage-dialog .report-damage-reference{background:linear-gradient(135deg,#e7f1ff,#fff);border:1px solid #c2d9f5;border-top:4px solid #3985d6;box-shadow:0 5px 16px #255a9810}
.report-damage-dialog .report-damage-reference:nth-child(2){background:linear-gradient(135deg,#edf6ff,#fff);border-top-color:#69a7e7}
.report-damage-dialog .report-damage-reference h4{color:#205c9c;font-weight:700}
.report-damage-dialog .report-damage-reference p{display:inline-block;background:#fff;border:1px solid #d8e7f8;border-radius:7px;padding:6px 9px;color:#244d79;font-weight:600}
.report-damage-dialog .report-damage-detail{color:#365573;margin-top:8px}
.report-damage-dialog .report-risk-entry{background:transparent;align-items:stretch}
.report-damage-dialog .report-risk-entry textarea{background:#fff;border-color:#b8d1ef;box-shadow:inset 0 2px 4px #205b9610}
.report-damage-dialog textarea:focus,.report-damage-dialog select:focus{outline:2px solid #69a9ed;outline-offset:2px;border-color:#3985d6}
.report-damage-dialog .btn-shell{border-color:#bed5f1;background:#fff;color:#24568d;transition:background .15s,box-shadow .15s}
.report-damage-dialog .btn-shell:hover{background:#eaf3ff;box-shadow:0 3px 10px #2762a21a}
.report-damage-dialog .report-risk-entry>.btn-shell,.report-damage-dialog .report-risk-actions>.btn-shell:last-child{background:linear-gradient(135deg,#337fd2,#205fa9);border-color:#2468b4;color:#fff;box-shadow:0 4px 12px #2666ad26}
.report-damage-dialog .report-risk-entry>.btn-shell:hover,.report-damage-dialog .report-risk-actions>.btn-shell:last-child:hover{background:#1e5b9f}
.report-damage-dialog .report-risk-actions{border-top:1px solid #d5e5f8;padding-top:14px}
.report-damage-dialog .report-risk-draft li{background:#ffffffb8;border:1px solid #d8e6f7;border-left:3px solid #6da4e2;border-radius:8px;padding:9px 12px;margin:8px 0}

.report-analysis[data-section="danos-lesiones"]{background:#fff;padding:24px;color:#26364d}
.report-analysis[data-section="danos-lesiones"]>h3{font-size:15px;font-weight:700;text-decoration:underline;margin-bottom:20px}
.report-analysis[data-section="danos-lesiones"] .report-analysis-groups{padding-left:20px}
.report-analysis[data-section="danos-lesiones"] .report-analysis-group{margin:16px 0}
.report-analysis[data-section="danos-lesiones"] .report-analysis-head h4{color:inherit;font-size:13px;line-height:1.6}
.report-analysis[data-section="danos-lesiones"] [data-group="parrafos"] .report-analysis-head h4{position:absolute;width:1px;height:1px;overflow:hidden;clip-path:inset(50%)}
.report-analysis[data-section="danos-lesiones"] [data-group="parrafos"] .report-analysis-head{justify-content:flex-end;margin-bottom:4px}
.report-analysis[data-section="danos-lesiones"] .report-paragraph-item{padding-left:20px;text-indent:-20px;line-height:1.6;margin:14px 0;text-align:justify}
.report-analysis[data-section="danos-lesiones"] [data-group="determinaciones"] .report-risk-list{padding-left:20px}
.report-analysis[data-section="danos-lesiones"] .report-risk-item{line-height:1.6;margin:4px 0}
@media(max-width:650px){.report-analysis[data-section="danos-lesiones"]{padding:14px}.report-analysis[data-section="danos-lesiones"] .report-analysis-groups{padding-left:0}.report-analysis[data-section="danos-lesiones"] .report-analysis-head{flex-wrap:wrap}}

.report-analysis[data-section="danos-lesiones"]{padding:16px 20px}
.report-analysis[data-section="danos-lesiones"]>h3{margin-bottom:10px}
.report-analysis[data-section="danos-lesiones"] .report-analysis-group{margin:6px 0}
.report-analysis[data-section="danos-lesiones"] .report-analysis-head{margin-bottom:3px;gap:6px}
.report-analysis[data-section="danos-lesiones"] .report-analysis-head h4{line-height:1.35;margin:0}
.report-analysis[data-section="danos-lesiones"] .report-risk-item{line-height:1.35;margin:2px 0}
.report-analysis[data-section="danos-lesiones"] .report-paragraph-item{line-height:1.4;margin:5px 0}
.report-analysis[data-section="danos-lesiones"] .report-group-empty .report-analysis-head{justify-content:flex-end;margin-bottom:0}
.report-analysis[data-section="danos-lesiones"] .report-analysis-head .btn-shell{padding:5px 9px;font-size:12px;min-height:0;box-shadow:none}
.report-analysis[data-section="danos-lesiones"] .report-analysis-status:empty{display:none}

.report-analysis[data-section="danos-lesiones"] [data-group="parrafos"]{position:relative;margin-top:1.4em;padding-right:125px;min-height:30px}
.report-analysis[data-section="danos-lesiones"] [data-group="parrafos"] .report-analysis-head{position:absolute;right:0;top:0;margin:0}
.report-analysis[data-section="danos-lesiones"] [data-group="parrafos"] .report-paragraph-item:first-child{margin-top:0}
@media(max-width:650px){.report-analysis[data-section="danos-lesiones"] [data-group="parrafos"]{padding-right:0}.report-analysis[data-section="danos-lesiones"] [data-group="parrafos"] .report-analysis-head{position:static;margin-bottom:2px}}

.report-analysis-footer{display:flex;justify-content:flex-end;align-items:center;gap:10px;flex-wrap:wrap;border-top:1px solid #dce7f4;padding-top:14px;margin-top:18px}
.report-analysis .report-analysis-save{display:inline-flex;align-items:center;justify-content:center;gap:9px;padding:11px 18px;border:1px solid #2466b3;border-radius:12px;background:linear-gradient(135deg,#3888dd,#205eaa);color:#fff;font:inherit;font-size:13px;font-weight:700;line-height:1.4;cursor:pointer;box-shadow:0 4px 12px #2466b32b,inset 0 1px 0 #ffffff26;transition:background .18s,box-shadow .18s,transform .18s}
.report-analysis .report-analysis-save:hover{background:linear-gradient(135deg,#2879ce,#194f93);box-shadow:0 6px 16px #2466b33b;transform:translateY(-1px)}
.report-analysis .report-analysis-save:active{transform:translateY(0)}
.report-analysis .report-analysis-save:focus-visible{outline:3px solid #92c5ff;outline-offset:3px}
.report-analysis fieldset:disabled .report-analysis-save{opacity:.55;cursor:default;transform:none;box-shadow:none}
.report-analysis .report-analysis-status{text-align:right;font-size:12px;margin:8px 0 0}
.report-analysis .report-analysis-status:empty{display:none}
@media(prefers-reduced-motion:reduce){.report-analysis .report-analysis-save{transition:none;transform:none}}

.report-analysis[data-section="danos-lesiones"] [data-group="determinaciones"] .report-analysis-head{align-items:flex-start}
.report-analysis[data-section="danos-lesiones"] [data-group="determinaciones"] h4{flex:1;min-width:0}
.report-analysis[data-section="danos-lesiones"] [data-group="determinaciones"] ul.report-risk-list{list-style:none;margin:4px 0 0;padding-left:20px}

.report-analysis[data-section="evidencias"]{background:#fff;padding:16px 20px;color:#26364d}
.report-analysis[data-section="evidencias"]>h3{font-size:15px;font-weight:700;text-decoration:underline;margin-bottom:10px}
.report-analysis[data-section="evidencias"] .report-analysis-group{margin:6px 0}
.report-evidence{position:relative;padding-left:24px;margin:12px 0 18px;font-size:13px;line-height:1.45}
.report-analysis .report-evidence h4{font-size:13px;font-weight:600;text-decoration:underline;margin:0 0 4px;text-indent:-20px}
.report-evidence p{margin:4px 0 8px;white-space:pre-wrap;overflow-wrap:anywhere;text-align:justify}
.report-evidence-characteristics{list-style:none;padding:0;margin:8px 0 10px}.report-evidence-characteristics li::before{content:"– ";}.report-evidence-characteristics li{white-space:pre-wrap;overflow-wrap:anywhere}
.report-evidence-edit{font-size:11px;padding:4px 8px}
.report-evidence-editor .report-analysis-fields{gap:10px 14px;margin-bottom:12px}
.report-evidence-editor label{display:block;font-size:12px;font-weight:600;color:#24568d}
.report-evidence-editor textarea,.report-evidence-editor select{display:block;box-sizing:border-box;width:100%;margin-top:5px;padding:8px;border:1px solid #bad2ef;border-radius:7px;background:#fff;color:#26364d;font:inherit}
.report-evidence-editor textarea{min-height:62px;resize:vertical;font-weight:400}
.report-evidence-dialog .report-risk-draft li{flex-wrap:wrap}.report-evidence-dialog .report-risk-draft span{flex-basis:55%}

.report-evidence-editor input[type="text"]{box-sizing:border-box;width:100%;margin-top:5px;padding:9px;border:1px solid #bad2ef;border-radius:7px;background:#fff;color:#26364d;font:inherit;font-weight:400}
.report-characteristic-entry{display:flex;align-items:center;gap:7px}.report-characteristic-entry input{flex:1;min-width:0}.report-characteristic-entry button{margin-top:5px;padding:7px 10px}
.report-characteristic-list{list-style:none;padding:0;margin:6px 0;font-weight:400}.report-characteristic-list li{display:flex;align-items:center;gap:7px;padding:4px 0}.report-characteristic-list li::before{content:"–"}.report-characteristic-list span{flex:1;overflow-wrap:anywhere}.report-characteristic-list button{padding:3px 7px;font-size:11px}

.report-analysis[data-section="integral"]{background:#fff;padding:16px 20px;color:#26364d}
.report-analysis[data-section="integral"]>h3{font-size:15px;font-weight:700;text-decoration:underline;margin-bottom:10px}
.report-analysis[data-section="integral"] .report-analysis-group{margin:6px 0}
.report-analysis[data-section="integral"] .report-analysis-head{justify-content:flex-end;margin-bottom:6px}
.report-analysis[data-section="integral"] .report-analysis-head h4{display:none}
.report-analysis[data-section="integral"] .report-analysis-head .btn-shell{padding:5px 9px;font-size:12px;box-shadow:none}
.report-analysis[data-section="integral"] .report-paragraph-item{padding-left:24px;text-indent:-20px;line-height:1.45;margin:10px 0;text-align:justify;white-space:pre-wrap;overflow-wrap:anywhere}
.report-integral-dialog .report-risk-entry textarea{min-height:140px;line-height:1.5}
.report-integral-dialog .report-risk-entry>.btn-shell{align-self:flex-end}
.report-integral-dialog .report-risk-draft span{line-height:1.5}
@media(max-width:650px){.report-analysis[data-section="integral"]{padding:14px}.report-integral-dialog .report-risk-entry{flex-wrap:wrap}.report-integral-dialog .report-risk-entry textarea{flex-basis:100%}}

.report-risk-dialog.report-integral-dialog{width:min(1200px,calc(100vw - 32px));max-height:92vh}
.report-integral-references{margin-bottom:16px;border:1px solid #c4daf5;border-radius:12px;overflow:hidden;background:#fff}
.report-reference-tabs{display:flex;flex-wrap:wrap;gap:5px;padding:9px;background:linear-gradient(135deg,#e5f0ff,#f5f9ff);border-bottom:1px solid #d5e5f8}
.report-reference-tabs button{border:1px solid #c8dbf3;border-radius:8px;padding:7px 10px;background:#fff;color:#2b598b;font:inherit;font-size:12px;font-weight:600;cursor:pointer}
.report-reference-tabs button[aria-selected="true"]{background:#246bb8;border-color:#246bb8;color:#fff;box-shadow:0 3px 8px #246bb826}
.report-reference-tabs button:focus-visible{outline:2px solid #5195dc;outline-offset:2px}
.report-reference-panel{max-height:40vh;overflow:auto;padding:14px;color:#26364d;font-size:13px;line-height:1.45;scrollbar-gutter:stable}
.report-reference-panel .report-analysis-head:empty{display:none}.report-reference-panel .report-analysis-group{margin:6px 0 12px}
.report-reference-panel h3,.report-reference-panel h4{color:#24568d;font-size:14px;margin:6px 0}
.report-reference-panel .report-reference-value{white-space:pre-wrap;font-weight:400;margin:4px 0 8px}
.report-reference-panel .report-analysis-fields{gap:8px}.report-reference-panel label{font-size:12px;font-weight:600}
.report-reference-panel .report-analysis-group{padding-right:0!important}.report-reference-panel .report-analysis-head{position:static!important;min-height:0}
.report-reference-panel .participant-overview-unit,.report-reference-panel .module-card{margin-bottom:12px}
.report-reference-panel .field-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px}
.report-reference-panel .field-card{grid-column:auto;min-width:0;overflow-wrap:anywhere}
.report-reference-panel .field-label{color:#436990;font-size:11px}.report-reference-panel .field-value{font-size:13px}
.report-integral-dialog .report-risk-entry{position:static;flex-wrap:wrap}
.report-integral-writing-label{flex-basis:100%;font-size:13px;color:#205c9c;font-weight:700;margin-bottom:3px}
.report-integral-dialog .report-risk-entry textarea{min-height:100px}
@media(max-width:650px){.report-reference-panel{max-height:35vh}.report-reference-panel .field-grid{grid-template-columns:1fr}}

.report-risk-dialog.report-integral-dialog{height:min(900px,92vh);box-sizing:border-box}
.report-integral-dialog .report-reference-panel{height:34vh;min-height:160px;max-height:none;box-sizing:border-box;overflow:auto}
.report-reference-people{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px;align-items:start}
.report-reference-person{min-width:0;padding:11px;border:1px solid #c9def5;border-top:3px solid #4b91d9;border-radius:9px;background:linear-gradient(135deg,#f0f6ff,#fff);font-size:12px;line-height:1.4;overflow-wrap:anywhere}
.report-reference-person strong{display:block;color:#204f82;font-size:13px;margin:3px 0}
.report-reference-person p{margin:3px 0;font-size:12px;color:#52677e}
.report-reference-person span{font-size:11px}
.report-reference-person .report-reference-person-vehicle{border-top:1px solid #dce8f6;padding-top:6px;margin-top:7px;color:#2d6299;font-weight:600}
@media(max-width:900px){.report-reference-people{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(max-width:550px){.report-reference-people{grid-template-columns:1fr}.report-integral-dialog .report-reference-panel{height:30vh;min-height:140px}}

.report-analysis[data-section="causalidad"]{background:#fff;padding:16px 20px;color:#26364d}
.report-analysis[data-section="causalidad"]>h3{font-size:15px;font-weight:700;text-decoration:underline;margin-bottom:10px}
.report-analysis[data-section="causalidad"] .report-analysis-group{margin:8px 0 16px}
.report-analysis[data-section="causalidad"] .report-analysis-head h4{font-size:13px;color:inherit;font-weight:700;text-decoration:underline}
.report-analysis[data-section="causalidad"] .report-analysis-head{margin-bottom:6px}
.report-analysis[data-section="causalidad"] .report-analysis-head.report-causality-empty{justify-content:flex-end}
.report-analysis[data-section="causalidad"] .report-analysis-head .btn-shell{font-size:12px;padding:5px 9px;box-shadow:none}
.report-causality-item{padding-left:24px;margin:10px 0 14px;font-size:13px;line-height:1.45}
.report-analysis .report-causality-item h4{font-size:13px;font-weight:600;text-decoration:underline;color:inherit;text-indent:-20px;margin:0 0 4px}
.report-causality-item p{white-space:pre-wrap;text-align:justify;margin:4px 0 7px;overflow-wrap:anywhere}
.report-causality-item .report-causality-person{font-size:12px;color:#4f6883}
.report-causality-dialog .report-evidence-editor{margin-top:12px}
.report-causality-dialog .report-risk-draft li{flex-wrap:wrap}
.report-causality-dialog .report-risk-draft span{flex-basis:55%}
@media(max-width:650px){.report-analysis[data-section="causalidad"]{padding:14px}}

.report-analysis[data-section="velocidad"]{background:#fff;padding:16px 20px;color:#26364d}
.report-analysis[data-section="velocidad"]>h3{font-size:15px;font-weight:700;text-decoration:underline;margin-bottom:5px}
.report-speed-introduction{font-size:13px;line-height:1.45;text-align:justify;margin:0 0 14px;padding-left:18px}
.report-analysis[data-section="velocidad"] .report-analysis-group{margin:8px 0}
.report-analysis[data-section="velocidad"] .report-analysis-head{justify-content:flex-end;margin-bottom:4px}
.report-analysis[data-section="velocidad"] [data-group="parrafos"] .report-analysis-head h4,.report-analysis[data-section="velocidad"] [data-group="estimaciones"] .report-analysis-head h4{display:none}
.report-analysis[data-section="velocidad"] .report-group-empty .report-analysis-head h4{display:none}
.report-analysis[data-section="velocidad"] .report-analysis-head .btn-shell{padding:5px 9px;font-size:12px;box-shadow:none}
.report-analysis[data-section="velocidad"] .report-paragraph-item{padding-left:28px;text-indent:-20px;margin:5px 0;line-height:1.45;text-align:justify}
.report-analysis[data-section="velocidad"] .report-estimation-item{padding-left:18px;margin:14px 0 8px;line-height:1.45;text-align:justify}
.report-analysis[data-section="velocidad"] .report-estimation-item strong{text-decoration:underline}
@media(max-width:650px){.report-analysis[data-section="velocidad"]{padding:14px}}

.report-risk-dialog.report-speed-dialog{width:min(960px,calc(100vw - 32px));border-radius:16px}
.report-speed-dialog .report-risk-entry{position:static;align-items:flex-end;gap:10px}
.report-speed-dialog .report-risk-entry textarea{min-height:105px;line-height:1.5;box-sizing:border-box}
.report-speed-dialog .report-risk-entry>.btn-shell{padding:10px 16px;border-radius:10px}
.report-speed-dialog .report-risk-draft{margin:12px 0;max-height:42vh;overflow:auto;padding-right:4px}
.report-speed-dialog .report-risk-draft li{align-items:flex-start;gap:12px;margin:8px 0;padding:12px}
.report-speed-dialog .report-risk-draft span{font-size:13px;line-height:1.5}
.report-speed-dialog .report-risk-draft button{flex-shrink:0;font-size:12px;padding:6px 10px;border-radius:8px}
.report-speed-dialog .report-risk-actions>.btn-shell{padding:9px 15px;border-radius:10px}
@media(max-width:650px){.report-speed-dialog .report-risk-entry{flex-wrap:wrap}.report-speed-dialog .report-risk-entry textarea{flex-basis:100%}.report-speed-dialog .report-risk-draft li{flex-wrap:wrap}.report-speed-dialog .report-risk-draft span{flex-basis:100%}}

.report-analysis[data-section="velocidad"] .report-speed-title{font-size:15px;font-weight:700;text-decoration:underline;margin:16px 0 5px}
.report-analysis[data-section="velocidad"] [data-group="riesgos"]{margin-top:0;margin-bottom:12px}

.report-analysis[data-section="infracciones"]{background:#fff;padding:16px 20px;color:#26364d}
.report-analysis[data-section="infracciones"]>h3{font-size:15px;font-weight:700;text-decoration:underline;margin-bottom:10px}
.report-analysis[data-section="infracciones"] .report-analysis-head{justify-content:space-between;margin-bottom:5px}
.report-analysis[data-section="infracciones"] .report-analysis-head h4{color:inherit;font-size:13px;font-weight:700;text-decoration:underline}
.report-analysis[data-section="infracciones"] .report-analysis-head.report-causality-empty,.report-analysis[data-section="infracciones"] .report-group-empty .report-analysis-head{justify-content:flex-end}
.report-analysis[data-section="infracciones"] [data-group="infracciones"] .report-analysis-head{justify-content:flex-end}
.report-analysis[data-section="infracciones"] .report-causality-item{padding-left:20px;margin:8px 0 14px}
.report-analysis[data-section="infracciones"] .report-causality-item h4{text-indent:0}
.report-analysis[data-section="infracciones"] .report-infraction-person{margin-top:14px;font-weight:700}
.report-infraction-norm{font-weight:600}
.report-analysis[data-section="infracciones"] .report-paragraph-item{line-height:1.45;text-align:justify}

.report-infraction-preview{margin:12px 0;padding:12px;border:1px solid #bdd5f3;border-radius:9px;background:#eaf3ff;color:#24568d;font-size:13px;line-height:1.5}
.report-infraction-preview p{margin:5px 0 0}
.report-infraction-articles{padding:12px;margin:12px 0;background:#ffffffcf;border:1px solid #cbdff6;border-radius:10px}
.report-infraction-articles h4{color:#205c9c;font-size:14px;margin:0 0 10px}
.report-infraction-content{display:block;margin:10px 0}
.report-infraction-article-list{margin-top:10px}
.report-infraction-article-row{display:flex;align-items:flex-start;gap:8px;border-top:1px solid #d8e7f8;padding:10px 0;font-size:12px}
.report-infraction-article-row>div{flex:1;min-width:0}.report-infraction-article-row p{white-space:pre-wrap;margin:4px 0;line-height:1.45;overflow-wrap:anywhere}
.report-infraction-dialog [hidden]{display:none!important}
.report-infraction-dialog .report-risk-draft li{flex-wrap:wrap}
.report-infraction-dialog .report-risk-draft span{flex-basis:60%}
@media(max-width:650px){.report-infraction-article-row{flex-wrap:wrap}.report-infraction-article-row>div{flex-basis:100%}}

.report-analysis[data-section="infracciones"] .report-causality-item h4.report-infraction-person{color:#967015;text-decoration-color:#b68b2b}

#analisis-tabs .nav-link[data-information="filled"]{background:linear-gradient(135deg,#f1fbf5,#dff3e7);border-color:#acd5bb;color:#286447;opacity:1;filter:none}
#analisis-tabs .nav-link[data-information="filled"]::before{background:#65ae83}
#analisis-tabs .nav-link[data-information="empty"]{background:linear-gradient(135deg,#fff7f7,#fbe7e8);border-color:#ebc0c3;color:#a34f57;opacity:1;filter:none}
#analisis-tabs .nav-link[data-information="empty"]::before{background:#d68b93}
#analisis-tabs .nav-link[data-information="filled"].active{background:linear-gradient(135deg,#e8f8ef,#cdebd9);border-color:#65ad83;color:#185637;box-shadow:0 5px 14px #35765320,inset 0 0 0 1px #ffffffb3}
#analisis-tabs .nav-link[data-information="empty"].active{background:linear-gradient(135deg,#fff0f1,#f6d6da);border-color:#ce8c95;color:#923c48;box-shadow:0 5px 14px #a34f5720,inset 0 0 0 1px #ffffffb3}
#analisis-tabs .nav-link[data-information]:focus-visible{outline:2px solid currentColor;outline-offset:2px}
</style>
<?php foreach (['descripcion' => 'Descripción analítica', 'velocidad' => 'Determinación de velocidad', 'evidencias' => 'Análisis de evidencias', 'integral' => 'Análisis integral', 'causalidad' => 'Causalidad del accidente', 'infracciones' => 'Infracciones administrativas'] as $section => $title): ?>
<div class="tab-pane fade <?= $section === 'velocidad' ? 'show active' : '' ?>" id="analisis-<?= h($section) ?>" role="tabpanel">
<?php require __DIR__ . '/accidente_analisis_formulario.php'; ?>
</div>
<?php endforeach; ?>
<script type="application/json" id="report-analysis-context"><?= json_encode(['caseId' => $accidente_id, 'inspectionSources' => array_values(array_map(static fn(array $itp): array => array_intersect_key($itp, array_flip(['id','fecha_itp','hora_itp','llegada_lugar','localizacion_unidades','forma_via','punto_referencia','descripcion_via1','configuracion_via1','material_via1','señalizacion_via1','senializacion_via1','ordenamiento_via1','iluminacion_via1','visibilidad_via1','intensidad_via1','fluidez_via1','medidas_via1','observaciones_via1','descripcion_via2','configuracion_via2','material_via2','señalizacion_via2','senializacion_via2','ordenamiento_via2','iluminacion_via2','visibilidad_via2','intensidad_via2','fluidez_via2','medidas_via2','observaciones_via2'])), $itps)), 'accidentPlace' => \App\Support\AccidentPlace::fromRecord($A, 'lugar', (int) $accidente_id), 'saved' => $reportAnalysis, 'vehicles' => $reportVehicles, 'damageReferences' => $reportDamageReferences, 'deceasedReferences' => array_map(static fn(array $person): array => ['label' => $person['nombre'], 'detail' => trim((string) ($person['lesiones'] ?? '')) ?: 'Sin lesiones registradas para esta persona.'], $analysisFallecidoRows), 'people' => $reportPeople, 'articleTemplates' => array_values($reportArticleTemplates), 'evidenceDetails' => $reportEvidenceDetails, 'evidenceTemplates' => array_values($reportEvidenceTemplates), 'editable' => $reportCanEdit, 'csrf' => \App\Support\Access::csrf()], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?></script>
<script src="assets/js/accidente-descripcion-editor.js?v=<?= filemtime(__DIR__ . '/../assets/js/accidente-descripcion-editor.js') ?>" defer></script>
<script src="assets/js/accidente-infracciones-editor.js?v=<?= filemtime(__DIR__ . '/../assets/js/accidente-infracciones-editor.js') ?>" defer></script>
<script src="assets/js/accidente-analisis-informe.js?v=<?= filemtime(__DIR__ . '/../assets/js/accidente-analisis-informe.js') ?>" defer></script>
