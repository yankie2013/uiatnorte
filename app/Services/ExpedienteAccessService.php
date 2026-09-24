<?php
declare(strict_types=1);
namespace App\Services;
use App\Support\Access;
use PDO;
use RuntimeException;
use Throwable;

final class ExpedienteAccessService
{
    public function __construct(private PDO $pdo) {}
    public function change(int $case, string $action, int $target, string $reason): void {
        $actor=Access::id();
        $this->pdo->beginTransaction();
        try {
            $s=$this->pdo->prepare('SELECT * FROM accidentes WHERE id=? FOR UPDATE');$s->execute([$case]);$row=$s->fetch();
            if(!$row)throw new RuntimeException('Expediente no encontrado.');
            $owner=(int)$row['responsable_id'];
            if(!empty($row['eliminado_en']) && $action!=='restaurar')throw new RuntimeException('El expediente está eliminado.');
            if(in_array($action,['verificar_ubicacion','cambiar_estado'],true)) Access::requireEdit($case);
            if(!Access::admin() && !in_array($action,['verificar_ubicacion','cambiar_estado'],true) && ($action!=='aceptar' && ($owner!==$actor || Access::role()!=='jefe_emi')))throw new RuntimeException('Solo el JEFE EMI responsable puede administrar este expediente.');
            $this->pdo->exec('SET @rbac_assignment=1');
            if(in_array($action,['compartir','transferir','reasignar'],true)) {
                $s=$this->pdo->prepare('SELECT rol FROM usuarios WHERE id=? AND activo=1');$s->execute([$target]);$role=$s->fetchColumn();
                if($role!==($action==='compartir'?'adjunto':'jefe_emi'))throw new RuntimeException('Seleccione un usuario activo del perfil correspondiente.');
            }
            switch($action) {
                case 'cambiar_estado':
                    if(!in_array($reason,['Pendiente','Con diligencias','Resuelto','Desestimado'],true))throw new RuntimeException('Estado no válido.');
                    $this->pdo->prepare('UPDATE accidentes SET estado=? WHERE id=?')->execute([$reason,$case]);break;
                case 'verificar_ubicacion':
                    if($row['latitud']===null || $row['longitud']===null)throw new RuntimeException('Registre ambas coordenadas antes de verificar la ubicación.');
                    $this->pdo->prepare('UPDATE accidentes SET ubicacion_verificada=1 WHERE id=?')->execute([$case]);break;
                case 'compartir':
                    $this->pdo->prepare('INSERT INTO expediente_colaboradores(accidente_id,usuario_id,asignado_por) VALUES(?,?,?) ON DUPLICATE KEY UPDATE asignado_por=VALUES(asignado_por),asignado_en=NOW(),revocado_en=NULL')->execute([$case,$target,$actor]);break;
                case 'revocar':
                    $this->pdo->prepare('UPDATE expediente_colaboradores SET revocado_en=NOW() WHERE accidente_id=? AND usuario_id=? AND revocado_en IS NULL')->execute([$case,$target]);break;
                case 'transferir':
                    if($target===$owner)throw new RuntimeException('El destinatario ya es responsable.');
                    if(trim($reason)==='')throw new RuntimeException('Indique el motivo de la transferencia.');
                    $s=$this->pdo->prepare("SELECT id FROM expediente_transferencias WHERE accidente_id=? AND estado='pendiente'");$s->execute([$case]);if($s->fetch())throw new RuntimeException('Ya existe una transferencia pendiente.');
                    $this->pdo->prepare('INSERT INTO expediente_transferencias(accidente_id,origen_id,destino_id,motivo) VALUES(?,?,?,?)')->execute([$case,$owner,$target,$reason]);break;
                case 'aceptar':
                    $s=$this->pdo->prepare("SELECT * FROM expediente_transferencias WHERE accidente_id=? AND estado='pendiente' AND destino_id=? FOR UPDATE");$s->execute([$case,$actor]);$transfer=$s->fetch();
                    if(!$transfer || Access::role()!=='jefe_emi' || (int)$transfer['origen_id']!==$owner)throw new RuntimeException('No existe una transferencia vigente dirigida a usted.');
                    $this->pdo->prepare('UPDATE accidentes SET responsable_id=?,asignado_en=NOW() WHERE id=?')->execute([$actor,$case]);
                    $this->pdo->prepare('UPDATE expediente_colaboradores SET revocado_en=NOW() WHERE accidente_id=? AND revocado_en IS NULL')->execute([$case]);
                    $this->pdo->prepare("UPDATE expediente_transferencias SET estado='aceptada',resuelto_en=NOW() WHERE id=?")->execute([$transfer['id']]);break;
                case 'cancelar_transferencia':
                    $this->pdo->prepare("UPDATE expediente_transferencias SET estado='cancelada',resuelto_en=NOW() WHERE accidente_id=? AND estado='pendiente'")->execute([$case]);break;
                case 'reasignar':
                    if($target===$owner)throw new RuntimeException('El usuario seleccionado ya es responsable de este expediente.');
                    if(!Access::admin() || trim($reason)==='')throw new RuntimeException('La reasignación administrativa requiere motivo y permiso de administrador.');
                    $this->pdo->prepare('UPDATE accidentes SET responsable_id=?,asignado_en=NOW() WHERE id=?')->execute([$target,$case]);
                    $this->pdo->prepare('UPDATE expediente_colaboradores SET revocado_en=NOW() WHERE accidente_id=? AND revocado_en IS NULL')->execute([$case]);
                    $this->pdo->prepare("UPDATE expediente_transferencias SET estado='cancelada',resuelto_en=NOW() WHERE accidente_id=? AND estado='pendiente'")->execute([$case]);break;
                case 'eliminar': case 'restaurar':
                    if(!Access::admin() || trim($reason)==='')throw new RuntimeException('Solo el administrador puede eliminar o restaurar, indicando un motivo.');
                    if($action==='eliminar')$this->pdo->prepare('UPDATE accidentes SET eliminado_en=NOW(),eliminado_por=?,motivo_eliminacion=? WHERE id=?')->execute([$actor,$reason,$case]);
                    else $this->pdo->prepare('UPDATE accidentes SET eliminado_en=NULL,eliminado_por=NULL,motivo_eliminacion=NULL WHERE id=?')->execute([$case]);
                    break;
                default: throw new RuntimeException('Acción desconocida.');
            }
            $this->pdo->prepare("INSERT INTO auditoria(usuario_id,tabla,registro_id,accidente_id,accion,motivo) VALUES(?,'accidentes',?,?,?,?)")->execute([$actor,$case,$case,$action,$reason]);
            $this->pdo->commit();
        } catch(Throwable $e) {if($this->pdo->inTransaction())$this->pdo->rollBack();throw $e;}
        finally {$this->pdo->exec('SET @rbac_assignment=NULL');}
    }
}
