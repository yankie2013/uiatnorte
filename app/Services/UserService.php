<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\UserRepository;
use InvalidArgumentException;

final class UserService
{
    public function __construct(private UserRepository $repository)
    {
    }

    public function defaultData(): array
    {
        return [
            'nombre' => '',
            'email' => '',
            'rol' => 'jefe_emi',
            'cip' => '', 'grado' => '', 'cargo' => '', 'unidad' => 'DEPIAT',
        ];
    }

    public function allowedRolesFor(string $actorRole): array
    {
        return match ($actorRole) {
            'kayiosama' => [],
            'admin' => ['jefe_emi', 'adjunto', 'secretaria', 'guardia', 'admin'],
            default => [],
        };
    }

    public function create(array $input, string $actorRole): int
    {
        $allowed = $this->allowedRolesFor($actorRole);
        if ($allowed === []) {
            throw new InvalidArgumentException('Acceso denegado. Solo el administrador puede registrar usuarios.');
        }

        $nombre = trim((string) ($input['nombre'] ?? ''));
        $email = trim((string) ($input['email'] ?? ''));
        $cip = \App\Support\PasswordPolicy::cip((string)($input['cip'] ?? ''));
        $rol = (string) ($input['rol'] ?? 'viewer');

        if ($nombre === '') {
            throw new InvalidArgumentException('Falta el nombre.');
        }
        if ($email === '') {
            throw new InvalidArgumentException('Falta el email.');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Email invalido.');
        }
        if (!in_array($rol, $allowed, true)) {
            throw new InvalidArgumentException('Perfil no permitido.');
        }
        if ($this->repository->findByEmail($email) !== null) {
            throw new InvalidArgumentException('El email ya esta registrado.');
        }

        if ($this->repository->findByIdentifier($cip) !== null) throw new InvalidArgumentException('El CIP ya está registrado.');

        return $this->repository->create([
            'email' => $email,
            'nombre' => $nombre,
            'rol' => $rol,
            'pass_hash' => password_hash($cip, PASSWORD_DEFAULT),
            'grado' => trim((string)($input['grado'] ?? '')),
            'cip' => $cip,
            'cargo' => trim((string)($input['cargo'] ?? '')),
            'unidad' => trim((string)($input['unidad'] ?? 'DEPIAT')),
        ]);
    }
}
