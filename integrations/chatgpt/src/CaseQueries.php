<?php
declare(strict_types=1);
namespace Uiat\Connector;
use PDO;
use RuntimeException;

/** Whitelisted fields only. Caller identity comes from OAuthCore, not tool parameters. */
final class CaseQueries
{
    public function __construct(private PDO $pdo,private bool $cameraCaseContext=false,private bool $cameraYearName=false) {}
    /** Public catalog lookup only after prepare() has authorized the case. No current-year guessing. */
    public function nombreDelAno(int $year): array {
        if($year<1901||$year>2155)throw new RuntimeException('Año de emisión inválido.');
        if(!$this->cameraYearName)return ['estado'=>'no_habilitado','nombres'=>[]];
        $rows=$this->rows("SELECT DISTINCT nombre FROM oficio_oficial_ano WHERE anio=? AND TRIM(nombre)<>'' ORDER BY nombre LIMIT 2",[$year]);
        $names=array_values(array_unique(array_map(static fn($row)=>trim((string)$row['nombre']),$rows)));
        return ['estado'=>count($names)===1?'registrado':(count($names)>1?'ambiguo':'no_registrado'),'nombres'=>$names];
    }
    private function scope(Actor $a): array {
        if ($a->id<=0) return ['1=0',[]];
        return match ($a->role) {
            'admin'=>['1=1',[]],
            'jefe_emi'=>['a.responsable_id=?',[$a->id]],
            'adjunto'=>['EXISTS (SELECT 1 FROM expediente_colaboradores ec WHERE ec.accidente_id=a.id AND ec.usuario_id=? AND ec.revocado_en IS NULL)',[$a->id]],
            'secretaria','administracion'=>["EXISTS (SELECT 1 FROM expediente_transferencias et WHERE et.accidente_id=a.id AND et.destino_id=? AND et.tipo='archivo' AND et.estado='aceptada')",[$a->id]],
            default=>['1=0',[]],
        };
    }
    private function rows(string $sql,array $params): array {
        $s=$this->pdo->prepare($sql);
        foreach ($params as $i=>$v)$s->bindValue($i+1,$v,is_int($v)?PDO::PARAM_INT:PDO::PARAM_STR);
        $s->execute();return $s->fetchAll(PDO::FETCH_ASSOC);
    }
    private function id(int $id): void {if ($id<=0)throw new RuntimeException('Identificador inválido.');}
    public function integral(Actor $actor,int $id,string $section,int $limit=20,int $after=0): array {
        $this->ficha($actor,$id); // Fail closed even for an empty section or a foreign cursor.
        [$scope,$params]=$this->scope($actor);
        require_once __DIR__.'/IntegralQueries.php';
        return (new IntegralQueries($this->pdo))->read($id,$section,$limit,$after,$scope,$params);
    }
    public function ficha(Actor $actor,int $id): array {
        $this->id($id);[$scope,$params]=$this->scope($actor);
        $rows=$this->rows("SELECT a.id,a.sidpol,a.registro_sidpol,a.fecha_accidente,a.estado,CASE WHEN a.lugar IS NULL OR TRIM(a.lugar)='' THEN 1 ELSE 0 END AS lugar_sin_registrar FROM accidentes_activos a WHERE ($scope) AND a.id=?",[...$params,$id]);
        if (!$rows)throw new RuntimeException('Expediente no disponible.');
        $rows[0]['fuente']=['expediente_id'=>$id,'seccion'=>'ficha','campos'=>['sidpol','registro_sidpol','fecha_accidente','estado','lugar_sin_registrar']];
        return $rows[0];
    }
    /** Explicit draft-only projection: actual location, never an unscoped table fetch. */
    public function fichaParaBorrador(Actor $actor,int $id): array {
        $this->id($id);[$scope,$params]=$this->scope($actor);
        $rows=$this->rows("SELECT a.id,a.sidpol,a.registro_sidpol,a.fecha_accidente,a.lugar FROM accidentes_activos a WHERE ($scope) AND a.id=?",[...$params,$id]);
        if (!$rows)throw new RuntimeException('Expediente no disponible.');
        $source=$rows[0];
        $source['fuente']=['expediente_id'=>$id,'seccion'=>'ficha','campos'=>['sidpol','registro_sidpol','fecha_accidente','lugar']];
        if($this->cameraCaseContext) {
            // The final lookup and each multi-value lookup include the same case authorization.
            $context=$this->rows("SELECT a.referencia,f.nombre AS fiscalia FROM accidentes_activos a LEFT JOIN fiscalia f ON f.id=a.fiscalia_id WHERE ($scope) AND a.id=?",[...$params,$id]);
            if(!$context)throw new RuntimeException('Expediente no disponible.');
            $source['referencia']=$context[0]['referencia'];$source['fiscalia']=$context[0]['fiscalia'];
            foreach(['modalidad','consecuencia'] as $kind) {
                // Identifiers are fixed internal constants, never tool input.
                $sql=$kind==='modalidad'
                    ? "SELECT DISTINCT c.id,c.nombre FROM accidente_modalidad_activos x JOIN modalidad_accidente c ON c.id=x.modalidad_id JOIN accidentes_activos a ON a.id=x.accidente_id WHERE ($scope) AND a.id=? ORDER BY c.id LIMIT 51"
                    : "SELECT DISTINCT c.id,c.nombre FROM accidente_consecuencia_activos x JOIN consecuencia_accidente c ON c.id=x.consecuencia_id JOIN accidentes_activos a ON a.id=x.accidente_id WHERE ($scope) AND a.id=? ORDER BY c.id LIMIT 51";
                $items=$this->rows($sql,[...$params,$id]);
                if(count($items)>50)throw new RuntimeException('Catálogo del expediente demasiado extenso.');
                $names=array_values(array_filter(array_map(static fn($r)=>trim((string)$r['nombre']),$items),static fn($v)=>$v!==''));
                $source[$kind]=implode('; ',$names);
                $source[$kind.'_registros']=$items;
            }
            $source['fuente']['campos']=[...$source['fuente']['campos'],'referencia','fiscalia','modalidad','consecuencia'];
            $source['fuentes_contexto']=['referencia'=>'accidentes_activos.referencia','fiscalia'=>'fiscalia.nombre mediante accidentes_activos.fiscalia_id','modalidad'=>'accidente_modalidad_activos y modalidad_accidente','consecuencia'=>'accidente_consecuencia_activos y consecuencia_accidente'];
        }
        return $source;
    }
    private function searchQuery(string $query): array {
        if (!preg_match("~^[\\p{L}\\p{M}\\p{N} ./'()\\-]{3,100}$~uD",$query) || trim($query)!==$query)throw new RuntimeException('Búsqueda inválida.');
        $tokens=preg_split('/\\s+/u',$query,-1,PREG_SPLIT_NO_EMPTY);
        if (!$tokens || count($tokens)>8 || !preg_match('/[\\p{L}\\p{N}]/u',$query))throw new RuntimeException('Búsqueda inválida.');
        return $tokens;
    }
    /** Fixed expressions only. utf8mb4_unicode_ci gives accent/case-insensitive matching in MariaDB. */
    private function searchText(string $expression): string {
        return $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'
            ? "CONVERT($expression USING utf8mb4) COLLATE utf8mb4_unicode_ci" : "LOWER($expression)";
    }
    private function searchLike(string $value,bool $prefix=false): string {
        $value=strtr($value,['!'=>'!!','%'=>'!%','_'=>'!_']);
        return ($prefix?'':'%').$value.'%';
    }
    private function searchWords(array $expressions,array $tokens): array {
        $sql=[];$params=[];
        foreach($tokens as $token){$one=[];foreach($expressions as $expression){$one[]=$this->searchText($expression)." LIKE ".$this->searchText('?')." ESCAPE '!'";$params[]=$this->searchLike($token);}$sql[]='('.implode(' OR ',$one).')';}
        return [implode(' AND ',$sql),$params];
    }
    private function searchCriterion(string $type,string $query): array {
        $tokens=$this->searchQuery($query);
        if($type==='sidpol')return ["(a.sidpol LIKE ? ESCAPE '!' OR a.registro_sidpol LIKE ? ESCAPE '!')",[$this->searchLike($query,true),$this->searchLike($query,true)]];
        if($type==='persona'){
            [$words,$params]=$this->searchWords(['p.nombres','p.apellido_paterno','p.apellido_materno'],$tokens);
            return ["EXISTS (SELECT 1 FROM involucrados_personas_activos ip JOIN personas p ON p.id=ip.persona_id WHERE ip.accidente_id=a.id AND ($words))",$params];
        }
        if($type==='placa'){
            $plate=str_replace([' ','-'],'',$query);
            if(!preg_match('/^[a-zA-Z0-9]{3,15}$/D',$plate))throw new RuntimeException('Placa inválida.');
            $column=$this->searchText("REPLACE(REPLACE(v.placa,'-',''),' ','')");$parameter=$this->searchText('?');
            return ["EXISTS (SELECT 1 FROM involucrados_vehiculos_activos iv JOIN vehiculos v ON v.id=iv.vehiculo_id WHERE iv.accidente_id=a.id AND $column LIKE $parameter ESCAPE '!')",[$this->searchLike($plate,true)]];
        }
        if($type==='distrito'){
            [$words,$params]=$this->searchWords(['u.nombre'],$tokens);
            return ["EXISTS (SELECT 1 FROM ubigeo_distrito u WHERE u.cod_dep=a.cod_dep AND u.cod_prov=a.cod_prov AND u.cod_dist=a.cod_dist AND ($words))",$params];
        }
        if($type==='comisaria'){
            [$words,$params]=$this->searchWords(['c.nombre'],$tokens);
            return ["EXISTS (SELECT 1 FROM comisarias c WHERE c.id=a.comisaria_id AND ($words))",$params];
        }
        if($type==='todos'){
            $sql=[];$params=[];
            foreach(['sidpol','persona','placa','distrito','comisaria'] as $kind){
                // Non-plate text still searches all other categories.
                if($kind==='placa'&&!preg_match('/^[a-zA-Z0-9]{3,15}$/D',str_replace([' ','-'],'',$query)))continue;
                [$part,$bound]=$this->searchCriterion($kind,$query);$sql[]='('.$part.')';$params=[...$params,...$bound];
            }
            return ['('.implode(' OR ',$sql).')',$params];
        }
        throw new RuntimeException('Tipo de búsqueda inválido.');
    }
    /** One row per authorized active case. No matched names, plates or search text in the response/audit. */
    public function buscar(Actor $actor,string $query,int $limit=20,int $after=0,string $type='sidpol',array $filters=[]): array {
        if($limit<1||$limit>50||$after<0||array_diff(array_keys($filters),['sidpol','persona','placa','distrito','comisaria']))throw new RuntimeException('Búsqueda inválida.');
        [$scope,$params]=$this->scope($actor);[$criterion,$bound]=$this->searchCriterion($type,$query);
        $where=["($scope)",'a.id>?',"($criterion)"];$params=[...$params,$after,...$bound];
        foreach($filters as $key=>$value){
            if(!is_string($value))throw new RuntimeException('Filtro inválido.');
            [$part,$bound]=$this->searchCriterion($key,$value);$where[]='('.$part.')';$params=[...$params,...$bound];
        }
        $params[]=$limit+1;
        $rows=$this->rows('SELECT a.id,a.sidpol,a.registro_sidpol,a.fecha_accidente,a.estado FROM accidentes_activos a WHERE '.implode(' AND ',$where).' ORDER BY a.id LIMIT ?',$params);
        return $this->page($rows,$limit);
    }
    private function page(array $rows,int $limit): array {
        $more=count($rows)>$limit;if($more)array_pop($rows);
        return ['registros'=>$rows,'siguiente_id'=>$more?(int)end($rows)['id']:null];
    }
    public function participantes(Actor $actor,int $id,int $limit=20,int $after=0): array {
        $this->ficha($actor,$id);
        if ($limit<1 || $limit>50 || $after<0)throw new RuntimeException('Paginación inválida.');
        [$scope,$params]=$this->scope($actor);
        // Authorization is part of the final query, not only a prior existence check.
        $rows=$this->rows("SELECT ip.id,p.nombres,p.apellido_paterno,p.apellido_materno,r.Nombre AS rol
            FROM involucrados_personas_activos ip JOIN accidentes_activos a ON a.id=ip.accidente_id
            JOIN personas p ON p.id=ip.persona_id LEFT JOIN participacion_persona r ON r.Id=ip.rol_id
            WHERE ($scope) AND a.id=? AND ip.id>? ORDER BY ip.id LIMIT ?",[...$params,$id,$after,$limit+1]);
        foreach($rows as &$row) $row['fuente']=['expediente_id'=>$id,'registro_id'=>(int)$row['id'],'seccion'=>'participantes','campos'=>['nombres','apellido_paterno','apellido_materno','rol']];
        return $this->page($rows,$limit);
    }
    public function diligencias(Actor $actor,int $id,int $limit=20,int $after=0): array {
        $this->ficha($actor,$id);
        if ($limit<1 || $limit>50 || $after<0)throw new RuntimeException('Paginación inválida.');
        [$scope,$params]=$this->scope($actor);
        $rows=$this->rows("SELECT d.id,d.estado,t.nombre AS tipo FROM diligencias_pendientes_activos d
            JOIN accidentes_activos a ON a.id=d.accidente_id LEFT JOIN tipo_diligencia t ON t.id=d.tipo_diligencia_id
            WHERE ($scope) AND a.id=? AND d.id>? ORDER BY d.id LIMIT ?",[...$params,$id,$after,$limit+1]);
        foreach($rows as &$row)$row['fuente']=['expediente_id'=>$id,'registro_id'=>(int)$row['id'],'seccion'=>'diligencias','campos'=>['estado','tipo']];
        return $this->page($rows,$limit);
    }
    public function faltantes(Actor $actor,int $id): array {
        $f=$this->ficha($actor,$id);$empty=[];
        foreach(['fecha_accidente','registro_sidpol'] as $field) {
            if ($f[$field]===null || trim((string)$f[$field])==='')$empty[]=['campo'=>$field,'hallazgo'=>'No registrado','fuente'=>['expediente_id'=>$id,'seccion'=>'ficha','campo'=>$field]];
        }
        if ((int)$f['lugar_sin_registrar']===1)$empty[]=['campo'=>'lugar','hallazgo'=>'No registrado','fuente'=>['expediente_id'=>$id,'seccion'=>'ficha','campo'=>'lugar']];
        return ['criterio'=>'campos-basicos-1','campos_sin_registrar'=>$empty,
            'documentos_evaluados'=>false,'requisitos_institucionales_evaluados'=>false,
            'limite'=>'Un campo vacío no prueba que el hecho no ocurrió. No es una evaluación de completitud ni de requisitos legales. Las diligencias se consultan por separado.'];
    }
}
