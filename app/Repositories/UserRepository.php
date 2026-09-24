<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class UserRepository
{
    private array $columns = [];

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
        $isNumber = preg_match('/^[0-9]+$/D', $identifier) === 1;
        $column = $isNumber && $this->hasColumn('cip') ? 'cip' : 'email';
        $select = ['id', 'email', 'nombre', 'rol', 'pass_hash', 'activo'];
        foreach (['grado', 'cip', 'must_change_password', 'auth_version'] as $optional) {
            $select[] = $this->hasColumn($optional) ? "`$optional`" : ($optional === 'must_change_password' || $optional === 'auth_version' ? "0 AS `$optional`" : "NULL AS `$optional`");
        }
        $st = $this->pdo->prepare('SELECT ' . implode(',', $select) . " FROM usuarios WHERE `$column`=? LIMIT 1");
        $st->execute([$identifier]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    private function hasColumn(string $column): bool
    {
        if (!array_key_exists($column, $this->columns)) {
            $st = $this->pdo->prepare('SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=\'usuarios\' AND column_name=?');
            $st->execute([$column]);
            $this->columns[$column] = (bool) $st->fetchColumn();
        }
        return $this->columns[$column];
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
