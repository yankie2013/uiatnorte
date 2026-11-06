<?php
declare(strict_types=1);
namespace App\Support;

use PDO;

/** Effective task status from its own documents. No writes, migrations or case-wide guessing. */
final class DiligenciaEstado
{
    private array $columns = [];
    public function __construct(private PDO $pdo) {}

    private function has(string $table, string $column): bool
    {
        if (!isset($this->columns[$table])) {
            try {
                if ($this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
                    $rows = $this->pdo->query('PRAGMA table_info('.$table.')')->fetchAll(PDO::FETCH_ASSOC);
                    $this->columns[$table] = array_fill_keys(array_column($rows,'name'),true);
                } else {
                    $rows = $this->pdo->query('SHOW COLUMNS FROM `'.$table.'`')->fetchAll(PDO::FETCH_ASSOC);
                    $this->columns[$table] = array_fill_keys(array_column($rows,'Field'),true);
                }
            } catch (\PDOException $e) {
                // Only genuinely missing optional tables are compatible fallbacks.
                if (!in_array((int)($e->errorInfo[1]??0),[1146],true)) throw $e;
                $this->columns[$table] = [];
            }
        }
        return isset($this->columns[$table][$column]);
    }

    public function expression(string $alias = 'dp'): string
    {
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/D',$alias)) throw new \InvalidArgumentException('Alias inválido.');
        $office = '0=1'; $reply = '0=1';
        if ($this->has('diligencias_pendientes_activos','oficio_id') && $this->has('oficios_activos','id')) {
            $valid = $this->has('oficios_activos','estado') ? " AND UPPER(COALESCE(oe.estado,''))<>'ANULADO'" : '';
            $office = "EXISTS (SELECT 1 FROM oficios_activos oe WHERE oe.id=$alias.oficio_id AND oe.accidente_id=$alias.accidente_id$valid)";
            if ($this->has('documentos_recibidos_activos','referencia_oficio_id')) {
                $reply = "($office AND EXISTS (SELECT 1 FROM documentos_recibidos_activos re WHERE re.referencia_oficio_id=$alias.oficio_id AND re.accidente_id=$alias.accidente_id))";
            }
        }
        $document = $this->has('diligencias_pendientes_activos','documento_realizado') ? "TRIM(COALESCE($alias.documento_realizado,''))<>''" : '0=1';
        $received = $this->has('diligencias_pendientes_activos','documentos_recibidos') ? "TRIM(COALESCE($alias.documentos_recibidos,''))<>''" : '0=1';
        $acts = [];
        if ($this->has('diligencias_actas','diligencia_id')) {
            foreach (DiligenciaActas::TYPES as $type=>$config) {
                $table = $config['table'];
                if ($this->has($table,'id')) $acts[] = "(ae.tipo='$type' AND EXISTS (SELECT 1 FROM $table ar WHERE ar.id=ae.acta_id AND ar.accidente_id=$alias.accidente_id))";
            }
        }
        if ($acts) $document = "($document OR EXISTS (SELECT 1 FROM diligencias_actas ae WHERE ae.diligencia_id=$alias.id AND (".implode(' OR ',$acts).')))';

        $citationsDone = '0=1'; $citationsPending = '0=1'; $citationsInvalid = '0=1';
        if ($this->has('diligencias_pendientes_activos','citacion_id') && $this->has('citacion_activos','id')) {
            $link = "$alias.citacion_id=ce.id";
            $expected = "CASE WHEN COALESCE($alias.citacion_id,0)>0 THEN 1 ELSE 0 END";
            if ($this->has('diligencias_pendientes_activos','citacion_ids')) {
                $json = "CASE WHEN JSON_VALID($alias.citacion_ids) THEN $alias.citacion_ids ELSE '[]' END";
                $link .= " OR JSON_CONTAINS($json,CAST(ce.id AS CHAR))";
                $expected = "(JSON_LENGTH($json) + CASE WHEN COALESCE($alias.citacion_id,0)>0 AND NOT JSON_CONTAINS($json,CAST($alias.citacion_id AS CHAR)) THEN 1 ELSE 0 END)";
            }
            $from = "FROM citacion_activos ce";
            $outcome = "'pendiente'";
            if ($this->has('citacion','estado_realizacion')) {
                $from .= ' JOIN citacion cb ON cb.id=ce.id';
                $outcome = "LOWER(TRIM(COALESCE(cb.estado_realizacion,'pendiente')))";
            } elseif ($this->has('citacion_activos','estado_realizacion')) {
                $outcome = "LOWER(TRIM(COALESCE(ce.estado_realizacion,'pendiente')))";
            }
            $where = "ce.accidente_id=$alias.accidente_id AND ($link)";
            $validCount = "(SELECT COUNT(*) $from WHERE $where)";
            $done = "(SELECT COUNT(*) $from WHERE $where AND $outcome='realizada')";
            $citationsDone = "(($expected)>0 AND $done=($expected))";
            $citationsPending = "EXISTS (SELECT 1 $from WHERE $where AND $outcome NOT IN ('realizada','suspendida','cancelada','cancelado','eliminada','reprogramada','no-realizada'))";
            $citationsInvalid = "(($expected)>0 AND ($validCount<($expected) OR EXISTS (SELECT 1 $from WHERE $where AND $outcome IN ('suspendida','cancelada','cancelado','eliminada','reprogramada','no-realizada'))))";
        }
        return "CASE
            WHEN $alias.estado='Cancelado' THEN 'Cancelado'
            WHEN ($reply OR $received OR $citationsDone) THEN 'Realizado'
            WHEN ($citationsInvalid AND NOT ($office OR $document OR $citationsPending)) THEN 'Pendiente'
            WHEN $alias.estado='Realizado' THEN 'Realizado'
            WHEN ($office OR $document OR $citationsPending OR $alias.estado='En proceso') THEN 'En proceso'
            ELSE 'Pendiente' END";
    }
}
