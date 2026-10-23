# Ordnerstruktur und Addons

Diese Seite beschreibt, wie der LMO aufgebaut ist: was im Paket steckt, was beim Betreiber
liegt, was bei einem Update ersetzt wird und was bleibt. Der zweite Teil erklärt, wie Addons
eingebunden werden und was ein Addon mitbringen muss.

Die Grundregel: **Programm und Daten sind getrennt.** Programm kommt aus dem Paket und wird bei
jedem Update ersetzt. Daten entstehen beim Betrieb und werden nie angefasst.

Was dabei Schritt für Schritt mit den Dateien geschieht (Installation, Update, Addon hinzufügen
oder entfernen), steht in [installation-und-addons.md](installation-und-addons.md).

## 1. Das Paket

So liefert Composer `lmo/lmo_php8` aus (gleich dem Repository):

```
lmo_php8/
├── composer.json
├── bin/lmo-install              richtet den Webordner ein und gleicht ihn nach Updates ab
├── src/                         Klassen des Kerns, Namensraum Lmo\ (Composer-Autoload)
│   ├── Addon/                   Schnittstelle und Erweiterungspunkte für Addons
│   ├── Install/                 der Installer hinter bin/lmo-install
│   ├── Liga/                    Ligadatei, Einstellungen, Berechnung
│   │   └── Rohdaten/
│   └── Web/                     Kontext, Konfiguration, Pfade, Vorlagen, Rahmen …
│       ├── Admin/               Controller der Administration
│       ├── Controller/          Controller der Besucherseiten
│       └── Seiten/              Bausteine (Ligenliste, Spieltagsmenü …)
├── tests/
├── doku/
└── lmo/                         der Webordner; lmo-install kopiert ihn zum Betreiber
    ├── *.php                    Einstiege (lmo.php, lmoadmin.php) und alter Code des Kerns
    ├── vorlagen/                Twig-Vorlagen des Kerns
    ├── lang/                    Sprachtexte des Kerns (lang-<Sprache>.txt)
    │   └── date/                Namen der Wochentage und Monate
    ├── config-default/cfg.txt   Vorlage der Kern-Konfiguration
    ├── daten/.htaccess          Sperre für den Datenordner (sonst leer)
    ├── ligen/                   Beispielliga
    ├── help/  img/  includes/  js/  template/
    └── addon/
        ├── readme.txt
        └── <name>/              ein Ordner je Addon, siehe Abschnitt 4
```

`src/` bleibt beim Betreiber im Composer-Ordner `vendor/lmo/lmo_php8/src` und wird von dort
geladen. In den Webordner kommt nur `lmo/`.

## 2. Die Laufzeit

So sieht ein Projekt beim Betreiber nach `composer install` aus:

```
projekt/
├── composer.json                ruft lmo-install nach install und update auf
├── vendor/
│   ├── autoload.php
│   └── lmo/lmo_php8/            das Paket
└── lmo/                         der Webordner
    │
    │   Programm (wird bei jedem Update ersetzt)
    ├── *.php  vorlagen/  lang/  help/  img/  includes/  js/  template/
    ├── config-default/
    ├── addon/<name>/            Programm des Addons
    ├── vendor/autoload.php      Verweis auf den Composer-Autoloader des Projekts (legt lmo-install an)
    │
    │   Laufzeit (wird nie überschrieben)
    ├── config/                  Einstellungen der Installation (per .htaccess gesperrt)
    │   ├── cfg.txt              Kern-Konfiguration
    │   ├── lmo-auth.php         Konten der Administration
    │   └── <addon>/cfg.txt      Konfiguration je Addon
    ├── daten/                   Daten der Addons (per .htaccess gesperrt)
    │   └── <addon>/             z. B. tipp/ (Tipper, Tipps), spieler/ (Statistiken)
    ├── ligen/                   Ligadateien (per .htaccess gesperrt)
    └── output/                  erzeugte Dateien (HTML-Export, Statistik)
```

## 3. Was bei einem Update passiert

`composer update` ruft `lmo-install` auf (`Lmo\Install\Installer`):

- **Programmdateien** werden aus dem Paket kopiert und überschreiben die alten. Was nicht mehr im
  Paket ist, wird entfernt. Dafür merkt sich der Installer, was er kopiert hat
  (`config/.install-manifest`). Er löscht also nie Dateien, die er nicht selbst gebracht hat.
- **Laufzeitordner** (`Installer::KEEP`: `config/`, `daten/`, `ligen/`, `output/` und die
  `.htaccess` im Webordner): Dort legt er nur fehlende Dateien an und überschreibt oder löscht
  nichts.

Beim nächsten Aufruf gleicht `lmo-setup.php` die Konfiguration mit den Vorlagen ab. Fehlende
Dateien werden aus `config-default/` und `addon/<name>/config-default/` kopiert, fehlende
Schlüssel an vorhandene `cfg.txt` angehängt. Vorhandene Werte bleiben unverändert. Der Abgleich
läuft nur, wenn sich eine Vorlage geändert hat (`config/.defaults-stamp`).

## 4. Addons

### Grundsätze

- **Ein Ordner ist ein Addon.** Alles, was zum Programm des Addons gehört, liegt in
  `addon/<name>/`. Hineinkopieren reicht: kein Eintrag in `composer.json`, keine Änderung am
  Kern.
- **Der Kern nennt keine Addons.** Er bietet Erweiterungspunkte an, und jedes Addon meldet
  sich dort selbst an.
- **Einstellungen und Daten liegen außerhalb des Addon-Ordners**, in `config/<name>/` und
  `daten/<name>/`. Wer das Addon austauscht oder löscht, verliert sie nicht.

### Aufbau eines Addons

```
addon/<name>/
├── addon.php                 Einstieg: gibt das Addon-Objekt zurück (Pflicht)
├── src/                      Klassen im Namensraum Lmo\Addon\<Name>\
├── vorlagen/                 Twig-Vorlagen
├── lang/lang-<Sprache>.txt   Sprachtexte, Zeilen "Nummer=Text"
├── config-default/cfg.txt    Vorlage der Konfiguration (nach config/<name>/cfg.txt)
└── *.php                     alter Code, solange noch nicht umgebaut
```

Nur `addon.php` ist Pflicht, alles andere nur bei Bedarf.

### Wie der LMO Addons lädt

1. `init.php` registriert `Lmo\Addon\Autoloader`. Eine Klasse
   `Lmo\Addon\<Name>\Teil\Klasse` liegt in `addon/<name>/src/Teil/Klasse.php` (Ordnername klein).
2. `Lmo\Addon\Addons::ausOrdner()` bindet jede `addon/<name>/addon.php` ein, in
   alphabetischer Reihenfolge. Die Datei gibt ein Objekt zurück, das `Lmo\Addon\Addon`
   implementiert. `name()` ist der Name des Ordners.
3. Die Addons stehen danach im `Lmo\Web\Kontext` jeder Anfrage (`$kontext->addons`). Der Kern
   fragt sie an seinen Erweiterungspunkten.

### Erweiterungspunkte

Ein Addon implementiert die Schnittstellen, an denen es etwas beiträgt:

| Schnittstelle | Methode | Wofür |
|---|---|---|
| `MitSeiten` | `controller()` | eigene Seiten im öffentlichen Teil (z. B. `action=spieler`) |
| `MitRahmenteilen` | `rahmenteile()` | Teile des Seitenrahmens, z. B. ein Menüpunkt |
| `MitZusatzJeSpiel` | `zusatzJeSpiel()` | HTML am Ende jeder Ergebniszeile |
| `MitZusatzUnterSeite` | `zusatzUnterSeite()` | HTML unter einer Seite |
| `MitAdminMenue` | `adminMenue()`, `ligaMenue()`, `startseite()` | Punkte in den Menüs der Administration |
| `MitAdminSeiten` | `adminController()` | eigene Seiten in der Administration |
| `MitSchaltern` | `schalter()` | Schalter in den Optionen (z. B. Addon ein/aus) |
| `MitLigaEinstellungen` | `ligaEinstellungen()` | Schalter in den Grundeinstellungen einer Liga (Schlüssel aus `[Options]`, z. B. `mittore`) |
| `MitErgebnisAuswertung` | `auswertungBeimSpeichern()` | Auswertung beim Speichern der Ergebnisse eines Spieltags, noch als Dateien nach alter Art (z. B. Tipps) |
| `MitErgebnisSpalten` | `ergebnisSpalten()` | eigene Spalten je Spiel in den Ergebnissen eines Spieltags (`ErgebnisSpalte`, z. B. „ohne Tippwertung“) |

Bei Seiten gilt diese Reihenfolge: erst die Controller des Kerns, dann die der Addons, zuletzt
die Auffangseiten des Kerns (die Ligenübersicht ohne gewählte Liga). Ein Controller entscheidet
in `zustaendig()` selbst, ob er eine Anfrage übernimmt. Alles, was er braucht (Anfrage,
geladene Liga, Konfiguration, Texte, Pfade, Sitzung), bekommt er im `Kontext`, ohne globale
Variablen.

### Konfiguration

- Die Vorlage liegt in `addon/<name>/config-default/cfg.txt`. `lmo-setup.php` legt daraus
  `config/<name>/cfg.txt` an und ergänzt später neue Schlüssel.
- Lesen: `$kontext->konfiguration->addon('<name>')` (Array Schlüssel => Wert).
- Schalter (`MitSchaltern`): `Konfiguration::addonSchalter('<name>', '<schlüssel>', $vorgabe)`.
  Die Vorgabe gehört ins Addon, nicht in `config-default/`. Sonst würde der Abgleich einen
  abgeschalteten Schalter bei bestehenden Installationen wieder einschalten.
- Alle Werte erscheinen außerdem in der Administration unter „Optionen › Addons“ als Textfelder.

### Sprachtexte

- `addon/<name>/lang/lang-<Sprache>.txt`, Zeilen `Nummer=Text`
- Neuer Code: `$kontext->texteVon('<name>')`; alter Code: `$text['<name>'][Nummer]`
- Bearbeiten lassen sie sich mit dem Übersetzungswerkzeug (`lang/lmo-admintranslate.php`)

### Daten

- Was beim Betrieb entsteht (Tipps, Statistiken …), gehört nach `daten/<name>/`
  (`PATH_TO_DATADIR . '/<name>'`).
- Das Addon legt seinen Ordner und fehlende Dateien selbst an. Leere Platzhalter-Dateien im
  Paket sind nicht nötig.
- Lagen die Daten früher im Addon-Ordner, verschiebt sie `Lmo\Addon\Datenordner::umziehen()`
  einmal. Das geschieht ganz oder gar nicht: Scheitert es, gilt weiter der alte Ort.
- Neuer Code bekommt den Ordner als Parameter, statt die Konstante zu lesen (siehe
  `SpielerAddon::datenordner()`, `TippAddon::datenordner()`).

### Ein kleines Beispiel

```php
<?php
// addon/brain/addon.php
return new class implements \Lmo\Addon\MitZusatzJeSpiel {
    public function name(): string
    {
        return 'brain';
    }

    public function zusatzJeSpiel(\Lmo\Liga\Spiel $spiel, \Lmo\Web\Kontext $kontext): string
    {
        $t = $kontext->texteVon('brain');   // addon/brain/lang/lang-<Sprache>.txt

        return ' <small>' . ($t[1] ?? '') . '</small>';
    }
};
```

Größere Addons legen ihre Klasse in `src/` ab und geben in `addon.php` nur eine Instanz zurück.
Vorbilder im Paket sind `addon/ticker/` (Teil des Rahmens), `addon/spieler/` (eigene Seite,
Menüpunkt, Schalter, Datenordner) und `addon/tipp/` (viele Seiten, Administration).

### Addons nach alter Art

Ältere Addons ohne `addon.php` (etwa PDF oder Stats, nicht Teil des LMO) bindet der Kern über
Brücken ein: `AltesSpielInclude`, `AltesSeitenInclude`, `AltesAdminAddon` und `AlteAdminSeite`
in `src/Addon/`. Sprachtexte unter `lang/<name>/` werden für sie weiter gelesen. Neue Addons
sollen diese Brücken nicht benutzen.

### Regeln für neuen Code

- `declare(strict_types=1)` und PHP 8.1, ohne globale Variablen
- jede neue Klasse und Vorlage mit dem LMO-Lizenzkopf (`SPDX-License-Identifier: GPL-2.0-or-later`)
- Klassen der Addons laufen durch PHPStan auf höchster Stufe (`phpstan-strict.neon.dist`,
  dort den Ordner `src/` des Addons eintragen)
