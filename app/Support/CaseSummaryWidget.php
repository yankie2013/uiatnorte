<?php

declare(strict_types=1);

if (!function_exists('case_summary_widget_h')) {
    function case_summary_widget_h($value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('case_summary_widget_text')) {
    function case_summary_widget_text($value): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', (string) $value));
    }
}

if (!function_exists('case_summary_widget_date')) {
    function case_summary_widget_date(?string $value): string
    {
        if (!$value || !strtotime($value)) {
            return '-';
        }

        return date('d/m/Y', strtotime($value));
    }
}

if (!function_exists('case_summary_widget_time')) {
    function case_summary_widget_time(?string $value): string
    {
        if (!$value || !strtotime($value)) {
            return '-';
        }

        return date('H:i', strtotime($value));
    }
}

if (!function_exists('case_summary_widget_join')) {
    function case_summary_widget_join(array $values): string
    {
        $values = array_values(array_filter(array_map('case_summary_widget_text', $values), static fn(string $value): bool => $value !== ''));
        if ($values === []) {
            return '-';
        }
        if (count($values) === 1) {
            return $values[0];
        }

        return implode(', ', array_slice($values, 0, -1)) . ' y ' . end($values);
    }
}

if (!function_exists('case_summary_widget_context')) {
    function case_summary_widget_context(PDO $pdo, int $accidenteId): ?array
    {
        if ($accidenteId <= 0) {
            return null;
        }

        $stmt = $pdo->prepare(
            "SELECT a.id,
                    a.sidpol,
                    a.registro_sidpol,
                    a.fecha_accidente,
                    a.lugar,
                    t.nombre AS distrito_ubicacion,
                    c.nombre AS jurisdiccion
               FROM accidentes a
          LEFT JOIN ubigeo_distrito t ON t.cod_dep = a.cod_dep AND t.cod_prov = a.cod_prov AND t.cod_dist = a.cod_dist
          LEFT JOIN comisarias c ON c.id = a.comisaria_id
              WHERE a.id = ?
              LIMIT 1"
        );
        $stmt->execute([$accidenteId]);
        $accidente = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$accidente) {
            return null;
        }

        $mods = [];
        try {
            $stmt = $pdo->prepare(
                "SELECT m.nombre
                   FROM accidente_modalidad am
                   JOIN modalidad_accidente m ON m.id = am.modalidad_id
                  WHERE am.accidente_id = ?
               ORDER BY am.modalidad_id"
            );
            $stmt->execute([$accidenteId]);
            $mods = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'nombre');
        } catch (Throwable) {
            $mods = [];
        }

        $cons = [];
        try {
            $stmt = $pdo->prepare(
                "SELECT c.nombre
                   FROM accidente_consecuencia ac
                   JOIN consecuencia_accidente c ON c.id = ac.consecuencia_id
                  WHERE ac.accidente_id = ?
               ORDER BY ac.consecuencia_id"
            );
            $stmt->execute([$accidenteId]);
            $cons = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'nombre');
        } catch (Throwable) {
            $cons = [];
        }

        $people = [];
        try {
            $stmt = $pdo->prepare(
                "SELECT ip.id,
                        ip.orden_persona,
                        ip.lesion,
                        p.tipo_doc,
                        p.num_doc,
                        p.nombres,
                        p.apellido_paterno,
                        p.apellido_materno,
                        COALESCE(pp.Nombre, 'Participante') AS rol_nombre,
                        COALESCE(iv.orden_participacion, '') AS ut,
                        COALESCE(v.placa, '') AS placa,
                        COALESCE(tv.nombre, '') AS veh_tipo
                   FROM involucrados_personas ip
                   JOIN personas p ON p.id = ip.persona_id
              LEFT JOIN participacion_persona pp ON pp.Id = ip.rol_id
              LEFT JOIN involucrados_vehiculos iv ON iv.accidente_id = ip.accidente_id AND iv.vehiculo_id = ip.vehiculo_id
              LEFT JOIN vehiculos v ON v.id = ip.vehiculo_id
              LEFT JOIN tipos_vehiculo tv ON tv.id = v.tipo_id
                  WHERE ip.accidente_id = ?
               ORDER BY COALESCE(iv.orden_participacion, ''), COALESCE(pp.Orden, 999), COALESCE(ip.orden_persona, 'Z'), p.apellido_paterno, p.apellido_materno, p.nombres"
            );
            $stmt->execute([$accidenteId]);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $name = case_summary_widget_text(trim((string) (($row['nombres'] ?? '') . ' ' . ($row['apellido_paterno'] ?? '') . ' ' . ($row['apellido_materno'] ?? ''))));
                $vehicleParts = array_values(array_filter([
                    case_summary_widget_text((string) ($row['ut'] ?? '')),
                    case_summary_widget_text((string) ($row['veh_tipo'] ?? '')),
                    case_summary_widget_text((string) ($row['placa'] ?? '')) !== '' ? 'placa ' . case_summary_widget_text((string) ($row['placa'] ?? '')) : '',
                ]));
                $meta = array_values(array_filter([
                    case_summary_widget_text((string) ($row['lesion'] ?? '')),
                    $vehicleParts !== [] ? implode(' - ', $vehicleParts) : '',
                ]));

                $people[] = [
                    'label' => case_summary_widget_text((string) ($row['rol_nombre'] ?? 'Participante')),
                    'name' => $name !== '' ? $name : 'Persona sin identificar',
                    'meta' => $meta !== [] ? implode(' · ', $meta) : '',
                    'doc_label' => case_summary_widget_text((string) ($row['tipo_doc'] ?? 'DNI')) ?: 'Doc.',
                    'doc_value' => case_summary_widget_text((string) ($row['num_doc'] ?? '')),
                    'plate' => case_summary_widget_text((string) ($row['placa'] ?? '')),
                ];
            }
        } catch (Throwable) {
            $people = [];
        }

        $sidpol = case_summary_widget_text((string) ($accidente['registro_sidpol'] ?? ''));
        if ($sidpol === '') {
            $sidpol = case_summary_widget_text((string) ($accidente['sidpol'] ?? ''));
        }

        return [
            'id' => (int) ($accidente['id'] ?? $accidenteId),
            'sidpol' => $sidpol,
            'modalidad' => case_summary_widget_join($mods),
            'consecuencia' => case_summary_widget_join($cons),
            'fecha' => case_summary_widget_date($accidente['fecha_accidente'] ?? null),
            'hora' => case_summary_widget_time($accidente['fecha_accidente'] ?? null),
            'lugar' => case_summary_widget_text((string) ($accidente['lugar'] ?? '')) ?: '-',
            'distrito' => case_summary_widget_text((string) ($accidente['distrito_ubicacion'] ?? ($accidente['distrito'] ?? ''))) ?: '-',
            'jurisdiccion' => case_summary_widget_text((string) ($accidente['jurisdiccion'] ?? '')) ?: '-',
            'people' => $people,
        ];
    }
}

if (!function_exists('case_summary_widget_render')) {
    function case_summary_widget_render(?array $context, string $id): string
    {
        if (!$context) {
            return '';
        }

        $sidpol = (string) ($context['sidpol'] ?? '');
        $modalId = 'case-summary-widget-' . preg_replace('/[^a-zA-Z0-9_-]+/', '-', $id);
        $people = $context['people'] ?? [];

        ob_start();
        ?>
        <style>
          .case-summary-widget{display:inline-grid;gap:6px;margin-top:7px}
          .case-summary-widget-trigger{display:inline-flex;align-items:center;gap:5px;width:max-content;padding:4px 9px;border:1px solid #cbd8eb;border-radius:999px;background:#fff;color:#315a87;font:inherit;font-size:12px;font-weight:800;line-height:1.2;cursor:pointer;box-shadow:0 3px 9px rgba(17,24,39,.05)}
          .case-summary-widget-trigger:hover{border-color:#8fb3e0;background:#f5f9ff;color:#1d4f91}
          .case-summary-widget-modal{position:fixed;inset:0;z-index:10000;display:grid;place-items:start center;padding:72px 14px 20px;background:rgba(15,23,42,.28);backdrop-filter:blur(2px);overflow:auto}
          .case-summary-widget-modal[hidden]{display:none}
          .case-summary-widget-dialog{width:min(390px,100%);border:1px solid #cbd8e8;border-radius:14px;background:#fff;color:#172033;box-shadow:0 24px 58px rgba(15,23,42,.28);overflow:hidden}
          .case-summary-widget-head{display:flex;align-items:flex-start;justify-content:space-between;gap:10px;padding:10px 12px;border-bottom:1px solid #e2e8f0;background:linear-gradient(180deg,#f8fbff 0%,#eef5ff 100%)}
          .case-summary-widget-head h2{margin:0;color:#1f3f68;font-size:13px;font-weight:900;line-height:1.2}
          .case-summary-widget-head p{margin:2px 0 0;color:#64748b;font-size:10.5px;font-weight:700;line-height:1.25}
          .case-summary-widget-close{flex:0 0 auto;padding:4px 8px;border-radius:8px;border:1px solid #cbd8e8;background:#fff;color:#334155;font-size:11px;font-weight:800;cursor:pointer}
          .case-summary-widget-body{display:grid;gap:8px;padding:10px 12px 12px}
          .case-summary-widget-fields{display:grid;grid-template-columns:112px minmax(0,1fr);gap:5px 8px;padding:8px;border:1px dashed #d5e0ef;border-radius:10px;background:#fbfdff;font-size:11px;line-height:1.25}
          .case-summary-widget-fields dt{margin:0;color:#7c5e12;font-weight:900}
          .case-summary-widget-fields dd{margin:0;color:#1f2937;font-weight:700;overflow-wrap:anywhere}
          .case-summary-widget-people{display:grid;gap:5px;max-height:220px;overflow:auto;padding-right:2px}
          .case-summary-widget-person{display:grid;grid-template-columns:78px minmax(0,1fr);gap:6px;padding:6px 7px;border:1px solid #dbe4f0;border-radius:9px;background:#f8fafc;font-size:11px;line-height:1.22}
          .case-summary-widget-role{color:#315a87;font-weight:900;overflow-wrap:anywhere}
          .case-summary-widget-name{color:#0f172a;font-weight:800;overflow-wrap:anywhere}
          .case-summary-widget-meta{display:block;margin-top:2px;color:#64748b;font-size:10.5px;font-weight:600}
          .case-summary-widget-copyline{display:flex;flex-wrap:wrap;gap:4px;margin-top:5px}
          .case-summary-widget-copy{display:inline-flex;align-items:center;gap:4px;min-height:21px;padding:2px 6px;border:1px solid #cfd8e7;border-radius:999px;background:#fff;color:#334155;font-size:10.5px;font-weight:800;line-height:1;box-shadow:none;cursor:pointer}
          .case-summary-widget-copy:hover{background:#f2f7ff;border-color:#9fbbe0;color:#1d4f91}
          .case-summary-widget-copy.is-copied{background:#e8f7ef;border-color:#86d6a4;color:#166534}
          @media (prefers-color-scheme:dark){
            .case-summary-widget-trigger{background:#111b30;border-color:#33435e;color:#bfdbfe}
            .case-summary-widget-dialog{background:#101a2d;border-color:#34445f;color:#e5edf8}
            .case-summary-widget-head{background:linear-gradient(180deg,#16243a 0%,#111b30 100%);border-color:#2f405a}
            .case-summary-widget-head h2{color:#dbeafe}.case-summary-widget-head p{color:#9fb0c8}
            .case-summary-widget-close,.case-summary-widget-copy{background:#0f172a;border-color:#33445f;color:#dbe6f4}
            .case-summary-widget-fields{background:#0f172a;border-color:#33445f}.case-summary-widget-fields dd,.case-summary-widget-name{color:#e5edf8}
            .case-summary-widget-person{background:#111b30;border-color:#33445f}.case-summary-widget-role{color:#93c5fd}.case-summary-widget-meta{color:#aebdd2}
          }
        </style>
        <span class="case-summary-widget">
          <button type="button" class="case-summary-widget-trigger" data-case-summary-open="<?= case_summary_widget_h($modalId) ?>" aria-controls="<?= case_summary_widget_h($modalId) ?>" aria-expanded="false" title="Abrir resumen SIDPOL (Ctrl + Alt + S)">
            Registro SIDPOL <?= case_summary_widget_h($sidpol !== '' ? $sidpol : '-') ?>
          </button>
        </span>
        <div class="case-summary-widget-modal" id="<?= case_summary_widget_h($modalId) ?>" role="dialog" aria-modal="true" aria-labelledby="<?= case_summary_widget_h($modalId) ?>-title" hidden>
          <div class="case-summary-widget-dialog">
            <div class="case-summary-widget-head">
              <div>
                <h2 id="<?= case_summary_widget_h($modalId) ?>-title">Resumen SIDPOL <?= case_summary_widget_h($sidpol !== '' ? $sidpol : '-') ?></h2>
                <p>Accidente #<?= (int) ($context['id'] ?? 0) ?> · <?= count($people) ?> persona(s)</p>
              </div>
              <button type="button" class="case-summary-widget-close" data-case-summary-close="<?= case_summary_widget_h($modalId) ?>">Cerrar</button>
            </div>
            <div class="case-summary-widget-body">
              <dl class="case-summary-widget-fields">
                <dt>Modalidad</dt><dd><?= case_summary_widget_h($context['modalidad'] ?? '-') ?></dd>
                <dt>Consecuencia</dt><dd><?= case_summary_widget_h($context['consecuencia'] ?? '-') ?></dd>
                <dt>F. accidente</dt><dd><?= case_summary_widget_h($context['fecha'] ?? '-') ?></dd>
                <dt>H. accidente</dt><dd><?= case_summary_widget_h($context['hora'] ?? '-') ?></dd>
                <dt>Lugar</dt><dd><?= case_summary_widget_h($context['lugar'] ?? '-') ?></dd>
                <dt>Distrito</dt><dd><?= case_summary_widget_h($context['distrito'] ?? '-') ?></dd>
                <dt>Jurisdicción</dt><dd><?= case_summary_widget_h($context['jurisdiccion'] ?? '-') ?></dd>
              </dl>
              <div class="case-summary-widget-people">
                <?php if ($people === []): ?>
                  <div class="case-summary-widget-person"><span class="case-summary-widget-role">Personas</span><span class="case-summary-widget-name">Sin involucrados registrados</span></div>
                <?php else: ?>
                  <?php foreach ($people as $person): ?>
                    <div class="case-summary-widget-person">
                      <span class="case-summary-widget-role"><?= case_summary_widget_h($person['label'] ?? 'Participante') ?></span>
                      <span>
                        <span class="case-summary-widget-name"><?= case_summary_widget_h($person['name'] ?? 'Persona sin identificar') ?></span>
                        <?php if ((string) ($person['meta'] ?? '') !== ''): ?><span class="case-summary-widget-meta"><?= case_summary_widget_h($person['meta']) ?></span><?php endif; ?>
                        <?php if ((string) ($person['doc_value'] ?? '') !== '' || (string) ($person['plate'] ?? '') !== ''): ?>
                          <span class="case-summary-widget-copyline">
                            <?php if ((string) ($person['doc_value'] ?? '') !== ''): ?>
                              <button type="button" class="case-summary-widget-copy" data-copy-value="<?= case_summary_widget_h($person['doc_value']) ?>"><?= case_summary_widget_h($person['doc_label'] ?? 'Doc.') ?> <?= case_summary_widget_h($person['doc_value']) ?></button>
                            <?php endif; ?>
                            <?php if ((string) ($person['plate'] ?? '') !== ''): ?>
                              <button type="button" class="case-summary-widget-copy" data-copy-value="<?= case_summary_widget_h($person['plate']) ?>">Placa <?= case_summary_widget_h($person['plate']) ?></button>
                            <?php endif; ?>
                          </span>
                        <?php endif; ?>
                      </span>
                    </div>
                  <?php endforeach; ?>
                <?php endif; ?>
              </div>
            </div>
          </div>
        </div>
        <script>
        (() => {
          const modal = document.getElementById(<?= json_encode($modalId) ?>);
          const open = document.querySelector('[data-case-summary-open="<?= case_summary_widget_h($modalId) ?>"]');
          const close = document.querySelector('[data-case-summary-close="<?= case_summary_widget_h($modalId) ?>"]');
          const closeModal = () => { if (!modal) return; modal.hidden = true; open?.setAttribute('aria-expanded', 'false'); };
          open?.addEventListener('click', () => { if (!modal) return; modal.hidden = false; open.setAttribute('aria-expanded', 'true'); close?.focus(); });
          close?.addEventListener('click', closeModal);
          modal?.addEventListener('click', (event) => { if (event.target === modal) closeModal(); });
          document.addEventListener('keydown', (event) => {
            const key = String(event.key || '').toLowerCase();
            if (event.ctrlKey && event.altKey && key === 's') {
              if (!modal) return;
              event.preventDefault();
              modal.hidden = false;
              open?.setAttribute('aria-expanded', 'true');
              close?.focus();
              return;
            }
            if (event.key === 'Escape' && modal && !modal.hidden) {
              event.preventDefault();
              closeModal();
              open?.focus();
            }
          });
          modal?.querySelectorAll('[data-copy-value]').forEach((button) => {
            button.addEventListener('click', async () => {
              const original = button.textContent;
              const text = String(button.dataset.copyValue || '').trim();
              if (!text) return;
              try {
                if (navigator.clipboard && typeof navigator.clipboard.writeText === 'function') {
                  await navigator.clipboard.writeText(text);
                } else {
                  const temp = document.createElement('textarea');
                  temp.value = text;
                  document.body.appendChild(temp);
                  temp.select();
                  document.execCommand('copy');
                  temp.remove();
                }
                button.classList.add('is-copied');
                button.textContent = 'Copiado';
                window.setTimeout(() => { button.classList.remove('is-copied'); button.textContent = original || 'Copiar'; }, 1100);
              } catch (_) {
                button.textContent = 'Error';
                window.setTimeout(() => { button.textContent = original || 'Copiar'; }, 1100);
              }
            });
          });
        })();
        </script>
        <?php
        return (string) ob_get_clean();
    }
}
