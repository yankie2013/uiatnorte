<?php
declare(strict_types=1);
namespace Uiat\Connector;
use PDO;
use RuntimeException;

/** Closed projections: every final query carries the same case scope. Never bootstraps the application. */
final class OfficeQueries
{
    public function __construct(private PDO $pdo,private CaseQueries $cases){}
    private function scoped(Actor $a):array {
        if($a->id<=0)return ['1=0',[]];
        return match($a->role){
            'admin'=>[$a->id>0?'1=1':'1=0',[]],
            'jefe_emi'=>['a.responsable_id=?',[$a->id]],
            'adjunto'=>['EXISTS(SELECT 1 FROM expediente_colaboradores ec WHERE ec.accidente_id=a.id AND ec.usuario_id=? AND ec.revocado_en IS NULL)',[$a->id]],
            'secretaria','administracion'=>["EXISTS(SELECT 1 FROM expediente_transferencias et WHERE et.accidente_id=a.id AND et.destino_id=? AND et.tipo='archivo' AND et.estado='aceptada')",[$a->id]],default=>['1=0',[]]};
    }
    private function rows(string $sql,array $args):array {$q=$this->pdo->prepare($sql);foreach($args as $i=>$v)$q->bindValue($i+1,$v,is_int($v)?PDO::PARAM_INT:PDO::PARAM_STR);$q->execute();return $q->fetchAll(PDO::FETCH_ASSOC);}
    public function base(Actor $actor,int $case,string $type):array {
        $base=$this->cases->fichaParaBorrador($actor,$case);[$scope,$args]=$this->scoped($actor);
        if($type==='peritaje')$query="SELECT u.nombre AS distrito FROM accidentes_activos a LEFT JOIN ubigeo_distrito u ON u.cod_dep=a.cod_dep AND u.cod_prov=a.cod_prov AND u.cod_dist=a.cod_dist WHERE ($scope) AND a.id=?";
        elseif($type==='remitir_informe_policial')$query="SELECT c.nombre AS comisaria,a.nro_informe_policial FROM accidentes_activos a LEFT JOIN comisarias c ON c.id=a.comisaria_id WHERE ($scope) AND a.id=?";
        else $query="SELECT c.nombre AS comisaria FROM accidentes_activos a LEFT JOIN comisarias c ON c.id=a.comisaria_id WHERE ($scope) AND a.id=?";
        $extra=$this->rows($query,[...$args,$case]);if(count($extra)!==1)throw new RuntimeException('Expediente no disponible.');return $base+$extra[0];
    }
    public function people(Actor $actor,int $case,bool $deceased,int $after=0):array {
        $this->cases->ficha($actor,$case);[$scope,$args]=$this->scoped($actor);
        $filter=$deceased?" AND UPPER(TRIM(COALESCE(ip.lesion,'')))='FALLECIDO'":'';
        return $this->rows("SELECT ip.id,p.nombres,p.apellido_paterno,p.apellido_materno,r.Nombre AS rol FROM involucrados_personas_activos ip JOIN accidentes_activos a ON a.id=ip.accidente_id JOIN personas p ON p.id=ip.persona_id LEFT JOIN participacion_persona r ON r.Id=ip.rol_id WHERE ($scope) AND a.id=? AND ip.id>? $filter ORDER BY ip.id LIMIT 51",[...$args,$case,$after]);
    }
    public function person(Actor $actor,int $case,int $record,string $type):array {
        [$scope,$args]=$this->scoped($actor);$extra='';
        if(in_array($type,['protocolo_necropsia','defuncion_reniec'],true))$extra=',p.fecha_nacimiento';
        if($type==='informe_medico')$extra=',p.num_doc';
        if($type==='defuncion_reniec')$extra.=',p.num_doc,p.sexo,p.estado_civil,p.nacionalidad,p.departamento_nac,p.provincia_nac,p.distrito_nac,p.nombre_padre,p.nombre_madre';
        $filter=in_array($type,['protocolo_necropsia','defuncion_reniec','solicitar_diligencias_comisaria'],true)?" AND UPPER(TRIM(COALESCE(ip.lesion,'')))='FALLECIDO'":'';
        $rows=$this->rows("SELECT ip.id,ip.persona_id,p.nombres,p.apellido_paterno,p.apellido_materno $extra FROM involucrados_personas_activos ip JOIN accidentes_activos a ON a.id=ip.accidente_id JOIN personas p ON p.id=ip.persona_id WHERE ($scope) AND a.id=? AND ip.id=? $filter",[...$args,$case,$record]);
        if(count($rows)!==1)throw new RuntimeException('Persona no disponible.');return $rows[0];
    }
    public function vehicles(Actor $actor,int $case,int $after=0):array {
        $this->cases->ficha($actor,$case);[$scope,$args]=$this->scoped($actor);
        return $this->rows("SELECT iv.id,v.placa FROM involucrados_vehiculos_activos iv JOIN accidentes_activos a ON a.id=iv.accidente_id JOIN vehiculos v ON v.id=iv.vehiculo_id WHERE ($scope) AND a.id=? AND iv.id>? ORDER BY iv.id LIMIT 51",[...$args,$case,$after]);
    }
    public function vehicle(Actor $actor,int $case,int $record,string $type):array {
        [$scope,$args]=$this->scoped($actor);$extra='';$joins='';
        if($type==='certificado_uper'){$extra=',t.nombre AS tipo';$joins=' LEFT JOIN tipos_vehiculo t ON t.id=v.tipo_id';}
        if($type==='peritaje'){
            $extra=',v.anio,v.color,m.nombre AS marca,mo.nombre AS modelo,c.codigo AS categoria,b.nombre AS carroceria';
            $joins=' LEFT JOIN marcas_vehiculo m ON m.id=v.marca_id LEFT JOIN modelos_vehiculo mo ON mo.id=v.modelo_id LEFT JOIN categoria_vehiculos c ON c.id=v.categoria_id LEFT JOIN carroceria_vehiculo b ON b.id=v.carroceria_id';
        }
        $rows=$this->rows("SELECT iv.id,iv.vehiculo_id,v.placa $extra FROM involucrados_vehiculos_activos iv JOIN accidentes_activos a ON a.id=iv.accidente_id JOIN vehiculos v ON v.id=iv.vehiculo_id $joins WHERE ($scope) AND a.id=? AND iv.id=?",[...$args,$case,$record]);
        if(count($rows)!==1)throw new RuntimeException('Vehículo no disponible.');$vehicle=$rows[0];
        if($type==='peritaje'){
            // Driver and owner must be linked to this very case and selected vehicle, not merely a global person ID.
            $vehicle['conductores']=$this->rows("SELECT ip.id,p.nombres,p.apellido_paterno,p.apellido_materno FROM involucrados_personas_activos ip JOIN accidentes_activos a ON a.id=ip.accidente_id JOIN personas p ON p.id=ip.persona_id WHERE ($scope) AND a.id=? AND ip.vehiculo_id=? AND ip.rol_id IN(SELECT Id FROM participacion_persona WHERE UPPER(TRIM(Nombre))='CONDUCTOR') ORDER BY ip.id LIMIT 51",[...$args,$case,(int)$vehicle['vehiculo_id']]);
            $vehicle['propietarios']=$this->rows("SELECT pv.id,pv.tipo_propietario,pv.razon_social,p.nombres,p.apellido_paterno,p.apellido_materno FROM propietario_vehiculo_activos pv JOIN involucrados_vehiculos_activos iv ON iv.id=pv.vehiculo_inv_id AND iv.accidente_id=pv.accidente_id JOIN accidentes_activos a ON a.id=pv.accidente_id LEFT JOIN personas p ON p.id=pv.propietario_persona_id WHERE ($scope) AND a.id=? AND iv.id=? ORDER BY pv.id LIMIT 51",[...$args,$case,$record]);
        }
        return $vehicle;
    }
    public function pericial(Actor $actor,int $case,int $personId,?int $record):array {
        [$scope,$args]=$this->scoped($actor);$filter=$record===null?'':' AND d.id=?';$params=[...$args,$case,$personId];if($record!==null)$params[]=$record;
        $rows=$this->rows("SELECT d.id,d.numero_pericial FROM documento_occiso_activos d JOIN accidentes_activos a ON a.id=d.accidente_id WHERE ($scope) AND a.id=? AND d.persona_id=? $filter ORDER BY d.id LIMIT 51",$params);
        if($record!==null&&count($rows)!==1)throw new RuntimeException('Documento no disponible.');return $rows;
    }
    public function reniec(Actor $actor,int $case,int $personRecord,?int $office):array {
        [$scope,$args]=$this->scoped($actor);$filter=$office===null?'':' AND o.id=?';$params=[...$args,$case,$personRecord];if($office!==null)$params[]=$office;
        $rows=$this->rows("SELECT o.id FROM oficios_activos o JOIN accidentes_activos a ON a.id=o.accidente_id JOIN involucrados_personas_activos ip ON ip.id=o.involucrado_persona_id AND ip.accidente_id=a.id JOIN oficio_defuncion_reniec d ON d.oficio_id=o.id WHERE ($scope) AND a.id=? AND ip.id=? $filter ORDER BY o.id LIMIT 51",$params);
        if($office!==null&&count($rows)!==1)throw new RuntimeException('Oficio origen no disponible.');
        if(count($rows)!==1)return ['opciones'=>$rows];
        $office=(int)$rows[0]['id'];$fields=['nombre_padre','nombre_madre','lugar_fallecimiento','fecha_fallecimiento','hora_fallecimiento','causa_muerte','agente_causante','certificado_defuncion_numero','certificado_necropsia_numero','adjuntos'];$select=[];
        foreach($fields as $field){$expr="JSON_EXTRACT(d.datos,'$.".$field."')";if($this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql')$expr='JSON_UNQUOTE('.$expr.')';$select[]=$expr.' AS '.$field;}
        $data=$this->rows('SELECT '.implode(',',$select)." FROM oficios_activos o JOIN accidentes_activos a ON a.id=o.accidente_id JOIN involucrados_personas_activos ip ON ip.id=o.involucrado_persona_id AND ip.accidente_id=a.id JOIN oficio_defuncion_reniec d ON d.oficio_id=o.id WHERE ($scope) AND a.id=? AND ip.id=? AND o.id=?",[...$args,$case,$personRecord,$office]);
        if(count($data)!==1)throw new RuntimeException('Datos no disponibles.');return ['oficio_id'=>$office,'datos'=>$data[0]];
    }
}
