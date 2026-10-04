# Parcely okresu Jičín

Webová aplikace, která zobrazuje katastrální parcely na mapě. Po kliknutí na parcelu ukáže její číslo, katastrální území, výměru, druh pozemku a způsob využití a nabídne odkaz do Nahlížení do katastru nemovitostí.

Pokrytí: všech 5 katastrálních území obce Jičín (Jičín, Hubálov, Moravčice, Popovice u Jičína, Robousy), celkem 16 953 parcel. Data pocházejí z RÚIAN (ČÚZK).

Rozhodnutí, měření a slepé uličky jsou popsané v [ZAPISNIK.md](ZAPISNIK.md).

## Požadavky

- **PHP 8.4** se zapnutými rozšířeními v `php.ini`:
  - `pdo_sqlite` – databáze,
  - `openssl` – stahování dat přes HTTPS,
  - `zip` – rozbalení stažených archivů.
- **QGIS 4.2.3** (obsahuje GDAL s nástrojem `ogr2ogr`) v `C:\Program Files\QGIS 4.2.3`. Při jiné verzi nebo cestě uprav první řádek s `call` ve `scripts/prevod.bat`.
- **Windows** – převodní skript je `.bat`. Na jiném systému stačí spustit tři příkazy `ogr2ogr` z `prevod.bat` ručně.
- **Připojení k internetu** – stažení dat, mapový podklad OpenStreetMap, přehledka ČÚZK a knihovna Leaflet z CDN.

Ověření rozšíření PHP:

    php -m

Ve výpisu musí být `pdo_sqlite`, `openssl` a `zip`.

## Spuštění

Všechny příkazy se spouští z kořenové složky projektu.

**1. Stažení dat z ČÚZK** (data RÚIAN obce Jičín a číselníky, cca 4 MB):

    php scripts/stahni.php

**2. Převod souřadnic** z S-JTSK (EPSG:5514) do WGS84 (EPSG:4326) a do formátu GeoJSON:

    scripts\prevod.bat

**3. Import do databáze SQLite** (`data/katastr.sqlite`):

    php scripts/import.php

Import má vypsat: 11 druhů pozemků, 30 způsobů využití, 5 katastrálních území, 16 953 parcel.

**4. Spuštění serveru:**

    php -S localhost:8000 -t public

Aplikace běží na <http://localhost:8000>.

Všechny skripty jde spouštět opakovaně, vždy přepíšou předchozí výsledek. Složka `data/` není v gitu, celá se vytvoří těmito kroky.

## Ovládání

- **Zoom 15 a méně:** hranice a názvy katastrálních území (přehledka ČÚZK).
- **Zoom 16 a více:** hranice parcel.
- **Zoom 18 a více:** navíc čísla parcel.
- **Kliknutí** kamkoliv na mapu (při jakémkoliv přiblížení) zobrazí detail parcely v daném bodě a parcelu zvýrazní.

## Struktura

    config.php          cesta k databázi
    public/             document root serveru
      index.html        stránka s mapou
      app.js            mapa (Leaflet), načítání parcel, detail, popisky
      style.css
      api.php           JSON API
    src/
      databaze.php      otevření databáze
      parcely.php       hledání parcel, sestavení čísla parcely a GeoJSON
      geometrie.php     test bodu v polygonu
    scripts/
      stahni.php        stažení dat z ČÚZK
      prevod.bat        převod dat přes ogr2ogr
      schema.sql        databázové schéma
      import.php        import do SQLite
    data/               stažená data a databáze (není v gitu)

Document root je jen `public/`, takže `config.php`, zdrojové kódy ani databáze nejsou z prohlížeče dostupné.

## API

Odpovědi jsou JSON v UTF-8, při podpoře v prohlížeči komprimované gzipem.

**Detail parcely v bodě**

    GET api.php?akce=detail&lon=15.3520402&lat=50.4373749

    {"id":1753300604,"cislo":"st. 77","katastralni_uzemi":"Jičín","vymera":509,
     "druh_pozemku":"zastavěná plocha a nádvoří","zpusob_vyuziti":null,"geometrie":{...}}

**Parcely ve výřezu mapy** (GeoJSON `FeatureCollection`, výřez nejvýše 0,06° × 0,03°)

    GET api.php?akce=parcely&zapad=15.339&jih=50.4315&vychod=15.365&sever=50.4425

| Kód | Význam |
|---|---|
| 200 | v pořádku |
| 400 | chybějící nebo neplatný parametr, příliš velký výřez, neznámá akce |
| 404 | v bodě není žádná parcela |
| 500 | chyba serveru (podrobnosti jen v logu serveru) |
