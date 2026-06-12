<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;
use Throwable;

final class ActaVisualizacionRepository
{
    public function __construct(private PDO $pdo) {}

    public function listByAccidente(int $accidenteId): array
    {
        $st = $this->pdo->prepare(
            "SELECT av.*,
                    (SELECT COUNT(*) FROM actas_visualizacion_participantes p WHERE p.acta_visualizacion_id=av.id) participantes_total,
                    (SELECT COUNT(*) FROM actas_visualizacion_documentos d WHERE d.acta_visualizacion_id=av.id) documentos_total,
                    (SELECT COUNT(*) FROM actas_visualizacion_discos d WHERE d.acta_visualizacion_id=av.id) discos_total
               FROM actas_visualizacion av
              WHERE av.accidente_id=?
           ORDER BY av.fecha_visualizacion DESC, av.hora_inicio DESC, av.id DESC"
        );
        $st->execute([$accidenteId]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function find(int $id): ?array
    {
        $st = $this->pdo->prepare('SELECT * FROM actas_visualizacion WHERE id=? LIMIT 1');
        $st->execute([$id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if (!$row) return null;
        $row['participantes'] = $this->children('actas_visualizacion_participantes', 'acta_visualizacion_id', $id);
        $row['documentos'] = $this->children('actas_visualizacion_documentos', 'acta_visualizacion_id', $id);
        $row['discos'] = $this->children('actas_visualizacion_discos', 'acta_visualizacion_id', $id);
        foreach ($row['discos'] as &$disco) {
            $disco['archivos'] = $this->children('actas_visualizacion_archivos', 'disco_id', (int) $disco['id']);
            foreach ($disco['archivos'] as &$archivo) {
                $archivo['descripciones'] = $this->children('actas_visualizacion_descripciones', 'archivo_id', (int) $archivo['id']);
            }
            unset($archivo);
        }
        unset($disco);
        return $row;
    }

    public function accident(int $accidenteId): ?array
    {
        $st = $this->pdo->prepare(
            "SELECT a.id, a.registro_sidpol, a.fecha_accidente, a.lugar, a.referencia,
                    ud.nombre accidente_distrito,
                    fa.nombre fiscalia,
                    TRIM(CONCAT_WS(' ', f.nombres, f.apellido_paterno, f.apellido_materno)) fiscal,
                    f.cargo fiscal_cargo, f.telefono fiscal_telefono
               FROM accidentes a
          LEFT JOIN ubigeo_distrito ud ON ud.cod_dep=a.cod_dep AND ud.cod_prov=a.cod_prov AND ud.cod_dist=a.cod_dist
          LEFT JOIN fiscalia fa ON fa.id=a.fiscalia_id
          LEFT JOIN fiscales f ON f.id=a.fiscal_id
              WHERE a.id=? LIMIT 1"
        );
        $st->execute([$accidenteId]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function participants(int $accidenteId): array
    {
        $sql = "SELECT fuente, fuente_id, nombre, condicion FROM (
                  SELECT CONVERT('INVOLUCRADO' USING utf8mb4) COLLATE utf8mb4_general_ci fuente, ip.id fuente_id,
                         CONVERT(TRIM(CONCAT_WS(' ',p.nombres,p.apellido_paterno,p.apellido_materno)) USING utf8mb4) COLLATE utf8mb4_general_ci nombre,
                         CONVERT(CONCAT(COALESCE(pp.Nombre,'Involucrado'), IF(ip.lesion IS NULL OR ip.lesion='', '', CONCAT(' - ',ip.lesion))) USING utf8mb4) COLLATE utf8mb4_general_ci condicion
                    FROM involucrados_personas ip JOIN personas p ON p.id=ip.persona_id
               LEFT JOIN participacion_persona pp ON pp.Id=ip.rol_id WHERE ip.accidente_id=?
                  UNION ALL
                  SELECT CONVERT('FAMILIAR' USING utf8mb4) COLLATE utf8mb4_general_ci, ff.id, CONVERT(TRIM(CONCAT_WS(' ',p.nombres,p.apellido_paterno,p.apellido_materno)) USING utf8mb4) COLLATE utf8mb4_general_ci,
                         CONVERT(CONCAT('Familiar de fallecido',IF(ff.parentesco IS NULL OR ff.parentesco='','',CONCAT(' - ',ff.parentesco))) USING utf8mb4) COLLATE utf8mb4_general_ci
                    FROM familiar_fallecido ff JOIN personas p ON p.id=ff.familiar_persona_id WHERE ff.accidente_id=?
                  UNION ALL
                  SELECT CONVERT('PROPIETARIO' USING utf8mb4) COLLATE utf8mb4_general_ci, pv.id,
                         CONVERT(CASE WHEN pv.tipo_propietario='JURIDICA' THEN pv.razon_social ELSE TRIM(CONCAT_WS(' ',p.nombres,p.apellido_paterno,p.apellido_materno)) END USING utf8mb4) COLLATE utf8mb4_general_ci,
                         CONVERT(CASE WHEN pv.tipo_propietario='JURIDICA' THEN 'Propietario - Persona juridica' ELSE 'Propietario' END USING utf8mb4) COLLATE utf8mb4_general_ci
                    FROM propietario_vehiculo pv LEFT JOIN personas p ON p.id=pv.propietario_persona_id WHERE pv.accidente_id=?
                  UNION ALL
                  SELECT CONVERT('ABOGADO' USING utf8mb4) COLLATE utf8mb4_general_ci, ab.id, CONVERT(TRIM(CONCAT_WS(' ',ab.nombres,ab.apellido_paterno,ab.apellido_materno)) USING utf8mb4) COLLATE utf8mb4_general_ci, CONVERT('Abogado' USING utf8mb4) COLLATE utf8mb4_general_ci
                    FROM abogados ab WHERE ab.accidente_id=?
                ) participantes WHERE nombre IS NOT NULL AND nombre<>'' ORDER BY condicion,nombre";
        $st = $this->pdo->prepare($sql);
        $st->execute([$accidenteId, $accidenteId, $accidenteId, $accidenteId]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function cameraDocuments(int $accidenteId): array
    {
        $st = $this->pdo->prepare(
            "SELECT CONVERT('OFICIO' USING utf8mb4) COLLATE utf8mb4_general_ci fuente, o.id fuente_id,
                    CONVERT(CONCAT('Oficio ',o.numero,'/',o.anio,' - ',COALESCE(oa.nombre,''),' - ',COALESCE(o.motivo,'')) USING utf8mb4) COLLATE utf8mb4_general_ci descripcion
               FROM oficios o LEFT JOIN oficio_asunto oa ON oa.id=o.asunto_id
              WHERE o.accidente_id=? AND (LOWER(CONVERT(CONCAT_WS(' ',oa.nombre,oa.detalle,o.motivo,o.referencia_texto) USING utf8mb4)) COLLATE utf8mb4_general_ci) REGEXP 'camara|video|vigilancia'
              UNION ALL
             SELECT CONVERT('RESPUESTA' USING utf8mb4) COLLATE utf8mb4_general_ci, dr.id,
                    CONVERT(CONCAT('Respuesta ',COALESCE(dr.numero_documento,''),' - ',COALESCE(dr.asunto,''),' - ',COALESCE(dr.entidad_persona,'')) USING utf8mb4) COLLATE utf8mb4_general_ci
               FROM documentos_recibidos dr LEFT JOIN oficios o ON o.id=dr.referencia_oficio_id LEFT JOIN oficio_asunto oa ON oa.id=o.asunto_id
              WHERE dr.accidente_id=? AND (LOWER(CONVERT(CONCAT_WS(' ',dr.asunto,dr.tipo_documento,dr.contenido,oa.nombre,oa.detalle,o.motivo) USING utf8mb4)) COLLATE utf8mb4_general_ci) REGEXP 'camara|video|vigilancia'
           ORDER BY fuente, fuente_id DESC"
        );
        $st->execute([$accidenteId, $accidenteId]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function save(?int $id, array $main, array $participants, array $documents, array $disks): int
    {
        $this->pdo->beginTransaction();
        try {
            if ($id) {
                $st = $this->pdo->prepare('UPDATE actas_visualizacion SET fecha_visualizacion=?,hora_inicio=?,observaciones=?,estado=? WHERE id=?');
                $st->execute([$main['fecha_visualizacion'],$main['hora_inicio'],$main['observaciones'],$main['estado'],$id]);
                $this->clearChildren($id);
            } else {
                $st = $this->pdo->prepare('INSERT INTO actas_visualizacion(accidente_id,fecha_visualizacion,hora_inicio,observaciones,estado) VALUES(?,?,?,?,?)');
                $st->execute([$main['accidente_id'],$main['fecha_visualizacion'],$main['hora_inicio'],$main['observaciones'],$main['estado']]);
                $id = (int) $this->pdo->lastInsertId();
            }
            $participantSt = $this->pdo->prepare('INSERT INTO actas_visualizacion_participantes(acta_visualizacion_id,fuente,fuente_id,nombre,condicion) VALUES(?,?,?,?,?)');
            foreach ($participants as $p) $participantSt->execute([$id,$p['fuente'],$p['fuente_id'],$p['nombre'],$p['condicion']]);
            $documentSt = $this->pdo->prepare('INSERT INTO actas_visualizacion_documentos(acta_visualizacion_id,fuente,fuente_id,descripcion) VALUES(?,?,?,?)');
            foreach ($documents as $d) $documentSt->execute([$id,$d['fuente'],$d['fuente_id'],$d['descripcion']]);
            $diskSt = $this->pdo->prepare('INSERT INTO actas_visualizacion_discos(acta_visualizacion_id,numero,marca,numero_serie,observaciones) VALUES(?,?,?,?,?)');
            $fileSt = $this->pdo->prepare('INSERT INTO actas_visualizacion_archivos(disco_id,nombre_archivo,tipo_archivo,peso,duracion,observaciones) VALUES(?,?,?,?,?,?)');
            $descriptionSt = $this->pdo->prepare('INSERT INTO actas_visualizacion_descripciones(archivo_id,orden,tiempo,detalle,captura_path) VALUES(?,?,?,?,?)');
            foreach ($disks as $index => $disk) {
                $diskSt->execute([$id,$index+1,$disk['marca'],$disk['numero_serie'],$disk['observaciones']]);
                $diskId = (int) $this->pdo->lastInsertId();
                foreach ($disk['archivos'] as $file) {
                    $fileSt->execute([$diskId,$file['nombre_archivo'],$file['tipo_archivo'],$file['peso'],$file['duracion'],$file['observaciones']]);
                    $fileId = (int) $this->pdo->lastInsertId();
                    foreach ($file['descripciones'] as $descriptionIndex => $description) {
                        $descriptionSt->execute([$fileId,$descriptionIndex+1,$description['tiempo'],$description['detalle'],$description['captura_path']]);
                    }
                }
            }
            $this->pdo->commit();
            return $id;
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $e;
        }
    }

    public function delete(int $id): void
    {
        $this->pdo->beginTransaction();
        try {
            $this->clearChildren($id);
            $this->pdo->prepare('DELETE FROM actas_visualizacion WHERE id=?')->execute([$id]);
            $this->pdo->commit();
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $e;
        }
    }

    private function clearChildren(int $id): void
    {
        $this->pdo->prepare('DELETE x FROM actas_visualizacion_descripciones x JOIN actas_visualizacion_archivos f ON f.id=x.archivo_id JOIN actas_visualizacion_discos d ON d.id=f.disco_id WHERE d.acta_visualizacion_id=?')->execute([$id]);
        $this->pdo->prepare('DELETE f FROM actas_visualizacion_archivos f JOIN actas_visualizacion_discos d ON d.id=f.disco_id WHERE d.acta_visualizacion_id=?')->execute([$id]);
        foreach (['actas_visualizacion_discos','actas_visualizacion_documentos','actas_visualizacion_participantes'] as $table) {
            $this->pdo->prepare("DELETE FROM {$table} WHERE acta_visualizacion_id=?")->execute([$id]);
        }
    }

    private function children(string $table, string $column, int $id): array
    {
        $st = $this->pdo->prepare("SELECT * FROM {$table} WHERE {$column}=? ORDER BY id");
        $st->execute([$id]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }
}
