@echo off
cd /d "%~dp0.."
if exist data\parcely.geojson del data\parcely.geojson
if exist data\body.geojson del data\body.geojson
if exist data\katastralni_uzemi.csv del data\katastralni_uzemi.csv
call "C:\Program Files\QGIS 4.2.3\bin\o4w_env.bat"
ogr2ogr -f GeoJSON data\parcely.geojson data\obec.xml Parcely -select "OriginalniHranice,Id,KmenoveCislo,PododdeleniCisla,DruhCislovaniKod,VymeraParcely,DruhPozemkuKod,ZpusobyVyuzitiPozemku,KatastralniUzemiKod" -t_srs EPSG:4326 -lco RFC7946=YES -lco COORDINATE_PRECISION=7
ogr2ogr -f GeoJSON data\body.geojson data\obec.xml Parcely -select "DefinicniBod,Id" -t_srs EPSG:4326 -lco RFC7946=YES -lco COORDINATE_PRECISION=7
ogr2ogr -f CSV data\katastralni_uzemi.csv data\obec.xml KatastralniUzemi -select "Kod,Nazev"