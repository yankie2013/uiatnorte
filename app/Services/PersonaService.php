<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\PersonaRepository;
use DateTimeImmutable;
use InvalidArgumentException;

final class PersonaService
{
    public function __construct(private PersonaRepository $repository)
    {
    }

    public function defaultData(?array $row = null, array $overrides = []): array
    {
        $base = [
            'tipo_doc' => 'DNI',
            'num_doc' => '',
            'apellido_paterno' => '',
            'apellido_materno' => '',
            'nombres' => '',
            'sexo' => '',
            'fecha_nacimiento' => '',
            'edad' => '',
            'estado_civil' => '',
            'nacionalidad' => 'PERUANA',
            'departamento_nac' => '',
            'provincia_nac' => '',
            'distrito_nac' => '',
            'domicilio' => '',
            'domicilio_departamento' => '',
            'domicilio_provincia' => '',
            'domicilio_distrito' => '',
            'ocupacion' => '',
            'grado_instruccion' => '',
            'nombre_padre' => '',
            'nombre_madre' => '',
            'celular' => '',
            'email' => '',
            'notas' => '',
            'foto_path' => '',
            'api_fuente' => '',
            'api_ref' => '',
        ];

        if ($row !== null) {
            foreach ($base as $field => $value) {
                if (array_key_exists($field, $row)) {
                    $base[$field] = (string) ($row[$field] ?? '');
                }
            }
        }

        return array_merge($base, $overrides);
    }

    public function listado(string $query, int $page, int $limit = 12): array
    {
        return $this->repository->paginate(trim($query), $page, $limit);
    }

    public function find(int $id): ?array
    {
        if ($id <= 0) {
            throw new InvalidArgumentException('Falta id.');
        }
        return $this->repository->find($id);
    }

    public function findByDocumentNumber(string $number): ?array
    {
        $number = trim($number);
        if ($number === '') {
            throw new InvalidArgumentException('Falta documento.');
        }
        return $this->repository->findByDocumentNumber($number);
    }

    public function detailContext(int $id): array
    {
        $row = $this->find($id);
        if ($row === null) {
            throw new InvalidArgumentException('Persona no encontrada.');
        }

        return [
            'row' => $row,
            'references' => $this->repository->referenceSummary($id),
            'involvement_count' => $this->repository->involvementCount($id),
        ];
    }

    public function create(array $input): int
    {
        $payload = $this->payload($input, null);
        return $this->repository->create($payload);
    }

    public function update(int $id, array $input): void
    {
        if ($this->repository->find($id) === null) {
            throw new InvalidArgumentException('Persona no encontrada.');
        }
        $payload = $this->payload($input, $id);
        $this->repository->update($id, $payload);
    }

    public function delete(int $id): void
    {
        $row = $this->repository->find($id);
        if ($row === null) {
            throw new InvalidArgumentException('Persona no encontrada.');
        }

        $references = $this->repository->referenceSummary($id);
        if ($references !== []) {
            $parts = array_map(static fn (array $item): string => $item['label'] . ' (' . $item['count'] . ')', $references);
            throw new InvalidArgumentException('No se puede eliminar la persona porque aun tiene referencias activas: ' . implode(', ', $parts) . '.');
        }

        $this->repository->delete($id);
    }

    private function payload(array $input, ?int $excludeId): array
    {
        $tipoDoc = strtoupper(trim((string) ($input['tipo_doc'] ?? 'DNI')));
        $numDoc = strtoupper(trim((string) ($input['num_doc'] ?? '')));
        $apellidoPaterno = trim((string) ($input['apellido_paterno'] ?? ''));
        $apellidoMaterno = trim((string) ($input['apellido_materno'] ?? ''));
        $nombres = trim((string) ($input['nombres'] ?? ''));
        $sexo = strtoupper(trim((string) ($input['sexo'] ?? '')));
        $fechaNacimiento = trim((string) ($input['fecha_nacimiento'] ?? ''));
        $email = $this->nullableTrim($input['email'] ?? null);
        $edad = $this->calculateAge($fechaNacimiento);

        if (!in_array($tipoDoc, ['DNI', 'CE', 'PAS', 'OTRO'], true)) {
            throw new InvalidArgumentException('Tipo de documento invalido.');
        }
        if ($numDoc === '') {
            throw new InvalidArgumentException('Numero de documento es requerido.');
        }
        if ($tipoDoc === 'DNI' && !preg_match('/^\d{8}$/', $numDoc)) {
            throw new InvalidArgumentException('El DNI debe tener 8 digitos.');
        }
        if ($tipoDoc !== 'DNI' && strlen($numDoc) > 20) {
            throw new InvalidArgumentException('El numero de documento es demasiado largo.');
        }
        if ($apellidoPaterno === '') {
            throw new InvalidArgumentException('Apellido paterno es requerido.');
        }
        if ($apellidoMaterno === '') {
            throw new InvalidArgumentException('Apellido materno es requerido.');
        }
        if ($nombres === '') {
            throw new InvalidArgumentException('Nombres es requerido.');
        }
        if (!in_array($sexo, ['M', 'F'], true)) {
            throw new InvalidArgumentException('Sexo es requerido.');
        }
        if ($fechaNacimiento === '') {
            throw new InvalidArgumentException('Fecha de nacimiento es requerida.');
        }
        if ($email !== null && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Correo invalido.');
        }
        if ($this->repository->existsDuplicate($tipoDoc, $numDoc, $excludeId)) {
            throw new InvalidArgumentException('Ya existe otra persona con ese tipo y numero de documento.');
        }

        return [
            'tipo_doc' => $tipoDoc,
            'num_doc' => $numDoc,
            'apellido_paterno' => $apellidoPaterno,
            'apellido_materno' => $apellidoMaterno,
            'nombres' => $nombres,
            'sexo' => $sexo,
            'fecha_nacimiento' => $fechaNacimiento,
            'edad' => $edad,
            'estado_civil' => $this->nullableTrim($input['estado_civil'] ?? null),
            'nacionalidad' => $this->nullableTrim($input['nacionalidad'] ?? 'PERUANA') ?? 'PERUANA',
            'departamento_nac' => $this->nullableTrim($input['departamento_nac'] ?? null),
            'provincia_nac' => $this->nullableTrim($input['provincia_nac'] ?? null),
            'distrito_nac' => $this->nullableTrim($input['distrito_nac'] ?? null),
            'domicilio' => $this->nullableTrim($input['domicilio'] ?? null),
            'domicilio_departamento' => $this->nullableTrim($input['domicilio_departamento'] ?? null),
            'domicilio_provincia' => $this->nullableTrim($input['domicilio_provincia'] ?? null),
            'domicilio_distrito' => $this->nullableTrim($input['domicilio_distrito'] ?? null),
            'ocupacion' => $this->nullableTrim($input['ocupacion'] ?? null),
            'grado_instruccion' => $this->nullableTrim($input['grado_instruccion'] ?? null),
            'nombre_padre' => $this->nullableTrim($input['nombre_padre'] ?? null),
            'nombre_madre' => $this->nullableTrim($input['nombre_madre'] ?? null),
            'celular' => $this->nullableTrim($input['celular'] ?? null),
            'email' => $email,
            'notas' => $this->nullableTrim($input['notas'] ?? null),
            'foto_path' => $this->nullableTrim($input['foto_path'] ?? null),
            'api_fuente' => $this->nullableTrim($input['api_fuente'] ?? null),
            'api_ref' => $this->nullableTrim($input['api_ref'] ?? null),
        ];
    }

    private function calculateAge(string $birthDate): int
    {
        try {
            $date = new DateTimeImmutable($birthDate);
        } catch (\Throwable) {
            throw new InvalidArgumentException('Fecha de nacimiento invalida.');
        }

        $today = new DateTimeImmutable('today');
        if ($date > $today) {
            throw new InvalidArgumentException('La fecha de nacimiento no puede ser futura.');
        }

        return $today->diff($date)->y;
    }

    private function nullableTrim(mixed $value): ?string
    {
        $text = trim((string) ($value ?? ''));
        return $text === '' ? null : $text;
    }
}
