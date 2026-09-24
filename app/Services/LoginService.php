<?php
declare(strict_types=1);
namespace App\Services;
use App\Repositories\UserRepository;
use App\Support\PasswordPolicy;
use PDO;
use RuntimeException;
use Throwable;
final class LoginService
{
    public function __construct(private PDO $pdo) {}
    public function authenticate(string $identifier, string $password): array
    {
        $user=(new UserRepository($this->pdo))->findByIdentifier(trim($identifier));
        if(!$user || !(int)$user['activo'] || !password_verify($password,$user['pass_hash']))throw new RuntimeException('CIP, correo o contraseña incorrectos.');
        return $user;
    }
    public static function beginSession(array $user): void
    {
        session_regenerate_id(true);
        unset($_SESSION['user'],$_SESSION['id'],$_SESSION['rol'],$_SESSION['password_setup']);
        if((int)$user['must_change_password']) {
            $_SESSION['password_setup']=['id'=>(int)$user['id'],'credential'=>hash('sha256',$user['pass_hash']),'expires'=>time()+900];
            return;
        }
        $_SESSION['user']=array_intersect_key($user,array_flip(['id','nombre','grado','email','rol','auth_version']));
        $_SESSION['id']=(int)$user['id'];$_SESSION['rol']=$user['rol'];
    }
    public static function pendingSetup(): ?array
    {
        $pending=$_SESSION['password_setup']??null;
        if(!is_array($pending) || (int)($pending['expires']??0)<=time()) {unset($_SESSION['password_setup']);return null;}
        return $pending;
    }
    public function completeSetup(array $pending,string $password,string $confirmation): array
    {
        if($password!==$confirmation)throw new RuntimeException('Las contraseñas no coinciden.');
        $this->pdo->beginTransaction();
        try {
            $s=$this->pdo->prepare('SELECT * FROM usuarios WHERE id=? FOR UPDATE');$s->execute([(int)($pending['id']??0)]);$user=$s->fetch();
            if(!$user || !$user['activo'] || !$user['must_change_password'] || (int)($pending['expires']??0)<=time() || !hash_equals(hash('sha256',$user['pass_hash']),(string)($pending['credential']??'')))throw new RuntimeException('El acceso temporal venció o fue restablecido. Vuelve a iniciar sesión.');
            PasswordPolicy::validate($password,(string)$user['cip']);
            if(password_verify($password,$user['pass_hash']))throw new RuntimeException('La nueva contraseña debe ser distinta de la contraseña inicial.');
            $this->pdo->exec('SET @actor_id='.(int)$user['id'].', @rbac_password_change=1');
            $hash=password_hash($password,PASSWORD_DEFAULT);
            $this->pdo->prepare('UPDATE usuarios SET pass_hash=?,must_change_password=0,auth_version=auth_version+1 WHERE id=?')->execute([$hash,$user['id']]);
            $this->pdo->commit();
            $user['must_change_password']=0;$user['auth_version']=(int)$user['auth_version']+1;$user['pass_hash']=$hash;
            return $user;
        }catch(Throwable $e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $e;}
        finally{$this->pdo->exec('SET @rbac_password_change=NULL, @actor_id=0');}
    }
}
