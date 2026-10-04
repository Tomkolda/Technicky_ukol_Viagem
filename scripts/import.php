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
    $pocet  = 0;
    fgetcsv($csv, 0, $oddelovac, '"', '');
    while (($radek = fgetcsv($csv, 0, $oddelovac, '"', '')) !== false) {
        $prikaz->execute([':kod' => (int) $radek[0], ':nazev' => iconv($kodovani, 'UTF-8', trim($radek[1]))]);
        $pocet++;
    }
    fclose($csv);
    return $pocet;   
}

$config = require __DIR__ . '/../config.php';

$db = pripojDatabazi($config['db_path']);
$db->exec(file_get_contents(__DIR__ . '/schema.sql'));

$druhyPozemku = importujCsv($db, __DIR__ . '/../data/SC_D_POZEMKU.csv', 'druhy_pozemku', 'CP1250', ';');
$pouzitiPozemku = importujCsv($db, __DIR__ . '/../data/SC_ZP_VYUZITI_POZ.csv', 'zpusoby_vyuziti', 'CP1250', ';');
$katastralniUzemi = importujCsv($db, __DIR__ . '/../data/katastralni_uzemi.csv', 'katastralni_uzemi', 'UTF-8', ',');
echo "druhy_pozemku: $druhyPozemku\n";
echo "pouziti_pozemku: $pouzitiPozemku\n";
echo "katastralni_uzemi: $katastralniUzemi\n";