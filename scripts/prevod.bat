@echo off
cd /d "%~dp0.."
call "C:\Program Files\QGIS 4.2.3\bin\o4w_env.bat"
ogr2ogr -f GeoJSON data\parcely.geojson data\20260930_OB_572659_UKSH.xml Parcely -select "OriginalniHranice,Id,KmenoveCislo,PododdeleniCisla,DruhCislovaniKod,VymeraParcely,DruhPozemkuKod,ZpusobyVyuzitiPozemku,KatastralniUzemiKod" -t_srs EPSG:4326 -lco RFC7946=YES -lco COORDINATE_PRECISION=7
ogr2ogr -f GeoJSON data\body.geojson data\20260930_OB_572659_UKSH.xml Parcely -select "DefinicniBod,Id" -t_srs EPSG:4326 -lco RFC7946=YES -lco COORDINATE_PRECISION=7
ogr2ogr -f CSV data\katastralni_uzemi.csv data\20260930_OB_572659_UKSH.xml KatastralniUzemi -select "Kod,Nazev"
