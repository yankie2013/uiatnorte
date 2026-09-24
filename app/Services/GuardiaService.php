<?php
declare(strict_types=1);
namespace App\Services;
use App\Support\Access;
use PDO;
use RuntimeException;
use Throwable;

final class GuardiaService
{
    public function __construct(private PDO $pdo) {}
    public function save(int $id,array $input): int {
        if(!in_array(Access::role(),['guardia','admin'],true))throw new RuntimeException('Solo guardia y administrador registran comunicaciones.');
        $fields=['fecha_llamada','comunicante','telefono','lugar','referencia','descripcion','tipo_hecho','vehiculos','afectados','latitud','longitud','cod_dep','cod_prov','cod_dist'];
        $data=[];foreach($fields as $f)$data[$f]=trim((string)($input[$f]??'')) ?: null;
        foreach(['fecha_llamada','lugar','descripcion'] as $f)if(!$data[$f])throw new RuntimeException('Complete fecha y hora de llamada, lugar y descripción.');
        $date=\DateTimeImmutable::createFromFormat('!Y-m-d\TH:i',(string)$data['fecha_llamada']);
        if(!$date || $date->format('Y-m-d\TH:i')!==$data['fecha_llamada'])throw new RuntimeException('Fecha de llamada inválida.');
        $data['fecha_llamada']=$date->format('Y-m-d H:i:s');
        if(($data['latitud']===null)!==($data['longitud']===null))throw new RuntimeException('Indique ambas coordenadas o ninguna.');
        foreach(['latitud'=>90,'longitud'=>180] as $f=>$limit)if($data[$f]!==null && (!is_numeric($data[$f]) || abs((float)$data[$f])>$limit))throw new RuntimeException('Coordenadas inválidas.');
        $this->pdo->beginTransaction();
        try {
            if($id) {
                $s=$this->pdo->prepare('SELECT *,NOW()<DATE_ADD(registrado_en, INTERVAL 12 HOUR) AS dentro_plazo FROM comunicaciones_guardia WHERE id=? FOR UPDATE');$s->execute([$id]);$row=$s->fetch();
                if(!$row || !empty($row['eliminado_en']) || (!Access::admin() && ((int)$row['creado_por']!==Access::id() || !$row['dentro_plazo'])))throw new RuntimeException('No puede editar esta comunicación: el plazo es de 12 horas desde el registro original.');
                $sql='UPDATE comunicaciones_guardia SET '.implode(',',array_map(fn($f)=>"`$f`=?",$fields)).' WHERE id=?';
                $this->pdo->prepare($sql)->execute([...array_values($data),$id]);
            } else {
                $this->pdo->prepare('INSERT INTO comunicaciones_guardia('.implode(',',$fields).',creado_por) VALUES('.implode(',',array_fill(0,count($fields)+1,'?')).')')->execute([...array_values($data),Access::id()]);
                $id=(int)$this->pdo->lastInsertId();
            }
            $this->pdo->commit();return $id;
        } catch(Throwable $e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $e;}
    }
    public function assign(int $id,int $chief): int {
        if(!in_array(Access::role(),['guardia','admin'],true))throw new RuntimeException('Perfil sin permiso de asignación.');
        $this->pdo->beginTransaction();
        try {
            $s=$this->pdo->prepare('SELECT * FROM comunicaciones_guardia WHERE id=? FOR UPDATE');$s->execute([$id]);$row=$s->fetch();
            if(!$row || $row['eliminado_en'] || (!Access::admin() && (int)$row['creado_por']!==Access::id()))throw new RuntimeException('Comunicación no disponible para asignación.');
            if($row['accidente_id'])throw new RuntimeException('La comunicación ya fue asignada. La siguiente derivación corresponde al JEFE EMI.');
            $s=$this->pdo->prepare("SELECT id FROM usuarios WHERE id=? AND activo=1 AND rol='jefe_emi'");$s->execute([$chief]);if(!$s->fetch())throw new RuntimeException('Seleccione un JEFE EMI activo.');
            $s=$this->pdo->prepare('SELECT 1 FROM ubigeo_distrito WHERE cod_dep=? AND cod_prov=? AND cod_dist=?');$s->execute([$row['cod_dep'],$row['cod_prov'],$row['cod_dist']]);if(!$s->fetch())throw new RuntimeException('Complete departamento, provincia y distrito antes de asignar el caso.');
            $this->pdo->exec('SET @rbac_guardia_assign=1');
            $this->pdo->prepare("INSERT INTO accidentes(registro_sidpol,tipo_registro,lugar,referencia,cod_dep,cod_prov,cod_dist,fecha_accidente,fecha_comunicacion,comunicante_nombre,comunicante_telefono,latitud,longitud,responsable_id,creado_por) VALUES(NULL,'Intervencion',?,?,?,?,?,NULL,?,?,?,?,?,?,?)")->execute([$row['lugar'],$row['referencia'],$row['cod_dep'],$row['cod_prov'],$row['cod_dist'],$row['fecha_llamada'],$row['comunicante'],$row['telefono'],$row['latitud'],$row['longitud'],$chief,Access::id()]);
            $case=(int)$this->pdo->lastInsertId();
            $this->pdo->prepare('UPDATE comunicaciones_guardia SET accidente_id=?,jefe_id=?,asignado_en=NOW() WHERE id=?')->execute([$case,$chief,$id]);
            $this->pdo->commit();return $case;
        } catch(Throwable $e) {if($this->pdo->inTransaction())$this->pdo->rollBack();throw $e;}
        finally {$this->pdo->exec('SET @rbac_guardia_assign=NULL');}
    }
}
