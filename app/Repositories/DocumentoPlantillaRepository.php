<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class DocumentoPlantillaRepository
{
    private array $columnCache = [];
    private array $tableCache = [];

    public function __construct(private PDO $pdo)
    {
    }

    public function hasTable(string $table): bool
    {
        if (isset($this->tableCache[$table])) {
            return $this->tableCache[$table];
        }

        $st = $this->pdo->prepare('SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
        $st->execute([$table]);
        return $this->tableCache[$table] = (bool) $st->fetchColumn();
    }

    public function hasColumn(string $table, string $column): bool
    {
        return in_array($column, $this->tableColumns($table), true);
    }

    public function oficioRemitirById(int $id): ?array
    {
        $joins = [];
        $select = [
            'o.*',
            'a.lugar AS acc_lugar',
            'a.fecha_accidente',
            'a.referencia AS acc_referencia',
            'a.folder',
            'e.nombre AS entidad_nombre',
            'e.siglas AS entidad_siglas',
            's.nombre AS asunto_nombre',
            's.detalle AS asunto_detalle',
        ];

        $joins[] = 'LEFT JOIN accidentes a ON a.id = o.accidente_id';
        $joins[] = 'LEFT JOIN oficio_entidad e ON e.id = o.entidad_id_destino';
        $joins[] = 'LEFT JOIN oficio_asunto s ON s.id = o.asunto_id';

        if ($this->hasTable('comisarias') && $this->hasColumn('accidentes', 'comisaria_id')) {
            $select[] = 'c.nombre AS comisaria_nombre';
            $joins[] = 'LEFT JOIN comisarias c ON c.id = a.comisaria_id';
        } else {
            $select[] = 'NULL AS comisaria_nombre';
        }

        if ($this->hasTable('grado_cargo') && $this->hasColumn('oficios', 'grado_cargo_id')) {
            $select[] = 'gc.nombre AS grado_cargo_nombre';
            $select[] = 'gc.abreviatura AS grado_cargo_abrev';
            $joins[] = 'LEFT JOIN grado_cargo gc ON gc.id = o.grado_cargo_id';
        } else {
            $select[] = 'NULL AS grado_cargo_nombre';
            $select[] = 'NULL AS grado_cargo_abrev';
        }

        if ($this->hasTable('oficio_persona_entidad') && $this->hasColumn('oficios', 'persona_destino_id')) {
            $select[] = 'ppe.nombres AS ppe_nombres';
            $select[] = 'ppe.apellido_paterno AS ppe_apep';
            $select[] = 'ppe.apellido_materno AS ppe_apem';
            $joins[] = 'LEFT JOIN oficio_persona_entidad ppe ON ppe.id = o.persona_destino_id';
        } else {
            $select[] = 'NULL AS ppe_nombres';
            $select[] = 'NULL AS ppe_apep';
            $select[] = 'NULL AS ppe_apem';
        }

        if ($this->hasTable('oficio_oficial_ano') && $this->hasColumn('oficios', 'oficial_ano_id')) {
            $select[] = 'oa.nombre AS nombre_oficial_ano';
            $joins[] = 'LEFT JOIN oficio_oficial_ano oa ON oa.id = o.oficial_ano_id';
        } else {
            $select[] = 'NULL AS nombre_oficial_ano';
        }

        $sql = 'SELECT ' . implode(', ', $select) . ' FROM oficios o ' . implode(' ', $joins) . ' WHERE o.id = ? LIMIT 1';
        $st = $this->pdo->prepare($sql);
        $st->execute([$id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function involucradosPersonasByAccidente(int $accidenteId): array
    {
        if ($accidenteId <= 0) {
            return [];
        }

        $roleJoin = '';
        $roleSelect = 'NULL AS rol_nombre';
        if ($this->hasTable('participacion_persona') && $this->hasColumn('involucrados_personas', 'rol_id')) {
            $roleJoin = 'LEFT JOIN participacion_persona rp ON rp.Id = ip.rol_id';
            $roleSelect = 'rp.Nombre AS rol_nombre';
        }

        $sql = "SELECT ip.*, COALESCE(p.nombres,'') AS nombres,
                       COALESCE(p.apellido_paterno,'') AS apellido_paterno,
                       COALESCE(p.apellido_materno,'') AS apellido_materno,
                       COALESCE(p.num_doc,'') AS num_doc,
                       {$roleSelect}
                FROM involucrados_personas ip
                LEFT JOIN personas p ON p.id = ip.persona_id
                {$roleJoin}
                WHERE ip.accidente_id = ?
                ORDER BY CAST(COALESCE(ip.orden_persona,'0') AS UNSIGNED) ASC, ip.id ASC";
        $st = $this->pdo->prepare($sql);
        $st->execute([$accidenteId]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function involucradosVehiculosByAccidente(int $accidenteId): array
    {
        if ($accidenteId <= 0) {
            return [];
        }

        $select = ['iv.*'];
        foreach (['placa', 'marca', 'modelo', 'tipo', 'observaciones'] as $column) {
            if ($this->hasColumn('vehiculos', $column)) {
                $select[] = "COALESCE(v.{$column}, '') AS {$column}";
            } else {
                $select[] = "'' AS {$column}";
            }
        }

        $sql = 'SELECT ' . implode(', ', $select) . '
                FROM involucrados_vehiculos iv
                LEFT JOIN vehiculos v ON v.id = iv.vehiculo_id
                WHERE iv.accidente_id = ?
                ORDER BY CAST(COALESCE(iv.orden_participacion,\'0\') AS UNSIGNED) ASC, iv.id ASC';
        $st = $this->pdo->prepare($sql);
        $st->execute([$accidenteId]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function personaById(int $id): ?array
    {
        $st = $this->pdo->prepare('SELECT nombres, apellido_paterno, apellido_materno, num_doc, celular, email, edad FROM personas WHERE id = ? LIMIT 1');
        $st->execute([$id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function personaByDoc(string $numDoc): ?array
    {
        $st = $this->pdo->prepare('SELECT celular, email, edad FROM personas WHERE num_doc = ? LIMIT 1');
        $st->execute([$numDoc]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function citacionById(int $id): ?array
    {
        $joins = ['LEFT JOIN accidentes a ON a.id = c.accidente_id', 'LEFT JOIN oficios o ON o.id = c.oficio_id'];
        $select = [
            'c.*',
            'a.registro_sidpol',
            'a.fecha_accidente',
            'a.lugar AS acc_lugar',
            'a.id AS acc_id',
            'o.numero AS oficio_num',
            'o.anio AS oficio_anio',
        ];

        if ($this->hasTable('comisarias') && $this->hasColumn('accidentes', 'comisaria_id')) {
            $select[] = 'cmi.nombre AS comisaria_nombre';
            $joins[] = 'LEFT JOIN comisarias cmi ON cmi.id = a.comisaria_id';
        } else {
            $select[] = 'NULL AS comisaria_nombre';
        }

        if ($this->hasTable('fiscalia') && $this->hasColumn('accidentes', 'fiscalia_id')) {
            $select[] = 'f.nombre AS fiscalia_nombre';
            $joins[] = 'LEFT JOIN fiscalia f ON f.id = a.fiscalia_id';
        } else {
            $select[] = 'NULL AS fiscalia_nombre';
        }

        $sql = 'SELECT ' . implode(', ', $select) . ' FROM citacion c ' . implode(' ', $joins) . ' WHERE c.id = ? LIMIT 1';
        $st = $this->pdo->prepare($sql);
        $st->execute([$id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function accidenteModalidad(int $accidenteId): string
    {
        if ($accidenteId <= 0) {
            return '';
        }

        foreach (['modalidad', 'modalidades', 'tipo_accidente', 'clase_accidente', 'modalidad_texto', 'modalidad_nombre'] as $column) {
            if ($this->hasColumn('accidentes', $column)) {
                $st = $this->pdo->prepare("SELECT {$column} FROM accidentes WHERE id = ? LIMIT 1");
                $st->execute([$accidenteId]);
                $value = trim((string) $st->fetchColumn());
                if ($value !== '') {
                    return $value;
                }
            }
        }

        if ($this->hasTable('accidente_modalidad') && $this->hasTable('modalidad_accidente')) {
            $sql = "SELECT GROUP_CONCAT(m.nombre ORDER BY m.nombre SEPARATOR '||')
                    FROM accidente_modalidad am
                    JOIN modalidad_accidente m ON m.id = am.modalidad_id
                    WHERE am.accidente_id = ?";
            $st = $this->pdo->prepare($sql);
            $st->execute([$accidenteId]);
            return trim((string) $st->fetchColumn());
        }

        if ($this->hasTable('accidente_modalidad') && $this->hasTable('modalidad')) {
            $sql = "SELECT GROUP_CONCAT(m.nombre ORDER BY m.nombre SEPARATOR '||')
                    FROM accidente_modalidad am
                    JOIN modalidad m ON m.id = am.modalidad_id
                    WHERE am.accidente_id = ?";
            $st = $this->pdo->prepare($sql);
            $st->execute([$accidenteId]);
            return trim((string) $st->fetchColumn());
        }

        return '';
    }

    public function accidenteHora(int $accidenteId, ?string $fechaAccidente): string
    {
        if ($accidenteId > 0) {
            foreach (['hora_accidente', 'hora'] as $column) {
                if ($this->hasColumn('accidentes', $column)) {
                    $st = $this->pdo->prepare("SELECT {$column} FROM accidentes WHERE id = ? LIMIT 1");
                    $st->execute([$accidenteId]);
                    $value = trim((string) $st->fetchColumn());
                    if ($value !== '') {
                        return substr($value, 0, 5);
                    }
                }
            }
        }

        $fechaAccidente = (string) ($fechaAccidente ?? '');
        if ($fechaAccidente !== '' && str_contains($fechaAccidente, ' ')) {
            $time = trim(explode(' ', $fechaAccidente, 2)[1] ?? '');
            return $time !== '' ? substr($time, 0, 5) : '';
        }

        return '';
    }

    private function tableColumns(string $table): array
    {
        if (isset($this->columnCache[$table])) {
            return $this->columnCache[$table];
        }

        $st = $this->pdo->prepare('SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
        $st->execute([$table]);
        return $this->columnCache[$table] = $st->fetchAll(PDO::FETCH_COLUMN, 0) ?: [];
    }
}
