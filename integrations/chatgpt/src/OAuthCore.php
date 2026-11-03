<?php
declare(strict_types=1);
namespace Uiat\Connector;

use DateInterval;
use DateTimeImmutable;
use League\OAuth2\Server\AuthorizationServer;
use League\OAuth2\Server\CryptKey;
use League\OAuth2\Server\Entities as E;
use League\OAuth2\Server\Entities\Traits as T;
use League\OAuth2\Server\Grant\AuthCodeGrant;
use League\OAuth2\Server\Grant\RefreshTokenGrant;
use League\OAuth2\Server\Repositories as R;
use League\OAuth2\Server\ResourceServer;
use Nyholm\Psr7\Response;
use PDO;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;
use Throwable;

final readonly class Actor
{
    public function __construct(public int $id, public string $role, public int $version) {}
    public function asArray(): array { return ['id'=>$this->id,'rol'=>$this->role]; }
}
final class RateLimited extends RuntimeException {
    public function __construct(string $message,public int $retryAfter=900) {parent::__construct($message);}
}

/** Reads identity metadata only. The caller supplies an isolated/approved read-only connection. */
final class Users
{
    public function __construct(private PDO $pdo) {}
    public function active(int $id): ?Actor {
        $s=$this->pdo->prepare('SELECT id,rol,activo,must_change_password,auth_version FROM usuarios WHERE id=?');
        $s->execute([$id]);$u=$s->fetch(PDO::FETCH_ASSOC);
        if (!$u || (int)$u['activo']!==1 || (int)$u['must_change_password']!==0
            || !in_array($u['rol'],['admin','jefe_emi','adjunto','secretaria','administracion'],true)) return null;
        return new Actor((int)$u['id'],(string)$u['rol'],(int)$u['auth_version']);
    }
    /** Trusted web-session adapter only; never accepts a user id from tool arguments. */
    public function fromSession(array $session): Actor {
        $u=$session['user']??[];
        $actor=$this->active((int)($u['id']??0));
        $started=$session['api_authenticated_at']??0;
        if (!$actor || !isset($u['auth_version']) || $actor->version!==(int)$u['auth_version']
            || !is_int($started) || $started<=0 || $started>time() || time()-$started>=900) throw new RuntimeException('Vuelve a iniciar sesión para autorizar la conexión.');
        return $actor;
    }
}

final class Client implements E\ClientEntityInterface {
    use T\EntityTrait,T\ClientTrait;
    public function __construct(string $id,string $redirect) {
        $this->setIdentifier($id);$this->name='UIAT NORTE — consulta autorizada';$this->redirectUri=$redirect;$this->isConfidential=false;
    }
}
final class Scope implements E\ScopeEntityInterface {
    use T\EntityTrait,T\ScopeTrait;
    public function __construct(string $id='uiat:read') {$this->setIdentifier($id);}
}
final class UserEntity implements E\UserEntityInterface {
    use T\EntityTrait;
    public function __construct(int $id) {$this->setIdentifier((string)$id);}
}
final class Code implements E\AuthCodeEntityInterface {
    use T\EntityTrait,T\TokenEntityTrait,T\AuthCodeTrait;
}
final class Token implements E\AccessTokenEntityInterface {
    use T\EntityTrait,T\TokenEntityTrait,T\AccessTokenTrait;
    public function __construct(private string $issuer,private string $resource) {}
    /** Audience is the MCP resource, not a shared administrative client identity. */
    public function toString(): string {
        $this->initJwtConfiguration();
        return $this->jwtConfiguration->builder()->issuedBy($this->issuer)
            ->permittedFor($this->resource)->identifiedBy($this->getIdentifier())
            ->issuedAt(new DateTimeImmutable())->canOnlyBeUsedAfter(new DateTimeImmutable())
            ->expiresAt($this->getExpiryDateTime())->relatedTo((string)$this->getUserIdentifier())
            ->withClaim('client_id',$this->getClient()->getIdentifier())
            ->withClaim('scopes',$this->getScopes())
            ->getToken($this->jwtConfiguration->signer(),$this->jwtConfiguration->signingKey())->toString();
    }
}
final class Clients implements R\ClientRepositoryInterface {
    public function __construct(private string $id,private string $redirect) {}
    public function getClientEntity(string $clientIdentifier): ?E\ClientEntityInterface {
        return hash_equals($this->id,$clientIdentifier)?new Client($this->id,$this->redirect):null;
    }
    public function validateClient(string $clientIdentifier,?string $clientSecret,?string $grantType): bool {
        return in_array($grantType,['authorization_code','refresh_token'],true) && $this->getClientEntity($clientIdentifier)!==null && ($clientSecret===null || $clientSecret==='');
    }
}
final class Scopes implements R\ScopeRepositoryInterface {
    public function getScopeEntityByIdentifier(string $identifier): ?E\ScopeEntityInterface {return in_array($identifier,['uiat:read','offline_access'],true)?new Scope($identifier):null;}
    public function finalizeScopes(array $scopes,string $grantType,E\ClientEntityInterface $clientEntity,?string $userIdentifier=null,?string $authCodeId=null): array {
        $names=array_map(fn($s)=>$s->getIdentifier(),$scopes);
        if (!in_array('uiat:read',$names,true) || array_diff($names,['uiat:read','offline_access']) || count($names)!==count(array_unique($names))) throw new RuntimeException('Ámbito no autorizado.');
        return $scopes;
    }
}

/** Separate private OAuth state, not the application's database or a web-accessible file. */
final class State
{
    public bool $allowOffline=false;
    public ?int $familyDeadline=null;
    public ?string $familyId=null;
    public bool $securityRevocation=false;
    public function __construct(public PDO $pdo,public Users $users) {}
    public function createSchema(): void {
        $this->pdo->exec('CREATE TABLE auth_codes(id TEXT PRIMARY KEY,user_id INTEGER,version INTEGER,expires INTEGER,revoked INTEGER DEFAULT 0);
            CREATE TABLE access_tokens(id TEXT PRIMARY KEY,user_id INTEGER,version INTEGER,client TEXT,resource TEXT,expires INTEGER,family_expiry INTEGER,family_id TEXT,token_hash TEXT UNIQUE,revoked INTEGER DEFAULT 0);
            CREATE TABLE refresh_tokens(id TEXT PRIMARY KEY,access_id TEXT,expires INTEGER,token_hash TEXT UNIQUE,revoked INTEGER DEFAULT 0);
            CREATE TABLE budgets(bucket TEXT PRIMARY KEY,window INTEGER,count INTEGER)');
    }
    public function userMatches(int $id,int $version): bool {return $this->users->active($id)?->version===$version;}
    public function revokeUser(int $id): void {
        $s=$this->pdo->prepare('UPDATE refresh_tokens SET revoked=1 WHERE access_id IN (SELECT id FROM access_tokens WHERE user_id=?)');$s->execute([$id]);
        foreach (['access_tokens','auth_codes'] as $table) {
            $s=$this->pdo->prepare("UPDATE $table SET revoked=1 WHERE user_id=?");$s->execute([$id]);
        }
    }
    public function revokeFamily(string $family): void {
        $s=$this->pdo->prepare('UPDATE refresh_tokens SET revoked=1 WHERE access_id IN (SELECT id FROM access_tokens WHERE family_id=?)');$s->execute([$family]);
        $s=$this->pdo->prepare('UPDATE access_tokens SET revoked=1 WHERE family_id=?');$s->execute([$family]);
    }
    public function passwordAttempt(int $user,?int $now=null): void {$this->budget('password:'.$user,900,10,$now);}
    public function budget(string $bucket,int $seconds,int $max,?int $now=null): void {
        $window=intdiv($now??time(),$seconds);$allowed=false;
        $this->pdo->exec('BEGIN IMMEDIATE');
        try {
            $s=$this->pdo->prepare('SELECT window,count FROM budgets WHERE bucket=?');$s->execute([$bucket]);$r=$s->fetch(PDO::FETCH_ASSOC);
            $count=$r&&(int)$r['window']===$window?(int)$r['count']:0;
            if ($count<$max) {
                $s=$this->pdo->prepare('INSERT INTO budgets(bucket,window,count) VALUES(?,?,?) ON CONFLICT(bucket) DO UPDATE SET window=excluded.window,count=excluded.count');$s->execute([$bucket,$window,$count+1]);$allowed=true;
            }
            $this->pdo->exec('COMMIT');
        } catch(Throwable $e) {$this->pdo->exec('ROLLBACK');throw $e;}
        if(!$allowed)throw new RateLimited('Espera antes de volver a intentarlo.',$seconds);
    }
}
final class Codes implements R\AuthCodeRepositoryInterface {
    public function __construct(private State $state) {}
    public function getNewAuthCode(): E\AuthCodeEntityInterface {return new Code();}
    public function persistNewAuthCode(E\AuthCodeEntityInterface $authCodeEntity): void {
        $id=(int)$authCodeEntity->getUserIdentifier();$user=$this->state->users->active($id);
        if (!$user) throw new RuntimeException('Usuario no autorizado.');
        $s=$this->state->pdo->prepare('INSERT INTO auth_codes(id,user_id,version,expires) VALUES(?,?,?,?)');
        $s->execute([$authCodeEntity->getIdentifier(),$id,$user->version,$authCodeEntity->getExpiryDateTime()->getTimestamp()]);
    }
    public function revokeAuthCode(string $codeId): void {
        $s=$this->state->pdo->prepare('UPDATE auth_codes SET revoked=1 WHERE id=?');$s->execute([$codeId]);
    }
    public function isAuthCodeRevoked(string $codeId): bool {
        $s=$this->state->pdo->prepare('SELECT * FROM auth_codes WHERE id=?');$s->execute([$codeId]);$r=$s->fetch(PDO::FETCH_ASSOC);
        return !$r || (int)$r['revoked']===1 || (int)$r['expires']<=time() || !$this->state->userMatches((int)$r['user_id'],(int)$r['version']);
    }
}
final class Refresh implements E\RefreshTokenEntityInterface {use T\EntityTrait,T\RefreshTokenTrait;}
final class Refreshes implements R\RefreshTokenRepositoryInterface {
    public function __construct(private State $state) {}
    public function getNewRefreshToken(): ?E\RefreshTokenEntityInterface {return $this->state->allowOffline?new Refresh():null;}
    public function persistNewRefreshToken(E\RefreshTokenEntityInterface $refreshTokenEntity): void {
        $s=$this->state->pdo->prepare('INSERT INTO refresh_tokens(id,access_id,expires) VALUES(?,?,?)');
        $s->execute([$refreshTokenEntity->getIdentifier(),$refreshTokenEntity->getAccessToken()->getIdentifier(),min($refreshTokenEntity->getExpiryDateTime()->getTimestamp(),$this->state->familyDeadline??time())]);
    }
    public function revokeRefreshToken(string $tokenId): void {
        $s=$this->state->pdo->prepare('UPDATE refresh_tokens SET revoked=1 WHERE id=?');$s->execute([$tokenId]);
    }
    public function isRefreshTokenRevoked(string $tokenId): bool {
        $s=$this->state->pdo->prepare('SELECT r.revoked,r.expires,a.user_id,a.version,a.client,a.family_expiry,a.family_id FROM refresh_tokens r JOIN access_tokens a ON a.id=r.access_id WHERE r.id=?');
        $s->execute([$tokenId]);$r=$s->fetch(PDO::FETCH_ASSOC);
        if(!$r)return true;
        if((int)$r['revoked']===1) {
            // Replay of a previously consumed refresh token closes this user's client connection.
            $this->state->revokeFamily($r['family_id']);$this->state->securityRevocation=true;return true;
        }
        if((int)$r['expires']<=time()||(int)$r['family_expiry']<=time()||!$this->state->userMatches((int)$r['user_id'],(int)$r['version']))return true;
        $this->state->familyDeadline=(int)$r['family_expiry'];$this->state->familyId=$r['family_id'];return false;
    }
}
final class Tokens implements R\AccessTokenRepositoryInterface {
    public function __construct(private State $state,private string $issuer,private string $resource) {}
    public function getNewToken(E\ClientEntityInterface $clientEntity,array $scopes,?string $userIdentifier=null): E\AccessTokenEntityInterface {
        $this->state->allowOffline=in_array('offline_access',array_map(fn($s)=>$s->getIdentifier(),$scopes),true);
        $this->state->familyDeadline??=time()+604800;
        $this->state->familyId??=bin2hex(random_bytes(16));
        $t=new Token($this->issuer,$this->resource);$t->setClient($clientEntity);$t->setUserIdentifier($userIdentifier);
        foreach ($scopes as $s)$t->addScope($s);
        return $t;
    }
    public function persistNewAccessToken(E\AccessTokenEntityInterface $accessTokenEntity): void {
        $id=(int)$accessTokenEntity->getUserIdentifier();$user=$this->state->users->active($id);
        if (!$user)throw new RuntimeException('Usuario no autorizado.');
        $s=$this->state->pdo->prepare('INSERT INTO access_tokens(id,user_id,version,client,resource,expires,family_expiry,family_id) VALUES(?,?,?,?,?,?,?,?)');
        $s->execute([$accessTokenEntity->getIdentifier(),$id,$user->version,$accessTokenEntity->getClient()->getIdentifier(),$this->resource,min($accessTokenEntity->getExpiryDateTime()->getTimestamp(),$this->state->familyDeadline??time()),$this->state->familyDeadline,$this->state->familyId]);
    }
    public function revokeAccessToken(string $tokenId): void {
        $s=$this->state->pdo->prepare('UPDATE access_tokens SET revoked=1 WHERE id=?');$s->execute([$tokenId]);
    }
    public function record(string $id): array|false {
        $s=$this->state->pdo->prepare('SELECT * FROM access_tokens WHERE id=?');$s->execute([$id]);return $s->fetch(PDO::FETCH_ASSOC);
    }
    public function isAccessTokenRevoked(string $tokenId): bool {
        $r=$this->record($tokenId);
        return !$r || (int)$r['revoked']===1 || (int)$r['expires']<=time() || !hash_equals($this->resource,$r['resource'])
            || !$this->state->userMatches((int)$r['user_id'],(int)$r['version']);
    }
}

/** Internal, non-routed prototype. A browser consent/login adapter is required before deployment. */
final class OAuthCore
{
    private AuthorizationServer $authorization;
    private ResourceServer $resourceServer;
    private Tokens $tokens;
    public function __construct(private State $state,private string $issuer,private string $resource,private string $client,string $redirect,string $privateKey,string $publicKey,string $encryptionKey,DateInterval $accessTtl=new DateInterval('PT15M')) {
        foreach ([$issuer,$resource,$redirect] as $url) if (!filter_var($url,FILTER_VALIDATE_URL) || parse_url($url,PHP_URL_SCHEME)!=='https' || parse_url($url,PHP_URL_FRAGMENT)!==null) {
            // parse_url returns null when no fragment exists.
            throw new RuntimeException('URI HTTPS exacta requerida.');
        }
        $this->tokens=new Tokens($state,$issuer,$resource);
        $this->authorization=new AuthorizationServer(new Clients($client,$redirect),$this->tokens,new Scopes(),new CryptKey($privateKey,null,false),$encryptionKey);
        $refreshes=new Refreshes($state);
        $grant=new AuthCodeGrant(new Codes($state),$refreshes,new DateInterval('PT2M'));
        $grant->setRefreshTokenTTL(new DateInterval('P7D'));
        $this->authorization->enableGrantType($grant,$accessTtl);
        $refreshGrant=new RefreshTokenGrant($refreshes);$refreshGrant->setRefreshTokenTTL(new DateInterval('P7D'));
        $this->authorization->enableGrantType($refreshGrant,$accessTtl);
        $this->resourceServer=new ResourceServer($this->tokens,new CryptKey($publicKey,null,false));
    }
    private function resourceMatches(mixed $value): void {
        if (!is_string($value) || !hash_equals($this->resource,$value)) throw new RuntimeException('Recurso no autorizado.');
    }
    public function validateAuthorization(ServerRequestInterface $request): void {
        $q=$request->getQueryParams();$this->resourceMatches($q['resource']??null);
        if ($request->getMethod()!=='GET' || ($q['code_challenge_method']??null)!=='S256'
            || !is_string($q['code_challenge']??null) || !preg_match('/^[A-Za-z0-9_-]{43}$/D',$q['code_challenge'])
            || !is_string($q['state']??null) || strlen($q['state'])<16 || strlen($q['state'])>256) throw new RuntimeException('PKCE S256 y estado requeridos.');
        $this->authorization->validateAuthorizationRequest($request);
    }
    public function authorize(ServerRequestInterface $request,Actor $actor,bool $consent): \Psr\Http\Message\ResponseInterface {
        $this->validateAuthorization($request);
        if (!$this->state->userMatches($actor->id,$actor->version)) throw new RuntimeException('Usuario no autorizado.');
        $a=$this->authorization->validateAuthorizationRequest($request);
        $a->setUser(new UserEntity($actor->id));$a->setAuthorizationApproved($consent);
        $response=$this->authorization->completeAuthorizationRequest($a,new Response());
        return $response->withHeader('Location',$response->getHeaderLine('Location').'&iss='.rawurlencode($this->issuer));
    }
    public function exchange(ServerRequestInterface $request): \Psr\Http\Message\ResponseInterface {
        if ($request->getMethod()!=='POST')throw new RuntimeException('Solo POST.');
        $this->resourceMatches(($request->getParsedBody()??[])['resource']??null);
        // Serialize redemption before the library checks/consumes a code: one success only.
        $this->state->allowOffline=false;$this->state->familyDeadline=null;$this->state->familyId=null;$this->state->securityRevocation=false;
        $this->state->pdo->exec('BEGIN IMMEDIATE');
        try {
            $response=$this->authorization->respondToAccessTokenRequest($request,new Response());
            $issued=json_decode((string)$response->getBody(),true,512,JSON_THROW_ON_ERROR);
            $token=(new \Lcobucci\JWT\Token\Parser(new \Lcobucci\JWT\Encoding\JoseEncoder()))->parse($issued['access_token']);
            $id=$token->claims()->get('jti');
            $s=$this->state->pdo->prepare('UPDATE access_tokens SET token_hash=? WHERE id=?');$s->execute([hash('sha256',$issued['access_token']),$id]);
            if(isset($issued['refresh_token'])) {
                $s=$this->state->pdo->prepare('UPDATE refresh_tokens SET token_hash=? WHERE access_id=?');$s->execute([hash('sha256',$issued['refresh_token']),$id]);
            }
            $this->state->pdo->exec('COMMIT');return $response;
        } catch (Throwable $e) {$this->state->pdo->exec($this->state->securityRevocation?'COMMIT':'ROLLBACK');throw $e;}
    }
    public function authenticate(ServerRequestInterface $request): Actor {
        if (count($request->getHeader('Authorization'))!==1 || !preg_match('/^Bearer [A-Za-z0-9_-]+\.[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+$/iD',$request->getHeaderLine('Authorization'))) throw new RuntimeException('Bearer requerido.');
        $r=$this->resourceServer->validateAuthenticatedRequest($request);
        $id=(string)$r->getAttribute('oauth_access_token_id');$stored=$this->tokens->record($id);
        $raw=preg_replace('/^Bearer\s+/i','',$request->getHeaderLine('Authorization'));
        $parser=new \Lcobucci\JWT\Token\Parser(new \Lcobucci\JWT\Encoding\JoseEncoder());
        $jwt=$parser->parse($raw);
        $claims=$jwt->claims();
        if (!$stored || $stored['client']!==$this->client || $claims->get('iss')!==$this->issuer || $claims->get('aud')!==[$this->resource]
            || $claims->get('client_id')!==$stored['client'] || $claims->get('sub')!==(string)$stored['user_id']
            || !in_array('uiat:read',$r->getAttribute('oauth_scopes'),true)) throw new RuntimeException('Token no autorizado.');
        $actor=$this->state->users->active((int)$stored['user_id']);
        if (!$actor || $actor->version!==(int)$stored['version'])throw new RuntimeException('Usuario no autorizado.');
        return $actor;
    }
    public function requestBudget(Actor $actor): void {$this->state->budget('mcp:'.$actor->id,60,60);}
    public function revoke(ServerRequestInterface $request): void {
        $body=$request->getParsedBody()??[];
        if ($request->getMethod()!=='POST'||($body['client_id']??null)!==$this->client
            ||!is_string($body['token']??null)||strlen($body['token'])>8192)throw new RuntimeException('Revocación no válida.');
        // Match digests of exactly issued tokens, including expired access tokens.
        // Possession can revoke its own family; no parsing/decrypting untrusted tokens.
        $digest=hash('sha256',$body['token']);
        $s=$this->state->pdo->prepare('SELECT family_id FROM access_tokens WHERE token_hash=? AND client=? AND resource=?
            UNION SELECT a.family_id FROM refresh_tokens r JOIN access_tokens a ON a.id=r.access_id WHERE r.token_hash=? AND a.client=? AND a.resource=?');
        $s->execute([$digest,$this->client,$this->resource,$digest,$this->client,$this->resource]);
        $family=$s->fetchColumn();
        if($family!==false) {
            $this->state->pdo->exec('BEGIN IMMEDIATE');
            try {$this->state->revokeFamily($family);$this->state->pdo->exec('COMMIT');}
            catch(Throwable $e){$this->state->pdo->exec('ROLLBACK');throw $e;}
        }
    }
    public function metadata(): array {
        return ['issuer'=>$this->issuer,'authorization_endpoint'=>$this->issuer.'/authorize','token_endpoint'=>$this->issuer.'/token','revocation_endpoint'=>$this->issuer.'/revoke',
            'response_types_supported'=>['code'],'grant_types_supported'=>['authorization_code','refresh_token'],'scopes_supported'=>['uiat:read','offline_access'],
            'token_endpoint_auth_methods_supported'=>['none'],'code_challenge_methods_supported'=>['S256'],'authorization_response_iss_parameter_supported'=>true];
    }
}
