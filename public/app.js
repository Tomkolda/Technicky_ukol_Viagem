'use strict';

const MIN_ZOOM_PARCEL = 16;
const STRED_JICINA = [50.437, 15.352];
const NAHLIZENI_DO_KN = 'https://nahlizenidokn.cuzk.gov.cz/ZobrazObjekt.aspx';

const mapa = L.map('mapa', { preferCanvas: true }).setView(STRED_JICINA, MIN_ZOOM_PARCEL);

L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 19,
    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
}).addTo(mapa);

L.tileLayer.wms('https://services.cuzk.gov.cz/wms/wms.asp', {
    layers: 'prehledka_kat_uz',
    format: 'image/png',
    transparent: true,
    maxZoom: MIN_ZOOM_PARCEL - 1,
    attribution: '&copy; <a href="https://cuzk.gov.cz">ČÚZK</a>',
}).addTo(mapa);

const vrstvaParcel = L.geoJSON(null, {
    interactive: false,
    style: { color: '#d33', weight: 1, fillOpacity: 0.05 },
}).addTo(mapa);

mapa.createPane('vybranaParcela').style.zIndex = 450;

const vrstvaVybrane = L.geoJSON(null, {
    pane: 'vybranaParcela',
    interactive: false,
    style: { color: '#1565c0', weight: 3, fillOpacity: 0.25 },
}).addTo(mapa);

const hlaska = document.getElementById('hlaska');
let probihajiciNacitani = null;

async function nactiParcely() {
    probihajiciNacitani?.abort();

    const jeDostatecnePriblizeno = mapa.getZoom() >= MIN_ZOOM_PARCEL;
    hlaska.hidden = jeDostatecnePriblizeno;
    if (!jeDostatecnePriblizeno) {
        vrstvaParcel.clearLayers();
        return;
    }

    const vyrez = mapa.getBounds();
    const parametry = new URLSearchParams({
        akce: 'parcely',
        zapad: vyrez.getWest(),
        jih: vyrez.getSouth(),
        vychod: vyrez.getEast(),
        sever: vyrez.getNorth(),
    });

    probihajiciNacitani = new AbortController();
    try {
        const odpoved = await fetch(`api.php?${parametry}`, { signal: probihajiciNacitani.signal });
        if (!odpoved.ok) {
            throw new Error((await odpoved.json()).chyba);
        }
        const parcely = await odpoved.json();
        vrstvaParcel.clearLayers();
        vrstvaParcel.addData(parcely);
    } catch (chyba) {
        if (chyba.name !== 'AbortError') {
            console.error('Načtení parcel selhalo:', chyba);
        }
    }
}

function zobrazDetail(misto, parcela) {
    const radky = [
        ['Katastrální území', parcela.katastralni_uzemi],
        ['Výměra', `${parcela.vymera.toLocaleString('cs-CZ')} m²`],
        ['Druh pozemku', parcela.druh_pozemku],
        ['Způsob využití', parcela.zpusob_vyuziti ?? '–'],
    ];

    const tabulka = document.createElement('table');
    tabulka.className = 'detail';
    for (const [nazev, hodnota] of radky) {
        const radek = tabulka.insertRow();
        radek.appendChild(document.createElement('th')).textContent = nazev;
        radek.insertCell().textContent = hodnota;
    }

    const obsah = document.createElement('div');
    obsah.appendChild(document.createElement('strong')).textContent = `Parcela ${parcela.cislo}`;
    obsah.appendChild(tabulka);
    const odkaz = obsah.appendChild(document.createElement('a'));
    odkaz.href = `${NAHLIZENI_DO_KN}?${new URLSearchParams({ typ: 'parcela', id: parcela.id })}`;
    odkaz.target = '_blank';
    odkaz.rel = 'noopener';
    odkaz.textContent = 'Zobrazit v Nahlížení do KN (vlastníci, LV)';
    L.popup().setLatLng(misto).setContent(obsah).openOn(mapa);
    vrstvaVybrane.clearLayers();
    vrstvaVybrane.addData(parcela.geometrie);
}

async function nactiDetail(udalost) {
    vrstvaVybrane.clearLayers();
    const parametry = new URLSearchParams({
        akce: 'detail',
        lon: udalost.latlng.lng,
        lat: udalost.latlng.lat,
    });

    const odpoved = await fetch(`api.php?${parametry}`);
    if (odpoved.status === 404) {
        L.popup().setLatLng(udalost.latlng).setContent('Zde není žádná parcela').openOn(mapa);
        return;
    }
    if (!odpoved.ok) {
        console.error('Načtení detailu selhalo:', (await odpoved.json()).chyba);
        return;
    }

    zobrazDetail(udalost.latlng, await odpoved.json());
}

mapa.on('moveend', nactiParcely);
mapa.on('click', nactiDetail);
mapa.on('popupclose', () => vrstvaVybrane.clearLayers());
nactiParcely();