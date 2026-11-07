<?php
declare(strict_types=1);
namespace App\Repositories;

use App\Support\Access;
use PDO;
use RuntimeException;

/** Permission-scoped work queue. Assignment always comes from the linked case, never the task's author. */
final class DiligenciaInboxRepository
{
    public const STATES = ['Pendiente', 'En proceso', 'Realizado'];
    private ?string $effectiveState = null;
    private function stateExpression(): string { return $this->effectiveState ??= (new \App\Support\DiligenciaEstado($this->pdo))->expression(); }
    public const PAGE_SIZE = 20;
    public function __construct(private PDO $pdo) {}

    public static function allowed(): bool
    {
        return in_array(Access::role(), ['jefe_emi', 'admin'], true) && Access::id() > 0;
    }

    private function query(string $q = '', string $state = ''): array
    {
        if (!self::allowed()) throw new RuntimeException('No tienes acceso a esta bandeja.');
        $from = " FROM diligencias_pendientes_activos dp
            JOIN accidentes_activos a ON a.id=dp.accidente_id
            LEFT JOIN tipo_diligencia td ON td.id=dp.tipo_diligencia_id
            LEFT JOIN comisarias c ON c.id=a.comisaria_id
            LEFT JOIN ubigeo_distrito district ON district.cod_dep=a.cod_dep AND district.cod_prov=a.cod_prov AND district.cod_dist=a.cod_dist";
        $effective = $this->stateExpression();
        $where = " WHERE (" . Access::workspacePredicate('a') . ") AND ($effective) IN ('Pendiente','En proceso','Realizado')";
        $params = [];
        if ($state !== '') {
            if (!in_array($state, self::STATES, true)) throw new \InvalidArgumentException('Estado de búsqueda inválido.');
            $where .= ' AND ('.$effective.')=?'; $params[] = $state;
        }
        if ($q !== '') {
            $where .= " AND (td.nombre LIKE ? OR dp.contenido LIKE ? OR a.registro_sidpol LIKE ? OR CAST(a.id AS CHAR) = ?
                OR district.nombre LIKE ? OR c.nombre LIKE ?
                OR EXISTS (SELECT 1 FROM involucrados_personas_activos ip JOIN personas p ON p.id=ip.persona_id
                    WHERE ip.accidente_id=a.id AND CONCAT_WS(' ',p.nombres,p.apellido_paterno,p.apellido_materno) LIKE ?)
                OR EXISTS (SELECT 1 FROM involucrados_vehiculos_activos iv JOIN vehiculos v ON v.id=iv.vehiculo_id
                    WHERE iv.accidente_id=a.id AND v.placa LIKE ?))";
            $like = '%' . $q . '%';
            $params = array_merge($params, [$like,$like,$like,$q,$like,$like,$like,$like]);
        }
        return [$from, $where, $params];
    }

    private function columns(): string
    {
        return "SELECT dp.id,dp.accidente_id,".$this->stateExpression()." AS estado,dp.contenido,td.nombre tipo_nombre,
            a.registro_sidpol,a.lugar,a.fecha_accidente,c.nombre comisaria,district.nombre distrito";
    }

    public function find(int $id, string $q = '', string $state = ''): ?array
    {
        [$from,$where,$params] = $this->query($q,$state);
        $statement = $this->pdo->prepare($this->columns().$from.$where.' AND dp.id=?');
        $statement->execute([...$params,$id]);
        return $statement->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function changeState(int $id, string $state): array
    {
        if (!self::allowed()) throw new RuntimeException('No tienes acceso a esta bandeja.',403);
        if (!in_array($state,self::STATES,true)) throw new \InvalidArgumentException('Estado inválido.');
        $ownsTransaction = !$this->pdo->inTransaction();
        $savepoint = 'task_status_'.bin2hex(random_bytes(4));
        if ($ownsTransaction) $this->pdo->beginTransaction();
        else $this->pdo->exec('SAVEPOINT '.$savepoint);
        try {
            $lock = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql' ? ' FOR UPDATE' : '';
            $check = $this->pdo->prepare('SELECT dp.accidente_id FROM diligencias_pendientes_activos dp JOIN accidentes_activos a ON a.id=dp.accidente_id WHERE dp.id=? AND ('.Access::workspacePredicate('a').')'.$lock);
            $check->execute([$id]);
            $caseId = (int)$check->fetchColumn();
            if (!$caseId) throw new RuntimeException('Diligencia no disponible.',404);
            if (!Access::canEdit($caseId)) throw new RuntimeException('No tienes permiso para modificar esta diligencia.',403);
            $statement = $this->pdo->prepare('UPDATE diligencias_pendientes SET estado=?, actualizado_en=CURRENT_TIMESTAMP WHERE id=?');
            $statement->execute([$state,$id]);
            $row = $this->find($id);
            if (!$row || $row['estado']!==$state) {
                throw new RuntimeException('Los documentos vinculados determinan el estado '.($row['estado']??'actual').'. Revisa esos documentos antes de cambiarlo.',409);
            }
            if ($ownsTransaction) $this->pdo->commit();
            else $this->pdo->exec('RELEASE SAVEPOINT '.$savepoint);
            return $row;
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                if ($ownsTransaction) $this->pdo->rollBack();
                else { $this->pdo->exec('ROLLBACK TO SAVEPOINT '.$savepoint); $this->pdo->exec('RELEASE SAVEPOINT '.$savepoint); }
            }
            throw $e;
        }
    }

    public function counts(string $q = ''): array
    {
        [$from,$where,$params] = $this->query($q);
        $statement = $this->pdo->prepare('SELECT effective_state, COUNT(*) total FROM (SELECT '.$this->stateExpression().' AS effective_state'.$from.$where.') grouped_tasks GROUP BY effective_state');
        $statement->execute($params);
        $counts = array_fill_keys(self::STATES,0);
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) $counts[$row['effective_state']] = (int)$row['total'];
        return $counts;
    }

    public function search(string $q, string $state, int $page = 1, int $selectedId = 0, bool $locate = false): array
    {
        [$from,$where,$params] = $this->query($q,$state);
        $count = $this->pdo->prepare('SELECT COUNT(*)'.$from.$where);
        $count->execute($params); $total = (int)$count->fetchColumn();
        $pages = max(1,(int)ceil($total/self::PAGE_SIZE));
        $page = min(max(1,$page),$pages);
        if ($locate && $selectedId > 0 && $this->find($selectedId,$q,$state)) {
            $position = $this->pdo->prepare('SELECT COUNT(*)'.$from.$where.' AND dp.id>?');
            $position->execute([...$params,$selectedId]);
            $page = (int)floor((int)$position->fetchColumn()/self::PAGE_SIZE)+1;
        }
        $offset = ($page-1)*self::PAGE_SIZE;
        $statement = $this->pdo->prepare($this->columns().$from.$where.' ORDER BY dp.id DESC LIMIT '.self::PAGE_SIZE.' OFFSET '.$offset);
        $statement->execute($params); $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        $selected = $rows[0] ?? null;
        foreach ($rows as $row) if ((int)$row['id'] === $selectedId) { $selected = $row; break; }
        $counts = $this->counts($q);
        return compact('rows','total','page','pages','selected','counts');
    }
}
