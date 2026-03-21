<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class DiligenciaConductorRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function findByAccidenteAndInvolucrado(int $accidenteId, int $involucradoPersonaId): ?array
    {
        $st = $this->pdo->prepare('SELECT * FROM diligencias_conductor WHERE accidente_id = ? AND involucrado_persona_id = ? LIMIT 1');
        $st->execute([$accidenteId, $involucradoPersonaId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function save(array $payload, bool $exists): void
    {
        if ($exists) {
            $sql = 'UPDATE diligencias_conductor SET persona_id=?, vehiculo_id=?, licencia_numero=?, licencia_categoria=?, licencia_vencimiento=?, dosaje_fecha=?, dosaje_resultado=?, dosaje_gramos=?, recon_medico_fecha=?, recon_medico_result=?, toxico_fecha=?, toxico_resultado=?, toxico_sustancias=?, manif_inicio=?, manif_fin=?, manif_duracion_min=?, actualizado_en=CURRENT_TIMESTAMP WHERE accidente_id=? AND involucrado_persona_id=? LIMIT 1';
            $this->pdo->prepare($sql)->execute([
                $payload['persona_id'],
                $payload['vehiculo_id'],
                $payload['licencia_numero'],
                $payload['licencia_categoria'],
                $payload['licencia_vencimiento'],
                $payload['dosaje_fecha'],
                $payload['dosaje_resultado'],
                $payload['dosaje_gramos'],
                $payload['recon_medico_fecha'],
                $payload['recon_medico_result'],
                $payload['toxico_fecha'],
                $payload['toxico_resultado'],
                $payload['toxico_sustancias'],
                $payload['manif_inicio'],
                $payload['manif_fin'],
                $payload['manif_duracion_min'],
                $payload['accidente_id'],
                $payload['involucrado_persona_id'],
            ]);
            return;
        }

        $sql = 'INSERT INTO diligencias_conductor (accidente_id, involucrado_persona_id, persona_id, vehiculo_id, licencia_numero, licencia_categoria, licencia_vencimiento, dosaje_fecha, dosaje_resultado, dosaje_gramos, recon_medico_fecha, recon_medico_result, toxico_fecha, toxico_resultado, toxico_sustancias, manif_inicio, manif_fin, manif_duracion_min, creado_en, actualizado_en) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)';
        $this->pdo->prepare($sql)->execute([
            $payload['accidente_id'],
            $payload['involucrado_persona_id'],
            $payload['persona_id'],
            $payload['vehiculo_id'],
            $payload['licencia_numero'],
            $payload['licencia_categoria'],
            $payload['licencia_vencimiento'],
            $payload['dosaje_fecha'],
            $payload['dosaje_resultado'],
            $payload['dosaje_gramos'],
            $payload['recon_medico_fecha'],
            $payload['recon_medico_result'],
            $payload['toxico_fecha'],
            $payload['toxico_resultado'],
            $payload['toxico_sustancias'],
            $payload['manif_inicio'],
            $payload['manif_fin'],
            $payload['manif_duracion_min'],
        ]);
    }

    public function personaNombre(int $personaId): string
    {
        if ($personaId <= 0) {
            return '';
        }
        $st = $this->pdo->prepare("SELECT CONCAT_WS(' ', apellido_paterno, apellido_materno, nombres) FROM personas WHERE id = ? LIMIT 1");
        $st->execute([$personaId]);
        return (string) ($st->fetchColumn() ?: '');
    }

    public function vehiculoPlaca(int $vehiculoId): string
    {
        if ($vehiculoId <= 0) {
            return '';
        }
        $st = $this->pdo->prepare('SELECT placa FROM vehiculos WHERE id = ? LIMIT 1');
        $st->execute([$vehiculoId]);
        return (string) ($st->fetchColumn() ?: '');
    }
}
