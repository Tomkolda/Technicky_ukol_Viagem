<?php

declare(strict_types=1);

require_once __DIR__ . '/geometrie.php';

const DRUH_CISLOVANI_STAVEBNI = 1;

function sestavCisloParcely(int $druhCislovani, int $kmenoveCislo, ?int $pododdeleni): string
{
    $cislo = (string) $kmenoveCislo;

    if ($pododdeleni !== null) {
        $cislo .= '/' . $pododdeleni;
    }

    if ($druhCislovani === DRUH_CISLOVANI_STAVEBNI) {
        $cislo = 'st. ' . $cislo;
    }

    return $cislo;
}

function najdiParceluVBode(PDO $db, float $lon, float $lat): ?array
{
    $prikaz = $db->prepare(
        'SELECT p.id, p.druh_cislovani, p.kmenove_cislo, p.pododdeleni, p.vymera, p.geometrie,
                ku.nazev AS katastralni_uzemi,
                dp.nazev AS druh_pozemku,
                zv.nazev AS zpusob_vyuziti
         FROM parcely p
         JOIN katastralni_uzemi ku ON ku.kod = p.katastralni_uzemi_kod
         JOIN druhy_pozemku dp ON dp.kod = p.druh_pozemku_kod
         LEFT JOIN zpusoby_vyuziti zv ON zv.kod = p.zpusob_vyuziti_kod
         WHERE p.min_lon <= :lon AND p.max_lon >= :lon
           AND p.min_lat <= :lat AND p.max_lat >= :lat'
    );
    $prikaz->execute([':lon' => $lon, ':lat' => $lat]);

    foreach ($prikaz->fetchAll(PDO::FETCH_ASSOC) as $parcela) {
        $geometrie = json_decode($parcela['geometrie'], true, 512, JSON_THROW_ON_ERROR);
        if (!bodVPolygonu($lon, $lat, $geometrie['coordinates'])) {
            continue;
        }

        return [
            'id' => (int) $parcela['id'],
            'cislo' => sestavCisloParcely(
                (int) $parcela['druh_cislovani'],
                (int) $parcela['kmenove_cislo'],
                $parcela['pododdeleni'] !== null ? (int) $parcela['pododdeleni'] : null
            ),
            'katastralni_uzemi' => $parcela['katastralni_uzemi'],
            'vymera' => (int) $parcela['vymera'],
            'druh_pozemku' => $parcela['druh_pozemku'],
            'zpusob_vyuziti' => $parcela['zpusob_vyuziti'],
        ];
    }

    return null;
}

function sestavGeoJsonVyrezu(PDO $db, float $zapad, float $jih, float $vychod, float $sever): string
{
    $prikaz = $db->prepare(
        'SELECT id, druh_cislovani, kmenove_cislo, pododdeleni, geometrie
         FROM parcely
         WHERE min_lon <= :vychod AND max_lon >= :zapad
           AND min_lat <= :sever AND max_lat >= :jih'
    );
    $prikaz->execute([':zapad' => $zapad, ':jih' => $jih, ':vychod' => $vychod, ':sever' => $sever]);

    $prvky = [];
    foreach ($prikaz->fetchAll(PDO::FETCH_ASSOC) as $parcela) {
        $vlastnosti = [
            'cislo' => sestavCisloParcely(
                (int) $parcela['druh_cislovani'],
                (int) $parcela['kmenove_cislo'],
                $parcela['pododdeleni'] !== null ? (int) $parcela['pododdeleni'] : null
            ),
        ];

        $prvky[] = '{"type":"Feature","id":' . (int) $parcela['id']
            . ',"properties":' . json_encode($vlastnosti, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)
            . ',"geometry":' . $parcela['geometrie'] . '}';
    }

    return '{"type":"FeatureCollection","features":[' . implode(',', $prvky) . ']}';
}