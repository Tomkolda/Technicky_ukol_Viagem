<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/databaze.php';
require_once __DIR__ . '/../src/parcely.php';

const MAX_SIRKA_VYREZU = 0.06;
const MAX_VYSKA_VYREZU = 0.03;
const VELIKOST_KOUSKU_ODPOVEDI = 8192;

function odpovezJson(int $kodStavu, string $json): never
{
    http_response_code($kodStavu);
    header('Content-Type: application/json; charset=utf-8');

    // Vestavěný server PHP na Windows někdy velkou odpověď nedopíše, po kouscích ji pošle celou.
    foreach (str_split($json, VELIKOST_KOUSKU_ODPOVEDI) as $kousek) {
        echo $kousek;
        ob_flush();
        flush();
    }
    exit;
}

function odpovez(int $kodStavu, array $data): never
{
    odpovezJson($kodStavu, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
}

function nactiCislo(string $nazev, float $min, float $max): float
{
    $hodnota = filter_var($_GET[$nazev] ?? null, FILTER_VALIDATE_FLOAT);

    if ($hodnota === false || $hodnota < $min || $hodnota > $max) {
        throw new InvalidArgumentException("Parametr '$nazev' musí být číslo od $min do $max");
    }

    return $hodnota;
}

ob_start('ob_gzhandler');

$config = require __DIR__ . '/../config.php';

try {
    $db = otevriDatabazi($config['db_path']);
    $akce = is_string($_GET['akce'] ?? null) ? $_GET['akce'] : '';

    if ($akce === 'detail') {
        $lon = nactiCislo('lon', -180, 180);
        $lat = nactiCislo('lat', -90, 90);

        $parcela = najdiParceluVBode($db, $lon, $lat);
        if ($parcela === null) {
            odpovez(404, ['chyba' => 'V tomto bodě není žádná parcela']);
        }

        odpovez(200, $parcela);
    }

    if ($akce === 'parcely') {
        $zapad = nactiCislo('zapad', -180, 180);
        $jih = nactiCislo('jih', -90, 90);
        $vychod = nactiCislo('vychod', $zapad, $zapad + MAX_SIRKA_VYREZU);
        $sever = nactiCislo('sever', $jih, $jih + MAX_VYSKA_VYREZU);

        odpovezJson(200, sestavGeoJsonVyrezu($db, $zapad, $jih, $vychod, $sever));
    }

    odpovez(400, ['chyba' => "Neznámá akce '$akce'"]);
} catch (InvalidArgumentException $e) {
    odpovez(400, ['chyba' => $e->getMessage()]);
} catch (Throwable $e) {
    error_log((string) $e);
    odpovez(500, ['chyba' => 'Interní chyba serveru']);
}
