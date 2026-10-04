CREATE TABLE IF NOT EXISTS parcely (
    id INTEGER PRIMARY KEY,
    katastralni_uzemi_kod INTEGER NOT NULL,
    druh_cislovani INTEGER NOT NULL,
    kmenove_cislo INTEGER NOT NULL,
    pododdeleni INTEGER,
    vymera INTEGER NOT NULL,
    druh_pozemku_kod INTEGER NOT NULL,
    zpusob_vyuziti_kod INTEGER,
    geometrie TEXT NOT NULL,
    min_lon REAL NOT NULL,
    min_lat REAL NOT NULL,
    max_lon REAL NOT NULL,
    max_lat REAL NOT NULL,
    bod_lon REAL NOT NULL,
    bod_lat REAL NOT NULL
);

CREATE TABLE IF NOT EXISTS druhy_pozemku (
    kod INTEGER PRIMARY KEY,
    nazev TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS zpusoby_vyuziti (
    kod INTEGER PRIMARY KEY,
    nazev TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS katastralni_uzemi (
    kod INTEGER PRIMARY KEY,
    nazev TEXT NOT NULL
);

CREATE INDEX IF NOT EXISTS idx_parcely_min_lat_min_lon ON parcely (min_lat, min_lon);
