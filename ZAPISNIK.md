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
