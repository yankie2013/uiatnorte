<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    exit("Este script es solo para uso por CLI.\n");
}

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

header('Content-Type: text/plain; charset=utf-8');

$url = $argv[1] ?? 'https://korkaystore.com/uiatnorte/guardar_vehiculo.php';

$payload = [
    'altura' => '1.514 mt',
    'anMode' => '2016',
    'ancho' => '1.695 mt',
    'anoFab' => '2015',
    'coCateg' => 'Categoria M',
    'color' => 'NEGRO',
    'descTipoCarr' => 'SEDAN',
    'descTipoComb' => 'GASOLINA',
    'descTipoUso' => 'Vehiculos Particulares',
    'dni' => '',
    'ejes' => '2',
    'estado' => '',
    'fecIns' => '10/03/2016 09:04',
    'formulaRodante' => '4X2',
    'historial' => true,
    'longitud' => '4.465 mt',
    'marca' => 'NISSAN',
    'modelo' => 'VERSA',
    'noVin' => '3N1CN7AD2GK399945',
    'nomOficina' => 'LIMA',
    'nombre' => '',
    'numAsientos' => '5',
    'numCilindros' => '4',
    'numMotor' => 'HR16833613K',
    'numPartida' => '53311414',
    'numPasajeros' => '4',
    'numPlaca' => 'ANN697',
    'numRuedas' => '4',
    'numSerie' => '3N1CM7AD20K399945',
    'observacion' => null,
    'pesoBruto' => '1.425 tn',
    'pesoSeco' => null,
    'placaAnterior' => null,
    'poMotr' => '79.0685600',
    'status' => 'success',
    'tipoActo' => 'Primera Inscripcion de Dominio',
];

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'Accept: application/json',
    ],
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_TIMEOUT => 25,
]);

$res = curl_exec($ch);

if ($res === false) {
    echo "cURL ERROR: " . curl_error($ch) . PHP_EOL;
    echo "cURL ERRNO: " . curl_errno($ch) . PHP_EOL;
    curl_close($ch);
    exit(1);
}

$http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
$ct = (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
curl_close($ch);

echo "OK" . PHP_EOL;
echo "HTTP: {$http}" . PHP_EOL;
echo "Content-Type: {$ct}" . PHP_EOL . PHP_EOL;
echo "----- RESPUESTA -----" . PHP_EOL;
echo $res . PHP_EOL;
echo "---------------------" . PHP_EOL;
