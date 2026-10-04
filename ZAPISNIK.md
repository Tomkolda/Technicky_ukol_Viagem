# Výběr katastrálních území
- Jičín (okres Jičín) - 659541
- Hubálov (okres Jičín) - 771775
- Moravčice (okres Jičín) - 740217
- Popovice u Jičína (okres Jičín) - 725838
- Robousy (okres Jičín) - 740225

- Vybral jsem všech 5 katastrálních území obce Jičín, z důvodu toho že je tímto pokryta celá obec Jičín a není nutné filtrovat data z RÚIAN, jelikož se zde data získávají na celou obec.

- Území jedné obce ale neověří plynulost aplikace při zobrazení celého okresu.

# Výběr zdroje dat

| | INSPIRE (Katastrální parcely) | RÚIAN (VFR) |
|---|---|---|
| Parcelní číslo | ano, hotový text (`label`) | ano, rozdělené (`KmenoveCislo`, `PododdeleniCisla`, `DruhCislovaniKod`) |
| Výměra | ano (`areaValue`) | ano (`VymeraParcely`, m²) |
| Druh pozemku | ne | ano (`DruhPozemkuKod`, kód) |
| Způsob využití | ne | ano (`ZpusobyVyuzitiPozemku`, kód) |
| Stahování po | katastrálních územích | obcích |
| Formát | GML 3.2.1 | VFR (XML s GML geometrií) |
| Souřadnicový systém | S-JTSK (5514) nebo ETRS89 (4258) | S-JTSK (5514)|

**Rozhodnutí:** Rozhodl jsem se využít data pomocí RÚIAN

**Proč:** Obsahuje informace důležité při výkupu pozemku, např. o jaký druh pozemku se jedná, nebo způsob užití pozemku, tyto informace INSPIRE neposkytuje dle dokumentace

**Nevýhoda:** RÚIAN udává druh a způsob užití pozemku pomocí kodu, proto bude potřeba tyto kody převádět

# Stažení dat

Data RÚIAN se nabízí v několika podobných formátech, každý obsahuje něco jiného:

- **SHP export RÚIAN po obcích** – obsahuje katastrální území, ulice, adresy, stavební objekty, ale **ne parcely**.
- **VFK (výměnný formát katastru)** – parcely nejsou jako polygony, ale jako body a hranice, které by se musely složit. Navíc v něm chyběly atributy parcel (druh pozemku, výměra).
- **VFR (výměnný formát RÚIAN)**, soubor `OB_572659_UKSH` – obsahuje parcely s polygonem i všemi atributy. **Ten jsem použil.**

Název souboru: OB = obec, U = úplná data, K = kompletní (s geometrií), S = současná data, H = originální hranice.

Zjištění z dat:
- 16 953 parcel v 5 katastrálních územích
- souřadnicový systém S-JTSK (EPSG:5514), ověřeno přes `srsName` v souboru
- souřadnice jsou záporné (ve VFK kladné), na to si dát pozor při převodu
- u parcel je navíc způsob ochrany pozemku a definiční bod

**Překvapilo mě:** že ČÚZK má tři podobně pojmenované formáty a jen jeden obsahuje to, co potřebuji.

Výkon: od jakého přiblížení zobrazovat parcely

**Problém:** načtení všech parcel okresu najednou by aplikaci zaseklo kvůli:
1. přenosu dat (stovky MB),
2. paměti prohlížeče,
3. překreslování při každém posunu mapy.

**Výpočet:**
- Celý okres (~50 km) na monitoru s 1 900 px → ~26 m na pixel.
- Průměrná parcela v obci Jičín: ~25 km² / 16 953 parcel ≈ 1 500 m², tedy ~39 × 39 m → na obrazovce ~1,5 px.
- Aby byla parcela 20 px široká: 39 m / 20 px ≈ 1,95 m/px → na obrazovce je vidět ~3,7 km.
- To odpovídá zoomu webové mapy zhruba 15–16.

**Závěr:** při pohledu na celý okres nejsou parcely vidět, takže nemá smysl je posílat. Parcely zobrazovat až od zoomu ~15.


**Co zobrazit při menším přiblížení?**
- Katastrální mapu jako obrázek ze služby WMS od ČÚZK. Obrázek má stejnou velikost bez ohledu na počet parcel, takže je rychlý i nad celým okresem.
- Na obrázek nejde kliknout, proto kliknutí řeší backend: prohlížeč pošle souřadnice bodu a PHP najde v databázi parcelu, která v tom bodě leží. Funguje to při jakémkoli přiblížení.
- Kompromis: v tomto bodě aplikace závisí na dostupnosti služby ČÚZK.
- Ověřit: od jakého měřítka WMS ČÚZK parcely vůbec vykresluje.

**Jak zrychlit načítání tisíců parcel i při zoomu 15?**
- Načítat jen parcely ve viditelném výřezu mapy, ne všechny.
- Data převést předem (ogr2ogr: VFR → GeoJSON ve WGS84) a uložit do SQLite. Ke každé parcele uložit ohraničující obdélník, podle kterého se rychle vyhledává.
- Zvolil jsem jednodušší řešení (SQLite, GeoJSON, Leaflet), protože pro 5 katastrálních území stačí a dá se rychle postavit a změřit.
- Slabina: při posunu mapy se parcely načítají znovu, i ty, které už byly vidět.
- S víc časem / pro celý okres: vektorové dlaždice (MVT) z PostGIS s cache na disku. Dlaždice se načtou jen jednou a data se mění jen jednou měsíčně.

# Převod dat (ogr2ogr)

Soubor VFR jde otevřít v QGIS i v `ogr2ogr` přes obecný GML ovladač (speciální VFR ovladač v GDAL 3.13 není).
**Problém:** vrstva Parcely má tři geometrie – `DefinicniBod` (bod), `OriginalniHranice` (polygon) a `OriginalniHraniceOmpv` (multipolygon). QGIS nabízí jen první z nich, takže se parcely zobrazily jako body.
**Řešení:** převod přes `ogr2ogr` do GeoPackage, kde se v `-select` uvede jen geometrie `OriginalniHranice`:
    ogr2ogr -f GPKG data\parcely.gpkg data\20260930_OB_572659_UKSH.xml Parcely -select "OriginalniHranice,Id,KmenoveCislo,PododdeleniCisla,DruhCislovaniKod,VymeraParcely,DruhPozemkuKod,ZpusobyVyuzitiPozemku,KatastralniUzemiKod" -nln parcely
- Výsledek: 16 953 parcel jako polygony, soubor 6 MB (původní XML 36 MB).
- Nad podkladem OpenStreetMap parcely sedí na domy a ulice, takže souřadnice jsou v pořádku.
- Zatím nevyřešeno: co znamená `OriginalniHraniceOmpv` a jestli ji můžu ignorovat. BPEJ (`BonitovanyDil…`) zatím nepřevádím, protože jde o seznamy hodnot.

# Ověření dat proti katastru

Ověřeno na parcele **st. 77, k. ú. Jičín** (Valdštejnovo náměstí) v Nahlížení do KN:
- výměra 509 m² – sedí
- druh pozemku kód 13 = „zastavěná plocha a nádvoří“ – sedí
Co z toho plyne pro aplikaci:
- číslo parcely je potřeba sestavit z `DruhCislovaniKod` (1 = stavební, „st.“), `KmenoveCislo` a `PododdeleniCisla` (za lomítkem)
- parcela č. 77 a st. 77 jsou dvě různé parcely ve stejném k. ú.
- některé hodnoty chybí (např. způsob využití je NULL), aplikace s tím musí počítat

# Převod kódů na text (číselníky)

**Rozhodnutí:** kódy (druh pozemku, způsob využití) převádím pomocí oficiálních číselníků ČÚZK, které naimportuji do databáze.
**Proč:** jde o oficiální zdroj, nemusím hodnoty ručně přepisovat (riziko překlepu) a při změně číselníku stačí stáhnout novou verzi bez úpravy kódu.¨

# Struktura projektu

    public/      – document root: index.html, app.js, style.css, api.php
    src/         – PHP třídy (mimo document root)
    config.php   – konfigurace (mimo document root)
    scripts/     – import dat
    data/        – stažená data a databáze SQLite (v .gitignore)
**Rozhodnutí 2: frontend i API obsluhuje jeden server (`php -S localhost:8000 -t public`).**
**Proč:** kdyby frontend a API běžely na různých adresách nebo portech, prohlížeč by požadavky na API blokoval (CORS) a musel bych na serveru nastavovat povolující hlavičky. Vestavěný PHP server statické soubory (HTML, JS, CSS) posílá sám a PHP soubory spouští, takže stačí jedna adresa.
**Zvažoval jsem:** oddělené složky `backend/` a `frontend/`. Pro takhle malý projekt by to bylo zbytečné zanořování.

# Návrh databáze (SQLite)

Schéma je v `scripts/schema.sql`. Tabulky:

- `parcely` – atributy parcely, geometrie jako GeoJSON (WGS84), ohraničující obdélník a definiční bod
- `druhy_pozemku`, `zpusoby_vyuziti`, `katastralni_uzemi` – číselníky (`kod`, `nazev`)

**Primární klíč parcely:** `Id` z RÚIAN. Je jednoznačné v celé ČR, na rozdíl od čísla parcely, které se opakuje v různých k. ú. a liší se jen druhem číslování (77 vs. st. 77).

**Jen potřebné sloupce:** interní údaje ČÚZK (`IdTransakce`, `RizeniId`, `PlatiOd`) neukládám, aplikace je nepotřebuje.

**Geometrie jako GeoJSON text:** SQLite nemá typ pro polygony. GeoJSON umí prohlížeč i Leaflet přečíst přímo, takže ho PHP pošle bez převádění.

**Číselníky – každý ve vlastní tabulce:** původně jsem je dal do jedné tabulky, ale jsou to tři nezávislé seznamy s různým počtem položek, takže by jeden řádek míchal nesouvisející údaje (normalizace). Primárním klíčem je přímo `kod`, takže se kód nemůže opakovat.

**Číslo parcely skládám v PHP, neukládám ho:** je odvozené z `druh_cislovani`, `kmenove_cislo` a `pododdeleni`. 

# Import do SQLite a transakce
Import je ve `scripts/import.php`: smaže starou databázi, vytvoří tabulky ze `schema.sql`, naimportuje číselníky (CSV) a parcely (GeoJSON z `prevod.bat`).
Výsledek: 11 druhů pozemku, 30 způsobů využití, 5 katastrálních území, 16 953 parcel.
**Měření rychlosti:**
| Varianta | Čas |
|---|---|
| bez transakce | ~45 s |
| s transakcí | 0,5 s |
**Proč:** bez transakce SQLite bere každý INSERT jako samostatnou transakci a po každém čeká, až disk potvrdí zápis, tedy 16 953× čekání na disk. S transakcí se zapisuje jednou na konci.
**Výhoda navíc:** když import uprostřed selže, neuloží se nic a databáze nezůstane napůl naplněná.
**Co mě překvapilo:**
- číselníky ČÚZK jsou v kódování Windows-1250 se středníkem, kdežto CSV z ogr2ogr je v UTF-8 s čárkou. `mb_convert_encoding` Windows-1250 nezná, funguje `iconv`.
- `ogr2ogr` u chybějících hodnot (poddělení, způsob využití) klíč v GeoJSON úplně vynechá, proto `?? null`.
- skript „úspěšně“ doběhl i se špatnými daty (záhlaví CSV uložené jako řádek s kódem 0, druhy pozemku místo způsobů využití). Počty řádků je potřeba kontrolovat proti očekávání, ne jen sledovat, jestli skript nespadl.
**Limit:** soubory GeoJSON se načítají celé do paměti (`json_decode`). Pro 5 k. ú. (9 MB) to nevadí, pro celý okres by bylo potřeba číst soubor postupně.
