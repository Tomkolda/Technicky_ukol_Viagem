# Zápisník

## 3. 10. – Která katastrální území

Vzal jsem všech pět katastrálních území obce Jičín:

- Jičín – 659541
- Hubálov – 771775
- Moravčice – 740217
- Popovice u Jičína – 725838
- Robousy – 740225

Zadání chce Jičín a tři další, ale data RÚIAN se stahují po celých obcích, takže jsem dostal všech pět najednou a nemusel jsem nic filtrovat. Je mi jasné, že jedna obec neověří, jestli by aplikace zvládla celý okres – k tomu se vracím u výkonu.

## 3. 10. – Odkud vzít data

Porovnával jsem dva zdroje od ČÚZK:

| | INSPIRE (Katastrální parcely) | RÚIAN (VFR) |
|---|---|---|
| Parcelní číslo | ano, hotový text | ano, rozdělené na kmenové číslo, poddělení a druh číslování |
| Výměra | ano | ano |
| Druh pozemku | ne | ano (kód) |
| Způsob využití | ne | ano (kód) |
| Stahuje se po | katastrálních územích | obcích |
| Souřadnice | S-JTSK nebo ETRS89 | S-JTSK |

Vybral jsem RÚIAN. Když někdo vykupuje pozemky, zajímá ho hlavně, jestli jde o ornou půdu, zahradu nebo zastavěnou plochu, a to INSPIRE vůbec nemá. Cenou za to je, že RÚIAN udává druh a využití pozemku jen kódem, takže budu potřebovat číselníky.

## 3. 10. – Stahování dat a slepé uličky

Tohle mi zabralo víc času, než jsem čekal. ČÚZK má několik podobně pojmenovaných formátů a každý obsahuje něco jiného:

- **SHP export RÚIAN** po obcích – jsou v něm ulice, adresy a budovy, ale parcely ne.
- **VFK** – parcely v něm nejsou jako polygony, ale jako jednotlivé body a hranice, které by se musely skládat. Navíc v mém souboru chyběly atributy parcel.
- **VFR**, soubor `OB_572659_UKSH` – konečně parcely s polygonem i se vším ostatním. Název znamená obec, úplná data, kompletní (s geometrií), současná, originální hranice.

Ve staženém souboru je 16 953 parcel. Souřadnice jsou v S-JTSK (EPSG:5514) a jsou záporné, zatímco ve VFK byly kladné – na to jsem si musel dát pozor při převodu.

## 3. 10. – Kolik parcel se dá ukázat najednou

Zadání chce, aby aplikace byla plynulá i nad celým okresem. Kdybych poslal do prohlížeče všechny parcely okresu, šlo by o stovky MB a prohlížeč by je při každém posunu mapy překresloval. Zkusil jsem si spočítat, kdy má vůbec smysl parcely kreslit:

- Celý okres má asi 50 km, na monitoru s 1 900 px je to ~26 m na pixel.
- Průměrná parcela v Jičíně má ~1 500 m², tedy asi 39 × 39 m. Nad celým okresem by to byl 1,5 pixelu.
- Aby byla parcela vidět aspoň na 20 px, potřebuju ~2 m na pixel, což je zoom 15–16.

Nad celým okresem tedy parcely stejně nejsou vidět a nemá smysl je posílat. Původně jsem chtěl na menším přiblížení ukázat obrázek katastrální mapy z WMS ČÚZK a parcely kreslit od zoomu 15. Obojí jsem později upravil podle měření (viz „Mapa“ a „Parcely ve výřezu“).

Kliknutí jsem se rozhodl řešit na serveru: prohlížeč pošle souřadnice a PHP najde parcelu, která tam leží. Díky tomu jde kliknout na jakémkoliv přiblížení, i když parcely nejsou nakreslené.

Zvolil jsem jednoduché řešení – SQLite, GeoJSON a Leaflet –, protože pro pět katastrálních území stačí a dá se rychle postavit a změřit. Pro celý okres bych sáhl po vektorových dlaždicích z PostGIS, viz konec zápisníku.

## 3. 10. – Převod souřadnic

Soubor VFR jsem nejdřív otevřel v QGIS a parcely se ukázaly jako tečky. Ukázalo se, že vrstva Parcely má tři geometrie (definiční bod, hranici a ještě jednu hranici pro jiný typ mapy) a QGIS vzal tu první. Pomohl `ogr2ogr` s `-select`, kde vyberu jen `OriginalniHranice`.

Pro aplikaci převádím do GeoJSON ve WGS84, protože ten umí Leaflet přečíst přímo. Jeden prvek GeoJSON ale může mít jen jednu geometrii, takže definiční body převádím zvlášť a import je s parcelami spojí podle `Id`. Přesnost souřadnic jsem omezil na 7 desetinných míst, což je zhruba centimetr – víc pro mapu není potřeba a soubor je menší.

Když jsem parcely položil na OpenStreetMap, seděly přesně na domy a ulice, takže převod je v pořádku.

## 3. 10. – Sedí data s katastrem?

Než jsem začal programovat, chtěl jsem mít jistotu, že čtu data správně. Vybral jsem parcelu st. 77 v Jičíně na Valdštejnově náměstí a porovnal ji s Nahlížením do KN. Výměra 509 m² sedí a druh pozemku s kódem 13 je „zastavěná plocha a nádvoří“, což taky sedí.

Při tom jsem si uvědomil dvě věci. Parcela 77 a parcela st. 77 jsou dvě různé parcely ve stejném území, takže číslo parcely musím skládat i s druhem číslování. A některé údaje chybí – třeba způsob využití je u většiny parcel prázdný – takže s tím aplikace musí počítat.

Na převod kódů na text používám oficiální číselníky ČÚZK místo vlastní tabulky v kódu. Nemusím nic přepisovat ručně a když ČÚZK číselník změní, stačí ho stáhnout znovu.

## 4. 10. – Struktura projektu

    public/      index.html, app.js, style.css, api.php
    src/         PHP funkce
    scripts/     stažení, převod a import dat
    config.php
    data/        stažená data a databáze (není v gitu)

Server servíruje jen složku `public/`. Kdyby servíroval celý projekt, šlo by si v prohlížeči stáhnout `config.php` nebo rovnou celou databázi. Frontend i API běží na stejné adrese, takže nemusím řešit CORS. Zvažoval jsem oddělené složky `backend/` a `frontend/`, ale na takhle malý projekt mi to přišlo zbytečné.

## 4. 10. – Databáze

Schéma je ve `scripts/schema.sql`. Hlavní tabulka `parcely` a tři číselníky – druhy pozemků, způsoby využití a katastrální území.

Původně jsem měl všechny číselníky v jedné tabulce, ale jsou to tři nesouvisející seznamy, tak jsem je rozdělil. Jako primární klíč parcely beru `Id` z RÚIAN, protože číslo parcely se v různých územích opakuje. Číslo parcely neukládám, skládám ho v PHP z jeho částí.

SQLite neumí polygony, takže geometrii ukládám jako text GeoJSON a vedle ní ohraničující obdélník ve čtyřech sloupcích. Hledání parcel ve výřezu je pak obyčejné porovnávání čísel. Chtěl jsem použít prostorový index R*Tree, který SQLite má, ale PHP na Windows ho nemá zkompilovaný, takže jsem použil obyčejný index.

## 4. 10. – Import a 45 sekund

První verze importu běžela asi 45 sekund. Po zabalení do transakce trvá 0,5 sekundy. Bez transakce SQLite po každém z 16 953 INSERTů čeká, až disk potvrdí zápis. S transakcí zapíše všechno najednou. Dostal jsem tím zadarmo i to, že když import uprostřed spadne, databáze nezůstane napůl naplněná.

Pár věcí mě potrápilo:

- Číselníky ČÚZK jsou ve Windows-1250 se středníkem, CSV z ogr2ogr v UTF-8 s čárkou. `mb_convert_encoding` Windows-1250 nezná, fungoval až `iconv`.
- `ogr2ogr` chybějící hodnoty v GeoJSON vůbec nezapíše, takže musím počítat s tím, že klíč nemusí existovat.
- Import mi jednou „úspěšně“ doběhl se záhlavím CSV uloženým jako řádek a podruhé s druhy pozemků místo způsobů využití. Od té doby skript vypisuje počty řádků a já je kontroluju: má vyjít 11, 30, 5 a 16 953.


## 4. 10. – Na kterou parcelu uživatel klikl

Hledám ve dvou krocích. Databáze nejdřív vrátí parcely, jejichž obdélník obsahuje kliknutý bod – obvykle jich je několik, protože obdélníky sousedů se překrývají. Pak v PHP u každé ověřím, jestli bod leží opravdu uvnitř polygonu. Používám metodu paprsku: z bodu vedu vodorovnou čáru a počítám, kolikrát protne hranici parcely. Lichý počet znamená, že bod je uvnitř. Funguje to i pro parcely s dírou uprostřed.

Funkci jsem ověřil tak, že jsem do ní pustil všech 16 953 definičních bodů a každý vyšel uvnitř své parcely.

Při psaní dotazu jsem narazil na to, že obyčejný `JOIN` na způsob využití vrátil jen 4 449 parcel z 16 953, protože ostatní způsob využití nemají. S `LEFT JOIN` se vrátí všechny.

## 4. 10. – API

`api.php` má dvě akce: `detail` vrátí parcelu v bodě a `parcely` vrátí parcely ve výřezu. Vstupy kontroluju, takže když někdo pošle `lon=abc`, dostane chybu 400 s vysvětlením místo pádu. Když v bodě žádná parcela není, vrací se 404. Při chybě na serveru uživatel uvidí jen „Interní chyba serveru“ a podrobnosti jdou do logu, aby text výjimky neprozradil cesty nebo strukturu databáze.

## 4. 10. – Parcely ve výřezu

Tady jsem nejvíc měřil.

Geometrie je v databázi uložená jako hotový text JSON. Zkusil jsem dvě varianty: buď ji dekódovat a celou odpověď znovu zakódovat, nebo text rovnou vložit do odpovědi. Pro 4 542 parcel trvá první varianta 87 ms a druhá 4 ms, s úplně stejným výsledkem. Vkládání je bezpečné, protože do databáze geometrii ukládá můj import přes `json_encode`.

Pak jsem změřil, kolik dat by šlo do prohlížeče v nejhustším místě, v centru Jičína:

| Zoom | Parcel | Velikost odpovědi |
|---|---|---|
| 15 | 10 140 | 4,5 MB |
| 16 | 4 542 | 2,2 MB |

Zoom 15 je moc, takže parcely kreslím až od zoomu 16. Zapnul jsem ještě kompresi gzip, což je v PHP jeden řádek, a odpověď klesla z 2,2 MB na asi 400 kB. Velikost výřezu jsem na serveru omezil, aby si nikdo nemohl říct o všechna data najednou.

## 4. 10. – Mapa

Při stavbě mapy jsem zjistil, že WMS ČÚZK kreslí parcely až od měřítka 1 : 5 000, což je zhruba zoom 17. Můj původní plán ukazovat obrázek parcel na malém přiblížení tedy padl. Ve stejné službě jsem ale našel přehledku katastrálních území, takže na malém přiblížení ukazuju hranice a názvy území a hlášku, ať uživatel mapu přiblíží.

Pro plynulost se ukázalo jako nejdůležitější přepnout Leaflet na kreslení do canvasu. Normálně by z každé parcely udělal samostatný prvek SVG a se 4 500 parcelami by se mapa při posouvání sekala. Parcely navíc nereagují na myš – kliknutí řeší mapa a server –, takže prohlížeč nemusí u každého polygonu hlídat, jestli je nad ním kurzor.

Parcely se načítají až po skončení posunu mapy. Když uživatel posune mapu znovu dřív, než dorazí odpověď, starý požadavek zruším, aby pomalejší starší odpověď nepřepsala novější.

## 4. 10. – Co dalšího ukázat v detailu

Prošel jsem, co dalšího v datech je: bonita půdy (BPEJ) u 6 933 parcel, způsob ochrany pozemku u 11 854 parcel a 5 317 staveb s číslem popisným, počtem podlaží a bytů. Všechno by znamenalo další tabulky a číselníky, tak jsem to zatím odložil.

Vlastníci v otevřených datech nejsou, jsou to osobní údaje. Zjistil jsem ale, že `Id` parcely z RÚIAN je stejné jako v Nahlížení do KN, takže v detailu je odkaz, který otevře přímo tu parcelu i s vlastníky. Aplikace sama žádné osobní údaje nemá.

## 4. 10. – Zvýraznění parcely a jedna záludná chyba

Po kliknutí se parcela zvýrazní, aby bylo vidět, ke které patří detail. Funguje to i na přiblížení, kde se parcely nekreslí, protože geometrii posílá API spolu s detailem.

Při testování jsem narazil na to, že po kliknutí na druhou parcelu zvýraznění zmizelo. Leaflet při otevření nového okna s detailem nejdřív zavře to staré, tím vyvolá událost „zavřeno“ a můj kód na ni zvýraznění smazal – jenže až potom, co jsem nakreslil to nové. Stačilo prohodit pořadí. Poučil jsem se, že u událostí může kód, který vypadá nezávisle, spustit něco úplně jiného.

## 4. 10. – Čísla parcel na mapě

Čísla parcel ukazuju až od zoomu 18. Na zoomu 16 by jich bylo přes 4 000 a překrývala by se, na zoomu 18 jich je na obrazovce kolem 400. Číslo stojí na definičním bodu parcely, ne v jejím středu – u parcely tvaru L nebo U by střed mohl ležet mimo ni.

## 4. 10. – Stažení dat jedním příkazem

Nakonec jsem napsal `scripts/stahni.php`, který stáhne data obce i číselníky přímo z ČÚZK. Celá příprava dat jsou teď tři příkazy – stažení, převod a import. Ověřil jsem to tak, že jsem smazal celou složku `data/` a pustil je znovu.

ČÚZK vydává data vždy k poslednímu dni měsíce, takže skript zkusí minulý měsíc, a když tam soubor ještě není, jde až tři měsíce zpátky. Stahování jsem nedal do importu, protože mezi nimi je převod přes `ogr2ogr` a import pouštím mnohem častěji, než potřebuju stahovat nová data.

## Co bych dělal s víc časem

- **Celý okres:** vektorové dlaždice z PostGIS s cache. Každá dlaždice se načte jednou a parcely by šly ukázat i na menším přiblížení.
- **Skutečný prostorový index** místo obdélníku v obyčejných sloupcích.
- **Pamatovat si načtené parcely** – teď se při posunu mapy načítají znovu i ty, které už byly vidět.
- **Testy v PHPUnit** – hlavně pro skládání čísla parcely, test bodu v polygonu a stavové kódy API.
- **Třídy a Composer** místo funkcí. Pro takhle malý projekt mi stačily funkce, ale s dalšími funkcemi by se to hodilo.
- **Převod souřadnic bez QGIS**, aby nebylo nutné ho instalovat.
- **Další údaje v detailu** – BPEJ, ochrana pozemku, stavba na parcele.
