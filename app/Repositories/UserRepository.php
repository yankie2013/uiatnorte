<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class UserRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function findByEmail(string $email): ?array
    {
        $st = $this->pdo->prepare('SELECT id, email, nombre, rol, pass_hash, activo FROM usuarios WHERE email = ? LIMIT 1');
        $st->execute([$email]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function findByIdentifier(string $identifier): ?array
    {
        $column = preg_match('/^[0-9]+$/D', $identifier) ? 'cip' : 'email';
        $st = $this->pdo->prepare("SELECT id,email,nombre,grado,rol,pass_hash,activo,cip,must_change_password,auth_version FROM usuarios WHERE $column=? LIMIT 1");
        $st->execute([$identifier]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function create(array $payload): int
    {
        $sql = 'INSERT INTO usuarios (email, nombre, rol, pass_hash, grado, cip, cargo, unidad, must_change_password, activo) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, 1)';
        $st = $this->pdo->prepare($sql);
        $st->execute([
            $payload['email'],
            $payload['nombre'],
            $payload['rol'],
            $payload['pass_hash'],
            $payload['grado'], $payload['cip'], $payload['cargo'], $payload['unidad'],
        ]);
        return (int) $this->pdo->lastInsertId();
    }
}
