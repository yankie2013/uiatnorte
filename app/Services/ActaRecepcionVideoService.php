<?php
declare(strict_types=1);

namespace App\Services;

use DateTimeImmutable;
use InvalidArgumentException;

final class ActaRecepcionVideoService
{
    public const DISTRICTS = [
        'Ancón', 'Independencia', 'San Martín de Porres', 'Los Olivos',
        'Puente Piedra', 'Comas', 'Carabayllo', 'Santa Rosa', 'Canta', 'Yangas',
    ];

    public static function canonicalDistrict(string $district): string
    {
        $normalize = static function (string $value): string {
            $value = mb_strtoupper(trim($value), 'UTF-8');
            $value = strtr($value, ['Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U']);
            return preg_replace('/\s+/u', ' ', $value) ?? $value;
        };
        $wanted = $normalize($district);
        foreach (self::DISTRICTS as $option) {
            if ($normalize($option) === $wanted) return $option;
        }
        return '';
    }

    public const FIELDS = [
        'fecha_acta' => 'Fecha del acta',
        'hora_inicio' => 'Hora de inicio',
        'hora_fin' => 'Hora de culminación',
        'distrito_acta' => 'Distrito de la diligencia',
        'unidad' => 'Unidad policial',
        'instructor_grado' => 'Grado del instructor',
        'instructor_nombre' => 'Nombre del instructor',
        'accidente_fecha' => 'Fecha y hora del accidente',
        'accidente_tipo' => 'Modalidad del accidente',
        'accidente_consecuencia' => 'Consecuencia del accidente',
        'accidente_lugar' => 'Lugar del accidente',
        'accidente_referencia' => 'Referencia del accidente',
        'accidente_distrito' => 'Distrito del accidente',
        'jefe_emi_nombre' => 'Nombre del jefe EMI',
        'jefe_emi_grado' => 'Grado del jefe EMI',
        'jefe_emi_cip' => 'CIP del jefe EMI',
        'jefe_emi_cargo' => 'Cargo del jefe EMI',
        'jefe_emi_unidad' => 'Unidad del jefe EMI',
        'fecha_hallazgo' => 'Fecha del hallazgo de la cámara',
        'hora_hallazgo' => 'Hora del hallazgo de la cámara',
        'ubicacion_camara' => 'Ubicación de la cámara',
        'persona_entrega' => 'Persona que proporcionó el video',
        'tipo_documento' => 'Tipo de documento',
        'numero_documento' => 'Número de documento',
        'oficio_numero' => 'Número de oficio',
        'medio_recepcion' => 'Medio de recepción del archivo',
        'telefono_origen' => 'Teléfono de origen',
        'telefono_destino' => 'Teléfono de destino',
        'marca_pc' => 'Marca de la PC',
        'nombre_archivo' => 'Nombre del archivo',
        'medio_grabacion' => 'Medio de grabación',
        'sobre_descripcion' => 'Descripción del sobre',
        'lacrado_descripcion' => 'Descripción del lacrado',
        'cadena_custodia' => 'Cadena de custodia',
    ];

    public static function defaults(array $accident, array $user): array
    {
        $incident = trim((string) ($accident['fecha_accidente'] ?? ''));
        $today = date('Y-m-d');
        return [
            'fecha_acta' => $today,
            'hora_inicio' => date('H:i'),
            'hora_fin' => date('H:i', strtotime('+20 minutes')),
            'distrito_acta' => self::canonicalDistrict((string) ($accident['distrito'] ?? '')),
            'unidad' => 'Unidad de Investigación de Accidentes de Tránsito Norte',
            'instructor_grado' => (string) ($user['grado'] ?? ''),
            'instructor_nombre' => (string) ($user['nombre'] ?? ''),
            'accidente_fecha' => $incident !== '' ? date('Y-m-d\TH:i', strtotime($incident)) : '',
            'accidente_tipo' => (string) ($accident['modalidades'] ?? ''),
            'accidente_consecuencia' => '',
            'accidente_lugar' => (string) ($accident['lugar'] ?? ''),
            'accidente_referencia' => (string) ($accident['referencia'] ?? ''),
            'accidente_distrito' => (string) ($accident['distrito'] ?? ''),
            'jefe_emi_nombre' => (string) ($accident['jefe_emi_nombre'] ?? ''),
            'jefe_emi_grado' => (string) ($accident['jefe_emi_grado'] ?? ''),
            'jefe_emi_cip' => (string) ($accident['jefe_emi_cip'] ?? ''),
            'jefe_emi_cargo' => (string) ($accident['jefe_emi_cargo'] ?? ''),
            'jefe_emi_unidad' => (string) ($accident['jefe_emi_unidad'] ?? ''),
            'fecha_hallazgo' => $incident !== '' ? substr($incident, 0, 10) : $today,
            'hora_hallazgo' => '',
            'ubicacion_camara' => '',
            'persona_entrega' => '',
            'tipo_documento' => 'DNI',
            'numero_documento' => '',
            'oficio_numero' => '',
            'medio_recepcion' => 'WhatsApp',
            'telefono_origen' => '',
            'telefono_destino' => (string) ($user['telefono'] ?? ''),
            'marca_pc' => '',
            'nombre_archivo' => '',
            'medio_grabacion' => 'DVD-R',
            'sobre_descripcion' => 'sobre manila color amarillo camello',
            'lacrado_descripcion' => 'cinta de embalaje transparente',
            'cadena_custodia' => 'Se adjunta el formato de cadena de custodia.',
        ];
    }

    public static function validated(array $input): array
    {
        $data = [];
        foreach (self::FIELDS as $key => $label) {
            $value = $input[$key] ?? '';
            if (!is_scalar($value)) throw new InvalidArgumentException('Valor inválido en ' . $label . '.');
            $data[$key] = trim((string) $value);
            if (mb_strlen($data[$key], 'UTF-8') > 1000) {
                throw new InvalidArgumentException($label . ' es demasiado largo.');
            }
        }
        $data['distrito_acta'] = self::canonicalDistrict($data['distrito_acta']);
        $data['accidente_distrito'] = self::canonicalDistrict($data['accidente_distrito']);
        foreach (['fecha_acta', 'hora_inicio', 'hora_fin', 'distrito_acta', 'unidad', 'instructor_nombre', 'accidente_fecha', 'accidente_lugar', 'accidente_distrito', 'jefe_emi_nombre', 'jefe_emi_grado', 'jefe_emi_cip', 'fecha_hallazgo', 'hora_hallazgo', 'ubicacion_camara', 'persona_entrega', 'tipo_documento', 'numero_documento', 'oficio_numero', 'medio_recepcion', 'marca_pc', 'nombre_archivo', 'medio_grabacion'] as $key) {
            if ($data[$key] === '') throw new InvalidArgumentException('Completa ' . self::FIELDS[$key] . '.');
        }
        foreach (['fecha_acta', 'fecha_hallazgo'] as $key) {
            $date = DateTimeImmutable::createFromFormat('!Y-m-d', $data[$key]);
            if (!$date || $date->format('Y-m-d') !== $data[$key]) throw new InvalidArgumentException('Fecha inválida en ' . self::FIELDS[$key] . '.');
        }
        $incident = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $data['accidente_fecha']);
        if (!$incident || $incident->format('Y-m-d\TH:i') !== $data['accidente_fecha']) throw new InvalidArgumentException('Fecha y hora del accidente inválidas.');
        foreach (['hora_inicio', 'hora_fin', 'hora_hallazgo'] as $key) {
            if (!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $data[$key])) throw new InvalidArgumentException('Hora inválida en ' . self::FIELDS[$key] . '.');
        }
        return $data;
    }

    private static function shortDate(string $value): string
    {
        $months = [1 => 'ENE', 'FEB', 'MAR', 'ABR', 'MAY', 'JUN', 'JUL', 'AGO', 'SEP', 'OCT', 'NOV', 'DIC'];
        $date = new DateTimeImmutable(substr($value, 0, 10));
        return $date->format('d') . $months[(int) $date->format('n')] . $date->format('Y');
    }

    public static function documentValues(array $d): array
    {
        $instructor = trim($d['instructor_grado'] . ' ' . $d['instructor_nombre']);
        $kind = trim($d['accidente_tipo']);
        $kind = $kind !== '' ? ' de tipo ' . $kind : '';
        $consequence = trim($d['accidente_consecuencia']);
        $consequence = $consequence !== '' ? ' ' . $consequence : '';
        $incidentPlace = $d['accidente_lugar'];
        if ($d['accidente_referencia'] !== '') {
            $reference = $d['accidente_referencia'];
            $incidentPlace .= ', ' . (preg_match('/^(?:referencia\b|ref\.)/iu', $reference) ? $reference : 'referencia ' . $reference);
        }
        $incidentPlace .= ', distrito de ' . $d['accidente_distrito'];
        $intro = 'En el distrito de ' . $d['distrito_acta'] . ', en la oficina de la ' . $d['unidad']
            . ', siendo las ' . $d['hora_inicio'] . ' horas del ' . self::shortDate($d['fecha_acta'])
            . ', el ' . $instructor . ' procede a formular el acta de recepción de video, transferencia de archivo a PC, grabado de disco, rotulado y lacrado, con motivo de las diligencias de investigación por accidente de tránsito'
            . $kind . $consequence . ', ocurrido el ' . self::shortDate($d['accidente_fecha'])
            . ' a las ' . substr($d['accidente_fecha'], 11, 5) . ' horas en ' . $incidentPlace
            . ', según el siguiente detalle:';
        $receipt = 'Que, siendo las ' . $d['hora_hallazgo'] . ' horas del ' . self::shortDate($d['fecha_hallazgo'])
            . ', al constituirse en el lugar de los hechos se ubicó una cámara de videovigilancia en ' . $d['ubicacion_camara']
            . '. Se identificó a ' . $d['persona_entrega'] . ', con ' . $d['tipo_documento'] . ' N° ' . $d['numero_documento']
            . ', a quien se entregó el oficio N° ' . $d['oficio_numero']
            . ' solicitando las grabaciones. La persona proporcionó un archivo de video mediante ' . $d['medio_recepcion'];
        if ($d['telefono_origen'] !== '') $receipt .= ' desde el número ' . $d['telefono_origen'];
        if ($d['telefono_destino'] !== '') $receipt .= ' al número ' . $d['telefono_destino'];
        $receipt .= ', relacionado con los hechos que se investigan.';
        $backup = 'Obtenida la grabación, se descargó en el disco duro de la PC marca ' . $d['marca_pc']
            . ' instalada en la oficina de la unidad policial, con el nombre de archivo «' . $d['nombre_archivo']
            . '». Seguidamente se grabó en un ' . $d['medio_grabacion'];
        if ($d['sobre_descripcion'] !== '') $backup .= ' y se introdujo en ' . $d['sobre_descripcion'];
        if ($d['lacrado_descripcion'] !== '') $backup .= ', debidamente rotulado y lacrado con ' . $d['lacrado_descripcion'];
        $backup .= '. ' . $d['cadena_custodia'];
        $closing = 'Siendo las ' . $d['hora_fin'] . ' horas del día de la fecha, se da por culminada la presente diligencia policial. Los participantes firman en señal de conformidad.';
        return [
            'acta_intro' => $intro,
            'acta_detalle_recepcion' => $receipt,
            'acta_detalle_respaldo' => $backup,
            'acta_cierre' => $closing,
            'jefe_emi_cip' => $d['jefe_emi_cip'],
            'jefe_emi_nombre' => $d['jefe_emi_nombre'],
            'jefe_emi_grado' => $d['jefe_emi_grado'],
        ];
    }
}
