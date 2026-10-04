<?php

declare(strict_types=1);

function pripojDatabazi(string $cesta): PDO
{
    if (file_exists($cesta)) {
        unlink($cesta);
    }

    $db = new PDO('sqlite:' . $cesta);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    return $db;
}

function importujCsv(PDO $db, string $soubor, string $tabulka, string $kodovani, string $oddelovac): int
{
    $csv = fopen($soubor, 'r');
    if ($csv === false) {
        throw new RuntimeException("Nelze otevřít soubor: $soubor");
    }

    $prikaz = $db->prepare("INSERT INTO $tabulka (kod, nazev) VALUES (:kod, :nazev)");
    $pocet = 0;

    fgetcsv($csv, 0, $oddelovac, '"', '');
    while (($radek = fgetcsv($csv, 0, $oddelovac, '"', '')) !== false) {
        $prikaz->execute([':kod' => (int) $radek[0], ':nazev' => iconv($kodovani, 'UTF-8', trim($radek[1]))]);
        $pocet++;
    }
    fclose($csv);

    return $pocet;
}

function spocitejObdelnik(array $geometrie): array
{
    $minLon = $geometrie['coordinates'][0][0][0];
    $minLat = $geometrie['coordinates'][0][0][1];
    $maxLon = $geometrie['coordinates'][0][0][0];
    $maxLat = $geometrie['coordinates'][0][0][1];

    foreach ($geometrie['coordinates'][0] as $bod) {
        $minLon = min($minLon, $bod[0]);
        $minLat = min($minLat, $bod[1]);
        $maxLon = max($maxLon, $bod[0]);
        $maxLat = max($maxLat, $bod[1]);
    }

    return ['min_lon' => $minLon, 'min_lat' => $minLat, 'max_lon' => $maxLon, 'max_lat' => $maxLat];
}

function nactiGeoJson(string $soubor): array
{
    $obsah = file_get_contents($soubor);
    if ($obsah === false) {
        throw new RuntimeException("Nelze načíst soubor: $soubor");
    }

    return json_decode($obsah, true, 512, JSON_THROW_ON_ERROR);
}

function importujParcely(PDO $db, string $souborParcel, string $souborBodu): int
{
    $body = [];
    foreach (nactiGeoJson($souborBodu)['features'] as $bod) {
        $body[$bod['properties']['Id']] = $bod['geometry']['coordinates'];
    }

    $prikaz = $db->prepare(
        'INSERT INTO parcely (
            id, katastralni_uzemi_kod, druh_cislovani, kmenove_cislo, pododdeleni,
            vymera, druh_pozemku_kod, zpusob_vyuziti_kod, geometrie,
            min_lon, min_lat, max_lon, max_lat, bod_lon, bod_lat
        ) VALUES (
            :id, :katastralni_uzemi_kod, :druh_cislovani, :kmenove_cislo, :pododdeleni,
            :vymera, :druh_pozemku_kod, :zpusob_vyuziti_kod, :geometrie,
            :min_lon, :min_lat, :max_lon, :max_lat, :bod_lon, :bod_lat
        )'
    );

    $pocet = 0;
    foreach (nactiGeoJson($souborParcel)['features'] as $parcela) {
        $vlastnosti = $parcela['properties'];
        $id = $vlastnosti['Id'];

        if (!isset($body[$id])) {
            throw new RuntimeException("Parcela $id nemá definiční bod.");
        }

        $prikaz->execute([
            'id' => $id,
            'katastralni_uzemi_kod' => $vlastnosti['KatastralniUzemiKod'],
            'druh_cislovani' => $vlastnosti['DruhCislovaniKod'],
            'kmenove_cislo' => $vlastnosti['KmenoveCislo'],
            'pododdeleni' => $vlastnosti['PododdeleniCisla'] ?? null,
            'vymera' => $vlastnosti['VymeraParcely'],
            'druh_pozemku_kod' => $vlastnosti['DruhPozemkuKod'],
            'zpusob_vyuziti_kod' => $vlastnosti['ZpusobyVyuzitiPozemku'] ?? null,
            'geometrie' => json_encode($parcela['geometry'], JSON_THROW_ON_ERROR),
            'bod_lon' => $body[$id][0],
            'bod_lat' => $body[$id][1],
        ] + spocitejObdelnik($parcela['geometry']));
        $pocet++;
    }

    return $pocet;
}

$config = require __DIR__ . '/../config.php';

$db = pripojDatabazi($config['db_path']);
$db->exec(file_get_contents(__DIR__ . '/schema.sql'));

$start = microtime(true);
$db->beginTransaction();
$druhyPozemku = importujCsv($db, __DIR__ . '/../data/SC_D_POZEMKU.csv', 'druhy_pozemku', 'CP1250', ';');
$zpusobyVyuziti = importujCsv($db, __DIR__ . '/../data/SC_ZP_VYUZITI_POZ.csv', 'zpusoby_vyuziti', 'CP1250', ';');
$katastralniUzemi = importujCsv($db, __DIR__ . '/../data/katastralni_uzemi.csv', 'katastralni_uzemi', 'UTF-8', ',');
$parcely = importujParcely($db, __DIR__ . '/../data/parcely.geojson', __DIR__ . '/../data/body.geojson');
$db->commit();

printf("čas importu: %.1f s\n", microtime(true) - $start);
echo "druhy_pozemku: $druhyPozemku\n";
echo "zpusoby_vyuziti: $zpusobyVyuziti\n";
echo "katastralni_uzemi: $katastralniUzemi\n";
echo "parcely: $parcely\n";
