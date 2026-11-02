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

use Closure;
use Lmo\Liga\LigaDatei;
use Lmo\Liga\Rohdaten\Liga;
use Lmo\Web\Controller\Controller;
use Lmo\Web\Kontext;
use Lmo\Web\Meldung;
use Lmo\Web\Seite;
use Lmo\Web\Vorlagen;

/**
 * Die Auswertung des Tippspiels in der Administration (todo=tipp, nur Hauptadmin): je getippter
 * Liga die Tipps auswerten (save=2) und die Tipp-Einsicht erstellen (save=3), dazu die
 * Gesamtwertung (save=4). Die Liga wertet Ligaauswertung aus, Einsicht und Gesamtwertung noch
 * der alte Code (lmo-tippsaveeinsicht.php, lmo-tippsavewertgesamt.php). Bis 4.5 lmo-admintipp.php.
 */
final class AdminAuswertungController implements Controller
{
    /** save => Datei der Auswertung nach alter Art */
    private const AUSWERTUNGEN = [3 => 'lmo-tippsaveeinsicht.php', 4 => 'lmo-tippsavewertgesamt.php'];

    /**
     * @param string $ordner Ordner des Addons (addon/tipp)
     * @param string $daten Datenordner des Tippspiels (daten/tipp)
     * @param Closure(string, array<string, mixed>): string $alterCode bindet eine Datei nach alter Art im globalen Bereich ein (mit diesen Variablen), gibt ihre Ausgabe zurueck
     */
    public function __construct(private string $ordner, private string $daten, private Closure $alterCode)
    {
    }

    public function zustaendig(Kontext $kontext): bool
    {
        return $kontext->anfrage->text('todo') == 'tipp' && $kontext->sitzung->zahl('lmouserok') == 2;
    }

    public function zeige(Kontext $kontext): Seite
    {
        $a = $kontext->anfrage;
        $cfg = $kontext->konfiguration->addon('tipp');
        $save = $a->text('save', '0');
        $liga = $a->text('liga');
        $ausgabe = '';
        if ($save == 2) {
            $ausgabe = $this->werteAus($kontext, $cfg, $liga);
        } elseif (isset(self::AUSWERTUNGEN[(int) $save]) && $save == (int) $save) {
            $ausgabe = ($this->alterCode)($this->ordner . '/' . self::AUSWERTUNGEN[(int) $save], ['save' => $save, 'liga' => $liga]);
        }
        $t = $kontext->texteVon('tipp');

        return new Seite($ausgabe . Vorlagen::zeige(dirname(__DIR__) . '/vorlagen', 'admin_auswertung.html.twig', [
            'text' => $kontext->text,
            't' => $t,
            'adresse' => $kontext->adresse,
            'menue' => AdminMenue::html($kontext, 'tipp'),
            'auswerten' => $this->ligen($kontext, $cfg, 'auswert', (int) $save == 2),
            'einsicht' => $this->ligen($kontext, $cfg, 'einsicht', (int) $save == 3),
            'gesamt' => ($cfg['gesamt'] ?? '') == 1,
        ]));
    }

    /**
     * Wertet die Tipps einer Liga aus (Ligaauswertung): den Spieltag st, 0 fuer alle; mit start und
     * ende nur diese Tipper. Zurueck kommt die Meldung.
     *
     * @param array<string, string> $cfg Einstellungen des Tippspiels
     */
    private function werteAus(Kontext $kontext, array $cfg, string $liga): string
    {
        $a = $kontext->anfrage;
        $t = $kontext->texteVon('tipp');
        $text = $kontext->text;
        $bilder = $kontext->pfade->bilder;
        $datei = $kontext->pfade->lmo . '/' . $kontext->konfiguration->ligenordner() . $liga . '.l98';
        $meldung = '';
        $rohdaten = null;
        if (file_exists($datei)) {
            $meldung = Meldung::html($bilder, ($t[0] ?? '') . ': ' . ($t[29] ?? '') . ' <var>' . htmlspecialchars($liga) . '</var> ' . ($t[65] ?? ''));
            $rohdaten = Liga::lies($datei);
        }
        $auswertung = new Ligaauswertung($this->daten . '/' . ($cfg['dirtipp'] ?? ''), $this->daten . '/' . ($cfg['tippauthtxt'] ?? 'lmo-tippauth.txt'), $cfg, (string) ($text[0] ?? ''), (string) ($text[1] ?? ''));
        if (!$auswertung->werte($liga, $rohdaten, $a->zahl('st'), $a->gesetzt('start') ? $a->zahl('start') : null, $a->gesetzt('ende') ? $a->zahl('ende') : null)) {
            $meldung .= Meldung::html($bilder, ($t[29] ?? '') . ' ' . ($text[283] ?? ''), true);
        }

        return $meldung;
    }

    /**
     * Die getippten Ligen mit der Auswahl der Spieltage und "von"/"bis" der Tipper (bis 4.5
     * lmo-tippnewdir.php mit iptype auswert bzw. einsicht).
     *
     * @param array<string, string> $cfg Einstellungen des Tippspiels
     * @param bool $gewaehlt die Anfrage war diese Auswertung (dann gelten Spieltag, von, bis der Anfrage fuer ihre Liga)
     * @return list<array<string, mixed>>
     */
    private function ligen(Kontext $kontext, array $cfg, string $art, bool $gewaehlt): array
    {
        $a = $kontext->anfrage;
        $text = $kontext->text;
        $t = $kontext->texteVon('tipp');
        $ordner = $kontext->pfade->lmo . '/' . $kontext->konfiguration->ligenordner();
        $dateien = [];
        foreach (scandir(rtrim($ordner, '/')) ?: [] as $datei) {
            if (strtolower(substr($datei, -4)) == '.l98') {
                $dateien[] = $datei;
            }
        }
        sort($dateien);
        $zuTippen = explode(',', $cfg['ligenzutippen'] ?? '');
        $immerAlle = ($cfg['immeralle'] ?? '') == 1;
        $tippordner = $this->daten . '/' . ($cfg['dirtipp'] ?? '');
        $ohneNamen = 0;
        $ligen = [];
        foreach ($dateien as $datei) {
            $optionen = LigaDatei::liesAbschnitt($ordner . $datei, 'Options');
            $name = $optionen['Name'] ?? '';
            $aktuell = $optionen['Actual'] ?? '';
            $mannschaften = $optionen['Teams'] ?? '';
            $liga = new Liga();
            $liga->optionen = $optionen;
            $spieltage = $liga->einstellungen()->spieltage();
            $pokal = ($optionen['Type'] ?? '') == '1';
            if ($name == '') {
                $name = ($text[507] ?? '') . ++$ohneNamen;
            }
            $kurz = substr($datei, 0, -4);
            if (!in_array($kurz, $zuTippen, true) && !$immerAlle) {
                continue;
            }
            $dieseLiga = $gewaehlt && $a->text('liga') == $kurz;
            if ($dieseLiga && $a->text('st') >= 0) {
                $aktuell = $a->text('st');
            }
            $optionenSt = [];
            if ($art == 'auswert' && !$pokal) {
                $optionenSt[] = ['wert' => 0, 'name' => $t[228] ?? '', 'gewaehlt' => $aktuell == 0];
            }
            $runden = strlen(decbin((int) $mannschaften - 1));
            for ($y = 1; $y <= $spieltage; $y++) {
                $optionenSt[] = ['wert' => $y, 'name' => self::spieltagName($y, $pokal, $runden, $text), 'gewaehlt' => $y == $aktuell];
            }
            // "bis": so viele Tipper, wie es Tippdateien der Liga gibt
            $tipper = 0;
            foreach (scandir(rtrim($tippordner, '/')) ?: [] as $tippdatei) {
                if (strtolower(substr($tippdatei, 0, strlen($kurz))) == strtolower($kurz) && strtolower(substr($tippdatei, -4)) == '.tip') {
                    $tipper++;
                }
            }
            $ligen[] = [
                'name' => $name,
                'liga' => $kurz,
                'spieltage' => $optionenSt,
                'von' => $dieseLiga && $a->gesetzt('start') ? $a->text('start') : '1',
                'bis' => $dieseLiga && $a->gesetzt('ende') ? $a->text('ende') : (string) $tipper,
            ];
        }

        return $ligen;
    }

    /**
     * Ein Spieltag in der Auswahl; im Pokal die letzten Runden mit Namen (Finale, Halbfinale ...).
     *
     * @param array<int, string> $text
     */
    private static function spieltagName(int $y, bool $pokal, int $runden, array $text): string
    {
        if (!$pokal) {
            return $y . '. ' . ($text[2] ?? '');
        }

        return match ($y) {
            $runden => $text[374] ?? '',
            $runden - 1 => $text[373] ?? '',
            $runden - 2 => $text[372] ?? '',
            $runden - 3 => $text[371] ?? '',
            default => $y . '. ' . ($text[370] ?? ''),
        };
    }
}
