<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/databaze.php';
require_once __DIR__ . '/../src/parcely.php';

function odpovez(int $kodStavu, array $data): never
{
    http_response_code($kodStavu);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    exit;
}

function nactiCislo(string $nazev, float $min, float $max): float
{
    $hodnota = filter_var($_GET[$nazev] ?? null, FILTER_VALIDATE_FLOAT);

    if ($hodnota === false || $hodnota < $min || $hodnota > $max) {
        throw new InvalidArgumentException("Parametr '$nazev' musí být číslo od $min do $max");
    }

    return $hodnota;
}

$config = require __DIR__ . '/../config.php';

try {
    $db = otevriDatabazi($config['db_path']);
    $akce = $_GET['akce'] ?? '';

    if ($akce === 'detail') {
        $lon = nactiCislo('lon', -180, 180);
        $lat = nactiCislo('lat', -90, 90);

        $parcela = najdiParceluVBode($db, $lon, $lat);
        if ($parcela === null) {
            odpovez(404, ['chyba' => 'V tomto bodě není žádná parcela']);
        }

        odpovez(200, $parcela);
    }

    odpovez(400, ['chyba' => "Neznámá akce '$akce'"]);
} catch (InvalidArgumentException $e) {
    odpovez(400, ['chyba' => $e->getMessage()]);
} catch (Throwable $e) {
    error_log((string) $e);
    odpovez(500, ['chyba' => 'Interní chyba serveru']);
}