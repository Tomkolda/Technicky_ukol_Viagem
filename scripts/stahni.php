<?php

declare(strict_types=1);

const KOD_OBCE = 572659;
const ADRESA_VFR = 'https://vdp.cuzk.gov.cz/vymenny_format/soucasna/%s_OB_%d_UKSH.xml.zip';
const ADRESA_CISELNIKU = 'https://services.cuzk.gov.cz/sestavy/cis/%s.zip';
const CISELNIKY = ['SC_D_POZEMKU', 'SC_ZP_VYUZITI_POZ'];
const POCET_ZKOUSENYCH_MESICU = 3;

function stahni(string $adresa): ?string
{
    $kontext = stream_context_create(['http' => ['ignore_errors' => true, 'timeout' => 60]]);
    $obsah = file_get_contents($adresa, false, $kontext);

    if ($obsah === false || !str_contains(http_get_last_response_headers()[0] ?? '', ' 200 ')) {
        return null;
    }

    return $obsah;
}

function rozbalJedinySoubor(string $obsahZipu, string $cil): void
{
    $docasnySoubor = tempnam(sys_get_temp_dir(), 'zip');
    file_put_contents($docasnySoubor, $obsahZipu);

    $zip = new ZipArchive();
    if ($zip->open($docasnySoubor) !== true || $zip->numFiles !== 1) {
        unlink($docasnySoubor);
        throw new RuntimeException("Archiv pro $cil nemá očekávaný obsah");
    }

    file_put_contents($cil, $zip->getFromIndex(0));
    $zip->close();
    unlink($docasnySoubor);
}

function stahniVfr(string $slozka): string
{
    $mesic = new DateTimeImmutable('last day of previous month');

    for ($i = 0; $i < POCET_ZKOUSENYCH_MESICU; $i++) {
        $datum = $mesic->format('Ymd');
        $obsah = stahni(sprintf(ADRESA_VFR, $datum, KOD_OBCE));

        if ($obsah !== null) {
            rozbalJedinySoubor($obsah, $slozka . '/obec.xml');
            return $datum;
        }

        $mesic = $mesic->modify('last day of previous month');
    }

    throw new RuntimeException('Data RÚIAN se nepodařilo stáhnout');
}

$slozka = __DIR__ . '/../data';
if (!is_dir($slozka)) {
    mkdir($slozka);
}

$datum = stahniVfr($slozka);
echo "RÚIAN obec " . KOD_OBCE . " k $datum: data/obec.xml\n";

foreach (CISELNIKY as $ciselnik) {
    $obsah = stahni(sprintf(ADRESA_CISELNIKU, $ciselnik));
    if ($obsah === null) {
        throw new RuntimeException("Číselník $ciselnik se nepodařilo stáhnout");
    }

    rozbalJedinySoubor($obsah, "$slozka/$ciselnik.csv");
    echo "Číselník $ciselnik: data/$ciselnik.csv\n";
}