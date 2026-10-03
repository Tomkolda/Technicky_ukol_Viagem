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
| Souřadnicový systém | S-JTSK (5514) nebo ETRS89 (4258) | S-JTSK (5514) |

**Rozhodnutí:** Rozhodl jsem se využít data pomocí RÚIAN

**Proč:** Obsahuje informace důležité při výkupu pozemku, např. o jaký druh pozemku se jedná, nebo způsob užití pozemku, tyto informace INSPIRE neposkytuje dle dokumentace

**Nevýhoda:** RÚIAN udává druh a způsob užití pozemku pomocí kodu, prot bude potřeba tyto kody převádět