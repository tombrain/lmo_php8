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

namespace Lmo\Addon\Tipp;

use Lmo\Web\Controller\Controller;
use Lmo\Web\Kontext;
use Lmo\Web\Meldung;
use Lmo\Web\Seite;
use Lmo\Web\Vorlagen;

/**
 * Einen Tipper in der Administration bearbeiten oder anlegen (todo=tippuseredit, nur Hauptadmin):
 * nick=<Nick> bearbeitet, save=-1 legt neu an. Gespeichert werden die Daten, Verein und Ligen;
 * fuer neue Ligen bekommt der Tipper eine leere Tippdatei und einen Eintrag in der Auswertung,
 * fuer abgewaehlte verschwindet seine Tippdatei. Bis 4.5 lmo-admintippuseredit.php.
 */
final class AdminTipperBearbeitenController implements Controller
{
    /** @param string $daten Datenordner des Tippspiels (daten/tipp) */
    public function __construct(private string $daten)
    {
    }

    public function zustaendig(Kontext $kontext): bool
    {
        return $kontext->anfrage->text('todo') == 'tippuseredit' && $kontext->sitzung->zahl('lmouserok') == 2;
    }

    public function zeige(Kontext $kontext): Seite
    {
        $a = $kontext->anfrage;
        $t = $kontext->texteVon('tipp');
        $text = $kontext->text;
        $cfg = $kontext->konfiguration->addon('tipp');
        $bilder = $kontext->pfade->bilder;
        // Nick aus dem Link der Userverwaltung (zum Bearbeiten) oder aus dem Formular (zum Speichern);
        // das Formular hat immer newpage (bis 4.5 unterschied die Seite GET und POST)
        $bearbeiten = !$a->gesetzt('newpage');
        $nick = trim($a->text('nick'));
        $newpage = $a->text('newpage', '0');
        $save = $a->text('save', '0');
        if (!($nick != '' || $save == -1)) {
            return new Seite('');
        }
        $vereinswahl = $a->text('xtippervereinradio');
        $vereinAlt = $a->text('xtippervereinalt');
        $vereinNeu = $a->text('xtippervereinneu');
        $gewaehlt = $a->werte('xtipperligen');
        $tipperdatei = $this->daten . '/' . ($cfg['tippauthtxt'] ?? 'lmo-tippauth.txt');
        $tippordner = $this->daten . '/' . ($cfg['dirtipp'] ?? '');
        $imTeam = (int) ($cfg['tipperimteam'] ?? -1);
        $meldungen = '';
        $fehler = static function (string $meldung) use ($bilder, &$meldungen): void {
            $meldungen .= Meldung::html($bilder, $meldung, true);
        };

        // die Tipper in der Reihenfolge der Datei, ab 1
        $zeilen = [''];
        foreach (Tipperdatei::lies($tipperdatei)->alle() as $tipper) {
            $zeilen[] = $tipper->zeile();
        }
        $daten = [];
        if ($save != -1) {
            $gefunden = false;
            for ($i = 1; $i < count($zeilen) && !$gefunden; $i++) {
                // wie bisher: ohne Treffer bleiben die Felder des letzten Tippers stehen
                $daten = explode('|', $zeilen[$i]);
                if ($nick == $daten[0] && $newpage == 0) {
                    $gefunden = true;
                    $save = (string) $i;
                    if (!$bearbeiten) {
                        $newpage = '1';
                    }
                } elseif ($nick == $daten[0] && $newpage == 1) {
                    $newpage = '0';
                    $fehler($t[24] ?? '');
                }
            }
            if (!$gefunden) {
                $save = (string) count($zeilen);
            }
        } else {
            $daten = array_fill(0, 12, '');
            $save = (string) count($zeilen);
            $daten[2] = '5';
            $daten[9] = '1';
            $daten[10] = '1';
        }
        $daten = array_pad($daten, 12, '');

        if ($newpage != 1) {
            if ($daten[5] == '') {
                $vereinswahl = '0';
            } else {
                $vereinswahl = '1';
                $vereinAlt = $daten[5];
            }
        }

        if ($newpage == 1) {
            $daten[0] = $nick;
            $kennwort = trim($a->text('xtipperpass'));
            if ($kennwort != '') {
                $daten[1] = password_hash($kennwort, defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT);
            }
            // ohne Kennwort bleibt das bisherige
            $daten[3] = $a->text('xtippervorname') . ' ' . $a->text('xtippernachname');
            if (substr_count($daten[3], ' ') > 1) {
                $newpage = '0';
                $fehler($t[109] ?? '');
            }
            $daten[4] = $a->text('xtipperemail');
            $daten[6] = $a->text('xtipperstrasse');
            $daten[7] = $a->text('xtipperplz');
            $daten[8] = $a->text('xtipperort');
            $vereine = Tipperdatei::lies($tipperdatei)->vereine();
            if ($vereinswahl == '1') {
                $vereinAlt = trim($vereinAlt);
                if ($vereinAlt == '') {
                    $newpage = '0';
                    $fehler($t[71] ?? '');
                } elseif (isset($vereine[$vereinAlt]) && $vereine[$vereinAlt] >= $imTeam && $imTeam != 0) {
                    // max. Tipperanzahl schon erreicht
                    $newpage = '0';
                    $fehler($t[142] ?? '');
                }
            }
            if ($vereinswahl == '2') {
                $vereinNeu = trim($vereinNeu);
                if ($vereinNeu == '') {
                    $newpage = '0';
                    $fehler($t[72] ?? '');
                } elseif (isset($vereine[$vereinNeu])) {
                    // Team existiert bereits
                    $newpage = '0';
                    $fehler($t[143] ?? '');
                }
            }
        }

        if ($newpage == 1) {
            $verein = $vereinswahl == 1 ? $vereinAlt : ($vereinswahl == 2 ? $vereinNeu : '');
            $daten[2] = $a->gesetzt('xfrei') ? '5' : '';
            $daten[9] = $a->gesetzt('xnews') ? '1' : '-1';
            $daten[10] = $a->gesetzt('xremind') ? '1' : '-1';
            $zeilen[(int) $save] = implode('|', [$daten[0], $daten[1], $daten[2], $daten[3], $daten[4], $verein, $daten[6], $daten[7], $daten[8], $daten[9], $daten[10], 'EOL']);
            $inhalt = Tipperdatei::SCHUTZZEILE . PHP_EOL . implode(PHP_EOL, array_filter($zeilen, static fn (string $z): bool => $z !== '')) . PHP_EOL;
            file_put_contents($tipperdatei, $inhalt, LOCK_EX);

            // seine Tippdateien: abgewaehlte Ligen loeschen
            foreach (scandir(rtrim($tippordner, '/')) ?: [] as $tippdatei) {
                $unterstrich = (int) strrpos($tippdatei, '_');
                if (substr($tippdatei, $unterstrich + 1, -4) == $nick && strtolower(substr($tippdatei, -4)) == '.tip' && !in_array($tippdatei, array_map(static fn (string $liga): string => $liga . '_' . $nick . '.tip', $gewaehlt), true)) {
                    unlink($tippordner . $tippdatei);
                }
            }
            // neue Ligen: leere Tippdatei und ein Eintrag in der Auswertung der Liga (wie bisher mit dem bisherigen Verein)
            foreach ($gewaehlt as $liga) {
                $pfad = $tippordner . $liga . '_' . $nick . '.tip';
                if (!is_file($pfad)) {
                    if (!Tippdatei::leer()->speichere($pfad)) {
                        return new Seite(AdminMenue::html($kontext, 'tippuseredit') . $meldungen . Meldung::html($bilder, $text[283] ?? '', true));
                    }
                    file_put_contents($tippordner . 'auswert/' . $liga . '.aus', "\n[" . $nick . "]\nTeam=" . $daten[5] . "\nName=" . $daten[3] . "\n", FILE_APPEND | LOCK_EX);
                }
            }

            return new Seite(AdminMenue::html($kontext, 'tippuseredit') . $meldungen . Vorlagen::zeige(dirname(__DIR__) . '/vorlagen', 'admin_tipper_gespeichert.html.twig', [
                'text' => $text,
                't' => $t,
                'adresse' => $kontext->adresse,
            ]));
        }

        $neu = $save == count($zeilen);
        $leer = strpos($daten[3], ' ');
        // die Vereine fuer die Auswahl, nach Namen sortiert (wie bisher ueber Name und Nummer als Text)
        $auswahl = [];
        $nr = 0;
        foreach (Tipperdatei::lies($tipperdatei)->vereine() as $vereinsname => $tipperzahl) {
            $auswahl[strtolower((string) $vereinsname) . (50000000 + $nr++)] = ['name' => (string) $vereinsname, 'tipper' => $tipperzahl];
        }
        ksort($auswahl, SORT_STRING);
        $ligen = [];
        foreach (Tippligen::tippbar($kontext) as $liga) {
            $liga['angekreuzt'] = in_array($liga['datei'], $gewaehlt, true) || is_file($tippordner . $liga['datei'] . '_' . $nick . '.tip');
            $ligen[] = $liga;
        }

        return new Seite(AdminMenue::html($kontext, 'tippuseredit') . $meldungen . Vorlagen::zeige(dirname(__DIR__) . '/vorlagen', 'admin_tipper_bearbeiten.html.twig', [
            'text' => $text,
            't' => $t,
            'adresse' => $kontext->adresse,
            'bilder' => $bilder,
            'neu' => $neu,
            'nick' => $nick,
            'kennwort' => $neu ? self::zufallskennwort() : '',
            'daten' => $daten,
            'vorname' => $leer === false ? '' : substr($daten[3], 0, $leer),
            'nachname' => substr($daten[3], $leer === false ? 1 : $leer + 1),
            'mitVerein' => $imTeam >= 0,
            'vereinswahl' => $vereinswahl,
            'vereinAlt' => $vereinAlt,
            'vereinNeu' => $vereinNeu,
            'vereine' => array_values($auswahl),
            'ligen' => $ligen,
        ]));
    }

    /** Ein Vorschlag fuer das Kennwort eines neuen Tippers: 8 bis 16 Kleinbuchstaben und Ziffern (bis 4.5 lmo-adminuserpass.php). */
    private static function zufallskennwort(): string
    {
        $zeichen = 'abcdefghijklmnopqrstuvwxyz0123456789';
        $kennwort = '';
        for ($i = random_int(8, 16); $i > 0; $i--) {
            $kennwort .= $zeichen[random_int(0, strlen($zeichen) - 1)];
        }

        return $kennwort;
    }
}
