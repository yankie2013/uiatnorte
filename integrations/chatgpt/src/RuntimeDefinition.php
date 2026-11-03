<?php
declare(strict_types=1);
namespace Uiat\Connector;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;

/** Two-phase factory: admission runs before any business PDO connection is created. */
final class RuntimeDefinition
{
    public function __construct(private string $directory,private string $rateKey,private \Closure $factory) {
        if(!is_dir($directory)||(fileperms($directory)&0077)!==0||strlen($rateKey)<32)throw new RuntimeException('Admisión privada requerida.');
    }
    public function open(ServerRequestInterface $request): HttpKernel {
        $address=(string)($request->getServerParams()['REMOTE_ADDR']??'');
        if (!FileBudget::consume($this->directory,$address,$this->rateKey))throw new RateLimited('Límite de solicitudes alcanzado.',60);
        $kernel=($this->factory)();
        if(!$kernel instanceof HttpKernel)throw new RuntimeException('Factoría no válida.');
        return $kernel;
    }
}
