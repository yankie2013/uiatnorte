<?php
declare(strict_types=1);
namespace App\Repositories;

use App\Support\Access;
use PDO;
use RuntimeException;

/** Read-only work queue. Assignment always comes from the linked case, never the task's author. */
final class DiligenciaInboxRepository
{
    public const STATES = ['Pendiente', 'En proceso'];
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
        $where = " WHERE (" . Access::workspacePredicate('a') . ") AND dp.estado IN ('Pendiente','En proceso')";
        $params = [];
        if ($state !== '') {
            if (!in_array($state, self::STATES, true)) throw new \InvalidArgumentException('Estado de búsqueda inválido.');
            $where .= ' AND dp.estado=?'; $params[] = $state;
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
        return "SELECT dp.id,dp.accidente_id,dp.estado,dp.contenido,td.nombre tipo_nombre,
            a.registro_sidpol,a.lugar,a.fecha_accidente,c.nombre comisaria,district.nombre distrito";
    }

    public function find(int $id, string $q = '', string $state = ''): ?array
    {
        [$from,$where,$params] = $this->query($q,$state);
        $statement = $this->pdo->prepare($this->columns().$from.$where.' AND dp.id=?');
        $statement->execute([...$params,$id]);
        return $statement->fetch(PDO::FETCH_ASSOC) ?: null;
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
        return compact('rows','total','page','pages','selected');
    }
}
