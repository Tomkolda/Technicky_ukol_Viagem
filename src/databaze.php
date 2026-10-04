<?php

declare(strict_types=1);

function otevriDatabazi(string $cesta): PDO
{
    if (!file_exists($cesta)) {
        throw new RuntimeException("Databáze $cesta neexistuje, spusť nejdřív scripts/import.php");
    }

    $db = new PDO('sqlite:' . $cesta);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    return $db;
}
