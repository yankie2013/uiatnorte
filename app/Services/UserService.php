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
            'rol' => 'viewer',
        ];
    }

    public function allowedRolesFor(string $actorRole): array
    {
        return match ($actorRole) {
            'kayiosama' => ['viewer', 'editor', 'admin', 'kayiosama'],
            'admin' => ['viewer', 'editor'],
            default => [],
        };
    }

    public function create(array $input, string $actorRole): int
    {
        $allowed = $this->allowedRolesFor($actorRole);
        if ($allowed === []) {
            throw new InvalidArgumentException('Acceso denegado. Solo kayiosama o admin pueden registrar usuarios.');
        }

        $nombre = trim((string) ($input['nombre'] ?? ''));
        $email = trim((string) ($input['email'] ?? ''));
        $clave1 = (string) ($input['clave1'] ?? '');
        $clave2 = (string) ($input['clave2'] ?? '');
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
        if ($clave1 === '' || $clave2 === '') {
            throw new InvalidArgumentException('Debes ingresar la contrasena dos veces.');
        }
        if ($clave1 !== $clave2) {
            throw new InvalidArgumentException('Las contrasenas no coinciden.');
        }
        if (strlen($clave1) < 6) {
            throw new InvalidArgumentException('La contrasena debe tener al menos 6 caracteres.');
        }
        if (!in_array($rol, $allowed, true)) {
            $rol = 'viewer';
        }
        if ($this->repository->findByEmail($email) !== null) {
            throw new InvalidArgumentException('El email ya esta registrado.');
        }

        return $this->repository->create([
            'email' => $email,
            'nombre' => $nombre,
            'rol' => $rol,
            'pass_hash' => password_hash($clave1, PASSWORD_DEFAULT),
        ]);
    }
}
