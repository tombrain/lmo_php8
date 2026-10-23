# Installation, Update und Addons

Diese Seite beschreibt, was mit den Dateien bei einer Installation, bei einem Update und beim
Hinzufügen oder Entfernen eines Addons geschieht. Den Aufbau der Ordner und die Schnittstellen
für Addons beschreibt [ordnerstruktur.md](ordnerstruktur.md).

Kurz gesagt: **Programm** (alles aus dem Paket, auch `addon/<name>/`) wird ersetzt. **Laufzeit**
(`config/`, `daten/`, `ligen/`, `output/`) bleibt.

## 1. Neuinstallation

```
composer require lmo/lmo_php8
vendor/bin/lmo-install
```

**`lmo-install`** (`Lmo\Install\Installer`):

- kopiert `lmo/` aus dem Paket in den Webordner;
- merkt sich jede kopierte Programmdatei in `config/.install-manifest`;
- legt in `config/`, `daten/`, `ligen/` und `output/` nur an, was fehlt (die `.htaccess`-Sperren,
  die Beispielliga).

**Erster Aufruf von `lmo.php`** (`lmo-setup.php`):

- `config-default/cfg.txt` → `config/cfg.txt`
- je Addon `addon/<name>/config-default/cfg.txt` → `config/<name>/cfg.txt`
- legt die Ordner `ligen/archiv/`, `output/` und `daten/` an

**Beim Laden der Addons** legt jedes seinen Datenordner selbst an, z. B.:

- Tippspiel: `daten/tipp/` mit `tipps/`, `tipps/auswert/`, `tipps/auswert/vereine/`,
  `tipps/einsicht/` und einer leeren Tipperdatei `lmo-tippauth.txt`
- Spielerstatistik: `daten/spieler/`

**Erster Aufruf von `lmoadmin.php`:** Hier legst du das Konto des Admins an
(`config/lmo-auth.php`).

## 2. Update

```
composer update
```

`composer update` ruft `lmo-install` von selbst auf.

| Was | Was passiert |
|---|---|
| Programm (`*.php`, `vorlagen/`, `lang/`, `addon/…`) | wird überschrieben; Dateien, die nicht mehr im Paket sind, werden gelöscht, aber nur solche aus dem Manifest |
| Laufzeit (`config/`, `daten/`, `ligen/`, `output/`, `.htaccess`) | bleibt unberührt; nur fehlende Dateien werden angelegt |

Beim nächsten Aufruf:

- Hat sich eine Vorlage geändert, hängt `lmo-setup.php` neue Schlüssel an die vorhandenen
  `cfg.txt` an. Vorhandene Werte bleiben.
- Addons, deren Daten bis 4.5 im eigenen Ordner lagen, verschieben sie einmal nach
  `daten/<name>/`:
  - Spielerstatistik: `addon/spieler/stats/` → `daten/spieler/`
  - Tippspiel: `addon/tipp/lmo-tippauth.txt` und `addon/tipp/tipps/` → `daten/tipp/`

  Scheitert das Verschieben (etwa ohne Schreibrechte), bleibt alles am alten Ort und wird dort
  weiter benutzt.

Eigene Dateien im Webordner, die nicht aus dem Paket stammen, lässt ein Update immer liegen. Der
Installer löscht nur, was er selbst kopiert hat.

## 3. Addon hinzufügen

Den Ordner des Addons nach `lmo/addon/<name>/` kopieren. Beim nächsten Aufruf passiert der Rest
von selbst:

- `addon.php` wird gefunden, die Klassen lädt der Autoloader, die Texte kommen aus `lang/`.
- Die Kennung der Vorlagen umfasst auch die Ordner der Addons. Mit dem neuen Addon ändert sie
  sich, und `lmo-setup.php` legt `config/<name>/cfg.txt` aus der Vorlage des Addons an.
- Menüpunkte, Seiten und Schalter erscheinen, weil das Addon sich an den Erweiterungspunkten
  anmeldet.
- Seinen Datenordner `daten/<name>/` legt das Addon selbst an.

Ein selbst hinzugefügtes Addon übersteht spätere Updates. Es steht nicht im Manifest, wird also
weder überschrieben noch gelöscht. Ausnahme: Das Paket bringt irgendwann ein Addon mit demselben
Namen mit, dann werden dessen Dateien überschrieben.

## 4. Addon entfernen

Den Ordner `lmo/addon/<name>/` löschen.

- Die Erweiterungspunkte fallen weg: kein Menüpunkt, keine Seiten, kein Schalter. Die
  Sprachtexte gehen mit dem Ordner.
- `config/<name>/` und `daten/<name>/` bleiben stehen. Spielt man das Addon wieder ein, sind
  Einstellungen und Daten wieder da. Wer sie nicht mehr braucht, löscht beide Ordner von Hand.
- Die Seite „Optionen › Addons“ der Administration zeigt die Einstellungen weiter an, solange
  `config/<name>/cfg.txt` existiert.

### Addons aus dem Paket

Tippspiel, Spielerstatistik, Ticker, Viewer, Mini und Classlib gehören zum Paket. Löscht man
einen dieser Ordner, holt ihn das nächste `composer update` zurück. Abschalten lassen sich
Tippspiel und Spielerstatistik dauerhaft nur über ihren Schalter in der Administration
(„Optionen › Anzeigen/Darstellung“, Block „Addons“), nicht durch Löschen.

**`classlib` brauchen Viewer und Mini:** Der Kern kommt ohne sie aus. Viewer und Mini binden
`addon/classlib/ini.php` aber selbst ein; ohne den Ordner brechen ihre Seiten ab. Ein eigenes
Addon, das Klassen oder Funktionen der Classlib benutzt, bindet sie ebenso selbst ein.

## 5. Bekannte Einschränkungen

Einige Stellen im alten Code des Kerns sprechen Addons noch direkt an. Damit ein entferntes Addon
keine Fehler verursacht, prüfen sie, ob es geladen ist (`$kontext->addons->hat('<name>')`):

- **Ergebnisse eines Spieltags**: die Spalte „ohne Tippwertung“ und die Auswertung beim Speichern
  liefert das Tippspiel selbst über die Erweiterungspunkte `MitErgebnisSpalten` und
  `MitErgebnisAuswertung`; ohne `addon/tipp/` fehlen beide.
- **Grundeinstellungen einer Liga**: die Option „Spielerstatistik“ liefert das Addon selbst über
  den Erweiterungspunkt `MitLigaEinstellungen`; ohne `addon/spieler/` fehlt sie

Ist für die eingestellte Startseite einer Liga niemand zuständig (etwa „Spielerstatistik“,
`OnRun=7`, ohne `addon/spieler/`), zeigt der LMO die Tabelle, im Pokal die Ergebnisse.
