<?php

declare(strict_types=1);

function bodVPolygonu(float $lon, float $lat, array $polygon): bool
{
    $uvnitr = false;

    foreach ($polygon as $kruh) {
        $pocetBodu = count($kruh);

        for ($i = 0, $j = $pocetBodu - 1; $i < $pocetBodu; $j = $i++) {
            [$lonA, $latA] = $kruh[$i];
            [$lonB, $latB] = $kruh[$j];

            $hranaKrizujeVysku = ($latA > $lat) !== ($latB > $lat);
            if (!$hranaKrizujeVysku) {
                continue;
            }

            $lonPruseciku = $lonA + ($lat - $latA) * ($lonB - $lonA) / ($latB - $latA);
            if ($lon < $lonPruseciku) {
                $uvnitr = !$uvnitr;
            }
        }
    }

    return $uvnitr;
}
