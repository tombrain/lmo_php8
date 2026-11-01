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

namespace Lmo\Addon\Spieler;

use Lmo\Web\Admin\LigaNavigation;
use Lmo\Web\Controller\Controller;
use Lmo\Web\Kontext;
use Lmo\Web\Seite;
use Lmo\Web\Vorlagen;

/**
 * Die Spielerstatistik einer Liga pflegen (todo=edit&st=-4, Hauptadmin; Hilfsadmins, wenn die
 * Liga es zulaesst): Spieler und Spalten anlegen und loeschen (option addplayer, delplayer,
 * addcolumn, delcolumn), die Werte eintragen (statupdate), dazu Formelspalten, und die
 * Einstellungen der Liga (saveconfig). Die Statistik steht in daten/spieler/<liga>.stat, die
 * Einstellungen in daten/spieler/<liga>.cfg. Bis 4.5 lmo-statadmin.php.
 */
final class AdminController implements Controller
{
    /** Markiert im Spaltennamen eine Formelspalte */
    private const FORMEL = '*_*-*';

    /** Einstellungen der Liga: Name im Formular und in cfg.txt des Addons => Sprachtext als Name in der .cfg-Datei */
    private const EINSTELLUNGEN = [
        'standard_sortierung' => 21,
        'standard_richtung' => 13,
        'adminbereich_standard_sortierung' => 40,
        'anzeige_pro_seite' => 22,
        'nullwerte_anzeigen' => 23,
        'extra_sortierspalte' => 24,
        'vereinsweise_anzeigen' => 50,
        'adminbereich_hilfsadmin_zulassen' => 31,
        'adminbereich_hilfsadmin_fuer_spalten' => 46,
        'ligalink' => 41,
    ];

    /** @param string $daten Ordner der Statistiken (daten/spieler) */
    public function __construct(private string $daten)
    {
    }

    public function zustaendig(Kontext $kontext): bool
    {
        return $kontext->anfrage->text('todo') == 'edit' && $kontext->anfrage->text('st') == -4 && SpielerAddon::an($kontext->konfiguration);
    }

    public function zeige(Kontext $kontext): Seite
    {
        $geladen = $kontext->liga;
        if ($geladen === null) {
            return new Seite('');
        }
        $a = $kontext->anfrage;
        $t = $kontext->texteVon('spieler');
        $rolle = $kontext->sitzung->zahl('lmouserok');
        $name = substr(basename($geladen->datei), 0, -4);
        $statdatei = $this->daten . '/' . $name . '.stat';
        $cfgdatei = $this->daten . '/' . $name . '.cfg';
        $e = $this->einstellungen($kontext, $cfgdatei, $t);
        if (!($rolle == 2 || ($rolle == 1 && $e['adminbereich_hilfsadmin_zulassen'] == 1))) {
            return new Seite($t[33] ?? '');
        }
        $option = $a->text('option');
        $wert = $a->text('wert');

        // zu speichernde Einstellungen: was im Formular steht; Schalter ohne Haken beim Speichern 0
        foreach (['standard_sortierung', 'standard_richtung', 'adminbereich_standard_sortierung', 'ligalink', 'anzeige_pro_seite'] as $feld) {
            if ($a->gesetzt($feld)) {
                $e[$feld] = $a->text($feld);
            }
        }
        $schalter = ['nullwerte_anzeigen', 'vereinsweise_anzeigen', 'extra_sortierspalte'];
        if ($rolle == 2) {
            $schalter[] = 'adminbereich_hilfsadmin_zulassen';
            $schalter[] = 'adminbereich_hilfsadmin_fuer_spalten';
        }
        foreach ($schalter as $feld) {
            if ($a->gesetzt($feld)) {
                $e[$feld] = 1;
            } elseif ($option == 'saveconfig') {
                $e[$feld] = 0;
            }
        }
        $sort = $a->text('sort');
        if ($sort == '') {
            $sort = (string) intval($e['adminbereich_standard_sortierung']);
        }

        // die Statistik lesen; fehlt die Datei, beginnt sie mit der Spalte "Name"
        $ausgabe = '';
        $neu = !is_file($statdatei);
        $zeiger = fopen($statdatei, $neu ? 'w+b' : 'r+b');
        $spalten = $zeiger !== false ? fgetcsv($zeiger, separator: '#', escape: '') : false;
        if ($spalten === false && $zeiger !== false) {
            $spalten = [$t[2] ?? ''];
            fwrite($zeiger, $spalten[0] . "\n");
        }
        $spalten = array_map('strval', $spalten ?: []);
        $gespeichert = $spalten;
        $formel = [];
        $formeln = 0;
        foreach ($spalten as $i => $spalte) {
            $formel[$i] = str_contains($spalte, self::FORMEL);
            if ($formel[$i]) {
                $formeln++;
                $spalten[$i] = substr($spalte, 0, strlen($spalte) - 5);
            }
        }
        $formelzeile = [];
        if ($formeln > 0 && $zeiger !== false) {
            $formelzeile = array_map('strval', fgetcsv($zeiger, separator: '#', escape: '') ?: []);
        }
        $daten = [];
        $textspalten = [];
        while ($zeiger !== false && ($zeile = fgetcsv($zeiger, separator: '#', escape: ''))) {
            $zeile = array_map('strval', $zeile);
            foreach ($zeile as $i => $feld) {
                if (!is_numeric($feld)) {
                    $textspalten[$i] = true;
                }
            }
            $daten[] = $zeile;
        }
        if ($zeiger !== false) {
            fclose($zeiger);
        }
        if ($option != 'statupdate') {
            usort($daten, isset($textspalten[intval($sort)]) ? self::nachText(intval($sort)) : self::nachZahl(intval($sort)));
        }
        $anzahl = count($daten);
        $ligadatei = $kontext->pfade->lmo . '/' . $kontext->konfiguration->ligenordner() . $geladen->datei;

        switch ($option) {
            case 'addplayer':  // Spieler hinzufuegen
                if ($wert != '') {
                    $text = $gespeichert === [] ? '' : implode('#', $gespeichert) . "\n";
                    if ($formeln > 0) {
                        $text .= implode('#', $formelzeile) . "\n";
                        $daten = self::berechne($daten, $formel, $formelzeile, $spalten, $t);
                    }
                    foreach ($daten as $zeile) {
                        $text .= implode('#', $zeile) . "\n";
                    }
                    // die neue Zeile: in Textspalten "___", sonst 0, nach der Zeile davor (bei der ersten nach Verein und Spielerlink)
                    $neueZeile = [$wert];
                    for ($i = 1; $i < count($spalten); $i++) {
                        $leer = $anzahl == 0 ? ($spalten[$i] == ($t[25] ?? null) || $spalten[$i] == ($t[32] ?? null)) : !is_numeric($daten[$anzahl - 1][$i] ?? '');
                        $neueZeile[] = $leer ? ($t[43] ?? '') : '0';
                    }
                    $text .= implode('#', $neueZeile) . "\n";
                    $daten[] = $neueZeile;
                    $anzahl++;
                    @file_put_contents($statdatei, $text);
                    @touch($ligadatei);
                } else {
                    $ausgabe = $t[4] ?? '';
                }
                break;
            case 'delplayer':  // Spieler loeschen
                if ($wert != '') {
                    $text = implode('#', $gespeichert) . "\n";
                    if ($formeln > 0) {
                        $text .= implode('#', $formelzeile) . "\n";
                    }
                    foreach ($daten as $nr => $zeile) {
                        if ($nr != $wert) {
                            $text .= implode('#', $zeile) . "\n";
                        }
                    }
                    @file_put_contents($statdatei, $text);
                    // wie bisher neu gelesen: die Spaltennamen mit Formel-Markierung, die Zeilen ungeordnet
                    [$spalten, $daten] = self::liesNeu($statdatei, $formeln > 0);
                    $anzahl = count($daten);
                    @touch($ligadatei);
                }
                break;
            case 'addcolumn':  // Spalte hinzufuegen
                if ($wert != '') {
                    $wertNeu = $a->gesetzt('type') ? $a->text('type') : '0';
                    if ($wert == ($t[25] ?? null) || $wert == ($t[32] ?? null)) {
                        $wertNeu = $t[43] ?? '';
                    }
                    $nr = count($spalten);
                    $spalten[$nr] = $wert;
                    $gespeichert[$nr] = $wert;
                    if ($wertNeu == 'F') {
                        if ($formeln == 0) {
                            $formelzeile = array_fill(0, $nr, '0');
                        }
                        $formeln++;
                        $gespeichert[$nr] .= self::FORMEL;
                        $wertNeu = '0';
                        $formel[$nr] = true;
                    } else {
                        $formel[$nr] = false;
                    }
                    $formelzeile[$nr] = '0';
                    $text = implode('#', $gespeichert) . "\n";
                    foreach ($daten as $i => $zeile) {
                        $daten[$i][$nr] = $wertNeu;
                    }
                    if ($formeln > 0) {
                        $text .= implode('#', $formelzeile) . "\n";
                        $daten = self::berechne($daten, $formel, $formelzeile, $spalten, $t);
                    }
                    foreach ($daten as $zeile) {
                        $text .= implode('#', $zeile) . "\n";
                    }
                    @file_put_contents($statdatei, $text);
                    @touch($ligadatei);
                } else {
                    $ausgabe = $t[3] ?? '';
                }
                break;
            case 'delcolumn':  // Spalte loeschen
                if ($wert > 0 && isset($spalten[(int) $wert])) {
                    $nr = (int) $wert;
                    if ($formel[$nr]) {
                        $formeln--;
                    }
                    array_splice($spalten, $nr, 1);
                    array_splice($gespeichert, $nr, 1);
                    array_splice($formel, $nr, 1);
                    $text = implode('#', $gespeichert) . "\n";
                    foreach ($daten as $i => $zeile) {
                        array_splice($daten[$i], $nr, 1);
                    }
                    if ($formeln > 0) {
                        array_splice($formelzeile, $nr, 1);
                        $text .= implode('#', $formelzeile) . "\n";
                        $daten = self::berechne($daten, $formel, $formelzeile, $spalten, $t);
                    }
                    foreach ($daten as $zeile) {
                        $text .= implode('#', $zeile) . "\n";
                    }
                    @file_put_contents($statdatei, $text);
                    @touch($ligadatei);
                }
                break;
            case 'sortieren':
                $text = implode('#', $gespeichert) . "\n";
                if ($formeln > 0) {
                    $text .= implode('#', $formelzeile) . "\n";
                }
                foreach ($daten as $zeile) {
                    $text .= implode('#', $zeile) . "\n";
                }
                @file_put_contents($statdatei, $text);
                break;
            case 'statupdate':  // die Werte des Formulars uebernehmen
                foreach ($spalten as $i => $spalte) {
                    if ($a->gesetzt('spalten' . $i)) {
                        $spalten[$i] = $a->text('spalten' . $i);
                        $gespeichert[$i] = $a->text('spalten' . $i) . ($formel[$i] ? self::FORMEL : '');
                    }
                    if ($a->gesetzt('formel_str' . $i)) {
                        $formelzeile[$i] = $a->text('formel_str' . $i);
                    }
                }
                $text = implode('#', $gespeichert) . "\n";
                foreach ($daten as $j => $zeile) {
                    foreach ($spalten as $i => $spalte) {
                        if ($a->gesetzt('data' . $j . '|' . $i)) {
                            $daten[$j][$i] = $a->text('data' . $j . '|' . $i);
                        }
                    }
                }
                if ($formeln > 0) {
                    $text .= implode('#', $formelzeile) . "\n";
                    $daten = self::berechne($daten, $formel, $formelzeile, $spalten, $t);
                }
                foreach ($daten as $zeile) {
                    $text .= implode('#', $zeile) . "\n";
                }
                @file_put_contents($statdatei, $text);
                @touch($ligadatei);
                break;
            case 'saveconfig':  // Einstellungen der Liga sichern
                $text = '';
                foreach (self::EINSTELLUNGEN as $feld => $nr) {
                    if ($rolle != 2 && in_array($feld, ['adminbereich_hilfsadmin_zulassen', 'adminbereich_hilfsadmin_fuer_spalten'], true)) {
                        continue;
                    }
                    $text .= ($t[$nr] ?? '') . '=' . $e[$feld] . ($feld == 'ligalink' ? '' : "\n");
                }
                @file_put_contents($cfgdatei, $text, LOCK_EX);
                break;
        }
        if ($a->text('sort_direction', 'asc') == 'desc') {
            $daten = array_reverse($daten);
        }

        return new Seite($ausgabe . Vorlagen::zeige(dirname(__DIR__) . '/vorlagen', 'admin.html.twig', [
            'text' => $kontext->text,
            't' => $t,
            'navigation' => LigaNavigation::fuer($kontext, $geladen->liga, 'edit', $a->text('st')),
            'adresse' => $kontext->adresse,
            'bilder' => $kontext->pfade->bilder,
            'datei' => $geladen->datei,
            'st' => $a->text('st'),
            'sort' => $sort,
            'hauptadmin' => $rolle == 2,
            'spaltenRechte' => $rolle == 2 || ($rolle == 1 && $e['adminbereich_hilfsadmin_zulassen'] == 1 && $e['adminbereich_hilfsadmin_fuer_spalten'] == 1),
            'spalten' => $spalten,
            'spaltenMaskiert' => array_map(static fn (string $s): string => htmlentities(stripslashes($s), ENT_COMPAT), $spalten),
            // Feldgroessen wie bisher mit strlen (Bytes)
            'laenge' => [
                'spalten' => array_map('strlen', $spalten),
                'formeln' => array_map(static fn ($f): int => strlen((string) $f), $formelzeile),
                'anzeige_pro_seite' => strlen((string) $e['anzeige_pro_seite']),
                'ligalink' => strlen((string) $e['ligalink']),
            ],
            'formel' => $formel,
            'formeln' => $formeln,
            'formelzeile' => $formelzeile,
            'zeilen' => self::zeilen($daten, $spalten, $formel, $kontext, $t),
            'e' => $e,
            'vereinsspalte' => in_array($t[25] ?? null, $spalten, true),
        ]));
    }

    /**
     * Die Einstellungen der Liga: die des Addons (cfg.txt), ersetzt durch die aus der .cfg-Datei
     * der Liga, deren Zeilen wie die Sprachtexte heissen (wie bis 4.5 lmo-statloadconfig.php).
     *
     * @param array<int, string> $t
     * @return array<string, int|string>
     */
    private function einstellungen(Kontext $kontext, string $datei, array $t): array
    {
        $addon = $kontext->konfiguration->addon('spieler');
        $e = [];
        foreach (array_keys(self::EINSTELLUNGEN) as $feld) {
            $e[$feld] = isset($addon[$feld]) && is_scalar($addon[$feld]) ? (string) $addon[$feld] : '';
        }
        $e['ligalink'] = $t[18] ?? '';
        if (is_readable($datei) && ($zeilen = file($datei))) {
            foreach ($zeilen as $zeile) {
                $teile = explode('=', $zeile, 2);
                foreach (self::EINSTELLUNGEN as $feld => $nr) {
                    if (isset($t[$nr]) && $teile[0] == $t[$nr]) {
                        // Vorsortierung Admin und Linkbezeichnung wie gelesen, die uebrigen als Zahl
                        $e[$feld] = in_array($feld, ['adminbereich_standard_sortierung', 'ligalink'], true) ? ($teile[1] ?? '') : (int) ($teile[1] ?? 0);
                        break;
                    }
                }
            }
        }

        return $e;
    }

    /**
     * Die Datei nach dem Loeschen eines Spielers neu gelesen: Spaltennamen wie in der Datei, die
     * Zeilen der Spieler in ihrer Reihenfolge.
     *
     * @return array{list<string>, list<list<string>>}
     */
    private static function liesNeu(string $datei, bool $mitFormeln): array
    {
        $zeiger = fopen($datei, 'rb');
        if ($zeiger === false) {
            return [[], []];
        }
        $spalten = array_map('strval', fgetcsv($zeiger, separator: '#', escape: '') ?: []);
        if ($mitFormeln) {
            fgetcsv($zeiger, separator: '#', escape: '');  // Zeile mit Formeln uebergehen
        }
        $daten = [];
        while ($zeile = fgetcsv($zeiger, separator: '#', escape: '')) {
            $daten[] = array_map('strval', $zeile);
        }
        fclose($zeiger);

        return [$spalten, $daten];
    }

    /** Sortierung nach einer Zahlenspalte, absteigend (bis 4.5 cmpInt()). */
    private static function nachZahl(int $spalte): \Closure
    {
        return static function (array $a, array $b) use ($spalte): int {
            if (($b[$spalte] ?? null) == ($a[$spalte] ?? null)) {
                return 0;
            }

            return ($a[$spalte] ?? null) > ($b[$spalte] ?? null) ? -1 : 1;
        };
    }

    /** Sortierung nach einer Textspalte ohne Akzente, natuerlich (bis 4.5 cmpStr()). */
    private static function nachText(int $spalte): \Closure
    {
        $ohneAkzente = static fn (string $s): string => strtr($s, '¥µÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÜÝßàáâãäåæçèéêëìíîïðñòóôõöøùúûüýÿ', 'YuAAAAAAACEEEEIIIIDNOOOOOOUUUUYsaaaaaaaceeeeiiiionoooooouuuuyy');

        return static function (array $a2, array $a1) use ($spalte, $ohneAkzente): int {
            $x = $ohneAkzente((string) ($a1[$spalte] ?? ''));
            $y = $ohneAkzente((string) ($a2[$spalte] ?? ''));
            $c = strnatcasecmp($y, $x);

            return $c != 0 ? $c : strnatcasecmp($x, $y);
        };
    }

    /**
     * Rechnet die Formelspalten (bis 4.5 formel_berechnen()). Eine Formel darf ausser den Namen
     * der Spalten nur Zahlen, + - * / ( ) , . und MAX oder MIN enthalten; sonst steht dort der
     * Fehlertext. Division durch 0 ergibt 0, eine unvollstaendige Formel den Fehlertext.
     *
     * @param list<list<string>> $daten
     * @param array<int, bool> $formel
     * @param array<int, string> $formelzeile
     * @param list<string> $spalten
     * @param array<int, string> $t
     * @return list<list<string>>
     */
    private static function berechne(array $daten, array $formel, array $formelzeile, array $spalten, array $t): array
    {
        $nachLaenge = $spalten;
        uasort($nachLaenge, static fn (string $a, string $b): int => strlen($a) == strlen($b) ? 0 : (strlen($a) > strlen($b) ? -1 : 1));
        $code = [];
        for ($i = 0; $i < count($spalten); $i++) {
            if (!($formel[$i] ?? false)) {
                continue;
            }
            $ausdruck = strtoupper($formelzeile[$i] ?? '');
            $rest = $ausdruck;
            foreach ($nachLaenge as $nr => $name) {
                if ($i != $nr) {
                    $rest = str_replace(strtoupper($name), '', $rest);
                    $ausdruck = str_replace(strtoupper($name), "\$data[\$j][$nr]", $ausdruck);
                }
            }
            $rest = trim(strtr($rest, '+-*/0123456789.(),', '                  '));
            $code[$i] = $rest === '' || $rest == 'MAX' || $rest == 'MIN' ? '$help2 = round(' . $ausdruck . ', 2);' : null;
        }
        foreach ($code as $i => $befehl) {
            for ($j = 0; $j < count($daten); $j++) {
                $daten[$j][$i] = self::rechne($befehl, $daten, $j, $t[55] ?? '');
            }
        }

        return $daten;
    }

    /**
     * Fuehrt eine gepruefte Formel fuer eine Zeile aus; null: ungueltige Formel.
     *
     * @param list<list<string>> $data
     */
    private static function rechne(?string $befehl, array $data, int $j, string $fehler): string
    {
        if ($befehl === null) {
            return $fehler;
        }
        $help2 = 0.0;
        try {
            // phpcs:ignore -- gepruefte Formel aus Spaltennamen, Zahlen und Rechenzeichen
            @eval($befehl);
        } catch (\DivisionByZeroError) {
            $help2 = 0.0;
        } catch (\Throwable) {
            // unvollstaendige Formel (z.B. "Tore/"): Fehlertext statt Abbruch der Seite
            $help2 = $fehler;
        }

        return (string) $help2;
    }

    /**
     * Die Zeilen zum Anzeigen: je Feld Name im Formular, Wert (maskiert), Art (formel, zahl,
     * verein, text) und bei Vereinen die Auswahl der Mannschaften.
     *
     * @param list<list<string>> $daten
     * @param list<string> $spalten
     * @param array<int, bool> $formel
     * @param array<int, string> $t
     * @return list<list<array<string, mixed>>>
     */
    private static function zeilen(array $daten, array $spalten, array $formel, Kontext $kontext, array $t): array
    {
        $liga = $kontext->liga?->liga;
        $vereine = [];
        if ($liga !== null) {
            for ($j = 0; $j <= $liga->einstellungen()->mannschaften(); $j++) {
                $vereine[] = htmlentities($j == 0 ? '___' : $liga->mannschaftsname($j));
            }
        }
        $zeilen = [];
        foreach ($daten as $j => $zeile) {
            $felder = [];
            for ($i = 0; $i < count($spalten); $i++) {
                $wert = htmlentities(stripslashes($zeile[$i] ?? ''), ENT_COMPAT);
                $art = match (true) {
                    ($formel[$i] ?? false) == 1 => 'formel',
                    is_numeric($wert) => 'zahl',
                    $spalten[$i] == ($t[25] ?? null) => 'verein',
                    default => 'text',
                };
                $felder[] = ['name' => 'data' . $j . '|' . $i, 'zeile' => $j, 'spalte' => $i, 'wert' => $wert, 'laenge' => strlen($wert), 'art' => $art, 'vereine' => $art == 'verein' ? $vereine : []];
            }
            $zeilen[] = $felder;
        }

        return $zeilen;
    }
}
