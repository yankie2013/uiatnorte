<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\DocumentoDosajeRepository;
use InvalidArgumentException;
use DateTime;

final class DocumentoDosajeService
{
    private const CUALITATIVO_OPTIONS = ['Negativo', 'Positivo', 'Se negó', 'Constatación'];

    public function __construct(private DocumentoDosajeRepository $repository)
    {
    }

    public function persona(int $personaId): ?array
    {
        return $personaId > 0 ? $this->repository->personaById($personaId) : null;
    }

    public function detalle(int $id): ?array
    {
        $row = $this->repository->find($id);
        if ($row === null) {
            return null;
        }

        return [
            'row' => $row,
            'fecha' => $this->fechaInput((string) ($row['fecha_extraccion'] ?? '')),
            'hora' => $this->horaInput((string) ($row['fecha_extraccion'] ?? '')),
        ];
    }

    public function listado(string $q, int $personaId): array
    {
        return $this->repository->search(trim($q), $personaId);
    }

    public function crear(array $input): int
    {
        $payload = $this->payload($input, true);
        return $this->repository->create($payload);
    }

    public function actualizar(int $id, array $input): void
    {
        if ($this->repository->find($id) === null) {
            throw new InvalidArgumentException('Registro no encontrado');
        }

        $payload = $this->payload($input, false);
        $this->repository->update($id, $payload);
    }

    public function eliminar(int $id): void
    {
        if ($this->repository->find($id) === null) {
            throw new InvalidArgumentException('Registro no encontrado');
        }

        $this->repository->delete($id);
    }

    public function cualitativoOptions(): array
    {
        return self::CUALITATIVO_OPTIONS;
    }

    public function cuantitativoTexto(string $val): string
    {
        if ($val === '') {
            return '';
        }

        $f = (float) str_replace(',', '.', $val);
        if (!is_numeric((string) $f)) {
            return '';
        }

        $f = round($f, 2);
        $g = (int) floor($f);
        $c = (int) round(($f - $g) * 100);
        $gpal = $this->numero0a99($g);
        $gpal = $g === 1 ? 'Un' : ucfirst($gpal);
        $parteG = $gpal . ' ' . ($g === 1 ? 'gramo' : 'gramos');
        $parteC = $c > 0 ? ' ' . $this->numero0a99($c) . ' centigramos' : '';
        $numFmt = number_format($f, 2, '.', '');

        return $parteG . $parteC . ' de alcohol por litro de sangre (' . $numFmt . ' g/L)';
    }

    private function payload(array $input, bool $requirePersona): array
    {
        $personaId = (int) ($input['persona_id'] ?? 0);
        $numero = $this->nullableTrim($input['numero'] ?? '');
        $numeroRegistro = $this->nullableTrim($input['numero_registro'] ?? '');
        $fecha = trim((string) ($input['fecha'] ?? ''));
        $hora = trim((string) ($input['hora'] ?? ''));
        $resultadoCualitativo = trim((string) ($input['resultado_cualitativo'] ?? ''));
        $resultadoCuantitativo = trim((string) ($input['resultado_cuantitativo'] ?? ''));
        $observaciones = $this->nullableTrim($input['observaciones'] ?? '');

        if ($requirePersona && $personaId <= 0) {
            throw new InvalidArgumentException('Falta seleccionar la persona.');
        }
        if ($resultadoCualitativo === '') {
            throw new InvalidArgumentException('Selecciona el resultado cualitativo.');
        }
        if (!in_array($resultadoCualitativo, self::CUALITATIVO_OPTIONS, true)) {
            throw new InvalidArgumentException('Resultado cualitativo inválido.');
        }
        if ($resultadoCuantitativo !== '' && !is_numeric(str_replace(',', '.', $resultadoCuantitativo))) {
            throw new InvalidArgumentException('El resultado cuantitativo debe ser numérico, ej. 1.80');
        }

        $fechaExtraccion = null;
        if ($fecha !== '') {
            $fechaExtraccion = $fecha . ($hora !== '' ? (' ' . $hora . ':00') : ' 00:00:00');
        }

        $cuant = $resultadoCuantitativo === '' ? null : str_replace(',', '.', $resultadoCuantitativo);
        $leer = $cuant !== null ? $this->cuantitativoTexto((string) $resultadoCuantitativo) : null;

        return [
            ':persona_id' => $personaId > 0 ? $personaId : null,
            ':numero' => $numero,
            ':numero_registro' => $numeroRegistro,
            ':fecha_extraccion' => $fechaExtraccion,
            ':resultado_cualitativo' => $resultadoCualitativo,
            ':resultado_cuantitativo' => $cuant,
            ':leer_cuantitativo' => $leer,
            ':observaciones' => $observaciones,
        ];
    }

    private function fechaInput(string $fechaHora): string
    {
        if ($fechaHora === '') {
            return '';
        }
        $dt = new DateTime($fechaHora);
        return $dt->format('Y-m-d');
    }

    private function horaInput(string $fechaHora): string
    {
        if ($fechaHora === '') {
            return '';
        }
        $dt = new DateTime($fechaHora);
        return $dt->format('H:i');
    }

    private function nullableTrim(mixed $value): ?string
    {
        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }

    private function numero0a99(int $n): string
    {
        $u = ['cero','uno','dos','tres','cuatro','cinco','seis','siete','ocho','nueve'];
        $esp = [
            10=>'diez',11=>'once',12=>'doce',13=>'trece',14=>'catorce',15=>'quince',
            16=>'dieciséis',17=>'diecisiete',18=>'dieciocho',19=>'diecinueve',
            20=>'veinte',21=>'veintiuno',22=>'veintidós',23=>'veintitrés',24=>'veinticuatro',
            25=>'veinticinco',26=>'veintiséis',27=>'veintisiete',28=>'veintiocho',29=>'veintinueve',
            30=>'treinta',40=>'cuarenta',50=>'cincuenta',60=>'sesenta',70=>'setenta',80=>'ochenta',90=>'noventa',
        ];
        if ($n < 10) {
            return $u[$n];
        }
        if ($n < 30) {
            return $esp[$n];
        }
        $d = (int) floor($n / 10) * 10;
        $r = $n % 10;
        return $esp[$d] . ($r ? ' y ' . $u[$r] : '');
    }
}
