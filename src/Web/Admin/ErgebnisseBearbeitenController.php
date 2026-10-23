<?php
/** Liga Manager Online 4
  *
  * http://lmo.sourceforge.net/
  *
  * Umgebaut fuer PHP 8 im Rahmen von lmo_php8 (https://github.com/henshingly/lmo_php8).
  *
  * SPDX-License-Identifier: GPL-2.0-or-later
  *
  * REMOVING OR CHANGING THE COPYRIGHT NOTICES IS NOT ALLOWED!
  */

declare(strict_types=1);

namespace Lmo\Web\Admin;

use Closure;
use DateTime;
use Lmo\Addon\ErgebnisSpalte;
use Lmo\Liga\Paarung;
use Lmo\Liga\Rohdaten\Liga;
use Lmo\Liga\Spiel;
use Lmo\Web\Controller\Controller;
use Lmo\Web\Kontext;
use Lmo\Web\Seite;
use Lmo\Web\Seiten\SpieltagsMenue;
use Lmo\Web\Vorlagen;

/**
 * Die Ergebnisse eines Spieltags bearbeiten (todo=edit mit einem Spieltag, Haupt- und
 * Hilfsadmins): Termine, Paarungen (Hauptadmin und erweiterte Hilfsadmins), Tore, Zusatz,
 * Wertung am gruenen Tisch, Notiz, Bericht und die Spalten der Addons (Lmo\Addon\MitErgebnisSpalten);
 * darunter aktueller Spieltag, Ticker und Newszeilen. save=1 speichert, save=990 erstellt den
 * Rueckrundenspielplan (lmo-rueckrunde.php). Gibt es den Spieltag nicht, gilt der aktuelle.
 * Bis 4.5 lmo-adminedit.php.
 */
final class ErgebnisseBearbeitenController implements Controller
{
    private const MONATE = ['0', 'January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];

    /**
     * @param Closure(Liga, string, string): array{string, Liga} $speichere speichert eine Liga wie lmo-saveliga.php (Rohdaten, Datei, Spieltag)
     * @param Closure(Liga, string, string, list<string>): array{string, Liga} $auswerten bindet die Auswertungen der Addons ein (Liga, Datei, Spieltag, Dateien)
     * @param Closure(string): string $rueckrunde erstellt den Rueckrundenspielplan der Datei, gibt die Meldung zurueck
     */
    public function __construct(private Closure $speichere, private Closure $auswerten, private Closure $rueckrunde)
    {
    }

    public function zustaendig(Kontext $kontext): bool
    {
        // die uebrigen Seiten mit todo=edit; st=-10 (Spieltage zuordnen) ist noch nach alter Art
        return $kontext->anfrage->text('todo') == 'edit' && $kontext->anfrage->text('st') != -10 && $kontext->sitzung->zahl('lmouserok') >= 1;
    }

    public function zeige(Kontext $kontext): Seite
    {
        $geladen = $kontext->liga;
        // ohne Liga (fehlt oder fuer diesen Hilfsadmin nicht freigegeben) bleibt die Seite leer
        if ($geladen === null) {
            return new Seite('');
        }
        $anfrage = $kontext->anfrage;
        $text = $kontext->text;
        $sitzung = $kontext->sitzung;
        $liga = $geladen->liga;
        $e = $liga->einstellungen();
        $pokal = $e->istPokal();
        $spieltage = $e->spieltage();
        $paarungRechte = $sitzung->zahl('lmouserok') == 2 || $sitzung->zahl('lmouserokerweitert') == 1;
        $spalten = $kontext->addons->ergebnisSpalten($kontext);
        // ohne Spieltag in der Anfrage der aktuelle (lmo-openfile.php)
        $st = $anfrage->gesetzt('st') ? $anfrage->text('st') : (string) $e->aktuellerSpieltag();
        // Spieltag, den es in der Liga nicht gibt (z.B. st=-2 als Hilfsadmin ohne Recht auf die
        // Mannschaftsverwaltung): stattdessen den aktuellen Spieltag bearbeiten
        if ($st < 1 || $st > $spieltage) {
            $st = (string) (($e->aktuellerSpieltag() >= 1 && $e->aktuellerSpieltag() <= $spieltage) ? $e->aktuellerSpieltag() : 1);
        }
        $save = $anfrage->text('save', '0');
        $meldung = '';
        if ($save == 1) {
            [$meldung, $liga] = $this->speichern($kontext, $liga, $geladen->datei, $st, $spalten, $paarungRechte);
        }
        // Rueckrunden-Hack
        if ($save == 990) {
            $meldung .= ($this->rueckrunde)($geladen->datei);
        }

        // der Stand nach dem Speichern zum Anzeigen
        $e = $liga->einstellungen();
        $spieltage = $e->spieltage();
        $mannschaften = $e->mannschaften();
        $partien = $e->partienImSpieltag((int) $st);
        $modus = Paarung::spieleJePaarung($liga, (int) $st);
        // Namen der Mannschaften fuer die Auswahllisten, davor "___" fuer keine
        $namen = array_fill(0, $mannschaften + 2, '');
        $namen[0] = '___';
        foreach ($liga->alleMannschaften() as $nr => $mannschaft) {
            $namen[$nr] = $mannschaft->anzeigename();
        }
        $news = [];
        foreach ($liga->news as $schluessel => $wert) {
            if ($schluessel != 'NC') {
                $news[] = stripslashes($wert);
            }
        }
        $breite = 18 - ($pokal ? 1 : 0);
        if ($e->mitVerlaengerung()) {
            $breite = $breite + 1 + ($pokal ? 1 : 0);
        }
        $addr = LigaNavigation::adresse($kontext, 'edit', $geladen->datei);
        $datum1 = $liga->spieltage[(int) $st]->werte['D1'] ?? '';
        $datum2 = $liga->spieltage[(int) $st]->werte['D2'] ?? '';
        $torfaktor = $e->torfaktor();

        return new Seite($meldung . Vorlagen::zeige($kontext->pfade->vorlagen, 'admin_ergebnisse.html.twig', [
            'text' => $text,
            'adresse' => $kontext->adresse,
            'bilder' => $kontext->pfade->bilder,
            'navigation' => LigaNavigation::fuer($kontext, $liga, 'edit', $st),
            'spieltagsmenue' => (new SpieltagsMenue($text, $kontext->pfade->vorlagen))->zeige($liga, (int) $st, $addr, false),
            'name' => $e->name(),
            'datei' => $geladen->datei,
            'st' => $st,
            'pokal' => $pokal,
            'paarungRechte' => $paarungRechte,
            'breite' => $breite,
            'datum1' => $datum1,
            'datum2' => $datum2,
            'kalender1' => self::monatJahr($datum1),
            'kalender2' => self::monatJahr($datum2),
            'faktortext' => $torfaktor > 0 && $torfaktor != 1 ? '(' . ($text[553 + (int) log10($torfaktor)] ?? '') . ')' : null,
            'verlaengerung' => $e->mitVerlaengerung(),
            'spalten' => $spalten,
            'zeilen' => $pokal ? $this->pokalZeilen($liga, (int) $st, $partien, $modus, $namen, $mannschaften, $spalten, $text) : $this->ligaZeilen($liga, (int) $st, $partien, $namen, $mannschaften, $spalten, $text),
            'abstand' => $modus > 1,
            'platz3' => $e->mitSpielUmPlatz3() && $st == $spieltage,
            'partien' => $partien,
            'spieltage' => $spieltage,
            'save' => $save == 1,
            'aktuell' => $e->aktuellerSpieltag(),
            'ticker' => $e->wert('ticker'),
            'news' => $news,
        ]));
    }

    /**
     * Uebernimmt das Formular in die Liga und speichert sie (bis 4.5 der Block mit save=1).
     *
     * @param list<ErgebnisSpalte> $spalten
     * @return array{string, Liga} Meldungen und die gespeicherte Liga
     */
    private function speichern(Kontext $kontext, Liga $liga, string $datei, string $st, array $spalten, bool $paarungRechte): array
    {
        $a = $kontext->anfrage;
        $text = $kontext->text;
        $e = $liga->einstellungen();
        $pokal = $e->istPokal();
        $spieltage = $e->spieltage();
        $spieltag = $liga->spieltag((int) $st);
        if ($kontext->sitzung->zahl('lmouserok') == 2) {
            foreach (['xdatum1', 'xdatum2'] as $feld) {
                $datum = trim($a->text($feld));
                if ($datum != '') {
                    $teile = explode('.', $datum);
                    $zeit = strtotime(($teile[0]) . ' ' . (self::MONATE[intval($teile[1] ?? 0)] ?? '') . ' ' . ($teile[2] ?? ''));
                    $datum = $zeit > -1 ? date('d.m.Y', (int) $zeit) : '';
                }
                if ($feld == 'xdatum1') {
                    $spieltag->setzeErstenTag($datum);
                } else {
                    $spieltag->setzeLetztenTag($datum);
                }
            }
        }
        // neue Ergebnisse: die Tabellenkorrektur gilt ab diesem Spieltag nicht mehr
        if ($e->mitHandicap()) {
            for ($i = (int) $st; $i <= $spieltage; $i++) {
                $liga->spieltag($i)->setzeTabellenkorrektur('0');
            }
        }
        $deftime = $kontext->konfiguration->wert('deftime');
        // Anstoss aus Datum und Uhrzeit des Formulars: Zeitstempel, null ohne oder mit unlesbarem Datum
        $anstoss = static function (string $nr) use ($a, $deftime): ?int {
            $tag = trim($a->text('xatdat' . $nr));
            $uhrzeit = trim($a->text('xattim' . $nr));
            if ($tag == '') {
                return null;
            }
            if ($uhrzeit == '') {
                $uhrzeit = $deftime;
            }
            $dt = DateTime::createFromFormat('d.m.Y H:i', $tag . ' ' . $uhrzeit);

            return $dt !== false ? $dt->getTimestamp() : null;
        };
        // Tore aus dem Formular: leer und "_" heissen nicht gespielt (null), "X" (nur Liga) 0, sonst die Zahl
        $tore = static function (string $feld, bool $mitX) use ($a): ?int {
            $wert = trim($a->text($feld));
            if ($wert == '' || $wert == '_') {
                return null;
            }
            if ($mitX && strtoupper($wert) == 'X') {
                return 0;
            }

            return intval($wert);
        };
        // Zusatz aus dem Formular: "_" (kein Zusatz, 0) und die beiden Anzeigetexte (2 = n.V., 1 = i.E.); alles andere: keine Angabe
        $zusatz = static function (string $wert) use ($text): ?int {
            if ($wert == '_') {
                return 0;
            }
            if ($wert == ($text[0] ?? null)) {
                return 2;
            }

            return $wert == ($text[1] ?? null) ? 1 : null;
        };
        $partien = $e->partienImSpieltag((int) $st);
        $modus = Paarung::spieleJePaarung($liga, (int) $st);
        $termine = [];
        for ($i = 0; $i < $partien; $i++) {
            $partie = $spieltag->partie($i + 1);
            if ($paarungRechte) {
                $partie->setzeMannschaften((int) $a->text('xteama' . $i, '0'), (int) $a->text('xteamb' . $i, '0'));
            }
            if (!$pokal) {
                $zeit = $anstoss((string) $i);
                $partie->setzeAnstoss($zeit);
                // mit Wertung am gruenen Tisch (0 = keine, 1 = fuer Heim, 2 = fuer Gast, 3 = gegen beide)
                $partie->setzeErgebnis($tore('xgoala' . $i, true), $tore('xgoalb' . $i, true), (int) $a->text('xmsieg' . $i));
                if ($e->mitVerlaengerung()) {
                    $partie->setzeZusatz($zusatz($a->text('xmspez' . $i)));
                }
                $partie->setzeNotiz(trim($a->text('xmnote' . $i)));
                $partie->setzeBericht(trim($a->text('xmberi' . $i)));
                foreach ($spalten as $spalte) {
                    $spalte->uebernimm($partie, $a->text($spalte->feld . $i), 1);
                }
                $termine[] = (int) $zeit;
            } else {
                for ($n = 0; $n < $modus; $n++) {
                    $zeit = $anstoss($i . $n);
                    $partie->setzeAnstoss($zeit, $n + 1);
                    $partie->setzeErgebnis($tore('xgoala' . $i . $n, false), $tore('xgoalb' . $i . $n, false), null, $n + 1);
                    $partie->setzeZusatz($zusatz($a->text('xmspez' . $i . $n)), $n + 1);
                    $partie->setzeNotiz(trim($a->text('xmnote' . $i . $n)), $n + 1);
                    $partie->setzeBericht(trim($a->text('xmberi' . $i . $n)), $n + 1);
                    foreach ($spalten as $spalte) {
                        $spalte->uebernimm($partie, $a->text($spalte->feld . $i . $n), $n + 1);
                    }
                    $termine[] = (int) $zeit;
                }
            }
        }
        // Fieberkurve: fuehrt die erste Kurve die Lieblingsmannschaft, zeigt die zweite deren naechsten Gegner
        $lieblingsmannschaft = $e->lieblingsmannschaft();
        if ($st < $spieltage && $lieblingsmannschaft > 0 && $e->fieberkurveErste() == $lieblingsmannschaft) {
            for ($y = 1; $y <= $e->partienJeSpieltag(); $y++) {
                $naechste = $liga->spieltage[(int) $st + 1]->partien[$y] ?? null;
                if ($naechste !== null && (int) $naechste->heim == $lieblingsmannschaft) {
                    $liga->setzeEinstellung('kurve2', (string) $naechste->gast);
                }
                if ($naechste !== null && (int) $naechste->gast == $lieblingsmannschaft) {
                    $liga->setzeEinstellung('kurve2', (string) $naechste->heim);
                }
            }
        }
        // Spieltagsdatum (falls nicht angegeben) aus Spieldaten extrahieren
        $spieltermine = array_filter($termine);
        if ($spieltermine !== []) {
            if (empty($spieltag->werte['D1'])) {
                $spieltag->setzeErstenTag(date('d.m.Y', min($spieltermine)));
            }
            if (empty($spieltag->werte['D2'])) {
                $spieltag->setzeLetztenTag(date('d.m.Y', max($spieltermine)));
            }
        }
        // Auswertungen der Addons beim Speichern (Lmo\Addon\MitErgebnisAuswertung), wie bisher im globalen Bereich
        $meldung = '';
        $dateien = $kontext->addons->auswertungBeimSpeichern($kontext);
        if ($dateien !== []) {
            [$meldung, $liga] = ($this->auswerten)($liga, $datei, $st, $dateien);
        }
        // aktueller Spieltag: der im Formular gewaehlte, sonst der bearbeitete
        $gewaehlt = trim($a->text('xstx'));
        $liga->setzeEinstellung('Actual', $gewaehlt != 0 ? $gewaehlt : $st);
        $liga->setzeEinstellung('ticker', trim($a->text('xnticker')));
        // die Newszeilen ohne die leeren, getrennt an den Zeilenumbruechen der Textarea (bis 4.5 an
        // '\n', Backslash und n: alles kam als eine Zeile mit Umbruechen in die Ligadatei)
        $liga->setzeNews(array_values(array_filter(preg_split('/\r\n|\r|\n/', $a->text('xnlines')) ?: [], static fn (string $y): bool => $y != '')));
        // gespeichert wird mit dem gewaehlten Spieltag als aktuellem
        [$gespeichert, $liga] = ($this->speichere)($liga, $datei, (string) ($liga->optionen['Actual'] ?? ''));

        return [$meldung . $gespeichert, $liga];
    }

    /**
     * Die Zeilen einer Liga: je Partie ein Spiel.
     *
     * @param array<int, string> $namen
     * @param list<ErgebnisSpalte> $spalten
     * @param array<int, string> $text
     * @return list<array<string, mixed>>
     */
    private function ligaZeilen(Liga $liga, int $st, int $partien, array $namen, int $mannschaften, array $spalten, array $text): array
    {
        $zeilen = [];
        for ($i = 0; $i < $partien; $i++) {
            $paarung = Paarung::ausRohdaten($liga, $st, $i + 1);
            $zeilen[] = self::spiel($paarung->spiele[0], (string) $i, $spalten, $text) + [
                'i' => $i,
                'heim' => $paarung->heim,
                'gast' => $paarung->gast,
                'heimName' => $namen[$paarung->heim] ?? '',
                'gastName' => $namen[$paarung->gast] ?? '',
                'heimOptionen' => self::optionen(range(0, $mannschaften), $paarung->heim, $namen),
                'gastOptionen' => self::optionen(range(0, $mannschaften), $paarung->gast, $namen),
            ];
        }

        return $zeilen;
    }

    /**
     * Die Zeilen eines Pokals: je Partie ihre Spiele (Hin- und Rueckspiel ...), mit wechselndem
     * Heimrecht je nach Playoff-Modus.
     *
     * @param array<int, string> $namen
     * @param list<ErgebnisSpalte> $spalten
     * @param array<int, string> $text
     * @return list<array<string, mixed>>
     */
    private function pokalZeilen(Liga $liga, int $st, int $partien, int $modus, array $namen, int $mannschaften, array $spalten, array $text): array
    {
        $e = $liga->einstellungen();
        $playdown = $e->wert('playdown');
        $playoff = $e->playoffModus();
        // wer in den Runden davor ausgeschieden ist (1); mit Spiel um Platz 3 die Verlierer der Runde davor (2)
        $ausgeschieden = array_fill(0, 129, '0');
        for ($i = 1; $i < $st; $i++) {
            foreach ($liga->paarungen($i) as $paarung) {
                $m = $paarung->sieger();
                if ($m == 0) {
                    continue;
                }
                $verlierer = $m == 1 ? $paarung->gast : $paarung->heim;
                $ausgeschieden[$verlierer] = ($e->mitSpielUmPlatz3() && $i == $st - 1) ? 2 : 1;
            }
        }
        $platz3 = $e->mitSpielUmPlatz3() && $st == $e->spieltage();
        $zeilen = [];
        for ($i = 0; $i < $partien; $i++) {
            $paarung = Paarung::ausRohdaten($liga, $st, $i + 1);
            // im Spiel um Platz 3 die Verlierer des Halbfinales, sonst wer noch dabei ist
            if ($platz3 && $i == 1) {
                $wahl = array_merge([0], array_values(array_filter(range(1, $mannschaften), static fn (int $y): bool => ($playdown == 0 && $ausgeschieden[$y] == 2) || $playdown == 1)));
            } else {
                $wahl = array_values(array_filter(range(0, $mannschaften), static fn (int $y): bool => ($playdown == 0 && $ausgeschieden[$y] == 0) || $playdown == 1));
            }
            $spiele = [];
            for ($n = 0; $n < $modus; $n++) {
                $spiele[] = self::spiel($paarung->spiele[$n], $i . $n, $spalten, $text) + [
                    'n' => $n,
                    // Paarung: ab dem zweiten Spiel nur die Namen, Heim zuerst, getauscht oder (unbekannter Modus) keine
                    'heimZuerst' => self::heimZuerst($playoff, $n),
                    // Tore: Heim zuerst, getauscht, oder (unbekannter Modus) keine Eingabe
                    'toreReihe' => self::toreReihe($playoff, $n),
                ];
            }
            $zeilen[] = [
                'i' => $i,
                'heimName' => $namen[$paarung->heim] ?? '',
                'gastName' => $namen[$paarung->gast] ?? '',
                'heimOptionen' => self::optionen($wahl, $paarung->heim, $namen),
                'gastOptionen' => self::optionen($wahl, $paarung->gast, $namen),
                'spiele' => $spiele,
            ];
        }

        return $zeilen;
    }

    /**
     * Die Felder eines Spiels.
     *
     * @param list<ErgebnisSpalte> $spalten
     * @param array<int, string> $text
     * @return array<string, mixed>
     */
    private static function spiel(Spiel $spiel, string $nr, array $spalten, array $text): array
    {
        if ($spiel->anstoss !== null && $spiel->anstoss != 0) {
            $dt = new DateTime();
            $dt->setTimestamp($spiel->anstoss);
            $tag = $dt->format('d.m.Y');
            $uhrzeit = $dt->format('H:i');
            $kalender = self::MONATE[intval($dt->format('m'))] . ' ' . $dt->format('Y');
        } else {
            $tag = $uhrzeit = $kalender = '';
        }
        $toreHeim = $spiel->toreHeimText();
        $toreGast = $spiel->toreGastText();

        return [
            'nr' => $nr,
            'tag' => $tag,
            'uhrzeit' => $uhrzeit,
            'kalender' => $kalender,
            'toreHeim' => $toreHeim == '-1' ? '_' : $toreHeim,
            'toreGast' => $toreGast == '-1' ? '_' : $toreGast,
            'zusatz' => $spiel->zusatzText($text[0] ?? '', $text[1] ?? ''),
            'wertung' => $spiel->wertung,
            'notiz' => htmlentities($spiel->notiz),
            'notizLeer' => trim($spiel->notiz) == '',
            'bericht' => htmlentities($spiel->bericht),
            'berichtLeer' => trim($spiel->bericht) == '',
            'spaltenWerte' => array_map(static fn (ErgebnisSpalte $s): string => $s->wert($spiel), $spalten),
        ];
    }

    /**
     * Auswahl der Mannschaften.
     *
     * @param array<int> $nummern
     * @param array<int, string> $namen
     * @return list<array{wert: int, name: string, gewaehlt: bool}>
     */
    private static function optionen(array $nummern, int $gewaehlt, array $namen): array
    {
        return array_map(static fn (int $y): array => ['wert' => $y, 'name' => $namen[$y] ?? '', 'gewaehlt' => $y == $gewaehlt], array_values($nummern));
    }

    /** Pokal ab dem zweiten Spiel: steht die Heimmannschaft der Paarung vorn? null: unbekannter Modus, keine Namen */
    private static function heimZuerst(int $playoff, int $n): ?bool
    {
        return match (true) {
            $playoff == 1 || $playoff == 2 => in_array($n, [1, 4, 6], true),   // Modus 2-2-1 oder 2-2-1-1-1
            $playoff == 3 => in_array($n, [1, 5, 6], true),                     // Modus 2-3-2
            $playoff < 1 => $n % 2 == 0,                                        // alles andere (0 und KO ohne Modus)
            default => null,
        };
    }

    /** Pokal: Tore der Heimmannschaft zuerst ('ab'), getauscht ('ba'), bei unbekanntem Modus keine Eingabe (null). */
    private static function toreReihe(int $playoff, int $n): ?string
    {
        return match (true) {
            $playoff == 1 || $playoff == 2 => in_array($n, [0, 1, 4, 6], true) ? 'ab' : 'ba',
            $playoff == 3 => in_array($n, [0, 1, 5, 6], true) ? 'ab' : 'ba',
            $playoff < 1 => $n % 2 == 0 ? 'ab' : 'ba',
            default => null,
        };
    }

    /** "Monat Jahr" eines Datums d.m.Y fuer den Kalender, '' ohne. */
    private static function monatJahr(string $datum): string
    {
        if ($datum == '') {
            return '';
        }
        $teile = explode('.', $datum);

        return (self::MONATE[intval($teile[1] ?? 0)] ?? '') . ' ' . ($teile[2] ?? '');
    }
}
