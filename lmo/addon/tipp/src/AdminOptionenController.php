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

use Lmo\Liga\LigaDatei;
use Lmo\Web\Anfrage;
use Lmo\Web\Controller\Controller;
use Lmo\Web\Kontext;
use Lmo\Web\Meldung;
use Lmo\Web\Seite;
use Lmo\Web\Vorlagen;

/**
 * Die Optionen des Tippspiels in der Administration (todo=tippoptions, nur Hauptadmin) auf 13
 * Seiten (show 0 bis 12); mit save=1 werden die Werte der gezeigten Seite in config/tipp/cfg.txt
 * gespeichert. Bis 4.5 lmo-admintippoptions.php mit lmo-admintippgetoptions.php,
 * lmo-admintippjavascript.php und lmo-tippnewdir.php.
 */
final class AdminOptionenController implements Controller
{
    /** show => Nummer des Namens in den Texten des Tippspiels (negativ: in den allgemeinen Texten) */
    private const SEITEN = [0 => 91, 1 => -220, 2 => 32, 3 => 240, 4 => 214, 5 => 239, 6 => 246, 7 => 157, 8 => 172, 9 => 247, 10 => 274, 11 => 163, 12 => 103];

    /** @param string $konfigurationsordner Ordner der Konfiguration (PATH_TO_CONFIGDIR) mit tipp/cfg.txt */
    public function __construct(private string $konfigurationsordner)
    {
    }

    public function zustaendig(Kontext $kontext): bool
    {
        return $kontext->anfrage->text('todo') == 'tippoptions' && $kontext->sitzung->zahl('lmouserok') == 2;
    }

    public function zeige(Kontext $kontext): Seite
    {
        $a = $kontext->anfrage;
        $t = $kontext->texteVon('tipp');
        $text = $kontext->text;
        $showText = $a->text('show', '0');
        // wie bisher lose verglichen: eine Zahl waehlt ihre Seite, alles andere keine
        $show = is_numeric($showText) ? (int) $showText : -1;
        $konfiguration = $kontext->konfiguration;
        $meldung = '';
        if ($a->text('save', '0') == 1) {
            $cfg = $konfiguration->addon('tipp');
            // gespeichert werden nur Werte, die die Konfiguration schon hat (wie lmo-savecfg.php)
            $konfiguration = $konfiguration->mit(['tipp' => array_intersect_key(self::geaendert($a, $show, $cfg), $cfg)]);
            $meldung = $konfiguration->speichere($this->konfigurationsordner)
                ? Meldung::html($kontext->pfade->bilder, $text[138] ?? '')
                : Meldung::html($kontext->pfade->bilder, $text[283] ?? '', true);
        }
        $k = $konfiguration->addon('tipp');

        $seiten = [];
        foreach (self::SEITEN as $nr => $name) {
            $seiten[$nr] = $name < 0 ? ($text[-$name] ?? '') : ($t[$name] ?? '');
        }

        return new Seite($meldung . Vorlagen::zeige(dirname(__DIR__) . '/vorlagen', 'admin_optionen.html.twig', [
            'text' => $text,
            't' => $t,
            'adresse' => $kontext->adresse,
            'menue' => AdminMenue::html($kontext, 'tippoptions'),
            'show' => $show,
            'showText' => $showText,
            'seiten' => $seiten,
            'k' => $k + array_fill_keys(self::schluessel(), ''),
            'ligen' => $show == 12 ? self::ligen($kontext, $k) : [],
        ]));
    }

    /**
     * Die neuen Werte der Seite show aus dem Formular, wie bis 4.5 lmo-admintippgetoptions.php.
     *
     * @param array<string, string> $k die bisherigen Werte
     * @return array<string, string|int>
     */
    private static function geaendert(Anfrage $a, int $show, array $k): array
    {
        $wert = static fn (string $feld, string|int $sonst): string|int => $a->gesetzt($feld) ? $a->text($feld) : $sonst;
        $an = static fn (string $feld, int $aus = 0): int => $a->gesetzt($feld) ? 1 : $aus;
        // eine Zahl groesser 0, sonst die Vorgabe
        $positiv = static fn (string $feld, string|int $sonst): string|int => is_numeric($a->text($feld)) && intval($a->text($feld)) > 0 ? $a->text($feld) : $sonst;
        $zahl = static fn (string $feld, string|int $sonst): string|int => is_numeric($a->text($feld)) ? $a->text($feld) : $sonst;
        $neu = [];
        switch ($show) {
            case 0:
                $neu['tippmodus'] = $wert('xtippmodus', 0);
                break;
            case 1:
                // ohne Feld bleibt der bisherige (bis 4.5 kam dann "tipps/" in das erste Kriterium bei Punktgleichheit)
                $dirtipp = $a->gesetzt('xdirtipp') ? $a->text('xdirtipp') : ($k['dirtipp'] ?? '');
                if (substr($dirtipp, -1) != '/') {
                    $dirtipp .= '/';
                }
                $neu['dirtipp'] = str_replace('\\', '/', $dirtipp);
                break;
            case 2:
                if (($k['tippmodus'] ?? '') == 1) {
                    $neu['rergebnis'] = $positiv('xrergebnis', 0);
                    $neu['rtendenzdiff'] = $positiv('xrtendenzdiff', 0);
                    if ((float) $neu['rergebnis'] < (float) $neu['rtendenzdiff']) {
                        $neu['rergebnis'] = $neu['rtendenzdiff'];
                    }
                    $neu['rtendenz'] = $positiv('xrtendenz', 0);
                    if ((float) $neu['rtendenzdiff'] < (float) $neu['rtendenz']) {
                        $neu['rtendenzdiff'] = $neu['rtendenz'];
                    }
                    $neu['rtor'] = $positiv('xrtor', 0);
                    $neu['rtendenztor'] = $wert('xrtendenztor', 0);
                    $neu['rtendenzremis'] = $wert('xrtendenzremis', 0);
                }
                $neu['rremis'] = $positiv('xrremis', 0);
                $neu['entscheidungnv'] = $an('xentscheidungnv');
                $neu['entscheidungie'] = $an('xentscheidungie');
                $neu['gtpunkte'] = $wert('xgtpunkte', 0);
                break;
            case 3:
                $neu['tippeinsicht'] = $an('xtippeinsicht');
                $neu['tipptabelle1'] = $an('xtipptabelle1');
                $neu['tippfieber'] = $an('xtippfieber');
                $neu['gesamt'] = $an('xgesamt');
                if ($neu['gesamt'] == 0) {
                    $neu['nurgesamt'] = 0;
                }
                $neu['regeln'] = $an('xregeln');
                if ($neu['regeln'] == 1) {
                    $neu['regelnlink'] = $wert('xregelnlink', 'tippregeln.php');
                }
                $neu['shownick'] = $wert('xshownick', '');
                $neu['showname'] = $wert('xshowname', '');
                $neu['showemail'] = $wert('xshowemail', '');
                // ohne Name und E-Mail-Adresse wenigstens der Nick (seit PHP 8 griff das nicht mehr: "" == 0 ist falsch)
                if ($neu['showname'] != 1 && $neu['showemail'] != 1) {
                    $neu['shownick'] = 1;
                }
                break;
            case 4:
                $neu['tippohne'] = $wert('xtippohne', 0);
                $neu['tippBis'] = $zahl('xtippbis', 0);
                $neu['tipperimteam'] = $zahl('xtipperteam', 0);
                $neu['imvorraus'] = $zahl('ximvorraus', -1);
                $neu['jokertipp'] = $an('xjokertipp');
                if ($neu['jokertipp'] == 1) {
                    $neu['jokertippmulti'] = $positiv('xjokertippmulti', 2);
                }
                break;
            case 5:
                foreach (['adresse', 'realname', 'freischaltcode', 'freischaltung', 'mailbeianmeldung'] as $schluessel) {
                    $neu[$schluessel] = $an('x' . $schluessel);
                }
                break;
            case 6:
                $neu['pfeiltipp'] = $an('xpfeiltipp');
                $neu['showtendenzabs'] = $wert('xshowtendenzabs', '');
                $neu['showtendenzpro'] = $wert('xshowtendenzpro', '');
                $neu['showdurchschntipp'] = $wert('xshowdurchschntipp', '');
                if ($neu['showtendenzabs'] == 1 || $neu['showtendenzpro'] == 1 || ($neu['showdurchschntipp'] == 1 && ($k['tippmodus'] ?? '') == 1)) {
                    $neu['tippeinsicht'] = 1;
                }
                $neu['sttipp'] = $an('xsttipp', -1);
                $neu['viewertipp'] = $an('xviewertipp');
                if ($neu['viewertipp'] == 1) {
                    $neu['viewertage'] = $positiv('xviewertage', 8);
                }
                $neu['akteinsicht'] = $an('xakteinsicht');
                break;
            case 7:
                $neu['einsichterst'] = $wert('xeinsichterst', 0);
                $neu['anzseite'] = $positiv('xanzseite', 40);
                break;
            case 8:
                $neu['tipptabelle'] = $an('xtipptabelle');
                $neu['wertverein'] = $an('xwertverein');
                break;
            case 9:
                $neu['nurgesamt'] = $an('xnurgesamt');
                $neu['anzseite1'] = $positiv('xanzseite1', 40);
                $neu['showzus'] = $an('xshowzus');
                $neu['showstsiege'] = $an('xshowstsiege');
                break;
            case 10:
                $neu['krit1'] = $wert('xkrit1', 0);
                $neu['krit2'] = $wert('xkrit2', 0);
                $neu['krit3'] = $wert('xkrit3', 0);
                break;
            case 11:
                $neu['aktauswert'] = $an('xaktauswert');
                $neu['aktauswertges'] = $an('xaktauswertges');
                break;
            case 12:
                $neu['immeralle'] = $an('ximmeralle');
                $neu['ligenzutippen'] = $neu['immeralle'] != 1 ? implode(',', $a->werte('xtipperligen')) : '';
                break;
        }

        return $neu;
    }

    /**
     * Alle Ligen im Ligenordner nach Dateinamen, angekreuzt die, die getippt werden; Ligen ohne Namen
     * heissen "Liga 1", "Liga 2" ...
     *
     * @param array<string, string> $k Werte des Tippspiels
     * @return list<array{datei: string, name: string, angekreuzt: bool}> datei ohne .l98
     */
    private static function ligen(Kontext $kontext, array $k): array
    {
        $ordner = $kontext->pfade->lmo . '/' . $kontext->konfiguration->ligenordner();
        $dateien = [];
        foreach (is_dir($ordner) ? (scandir($ordner) ?: []) : [] as $datei) {
            if (strtolower(substr($datei, -4)) == '.l98') {
                $dateien[] = $datei;
            }
        }
        sort($dateien);
        $zuTippen = explode(',', $k['ligenzutippen'] ?? '');
        $ligen = [];
        $ohneNamen = 0;
        foreach ($dateien as $datei) {
            $liga = substr($datei, 0, -4);
            $name = LigaDatei::liesAbschnitt($ordner . $datei, 'Options')['Name'] ?? '';
            if ($name == '') {
                $name = ($kontext->text[507] ?? '') . ++$ohneNamen;
            }
            $ligen[] = ['datei' => $liga, 'name' => $name, 'angekreuzt' => in_array($liga, $zuTippen, true) || ($k['immeralle'] ?? '') == 1];
        }

        return $ligen;
    }

    /** @return list<string> die Werte, die die Seiten zeigen (fehlt einer in der Konfiguration: leer) */
    private static function schluessel(): array
    {
        return ['adresse', 'aktauswert', 'aktauswertges', 'akteinsicht', 'anzseite', 'anzseite1', 'dirtipp', 'einsichterst', 'entscheidungie',
            'entscheidungnv', 'freischaltcode', 'freischaltung', 'gesamt', 'gtpunkte', 'immeralle', 'imvorraus', 'jokertipp', 'jokertippmulti',
            'krit1', 'krit2', 'krit3', 'mailbeianmeldung', 'nurgesamt', 'pfeiltipp', 'realname', 'regeln', 'regelnlink', 'rergebnis', 'rremis',
            'rtendenz', 'rtendenzdiff', 'rtendenzremis', 'rtendenztor', 'rtor', 'showdurchschntipp', 'showemail', 'showname', 'shownick',
            'showstsiege', 'showtendenzabs', 'showtendenzpro', 'showzus', 'sttipp', 'tippBis', 'tippeinsicht', 'tipperimteam', 'tippfieber',
            'tippmodus', 'tippohne', 'tipptabelle', 'tipptabelle1', 'viewertage', 'viewertipp', 'wertverein'];
    }
}
